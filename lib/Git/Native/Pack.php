<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * Packfile writer and reader. Writing never produces deltas (a plain pack is
 * protocol-valid, issue 29); reading applies ofs- and ref-deltas because
 * clients send them on push.
 */
class Pack {
	private const OBJ_OFS_DELTA = 6;
	private const OBJ_REF_DELTA = 7;

	// ---- writing -----------------------------------------------------------

	/**
	 * @param list<array{type: string, content: string}> $objects
	 */
	public static function write(array $objects): string {
		$out = 'PACK' . pack('N', 2) . pack('N', count($objects));
		$typeIds = array_flip(Objects::TYPE_NAMES);
		foreach ($objects as $object) {
			$type = $typeIds[$object['type']];
			$size = strlen($object['content']);
			// type + size varint: first byte carries type (bits 4-6) and low 4 size bits
			$byte = ($type << 4) | ($size & 0x0f);
			$size >>= 4;
			$header = '';
			while ($size > 0) {
				$header .= chr($byte | 0x80);
				$byte = $size & 0x7f;
				$size >>= 7;
			}
			$header .= chr($byte);
			$out .= $header . zlib_encode($object['content'], ZLIB_ENCODING_DEFLATE);
		}
		return $out . sha1($out, true);
	}

	// ---- reading -----------------------------------------------------------

	/**
	 * Parse a pack stream. Ref-delta bases may live outside the pack; they are
	 * resolved through $baseLookup(sha) => content-with-type or null.
	 *
	 * @param callable(string): (array{type: string, content: string}|null) $baseLookup
	 * @return list<array{type: string, content: string, sha: string}>
	 */
	public static function read(string $pack, callable $baseLookup): array {
		if (!str_starts_with($pack, 'PACK')) {
			throw new \RuntimeException('Not a pack stream');
		}
		$count = unpack('N', substr($pack, 8, 4))[1];
		$offset = 12;
		/** @var array<int, array{type: int, content: string}> $byOffset */
		$byOffset = [];
		$objects = [];

		for ($i = 0; $i < $count; $i++) {
			$entryOffset = $offset;
			$byte = ord($pack[$offset++]);
			$type = ($byte >> 4) & 0x07;
			$size = $byte & 0x0f;
			$shift = 4;
			while ($byte & 0x80) {
				$byte = ord($pack[$offset++]);
				$size |= ($byte & 0x7f) << $shift;
				$shift += 7;
			}

			$baseOffset = null;
			$baseSha = null;
			if ($type === self::OBJ_OFS_DELTA) {
				$byte = ord($pack[$offset++]);
				$rel = $byte & 0x7f;
				while ($byte & 0x80) {
					$byte = ord($pack[$offset++]);
					$rel = (($rel + 1) << 7) | ($byte & 0x7f);
				}
				$baseOffset = $entryOffset - $rel;
			} elseif ($type === self::OBJ_REF_DELTA) {
				$baseSha = bin2hex(substr($pack, $offset, 20));
				$offset += 20;
			}

			// inflate the entry; zlib tracks how many input bytes it consumed
			$ctx = inflate_init(ZLIB_ENCODING_DEFLATE);
			$content = '';
			$fed = 0;
			while (true) {
				$chunk = substr($pack, $offset + $fed, 65536);
				if ($chunk === '') {
					throw new \RuntimeException('Truncated pack entry');
				}
				$content .= inflate_add($ctx, $chunk);
				$fed += strlen($chunk);
				if (inflate_get_status($ctx) === ZLIB_STREAM_END) {
					break;
				}
			}
			$offset += inflate_get_read_len($ctx);

			if ($type === self::OBJ_OFS_DELTA || $type === self::OBJ_REF_DELTA) {
				if ($baseOffset !== null) {
					$base = $byOffset[$baseOffset] ?? null;
					if ($base === null) {
						throw new \RuntimeException('ofs-delta base not found in pack');
					}
					$baseType = $base['type'];
					$baseContent = $base['content'];
				} else {
					$base = $baseLookup($baseSha);
					if ($base === null) {
						throw new \RuntimeException('ref-delta base ' . $baseSha . ' not found');
					}
					$baseType = array_flip(Objects::TYPE_NAMES)[$base['type']];
					$baseContent = $base['content'];
				}
				$content = self::applyDelta($baseContent, $content);
				$type = $baseType;
			}

			$byOffset[$entryOffset] = ['type' => $type, 'content' => $content];
			$typeName = Objects::TYPE_NAMES[$type] ?? null;
			if ($typeName === null) {
				throw new \RuntimeException('Unknown object type ' . $type . ' in pack');
			}
			$objects[] = [
				'type' => $typeName,
				'content' => $content,
				'sha' => Objects::hash($typeName, $content),
			];
		}
		return $objects;
	}

	private static function applyDelta(string $base, string $delta): string {
		$offset = 0;
		$readVarint = function () use ($delta, &$offset): int {
			$value = 0;
			$shift = 0;
			do {
				$byte = ord($delta[$offset++]);
				$value |= ($byte & 0x7f) << $shift;
				$shift += 7;
			} while ($byte & 0x80);
			return $value;
		};
		$readVarint(); // base size (unchecked)
		$resultSize = $readVarint();

		$out = '';
		$len = strlen($delta);
		while ($offset < $len) {
			$op = ord($delta[$offset++]);
			if ($op & 0x80) {
				// copy from base
				$copyOffset = 0;
				$copySize = 0;
				foreach ([0, 8, 16, 24] as $i => $shift) {
					if ($op & (1 << $i)) {
						$copyOffset |= ord($delta[$offset++]) << $shift;
					}
				}
				foreach ([0, 8, 16] as $i => $shift) {
					if ($op & (0x10 << $i)) {
						$copySize |= ord($delta[$offset++]) << $shift;
					}
				}
				if ($copySize === 0) {
					$copySize = 0x10000;
				}
				$out .= substr($base, $copyOffset, $copySize);
			} elseif ($op > 0) {
				// insert literal
				$out .= substr($delta, $offset, $op);
				$offset += $op;
			} else {
				throw new \RuntimeException('Invalid delta opcode 0');
			}
		}
		if (strlen($out) !== $resultSize) {
			throw new \RuntimeException('Delta result size mismatch');
		}
		return $out;
	}
}
