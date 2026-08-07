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
use OCA\Repos\Git\Native\NativeGitService;
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
 * Serves git repositories over HTTP — smart protocol and annex objects,
 * entirely through the native PHP backend (issue 29) — authenticated with
 * Nextcloud credentials / app passwords.
 */
class GitRepoController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RepoManager $repoManager,
		private readonly NativeGitService $nativeGit,
		private readonly IUserSession $userSession,
		private readonly IThrottler $throttler,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	// ---- authentication and authorization ---------------------------------

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
		// retry once: rapid successive logins (annex clients probe in
		// parallel) can hit transient contention that reads as a failed
		// login and makes clients discard their stored credential
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

	private function findAuthorizedFolder(string $repo, IUser $user, bool $write): ?FolderDefinitionWithPermissions {
		// clone URLs carry the conventional .git suffix
		if (str_ends_with($repo, '.git')) {
			$repo = substr($repo, 0, -4);
		}
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

		$content = $this->pktLine('# service=' . $service . "\n") . '0000'
			. $this->nativeGit->advertise($folder->id, $service);

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
		return $this->gitResultResponse($this->nativeGit->uploadPack($folder->id, $this->getRequestBody()), 'git-upload-pack');
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
		return $this->gitResultResponse($this->nativeGit->receivePack($folder->id, $this->getRequestBody()), 'git-receive-pack');
	}

	// ---- dumb protocol pieces + annex objects ------------------------------

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getHead(string $repo): Response {
		return $this->withReadableFolder($repo, function (): Response {
			return new DataDisplayResponse("ref: refs/heads/main\n", Http::STATUS_OK, ['Content-Type' => 'text/plain']);
		});
	}

	/**
	 * git-annex probes the repo config to learn the annex uuid.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getConfig(string $repo): Response {
		return $this->withReadableFolder($repo, function (FolderDefinitionWithPermissions $folder): Response {
			return new DataDisplayResponse(
				$this->nativeGit->repoConfigText($folder->id),
				Http::STATUS_OK,
				['Content-Type' => 'text/plain'],
			);
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getObject(string $repo, string $path): Response {
		// loose-object dumb protocol is not offered; clients use smart HTTP
		return $this->withReadableFolder($repo, function (): Response {
			return new DataResponse(['error' => 'Use the smart protocol'], Http::STATUS_NOT_FOUND);
		});
	}

	/**
	 * Annex objects over the clone URL: the key is the last path component;
	 * content streams from the annex store (issue 29).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function annexObject(string $repo, string $path): Response {
		return $this->withReadableFolder($repo, function (FolderDefinitionWithPermissions $folder) use ($path): Response {
			$key = basename($path);
			if ($key === '' || str_contains($key, '..')) {
				return new DataResponse(['error' => 'Invalid key'], Http::STATUS_BAD_REQUEST);
			}
			$stream = $this->nativeGit->annexContentStream($folder->id, $key);
			if ($stream === null) {
				return new DataResponse(['error' => 'Content not present'], Http::STATUS_NOT_FOUND);
			}
			$size = $this->nativeGit->annexContentSize($folder->id, $key);
			$response = new StreamResponse($stream);
			$headers = [
				'Content-Type' => 'application/octet-stream',
				'Cache-Control' => 'no-cache',
			];
			if ($size !== null) {
				$headers['Content-Length'] = (string)$size;
			}
			$response->setHeaders($headers);
			return $response;
		});
	}

	// ------------------------------------------------------------------------

	private function withReadableFolder(string $repo, callable $handler): Response {
		$user = $this->authenticate();
		if ($user === null) {
			return $this->unauthorized();
		}
		$folder = $this->findAuthorizedFolder($repo, $user, false);
		if ($folder === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}
		return $handler($folder);
	}

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
