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
 * Annexed file content, content-addressed by annex key, in the app's appdata
 * area — the instance's primary storage, object stores included (issue 29).
 */
class AnnexStore {
	public function __construct(
		private readonly IAppData $appData,
	) {
	}

	private function folder(int $folderId, string $key, bool $create): ?ISimpleFolder {
		$path = 'annex-' . $folderId . '-' . substr(md5($key), 0, 2);
		try {
			return $this->appData->getFolder($path);
		} catch (NotFoundException) {
			return $create ? $this->appData->newFolder($path) : null;
		}
	}

	public function has(int $folderId, string $key): bool {
		return $this->folder($folderId, $key, false)?->fileExists($key) ?? false;
	}

	public function write(int $folderId, string $key, string $content): void {
		$folder = $this->folder($folderId, $key, true);
		if (!$folder->fileExists($key)) {
			$folder->newFile($key, $content);
		}
	}

	/**
	 * @return resource|null
	 */
	public function readStream(int $folderId, string $key) {
		$folder = $this->folder($folderId, $key, false);
		if ($folder === null) {
			return null;
		}
		try {
			return $folder->getFile($key)->read() ?: null;
		} catch (NotFoundException) {
			return null;
		}
	}

	public function size(int $folderId, string $key): ?int {
		$folder = $this->folder($folderId, $key, false);
		if ($folder === null) {
			return null;
		}
		try {
			return $folder->getFile($key)->getSize();
		} catch (NotFoundException) {
			return null;
		}
	}

	public function read(int $folderId, string $key): ?string {
		$folder = $this->folder($folderId, $key, false);
		if ($folder === null) {
			return null;
		}
		try {
			return $folder->getFile($key)->getContent();
		} catch (NotFoundException) {
			return null;
		}
	}
}
