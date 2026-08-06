<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Controller;

use OCA\Repos\Folder\FolderDefinitionWithPermissions;
use OCA\Repos\Folder\RepoManager;
use OCA\Repos\Git\GitCli;
use OCA\Repos\Git\GitException;
use OCA\Repos\Git\RepoGitService;
use OCA\Repos\Mount\FolderStorageManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Constants;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\Bruteforce\IThrottler;
use Psr\Log\LoggerInterface;

/**
 * Serves Git repositories over HTTP (smart protocol) and annex objects over
 * the same URL, authenticated with Nextcloud credentials / app passwords.
 *
 * https://git-scm.com/docs/http-protocol
 */
class GitRepoController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RepoManager $repoManager,
		private readonly RepoGitService $repoGitService,
		private readonly GitCli $git,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IUserSession $userSession,
		private readonly IThrottler $throttler,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	// ---- authentication and authorization ---------------------------------

	/**
	 * Git clients speak HTTP basic auth: challenge when anonymous, validate
	 * credentials (including app passwords) when given.
	 */
	private function authenticate(): ?IUser {
		if ($this->userSession->isLoggedIn()) {
			return $this->userSession->getUser();
		}

		$loginName = $this->request->server['PHP_AUTH_USER'] ?? '';
		$password = $this->request->server['PHP_AUTH_PW'] ?? '';
		if ($loginName === '') {
			return null;
		}

		/** @var \OC\User\Session $session */
		$session = $this->userSession;
		// retry once: rapid successive logins (git-annex probes both object
		// layouts in parallel) can hit transient DB contention that reads as
		// a failed login and makes the client discard its stored credential
		for ($attempt = 0; $attempt < 2; $attempt++) {
			try {
				if ($session->logClientIn($loginName, $password, $this->request, $this->throttler)) {
					return $this->userSession->getUser();
				}
			} catch (\Exception $e) {
				$this->logger->error('git http login failed', ['app' => 'repos', 'exception' => $e]);
			}
			usleep(150000);
		}
		return null;
	}

	private function unauthorized(): Response {
		$response = new DataResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
		$response->addHeader('WWW-Authenticate', 'Basic realm="Nextcloud Repositories", charset="UTF-8"');
		return $response;
	}

	/**
	 * Resolve a repo URL name (mount point or numeric id) to a folder the
	 * user may access with the needed permission.
	 */
	private function findAuthorizedFolder(string $repo, IUser $user, bool $write): ?FolderDefinitionWithPermissions {
		$needed = $write ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_READ;
		foreach ($this->repoManager->getFoldersForUser($user) as $folder) {
			if ($folder->mountPoint === $repo || (string)$folder->id === $repo) {
				return ($folder->permissions & $needed) === $needed ? $folder : null;
			}
		}
		return null;
	}

	// ---- smart HTTP protocol ----------------------------------------------

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function infoRefs(string $repo, string $service): Response {
		if (!in_array($service, ['git-upload-pack', 'git-receive-pack'], true)) {
			return new DataResponse(['error' => 'Invalid service'], Http::STATUS_BAD_REQUEST);
		}

		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, $service === 'git-receive-pack');
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		$gitDir = $this->repoGitService->getGitDir($folder->id);
		try {
			$refs = $service === 'git-upload-pack'
				? $this->git->uploadPackAdvertise($gitDir)
				: $this->git->receivePackAdvertise($gitDir);
		} catch (GitException $e) {
			$this->logger->error('git advertise failed', ['app' => 'repos', 'exception' => $e]);
			return new DataResponse(['error' => 'Git command failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$serviceHeader = '# service=' . $service . "\n";
		$content = $this->pktLine($serviceHeader) . '0000' . $refs;

		$response = new DataDisplayResponse($content, Http::STATUS_OK);
		$response->addHeader('Content-Type', 'application/x-' . $service . '-advertisement');
		$response->addHeader('Cache-Control', 'no-cache');
		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function uploadPack(string $repo): Response {
		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, false);
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$output = $this->git->uploadPack($this->repoGitService->getGitDir($folder->id), $this->getRequestBody());
		} catch (GitException $e) {
			$this->logger->error('upload-pack failed', ['app' => 'repos', 'exception' => $e]);
			return new DataResponse(['error' => 'Git command failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return $this->gitResultResponse($output, 'git-upload-pack');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function receivePack(string $repo): Response {
		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, true);
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$output = $this->git->receivePack($this->repoGitService->getGitDir($folder->id), $this->getRequestBody());
		} catch (GitException $e) {
			$this->logger->error('receive-pack failed', ['app' => 'repos', 'exception' => $e]);
			return new DataResponse(['error' => 'Git command failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// materialize the pushed state in the mounted worktree
		try {
			$this->repoGitService->syncWorktreeAfterPush($folder->id);
			$this->folderStorageManager->scanFolder($folder->id);
		} catch (\Exception $e) {
			$this->logger->error('worktree sync after push failed', ['app' => 'repos', 'exception' => $e]);
		}

		return $this->gitResultResponse($output, 'git-receive-pack');
	}

	// ---- dumb protocol + annex objects ------------------------------------

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getHead(string $repo): Response {
		return $this->serveRepoFile($repo, 'HEAD');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getObject(string $repo, string $path): Response {
		return $this->serveRepoFile($repo, 'objects/' . $path);
	}

	/**
	 * git-annex probes the repo's config over dumb HTTP to learn the annex
	 * uuid; without it a clone won't treat the origin as an annex peer.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getConfig(string $repo): Response {
		return $this->serveRepoFile($repo, 'config');
	}

	/**
	 * Annex objects over the clone URL (issue 25): git-annex requests
	 * annex/objects/<hashdirs>/<key>/<key> on http remotes. The key is the
	 * last path component; we resolve its content location instead of
	 * trusting the requested hash directories.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function annexObject(string $repo, string $path): Response {
		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, false);
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		$key = basename($path);
		if ($key === '' || str_contains($key, '..')) {
			return new DataResponse(['error' => 'Invalid key'], Http::STATUS_BAD_REQUEST);
		}

		$contentPath = $this->repoGitService->getAnnexContentPath($folder->id, $key);
		if ($contentPath === null) {
			return new DataResponse(['error' => 'Content not present'], Http::STATUS_NOT_FOUND);
		}

		$response = new StreamResponse($contentPath);
		$response->setHeaders([
			'Content-Type' => 'application/octet-stream',
			'Content-Length' => (string)filesize($contentPath),
			'Cache-Control' => 'no-cache',
		]);
		return $response;
	}

	private function serveRepoFile(string $repo, string $file): Response {
		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, false);
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		// no traversal: resolve within the bare repo only
		$file = str_replace(['..', '\\'], '', $file);
		$filePath = $this->repoGitService->getGitDir($folder->id) . '/' . $file;
		if (!is_file($filePath)) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		}

		$response = new StreamResponse($filePath);
		$response->setHeaders([
			'Content-Type' => 'application/octet-stream',
			'Cache-Control' => 'no-cache',
		]);
		return $response;
	}

	// ------------------------------------------------------------------------

	private function getRequestBody(): string {
		$body = file_get_contents('php://input');
		return $body === false ? '' : $body;
	}

	private function gitResultResponse(string $output, string $service): Response {
		$response = new DataDisplayResponse($output, Http::STATUS_OK);
		$response->addHeader('Content-Type', 'application/x-' . $service . '-result');
		$response->addHeader('Cache-Control', 'no-cache');
		return $response;
	}

	private function pktLine(string $data): string {
		$len = strlen($data) + 4;
		return sprintf('%04x', $len) . $data;
	}
}
