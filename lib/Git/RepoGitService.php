<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git;

use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Owns the on-disk repository layout and the git-level flows:
 * repository creation, worktree sync after pushes, and commit-on-write.
 *
 * Layout per repository (issue 22/23):
 *   <datadir>/__repos/<id>/repo.git   bare, authoritative
 *   <datadir>/__repos/<id>/files      linked worktree, mounted as folder storage
 */
class RepoGitService {
	public const DEFAULT_BRANCH = 'main';
	private const COMMITTER_NAME = 'Nextcloud Repositories';
	private const COMMITTER_EMAIL = 'repos@nextcloud.invalid';

	public function __construct(
		private readonly GitCli $git,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getBaseDir(int $folderId): string {
		$dataDir = $this->config->getSystemValue('datadirectory');
		return $dataDir . '/__repos/' . $folderId;
	}

	public function getGitDir(int $folderId): string {
		return $this->getBaseDir($folderId) . '/repo.git';
	}

	public function getWorktreeDir(int $folderId): string {
		return $this->getBaseDir($folderId) . '/files';
	}

	public function repoExists(int $folderId): bool {
		return is_dir($this->getGitDir($folderId));
	}

	/**
	 * Initialize bare repo + linked worktree + annex for a new repo folder.
	 * Must run before the folder storage is scanned so the worktree is the
	 * `files` directory from the start.
	 */
	public function createRepoStructure(int $folderId): void {
		$base = $this->getBaseDir($folderId);
		$gitDir = $this->getGitDir($folderId);
		$worktree = $this->getWorktreeDir($folderId);

		if (!is_dir($base) && !@mkdir($base, 0770, true)) {
			throw new GitException('Failed to create repository directory ' . $base);
		}

		$this->git->initBare($gitDir, self::DEFAULT_BRANCH);

		// seed an initial empty commit so the branch exists and a worktree can attach
		$tree = $this->git->mktreeEmpty($gitDir);
		$commit = $this->git->commitTree($gitDir, $tree, 'Initialize repository', self::COMMITTER_NAME, self::COMMITTER_EMAIL);
		$this->git->updateRef($gitDir, 'refs/heads/' . self::DEFAULT_BRANCH, $commit);
		$this->git->setHead($gitDir, 'refs/heads/' . self::DEFAULT_BRANCH);

		// the mounted worktree keeps the branch checked out; pushes update the
		// ref and we reset the worktree ourselves afterwards
		$this->git->configSet($gitDir, 'receive.denyCurrentBranch', 'ignore');
		// unlocked annexed files look like regular files to Nextcloud storage
		$this->git->configSet($gitDir, 'annex.addunlocked', 'true');
		// small files go to git, large ones to the annex, via one `annex add`
		$this->git->configSet($gitDir, 'annex.largefiles', 'largerthan=' . $this->getLargefilesThreshold());

		$this->git->worktreeAdd($gitDir, $worktree, self::DEFAULT_BRANCH);
		// the linked worktree inherits core.bare=true from the bare repo,
		// which git-annex refuses; override it with per-worktree config
		$this->git->configSet($gitDir, 'extensions.worktreeConfig', 'true');
		$this->git->configSetWorktree($worktree, 'core.bare', 'false');
		$this->git->annexInit($worktree, 'nextcloud');
	}

	public function deleteRepoStructure(int $folderId): void {
		$base = $this->getBaseDir($folderId);
		if (is_dir($base)) {
			$this->removeDirectory($base);
		}
	}

	/**
	 * Bring the worktree up to date after refs changed via push, then let the
	 * caller rescan the file cache.
	 */
	public function syncWorktreeAfterPush(int $folderId): void {
		$this->withRepoLock($folderId, function () use ($folderId): void {
			$this->git->resetHard($this->getWorktreeDir($folderId));
		});
	}

	/**
	 * Stage and commit paths in the worktree, attributed to the acting user.
	 * Returns the commit sha or null when the operation produced no change.
	 *
	 * @param string[] $paths worktree-relative paths affected by the operation
	 */
	public function commitWorktreePaths(int $folderId, array $paths, string $message, string $authorName, string $authorEmail): ?string {
		return $this->withRepoLock($folderId, function () use ($folderId, $paths, $message, $authorName, $authorEmail): ?string {
			$worktree = $this->getWorktreeDir($folderId);

			$existing = array_values(array_filter(
				$paths,
				fn (string $path): bool => file_exists($worktree . '/' . $path),
			));
			if ($existing !== []) {
				// annex add routes small files to git and large ones to the
				// annex according to annex.largefiles
				$this->git->annexAdd($worktree, $existing);
			}
			// pick up deletions and renames
			$this->git->addAll($worktree, $paths);

			return $this->git->commit($worktree, $message, $authorName, $authorEmail);
		});
	}

	/**
	 * @return list<array{sha: string, authorName: string, authorEmail: string, timestamp: int, subject: string}>
	 */
	public function getHistory(int $folderId, string $path, int $limit = 50): array {
		return $this->git->logFollow($this->getWorktreeDir($folderId), $path, $limit);
	}

	public function getAnnexKey(int $folderId, string $path): ?string {
		return $this->git->annexLookupKey($this->getWorktreeDir($folderId), $path);
	}

	/**
	 * Absolute filesystem path of the content for an annex key, or null when
	 * the content is not present in this repository.
	 */
	public function getAnnexContentPath(int $folderId, string $key): ?string {
		// run in the worktree context: annex may keep objects in the
		// worktree's private git dir rather than the common dir
		$worktree = $this->getWorktreeDir($folderId);
		$location = $this->git->annexContentLocation($worktree, $key);
		if ($location === null) {
			return null;
		}
		$absolute = str_starts_with($location, '/')
			? $location
			: $worktree . '/' . $location;
		$resolved = realpath($absolute);
		return ($resolved !== false && is_file($resolved)) ? $resolved : null;
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public function withRepoLock(int $folderId, callable $callback) {
		$lockFile = $this->getBaseDir($folderId) . '/.lock';
		$handle = fopen($lockFile, 'c');
		if ($handle === false) {
			throw new GitException('Failed to open repo lock for folder ' . $folderId);
		}
		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			throw new GitException('Failed to acquire repo lock for folder ' . $folderId);
		}
		try {
			return $callback();
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	private function getLargefilesThreshold(): string {
		return $this->config->getAppValue('repos', 'annex_largefiles', '100mb');
	}

	private function removeDirectory(string $dir): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ($iterator as $item) {
			// git object files are read-only; clear permissions before removal
			@chmod($item->getPathname(), 0700);
			if ($item->isDir() && !$item->isLink()) {
				@rmdir($item->getPathname());
			} else {
				@unlink($item->getPathname());
			}
		}
		@rmdir($dir);
	}
}
