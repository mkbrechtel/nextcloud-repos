<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * Git object model, by hand: encode/decode blobs, trees and commits.
 * `<type> <size>\0<content>`, addressed by sha1 of that framing (issue 29).
 */
class Objects {
	public const TYPE_COMMIT = 1;
	public const TYPE_TREE = 2;
	public const TYPE_BLOB = 3;
	public const TYPE_TAG = 4;

	public const TYPE_NAMES = [
		self::TYPE_COMMIT => 'commit',
		self::TYPE_TREE => 'tree',
		self::TYPE_BLOB => 'blob',
		self::TYPE_TAG => 'tag',
	];

	public static function hash(string $type, string $content): string {
		return sha1($type . ' ' . strlen($content) . "\0" . $content);
	}

	public static function frame(string $type, string $content): string {
		return $type . ' ' . strlen($content) . "\0" . $content;
	}

	/**
	 * @return array{type: string, content: string}
	 */
	public static function unframe(string $raw): array {
		$nul = strpos($raw, "\0");
		if ($nul === false) {
			throw new \RuntimeException('Malformed git object: no header terminator');
		}
		[$type, $size] = explode(' ', substr($raw, 0, $nul), 2);
		$content = substr($raw, $nul + 1);
		if ((int)$size !== strlen($content)) {
			throw new \RuntimeException('Malformed git object: size mismatch');
		}
		return ['type' => $type, 'content' => $content];
	}

	// ---- trees -------------------------------------------------------------

	/**
	 * @param array<string, array{mode: string, sha: string}> $entries name => entry
	 */
	public static function encodeTree(array $entries): string {
		// git sorts tree entries by name bytes, directories as name + '/'
		uksort($entries, function (string $a, string $b) use ($entries): int {
			$aa = $entries[$a]['mode'] === '40000' ? $a . '/' : $a;
			$bb = $entries[$b]['mode'] === '40000' ? $b . '/' : $b;
			return strcmp($aa, $bb);
		});
		$out = '';
		foreach ($entries as $name => $entry) {
			$out .= $entry['mode'] . ' ' . $name . "\0" . hex2bin($entry['sha']);
		}
		return $out;
	}

	/**
	 * @return array<string, array{mode: string, sha: string}>
	 */
	public static function decodeTree(string $content): array {
		$entries = [];
		$offset = 0;
		$len = strlen($content);
		while ($offset < $len) {
			$space = strpos($content, ' ', $offset);
			$nul = strpos($content, "\0", $space);
			$mode = substr($content, $offset, $space - $offset);
			$name = substr($content, $space + 1, $nul - $space - 1);
			$sha = bin2hex(substr($content, $nul + 1, 20));
			$entries[$name] = ['mode' => $mode, 'sha' => $sha];
			$offset = $nul + 21;
		}
		return $entries;
	}

	// ---- commits -----------------------------------------------------------

	/**
	 * @param string[] $parents
	 */
	public static function encodeCommit(
		string $treeSha,
		array $parents,
		string $authorName,
		string $authorEmail,
		int $timestamp,
		string $message,
	): string {
		$ident = sprintf('%s <%s> %d +0000', $authorName, $authorEmail, $timestamp);
		$out = 'tree ' . $treeSha . "\n";
		foreach ($parents as $parent) {
			$out .= 'parent ' . $parent . "\n";
		}
		$out .= 'author ' . $ident . "\n";
		$out .= 'committer ' . $ident . "\n";
		$out .= "\n" . $message;
		if (!str_ends_with($message, "\n")) {
			$out .= "\n";
		}
		return $out;
	}

	/**
	 * @return array{tree: string, parents: string[], authorName: string, authorEmail: string, timestamp: int, message: string}
	 */
	public static function decodeCommit(string $content): array {
		[$head, $message] = explode("\n\n", $content, 2) + [1 => ''];
		$tree = '';
		$parents = [];
		$authorName = '';
		$authorEmail = '';
		$timestamp = 0;
		foreach (explode("\n", $head) as $line) {
			if (str_starts_with($line, 'tree ')) {
				$tree = substr($line, 5);
			} elseif (str_starts_with($line, 'parent ')) {
				$parents[] = substr($line, 7);
			} elseif (str_starts_with($line, 'author ')) {
				if (preg_match('/^author (.*) <(.*)> (\d+) [+-]\d{4}$/', $line, $m)) {
					$authorName = $m[1];
					$authorEmail = $m[2];
					$timestamp = (int)$m[3];
				}
			}
		}
		return [
			'tree' => $tree,
			'parents' => $parents,
			'authorName' => $authorName,
			'authorEmail' => $authorEmail,
			'timestamp' => $timestamp,
			'message' => $message,
		];
	}
}
