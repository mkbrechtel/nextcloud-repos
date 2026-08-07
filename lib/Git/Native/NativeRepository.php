<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * High-level repository operations over the native object store: init,
 * tree manipulation, commits, log walks and reachability (issue 29).
 */
class NativeRepository {
	public const DEFAULT_BRANCH = 'main';
	public const HEAD_REF = 'refs/heads/' . self::DEFAULT_BRANCH;
	private const COMMITTER = 'Nextcloud Repositories';
	private const COMMITTER_MAIL = 'repos@nextcloud.invalid';

	public function __construct(
		private readonly ObjectStore $objects,
		private readonly RefStore $refs,
	) {
	}

	public function init(int $folderId, int $timestamp): string {
		$emptyTree = $this->objects->write($folderId, 'tree', '');
		$commitContent = Objects::encodeCommit(
			$emptyTree, [], self::COMMITTER, self::COMMITTER_MAIL, $timestamp, 'Initialize repository',
		);
		$sha = $this->objects->write($folderId, 'commit', $commitContent);
		$this->refs->set($folderId, self::HEAD_REF, $sha);
		$this->refs->indexCommit($folderId, $sha, Objects::decodeCommit($commitContent));
		return $sha;
	}

	public function head(int $folderId): ?string {
		return $this->refs->get($folderId, self::HEAD_REF);
	}

	/**
	 * Full recursive listing of a commit's tree: path => ['mode','sha'].
	 *
	 * @return array<string, array{mode: string, sha: string}>
	 */
	public function listTree(int $folderId, string $commitSha): array {
		$commit = $this->objects->read($folderId, $commitSha);
		if ($commit === null || $commit['type'] !== 'commit') {
			return [];
		}
		$treeSha = Objects::decodeCommit($commit['content'])['tree'];
		$out = [];
		$this->walkTree($folderId, $treeSha, '', $out);
		return $out;
	}

	private function walkTree(int $folderId, string $treeSha, string $prefix, array &$out): void {
		$tree = $this->objects->read($folderId, $treeSha);
		if ($tree === null) {
			return;
		}
		foreach (Objects::decodeTree($tree['content']) as $name => $entry) {
			$path = $prefix === '' ? $name : $prefix . '/' . $name;
			if ($entry['mode'] === '40000') {
				$this->walkTree($folderId, $entry['sha'], $path, $out);
			} else {
				$out[$path] = $entry;
			}
		}
	}

	/**
	 * Apply changes on top of the current head and commit.
	 *
	 * @param array<string, string|null> $changes path => content, null deletes
	 */
	public function commitChanges(
		int $folderId,
		array $changes,
		string $message,
		string $authorName,
		string $authorEmail,
		int $timestamp,
	): ?string {
		$head = $this->head($folderId);
		$flat = $head !== null ? $this->listTree($folderId, $head) : [];

		$dirty = false;
		foreach ($changes as $path => $content) {
			if ($content === null) {
				if (isset($flat[$path])) {
					unset($flat[$path]);
					$dirty = true;
				}
				continue;
			}
			$blobSha = Objects::hash('blob', $content);
			if (($flat[$path]['sha'] ?? null) === $blobSha) {
				continue;
			}
			$this->objects->write($folderId, 'blob', $content);
			$flat[$path] = ['mode' => '100644', 'sha' => $blobSha];
			$dirty = true;
		}
		if (!$dirty) {
			return null;
		}

		$treeSha = $this->writeTreeFromFlat($folderId, $flat);
		$commitContent = Objects::encodeCommit(
			$treeSha, $head !== null ? [$head] : [],
			$authorName, $authorEmail, $timestamp, $message,
		);
		$sha = $this->objects->write($folderId, 'commit', $commitContent);
		$this->refs->set($folderId, self::HEAD_REF, $sha);
		$this->refs->indexCommit($folderId, $sha, Objects::decodeCommit($commitContent));
		return $sha;
	}

	/**
	 * @param array<string, array{mode: string, sha: string}> $flat path => entry
	 */
	public function writeTreeFromFlat(int $folderId, array $flat): string {
		$nested = [];
		foreach ($flat as $path => $entry) {
			$parts = explode('/', $path);
			$cursor = &$nested;
			foreach (array_slice($parts, 0, -1) as $part) {
				$cursor[$part] ??= [];
				$cursor = &$cursor[$part];
			}
			$cursor[end($parts)] = $entry;
			unset($cursor);
		}
		return $this->writeTreeLevel($folderId, $nested);
	}

	private function writeTreeLevel(int $folderId, array $level): string {
		$entries = [];
		foreach ($level as $name => $node) {
			if (isset($node['mode'], $node['sha'])) {
				$entries[$name] = $node;
			} else {
				$entries[$name] = ['mode' => '40000', 'sha' => $this->writeTreeLevel($folderId, $node)];
			}
		}
		return $this->objects->write($folderId, 'tree', Objects::encodeTree($entries));
	}

	/**
	 * Commit log from head, optionally filtered to commits touching a path.
	 *
	 * @return list<array{sha: string, authorName: string, authorEmail: string, timestamp: int, subject: string}>
	 */
	public function log(int $folderId, string $path = '', int $limit = 50): array {
		$out = [];
		$sha = $this->head($folderId);
		while ($sha !== null && count($out) < $limit) {
			$object = $this->objects->read($folderId, $sha);
			if ($object === null || $object['type'] !== 'commit') {
				break;
			}
			$commit = Objects::decodeCommit($object['content']);
			$include = true;
			if ($path !== '') {
				$current = $this->pathSha($folderId, $commit['tree'], $path);
				$parentTree = null;
				if ($commit['parents'] !== []) {
					$parentObj = $this->objects->read($folderId, $commit['parents'][0]);
					if ($parentObj !== null) {
						$parentTree = Objects::decodeCommit($parentObj['content'])['tree'];
					}
				}
				$previous = $parentTree !== null ? $this->pathSha($folderId, $parentTree, $path) : null;
				$include = $current !== $previous;
			}
			if ($include) {
				$out[] = [
					'sha' => $sha,
					'authorName' => $commit['authorName'],
					'authorEmail' => $commit['authorEmail'],
					'timestamp' => $commit['timestamp'],
					'subject' => explode("\n", $commit['message'])[0],
				];
			}
			$sha = $commit['parents'][0] ?? null;
		}
		return $out;
	}

	private function pathSha(int $folderId, string $treeSha, string $path): ?string {
		$parts = explode('/', $path);
		$sha = $treeSha;
		foreach ($parts as $i => $part) {
			$tree = $this->objects->read($folderId, $sha);
			if ($tree === null || $tree['type'] !== 'tree') {
				return null;
			}
			$entries = Objects::decodeTree($tree['content']);
			$entry = $entries[$part] ?? null;
			if ($entry === null) {
				return null;
			}
			$sha = $entry['sha'];
			if ($i < count($parts) - 1 && $entry['mode'] !== '40000') {
				return null;
			}
		}
		return $sha;
	}

	/**
	 * Objects reachable from $wants but not from $haves, as pack-ready list.
	 *
	 * @param string[] $wants commit shas
	 * @param string[] $haves commit shas
	 * @return list<array{type: string, content: string}>
	 */
	public function objectsForPack(int $folderId, array $wants, array $haves): array {
		$stop = [];
		$queue = array_values($haves);
		$guard = 0;
		while ($queue !== [] && $guard++ < 10000) {
			$sha = array_pop($queue);
			if (isset($stop[$sha])) {
				continue;
			}
			$stop[$sha] = true;
			$object = $this->objects->read($folderId, $sha);
			if ($object !== null && $object['type'] === 'commit') {
				foreach (Objects::decodeCommit($object['content'])['parents'] as $parent) {
					$queue[] = $parent;
				}
			}
		}

		$commits = [];
		$queue = array_values($wants);
		$guard = 0;
		while ($queue !== [] && $guard++ < 10000) {
			$sha = array_pop($queue);
			if (isset($commits[$sha]) || isset($stop[$sha])) {
				continue;
			}
			$object = $this->objects->read($folderId, $sha);
			if ($object === null || $object['type'] !== 'commit') {
				continue;
			}
			$commits[$sha] = $object;
			foreach (Objects::decodeCommit($object['content'])['parents'] as $parent) {
				$queue[] = $parent;
			}
		}

		$shas = [];
		$objects = [];
		foreach ($commits as $sha => $object) {
			$shas[$sha] = true;
			$objects[] = ['type' => 'commit', 'content' => $object['content']];
			$treeSha = Objects::decodeCommit($object['content'])['tree'];
			$this->collectTree($folderId, $treeSha, $shas, $objects);
		}
		return $objects;
	}

	private function collectTree(int $folderId, string $treeSha, array &$shas, array &$objects): void {
		if (isset($shas[$treeSha])) {
			return;
		}
		$tree = $this->objects->read($folderId, $treeSha);
		if ($tree === null) {
			return;
		}
		$shas[$treeSha] = true;
		$objects[] = ['type' => 'tree', 'content' => $tree['content']];
		foreach (Objects::decodeTree($tree['content']) as $entry) {
			if ($entry['mode'] === '40000') {
				$this->collectTree($folderId, $entry['sha'], $shas, $objects);
			} elseif (!isset($shas[$entry['sha']])) {
				$blob = $this->objects->read($folderId, $entry['sha']);
				if ($blob !== null) {
					$shas[$entry['sha']] = true;
					$objects[] = ['type' => $blob['type'], 'content' => $blob['content']];
				}
			}
		}
	}
}
