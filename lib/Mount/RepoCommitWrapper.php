<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Mount;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\Repos\Git\RepoGitService;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * Commit-on-write (issue 24): every mutating operation arriving through
 * Nextcloud on a repo folder produces a git commit attributed to the acting
 * user. Also hides the worktree's .git pointer from Nextcloud entirely.
 */
class RepoCommitWrapper extends Wrapper {
	private readonly int $folderId;
	private readonly ?IUser $user;
	private readonly RepoGitService $repoGit;
	private readonly ?\OCA\Repos\Git\Native\NativeGitService $nativeGit;
	private readonly LoggerInterface $logger;

	public function __construct(array $arguments) {
		parent::__construct($arguments);
		$this->folderId = $arguments['folder_id'];
		$this->user = $arguments['user'];
		$this->repoGit = $arguments['repo_git'];
		$this->nativeGit = $arguments['native_git'] ?? null;
		$this->logger = $arguments['logger'];
	}

	private function isGitPath(string $path): bool {
		return $path === '.git' || str_starts_with($path, '.git/');
	}

	/**
	 * Paths that must never end up in git: the .git pointer and Nextcloud's
	 * transient upload part files.
	 */
	private function isUntrackedPath(string $path): bool {
		return $this->isGitPath($path) || str_ends_with($path, '.part');
	}

	private function commitPaths(array $paths, string $message): void {
		$paths = array_values(array_filter($paths, fn (string $path): bool => $path !== '' && !$this->isUntrackedPath($path)));
		if ($paths === []) {
			return;
		}
		try {
			$authorName = $this->user?->getDisplayName() ?? 'Nextcloud';
			$authorEmail = $this->user?->getEMailAddress()
				?? (($this->user?->getUID() ?? 'nextcloud') . '@nextcloud.invalid');
			if ($this->nativeGit !== null) {
				$this->nativeGit->commitFromStorage(
					$this->folderId, $this->getWrapperStorage(), $paths, $message, $authorName, $authorEmail,
				);
			} else {
				$this->repoGit->commitWorktreePaths($this->folderId, $paths, $message, $authorName, $authorEmail);
			}
		} catch (\Exception $e) {
			$this->logger->error('commit-on-write failed for repo folder ' . $this->folderId, [
				'app' => 'repos',
				'exception' => $e,
			]);
		}
	}

	private function isWriteMode(string $mode): bool {
		return str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, 'x')
			|| str_contains($mode, 'c') || str_contains($mode, '+');
	}

	// ---- mutating operations → commits ------------------------------------

	public function file_put_contents(string $path, mixed $data): int|float|false {
		if ($this->isGitPath($path)) {
			return false;
		}
		$result = parent::file_put_contents($path, $data);
		if ($result !== false) {
			$this->commitPaths([$path], 'Update ' . basename($path) . ' via Nextcloud');
		}
		return $result;
	}

	public function writeStream(string $path, $stream, ?int $size = null): int {
		if ($this->isGitPath($path)) {
			return 0;
		}
		$result = parent::writeStream($path, $stream, $size);
		if ($result > 0) {
			$this->commitPaths([$path], 'Update ' . basename($path) . ' via Nextcloud');
		}
		return $result;
	}

	public function fopen(string $path, string $mode) {
		if ($this->isGitPath($path)) {
			return false;
		}
		$stream = parent::fopen($path, $mode);
		if (!is_resource($stream) || !$this->isWriteMode($mode)) {
			return $stream;
		}
		return CallbackWrapper::wrap($stream, null, null, function () use ($path): void {
			$this->commitPaths([$path], 'Update ' . basename($path) . ' via Nextcloud');
		});
	}

	public function touch(string $path, ?int $mtime = null): bool {
		if ($this->isGitPath($path)) {
			return false;
		}
		$existed = parent::file_exists($path);
		$result = parent::touch($path, $mtime);
		if ($result && !$existed) {
			$this->commitPaths([$path], 'Create ' . basename($path) . ' via Nextcloud');
		}
		return $result;
	}

	public function unlink(string $path): bool {
		if ($this->isGitPath($path)) {
			return false;
		}
		$result = parent::unlink($path);
		if ($result) {
			$this->commitPaths([$path], 'Delete ' . basename($path) . ' via Nextcloud');
		}
		return $result;
	}

	public function rmdir(string $path): bool {
		if ($this->isGitPath($path)) {
			return false;
		}
		$result = parent::rmdir($path);
		if ($result) {
			$this->commitPaths([$path], 'Delete ' . basename($path) . ' via Nextcloud');
		}
		return $result;
	}

	public function rename(string $source, string $target): bool {
		if ($this->isGitPath($source) || $this->isGitPath($target)) {
			return false;
		}
		$result = parent::rename($source, $target);
		if ($result) {
			// an upload lands as a rename from a transient part file: that is
			// an update of the target, not a move
			$message = $this->isUntrackedPath($source)
				? 'Update ' . basename($target) . ' via Nextcloud'
				: sprintf('Move %s to %s via Nextcloud', $source, $target);
			$this->commitPaths([$source, $target], $message);
		}
		return $result;
	}

	public function copy(string $source, string $target): bool {
		if ($this->isGitPath($source) || $this->isGitPath($target)) {
			return false;
		}
		$result = parent::copy($source, $target);
		if ($result) {
			$this->commitPaths([$target], 'Copy to ' . $target . ' via Nextcloud');
		}
		return $result;
	}

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if ($this->isGitPath($targetInternalPath)) {
			return false;
		}
		$result = parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		if ($result) {
			$this->commitPaths([$targetInternalPath], 'Update ' . basename($targetInternalPath) . ' via Nextcloud');
		}
		return $result;
	}

	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if ($this->isGitPath($targetInternalPath)) {
			return false;
		}
		$result = parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		if ($result) {
			$this->commitPaths([$targetInternalPath], 'Update ' . basename($targetInternalPath) . ' via Nextcloud');
		}
		return $result;
	}

	// ---- hide .git from Nextcloud -----------------------------------------

	public function file_exists(string $path): bool {
		return $this->isGitPath($path) ? false : parent::file_exists($path);
	}

	public function getMetaData(string $path): ?array {
		return $this->isGitPath($path) ? null : parent::getMetaData($path);
	}

	public function opendir(string $path) {
		$handle = parent::opendir($path);
		if ($path !== '' || !is_resource($handle)) {
			return $handle;
		}
		$entries = [];
		while (($entry = readdir($handle)) !== false) {
			if ($entry !== '.git') {
				$entries[] = $entry;
			}
		}
		closedir($handle);
		return IteratorDirectory::wrap($entries);
	}

	public function getDirectoryContent(string $directory): \Traversable {
		foreach (parent::getDirectoryContent($directory) as $entry) {
			if ($directory === '' && ($entry['name'] ?? '') === '.git') {
				continue;
			}
			yield $entry;
		}
	}
}
