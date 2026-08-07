<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

use OCA\Repos\Mount\FolderStorageManager;
use OCP\Files\Storage\IStorage;
use Psr\Log\LoggerInterface;

/**
 * Glue between the native git core and the app: commit-on-write from folder
 * storage, materializing pushed commits back into it, history (issue 29).
 */
class NativeGitService {
	public function __construct(
		private readonly NativeRepository $repo,
		private readonly NativeProtocol $protocol,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly LoggerInterface $logger,
	) {
	}

	public static function isNative(array $options): bool {
		return ($options['backend'] ?? '') === 'native';
	}

	public function init(int $folderId): void {
		$this->repo->init($folderId, time());
	}

	/**
	 * Commit paths that changed in the folder storage (commit-on-write).
	 *
	 * @param string[] $paths storage-relative paths
	 */
	public function commitFromStorage(
		int $folderId,
		IStorage $storage,
		array $paths,
		string $message,
		string $authorName,
		string $authorEmail,
	): void {
		$changes = [];
		foreach ($paths as $path) {
			if ($storage->file_exists($path)) {
				if ($storage->is_dir($path)) {
					continue; // git tracks files; empty dirs have no representation
				}
				$content = $storage->file_get_contents($path);
				if ($content === false) {
					continue;
				}
				$changes[$path] = $content;
			} else {
				$changes[$path] = null;
			}
		}
		if ($changes === []) {
			return;
		}
		$this->repo->commitChanges($folderId, $changes, $message, $authorName, $authorEmail, time());
	}

	public function history(int $folderId, string $path, int $limit = 50): array {
		return $this->repo->log($folderId, $path, $limit);
	}

	// ---- protocol passthrough ---------------------------------------------

	public function advertise(int $folderId, string $service): string {
		return $service === 'git-receive-pack'
			? $this->protocol->advertiseReceivePack($folderId)
			: $this->protocol->advertiseUploadPack($folderId);
	}

	public function uploadPack(int $folderId, string $body): string {
		return $this->protocol->uploadPack($folderId, $body);
	}

	public function receivePack(int $folderId, string $body): string {
		$headBefore = $this->repo->head($folderId);
		$response = '';
		$applied = $this->protocol->receivePack($folderId, $body, $response);

		$headMoved = false;
		foreach ($applied as $update) {
			if ($update['ref'] === NativeRepository::HEAD_REF) {
				$headMoved = true;
			}
		}
		if ($headMoved) {
			try {
				$this->materialize($folderId, $headBefore);
			} catch (\Exception $e) {
				$this->logger->error('native materialize after push failed', ['app' => 'repos', 'exception' => $e]);
			}
		}
		return $response;
	}

	/**
	 * Write the new head's tree into the folder storage (after a push).
	 */
	private function materialize(int $folderId, ?string $oldHead): void {
		$newHead = $this->repo->head($folderId);
		if ($newHead === null) {
			return;
		}
		$newTree = $this->repo->listTree($folderId, $newHead);
		$oldTree = $oldHead !== null ? $this->repo->listTree($folderId, $oldHead) : [];

		// base storage: no commit wrapper, so writes here don't loop back
		$storage = $this->folderStorageManager->getBaseStorageForFolder($folderId, true);

		foreach ($newTree as $path => $entry) {
			if (($oldTree[$path]['sha'] ?? null) === $entry['sha']) {
				continue;
			}
			$blob = $this->readBlob($folderId, $entry['sha']);
			if ($blob === null) {
				continue;
			}
			$this->ensureParentDirs($storage, $path);
			$storage->file_put_contents($path, $blob);
		}
		foreach ($oldTree as $path => $entry) {
			if (!isset($newTree[$path]) && $storage->file_exists($path)) {
				$storage->unlink($path);
			}
		}

		$this->folderStorageManager->scanFolder($folderId);
	}

	private function readBlob(int $folderId, string $sha): ?string {
		$object = \OCP\Server::get(ObjectStore::class)->read($folderId, $sha);
		return ($object !== null && $object['type'] === 'blob') ? $object['content'] : null;
	}

	private function ensureParentDirs(IStorage $storage, string $path): void {
		$parts = explode('/', $path);
		array_pop($parts);
		$dir = '';
		foreach ($parts as $part) {
			$dir = $dir === '' ? $part : $dir . '/' . $part;
			if (!$storage->file_exists($dir)) {
				$storage->mkdir($dir);
			}
		}
	}
}
