<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Controller for serving Git repositories over HTTP
 *
 * Implements Git's "smart" HTTP protocol as documented in:
 * https://git-scm.com/docs/http-protocol
 *
 * This is a prototype implementation without authentication.
 */
class GitRepoController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the configured repos directory
	 */
	private function getReposDirectory(): ?string {
		$dir = $this->config->getAppValue('repos', 'repos_directory', '');
		if ($dir === '') {
			return null;
		}
		return $dir;
	}

	/**
	 * Get the full filesystem path for a repository
	 */
	private function getRepoPath(string $repo): ?string {
		$reposDir = $this->getReposDirectory();
		if ($reposDir === null) {
			return null;
		}

		// Sanitize repo name to prevent directory traversal
		$repo = basename($repo);
		if (!str_ends_with($repo, '.git')) {
			$repo .= '.git';
		}

		$path = $reposDir . '/' . $repo;

		// Check if repository exists
		if (!is_dir($path)) {
			return null;
		}

		return $path;
	}

	/**
	 * Info/refs endpoint for Git smart HTTP protocol
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function infoRefs(string $repo, string $service): Response {
		$repoPath = $this->getRepoPath($repo);
		if ($repoPath === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		// Validate service parameter
		if (!in_array($service, ['git-upload-pack', 'git-receive-pack'])) {
			return new DataResponse(['error' => 'Invalid service'], Http::STATUS_BAD_REQUEST);
		}

		// Execute git command to get refs
		// Use the service name without 'git-' prefix for the actual command
		$gitCommand = str_replace('git-', '', $service);
		$cmd = sprintf(
			'cd %s && git %s --stateless-rpc --advertise-refs . 2>&1',
			escapeshellarg($repoPath),
			escapeshellarg($gitCommand)
		);

		$output = shell_exec($cmd);

		if ($output === null || $output === false) {
			error_log("Git command failed for repo $repo, service $service");
			return new DataResponse(['error' => 'Git command failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Format response according to Git HTTP protocol
		// Add packet-line formatted service announcement
		$serviceHeader = '# service=' . $service . "\n";
		$content = $this->pktLine($serviceHeader) . "0000" . $output;

		$response = new DataDisplayResponse($content, Http::STATUS_OK);
		$response->addHeader('Content-Type', 'application/x-' . $service . '-advertisement');
		$response->addHeader('Cache-Control', 'no-cache');
		return $response;
	}

	/**
	 * git-upload-pack endpoint (for clone/fetch)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function uploadPack(string $repo): Response {
		return $this->executeGitService($repo, 'git-upload-pack');
	}

	/**
	 * git-receive-pack endpoint (for push)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function receivePack(string $repo): Response {
		return $this->executeGitService($repo, 'git-receive-pack');
	}

	/**
	 * Execute a Git service with the request body as input
	 */
	private function executeGitService(string $repo, string $service): Response {
		$repoPath = $this->getRepoPath($repo);
		if ($repoPath === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		// Get request body
		$input = file_get_contents('php://input');

		// Strip 'git-' prefix from service name to get the actual git command
		$gitCommand = str_replace('git-', '', $service);

		// Execute git command
		$cmd = sprintf(
			'git -C %s %s --stateless-rpc %s',
			escapeshellarg($repoPath),
			escapeshellarg($gitCommand),
			escapeshellarg($repoPath)
		);

		$descriptorspec = [
			0 => ['pipe', 'r'],  // stdin
			1 => ['pipe', 'w'],  // stdout
			2 => ['pipe', 'w'],  // stderr
		];

		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (!is_resource($process)) {
			return new DataResponse(['error' => 'Failed to execute Git command'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Write input to stdin
		fwrite($pipes[0], $input);
		fclose($pipes[0]);

		// Read output
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		// Read errors
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$returnCode = proc_close($process);

		if ($returnCode !== 0) {
			error_log("Git command failed for $service on $repo: return code=$returnCode, stderr: " . $errors);
			return new DataResponse(['error' => 'Git command failed', 'details' => $errors, 'code' => $returnCode], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Return response
		$response = new DataDisplayResponse($output, Http::STATUS_OK);
		$response->addHeader('Content-Type', 'application/x-' . $service . '-result');
		$response->addHeader('Cache-Control', 'no-cache');
		return $response;
	}

	/**
	 * Format a string as a Git packet-line
	 *
	 * Git packet-line format: 4-byte hex length (including the 4 bytes) + data
	 */
	private function pktLine(string $data): string {
		$len = strlen($data) + 4;
		return sprintf('%04x', $len) . $data;
	}

	/**
	 * HEAD file endpoint
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getHead(string $repo): Response {
		return $this->serveFile($repo, 'HEAD');
	}

	/**
	 * Serve a file from the repository
	 */
	private function serveFile(string $repo, string $file): Response {
		$repoPath = $this->getRepoPath($repo);
		if ($repoPath === null) {
			return new DataResponse(['error' => 'Repository not found'], Http::STATUS_NOT_FOUND);
		}

		// Sanitize file path to prevent directory traversal
		$file = str_replace(['..', '\\'], ['', '/'], $file);
		$filePath = $repoPath . '/' . $file;

		if (!file_exists($filePath) || !is_file($filePath)) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		}

		$response = new StreamResponse($filePath);
		$response->setHeaders([
			'Content-Type' => 'application/octet-stream',
			'Cache-Control' => 'no-cache',
		]);
		return $response;
	}

	/**
	 * Objects endpoint (for fetching Git objects)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getObject(string $repo, string $path): Response {
		return $this->serveFile($repo, 'objects/' . $path);
	}
}
