<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Loose git objects in the app's appdata area — which follows the instance's
 * primary storage (local disk or S3). Layout: git/<folderId>/objects/<aa>/<38>.
 */
class ObjectStore {
	/** @var array<string, string> request-local cache sha => raw frame */
	private array $cache = [];

	public function __construct(
		private readonly IAppData $appData,
	) {
	}

	private function folder(int $folderId, string $shard, bool $create): ?ISimpleFolder {
		$path = 'git-' . $folderId . '-' . $shard;
		try {
			return $this->appData->getFolder($path);
		} catch (NotFoundException) {
			return $create ? $this->appData->newFolder($path) : null;
		}
	}

	public function has(int $folderId, string $sha): bool {
		return $this->readRaw($folderId, $sha) !== null;
	}

	/**
	 * @return array{type: string, content: string}|null
	 */
	public function read(int $folderId, string $sha): ?array {
		$raw = $this->readRaw($folderId, $sha);
		return $raw === null ? null : Objects::unframe($raw);
	}

	public function readRaw(int $folderId, string $sha): ?string {
		if (isset($this->cache[$folderId . ':' . $sha])) {
			return $this->cache[$folderId . ':' . $sha];
		}
		$folder = $this->folder($folderId, substr($sha, 0, 2), false);
		if ($folder === null) {
			return null;
		}
		try {
			$file = $folder->getFile(substr($sha, 2));
		} catch (NotFoundException) {
			return null;
		}
		$raw = zlib_decode($file->getContent());
		if ($raw === false) {
			throw new \RuntimeException('Corrupt object ' . $sha);
		}
		$this->cache[$folderId . ':' . $sha] = $raw;
		return $raw;
	}

	/**
	 * @return string the object's sha
	 */
	public function write(int $folderId, string $type, string $content): string {
		$sha = Objects::hash($type, $content);
		$key = $folderId . ':' . $sha;
		if (isset($this->cache[$key])) {
			return $sha;
		}
		$folder = $this->folder($folderId, substr($sha, 0, 2), true);
		$name = substr($sha, 2);
		if (!$folder->fileExists($name)) {
			$folder->newFile($name, zlib_encode(Objects::frame($type, $content), ZLIB_ENCODING_DEFLATE));
		}
		$this->cache[$key] = Objects::frame($type, $content);
		return $sha;
	}
}
