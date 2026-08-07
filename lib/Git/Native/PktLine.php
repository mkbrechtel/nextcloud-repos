<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * Git pkt-line framing: 4 hex digits of length (including the 4) + payload;
 * "0000" is a flush packet.
 */
class PktLine {
	public static function line(string $data): string {
		return sprintf('%04x', strlen($data) + 4) . $data;
	}

	public const FLUSH = '0000';

	/**
	 * Parse a pkt-line stream into payloads; flush packets become null.
	 *
	 * @return array{lines: list<string|null>, rest: string} rest = bytes after
	 *         the first non-pkt content (e.g. a PACK stream)
	 */
	public static function parse(string $input): array {
		$lines = [];
		$offset = 0;
		$len = strlen($input);
		while ($offset + 4 <= $len) {
			$hex = substr($input, $offset, 4);
			if (str_starts_with(substr($input, $offset), 'PACK')) {
				break;
			}
			if (!ctype_xdigit($hex)) {
				break;
			}
			$size = (int)hexdec($hex);
			if ($size === 0) {
				$lines[] = null;
				$offset += 4;
				continue;
			}
			if ($size < 4 || $offset + $size > $len) {
				break;
			}
			$lines[] = substr($input, $offset + 4, $size - 4);
			$offset += $size;
		}
		return ['lines' => $lines, 'rest' => substr($input, $offset)];
	}
}
