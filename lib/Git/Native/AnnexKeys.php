<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * git-annex key math, verified against the real binary (issue 29):
 * SHA256E keys keep up to two trailing filename extensions (each 1-4
 * alphanumeric chars, case preserved); the git-annex branch stores a key's
 * location log under md5(key)[0:3]/[3:6]/<key>.log.
 */
class AnnexKeys {
	public static function keyForContent(string $fileName, string $content): string {
		return 'SHA256E-s' . strlen($content) . '--' . hash('sha256', $content) . self::extension($fileName);
	}

	public static function extension(string $fileName): string {
		$parts = explode('.', basename($fileName));
		array_shift($parts); // the base name itself
		$kept = [];
		foreach (array_reverse($parts) as $part) {
			if ($part === '' || strlen($part) > 4 || !ctype_alnum($part)) {
				break;
			}
			array_unshift($kept, $part);
			if (count($kept) === 2) {
				break;
			}
		}
		return $kept === [] ? '' : '.' . implode('.', $kept);
	}

	public static function branchHashDir(string $key): string {
		$md5 = md5($key);
		return substr($md5, 0, 3) . '/' . substr($md5, 3, 3);
	}

	public static function isPointer(string $content): bool {
		return strlen($content) < 32768 && preg_match('#^/annex/objects/[A-Za-z0-9][^/\n]*\n?$#', $content) === 1;
	}

	public static function pointerFor(string $key): string {
		return '/annex/objects/' . $key . "\n";
	}

	public static function keyFromPointer(string $content): ?string {
		if (!self::isPointer($content)) {
			return null;
		}
		return rtrim(substr(trim($content), strlen('/annex/objects/')), '/');
	}
}
