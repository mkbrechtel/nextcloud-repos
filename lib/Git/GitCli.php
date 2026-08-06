<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git;

use Psr\Log\LoggerInterface;

/**
 * Command layer over the git and git-annex binaries.
 *
 * Every git subprocess invocation in the app goes through exactly one named
 * method on this class — no shell strings at call sites. This is the seam for
 * a possible future native git implementation (issue 22).
 */
class GitCli {
	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param string[] $args
	 */
	private function run(array $args, ?string $cwd = null, ?string $input = null, array $extraEnv = []): CliResult {
		$env = array_merge([
			'HOME' => $this->homeDir(),
			'GIT_TERMINAL_PROMPT' => '0',
			'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
			'LANG' => 'C.UTF-8',
		], $extraEnv);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($args, $descriptors, $pipes, $cwd, $env);
		if (!is_resource($process)) {
			throw new GitException('Failed to spawn: ' . implode(' ', $args));
		}

		if ($input !== null) {
			fwrite($pipes[0], $input);
		}
		fclose($pipes[0]);

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exitCode = proc_close($process);

		if ($exitCode !== 0) {
			$this->logger->debug('git exited non-zero', [
				'app' => 'repos',
				'args' => $args,
				'exitCode' => $exitCode,
				'stderr' => $stderr,
			]);
		}

		return new CliResult($exitCode, (string)$stdout, (string)$stderr);
	}

	private function runOrThrow(array $args, ?string $cwd = null, ?string $input = null, array $extraEnv = []): string {
		$result = $this->run($args, $cwd, $input, $extraEnv);
		if ($result->exitCode !== 0) {
			throw new GitException(sprintf(
				'`%s` failed (%d): %s',
				implode(' ', $args),
				$result->exitCode,
				trim($result->stderr),
			));
		}
		return $result->stdout;
	}

	private function homeDir(): string {
		$dir = sys_get_temp_dir() . '/nextcloud-repos-home';
		if (!is_dir($dir)) {
			@mkdir($dir, 0770, true);
		}
		return $dir;
	}

	// ---- repository setup -------------------------------------------------

	public function initBare(string $gitDir, string $branch): void {
		$this->runOrThrow(['git', 'init', '--bare', '--initial-branch=' . $branch, $gitDir]);
	}

	public function mktreeEmpty(string $gitDir): string {
		return trim($this->runOrThrow(['git', '-C', $gitDir, 'mktree'], input: ''));
	}

	public function commitTree(string $gitDir, string $tree, string $message, string $authorName, string $authorEmail): string {
		return trim($this->runOrThrow(
			['git', '-C', $gitDir, 'commit-tree', $tree, '-m', $message],
			extraEnv: $this->identityEnv($authorName, $authorEmail),
		));
	}

	public function updateRef(string $gitDir, string $ref, string $sha): void {
		$this->runOrThrow(['git', '-C', $gitDir, 'update-ref', $ref, $sha]);
	}

	public function setHead(string $gitDir, string $ref): void {
		$this->runOrThrow(['git', '-C', $gitDir, 'symbolic-ref', 'HEAD', $ref]);
	}

	public function getHeadBranch(string $gitDir): string {
		$ref = trim($this->runOrThrow(['git', '-C', $gitDir, 'symbolic-ref', 'HEAD']));
		return str_starts_with($ref, 'refs/heads/') ? substr($ref, strlen('refs/heads/')) : $ref;
	}

	public function worktreeAdd(string $gitDir, string $worktreePath, string $branch): void {
		$this->runOrThrow(['git', '-C', $gitDir, 'worktree', 'add', $worktreePath, $branch]);
	}

	public function configSet(string $gitDir, string $key, string $value): void {
		$this->runOrThrow(['git', '-C', $gitDir, 'config', $key, $value]);
	}

	public function configSetWorktree(string $worktree, string $key, string $value): void {
		$this->runOrThrow(['git', '-C', $worktree, 'config', '--worktree', $key, $value]);
	}

	// ---- annex ------------------------------------------------------------

	public function annexInit(string $worktree, string $description): void {
		$this->runOrThrow(['git', '-C', $worktree, 'annex', 'init', $description]);
	}

	public function annexAdd(string $worktree, array $paths): void {
		$this->runOrThrow(array_merge(['git', '-C', $worktree, 'annex', 'add', '--'], $paths));
	}

	public function annexLookupKey(string $worktree, string $path): ?string {
		$result = $this->run(['git', '-C', $worktree, 'annex', 'lookupkey', '--', $path]);
		return $result->exitCode === 0 ? trim($result->stdout) : null;
	}

	public function annexContentLocation(string $gitDir, string $key): ?string {
		$result = $this->run(['git', '-C', $gitDir, 'annex', 'contentlocation', $key]);
		if ($result->exitCode !== 0) {
			return null;
		}
		$path = trim($result->stdout);
		return $path === '' ? null : $path;
	}

	// ---- working tree operations -----------------------------------------

	public function addAll(string $worktree, array $paths): void {
		$this->runOrThrow(array_merge(['git', '-C', $worktree, 'add', '-A', '--'], $paths));
	}

	/**
	 * @return string|null commit sha, or null when there was nothing to commit
	 */
	public function commit(string $worktree, string $message, string $authorName, string $authorEmail): ?string {
		$result = $this->run(
			['git', '-C', $worktree, 'commit', '-m', $message, '--author', sprintf('%s <%s>', $authorName, $authorEmail)],
			extraEnv: $this->identityEnv('Nextcloud Repositories', 'repos@nextcloud.invalid'),
		);
		if ($result->exitCode !== 0) {
			if (str_contains($result->stdout, 'nothing to commit') || str_contains($result->stderr, 'nothing to commit')) {
				return null;
			}
			throw new GitException('commit failed: ' . trim($result->stderr . ' ' . $result->stdout));
		}
		return $this->revParse($worktree, 'HEAD');
	}

	public function resetHard(string $worktree): void {
		$this->runOrThrow(['git', '-C', $worktree, 'reset', '--hard']);
	}

	public function revParse(string $gitDir, string $ref): ?string {
		$result = $this->run(['git', '-C', $gitDir, 'rev-parse', '--verify', '--quiet', $ref]);
		return $result->exitCode === 0 ? trim($result->stdout) : null;
	}

	/**
	 * @return list<array{sha: string, authorName: string, authorEmail: string, timestamp: int, subject: string}>
	 */
	public function logFollow(string $worktree, string $path, int $limit): array {
		$format = '%H%x1f%an%x1f%ae%x1f%at%x1f%s%x1e';
		$result = $this->run(array_merge(
			['git', '-C', $worktree, 'log', '--follow', '-n', (string)$limit, '--pretty=format:' . $format],
			$path === '' ? [] : ['--', $path],
		));
		if ($result->exitCode !== 0) {
			return [];
		}
		$entries = [];
		foreach (explode("\x1e", $result->stdout) as $record) {
			$record = ltrim($record, "\n");
			if ($record === '') {
				continue;
			}
			$fields = explode("\x1f", $record);
			if (count($fields) < 5) {
				continue;
			}
			$entries[] = [
				'sha' => $fields[0],
				'authorName' => $fields[1],
				'authorEmail' => $fields[2],
				'timestamp' => (int)$fields[3],
				'subject' => $fields[4],
			];
		}
		return $entries;
	}

	// ---- smart HTTP protocol ---------------------------------------------

	public function uploadPackAdvertise(string $gitDir): string {
		return $this->runOrThrow(['git', 'upload-pack', '--stateless-rpc', '--advertise-refs', $gitDir]);
	}

	public function receivePackAdvertise(string $gitDir): string {
		return $this->runOrThrow(['git', 'receive-pack', '--stateless-rpc', '--advertise-refs', $gitDir]);
	}

	public function uploadPack(string $gitDir, string $requestBody): string {
		return $this->runOrThrow(['git', 'upload-pack', '--stateless-rpc', $gitDir], input: $requestBody);
	}

	public function receivePack(string $gitDir, string $requestBody): string {
		return $this->runOrThrow(['git', 'receive-pack', '--stateless-rpc', $gitDir], input: $requestBody);
	}

	// ----------------------------------------------------------------------

	private function identityEnv(string $name, string $email): array {
		return [
			'GIT_AUTHOR_NAME' => $name,
			'GIT_AUTHOR_EMAIL' => $email,
			'GIT_COMMITTER_NAME' => $name,
			'GIT_COMMITTER_EMAIL' => $email,
		];
	}
}
