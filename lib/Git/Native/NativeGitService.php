<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

use OCA\Repos\Mount\FolderStorageManager;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Glue between the native git core and the app: commit-on-write from folder
 * storage (annexing large files), materializing pushed commits back,
 * history, and the hand-built git-annex branch (issue 29).
 */
class NativeGitService {
	public const ANNEX_BRANCH = 'refs/heads/git-annex';
	private const DEFAULT_THRESHOLD = 52428800; // 50 MB

	public function __construct(
		private readonly NativeRepository $repo,
		private readonly NativeProtocol $protocol,
		private readonly ObjectStore $objects,
		private readonly AnnexStore $annexStore,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function init(int $folderId): void {
		$this->repo->init($folderId, time());
		$this->getUuid($folderId); // seeds uuid.log on the git-annex branch
	}

	// ---- annex identity ----------------------------------------------------

	public function getUuid(int $folderId): string {
		$uuid = $this->config->getAppValue('repos', 'annex_uuid_' . $folderId, '');
		if ($uuid === '') {
			$bytes = random_bytes(16);
			$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
			$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
			$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
			$this->config->setAppValue('repos', 'annex_uuid_' . $folderId, $uuid);
			$this->repo->commitChangesOnRef(
				$folderId,
				self::ANNEX_BRANCH,
				['uuid.log' => $uuid . ' nextcloud timestamp=' . time() . "s\n"],
				'update',
				time(),
			);
		}
		return $uuid;
	}

	private function threshold(): int {
		return (int)$this->config->getAppValue('repos', 'annex_threshold', (string)self::DEFAULT_THRESHOLD);
	}

	private function recordPresent(int $folderId, string $key): void {
		$uuid = $this->getUuid($folderId);
		$logPath = AnnexKeys::branchHashDir($key) . '/' . $key . '.log';
		$this->repo->commitChangesOnRef(
			$folderId,
			self::ANNEX_BRANCH,
			[$logPath => sprintf("%d.%06ds 1 %s\n", time(), 0, $uuid)],
			'update',
			time(),
		);
	}

	// ---- commit-on-write ---------------------------------------------------

	/**
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
		$threshold = $this->threshold();
		$changes = [];
		$annexed = [];
		foreach ($paths as $path) {
			if ($storage->file_exists($path)) {
				if ($storage->is_dir($path)) {
					continue; // git tracks files; empty dirs have no representation
				}
				$content = $storage->file_get_contents($path);
				if ($content === false) {
					continue;
				}
				if (strlen($content) >= $threshold) {
					$key = AnnexKeys::keyForContent(basename($path), $content);
					$this->annexStore->write($folderId, $key, $content);
					$changes[$path] = AnnexKeys::pointerFor($key);
					$annexed[] = $key;
				} else {
					$changes[$path] = $content;
				}
			} else {
				$changes[$path] = null;
			}
		}
		if ($changes === []) {
			return;
		}
		$this->repo->commitChanges($folderId, $changes, $message, $authorName, $authorEmail, time());
		foreach ($annexed as $key) {
			$this->recordPresent($folderId, $key);
		}
	}

	public function history(int $folderId, string $path, int $limit = 50): array {
		return $this->repo->log($folderId, $path, $limit);
	}

	/**
	 * @return array{key: string, present: bool}|null annex state of a path at head
	 */
	public function annexInfo(int $folderId, string $path): ?array {
		$content = $this->repo->readPathAtRef($folderId, NativeRepository::HEAD_REF, $path);
		if ($content === null) {
			return null;
		}
		$key = AnnexKeys::keyFromPointer($content);
		if ($key === null) {
			return null;
		}
		return ['key' => $key, 'present' => $this->annexStore->has($folderId, $key)];
	}

	/**
	 * @return resource|null content stream for an annex key
	 */
	public function annexContentStream(int $folderId, string $key) {
		return $this->annexStore->readStream($folderId, $key);
	}

	public function annexContentSize(int $folderId, string $key): ?int {
		return $this->annexStore->size($folderId, $key);
	}

	public function repoConfigText(int $folderId): string {
		return "[core]\n\trepositoryformatversion = 0\n\tbare = true\n"
			. "[annex]\n\tuuid = " . $this->getUuid($folderId) . "\n\tversion = 10\n";
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
	 * Annex pointers materialize as their content when the store has it.
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
			$object = $this->objects->read($folderId, $entry['sha']);
			if ($object === null || $object['type'] !== 'blob') {
				continue;
			}
			$blob = $object['content'];
			$key = AnnexKeys::keyFromPointer($blob);
			if ($key !== null) {
				$stored = $this->annexStore->read($folderId, $key);
				if ($stored !== null) {
					$blob = $stored;
				}
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
