<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

/**
 * Git smart HTTP protocol v0, minimal capability set, in PHP (issue 29).
 */
class NativeProtocol {
	public function __construct(
		private readonly ObjectStore $objects,
		private readonly RefStore $refs,
		private readonly NativeRepository $repo,
	) {
	}

	private const AGENT = 'agent=nextcloud-repos-native';

	public function advertiseUploadPack(int $folderId): string {
		$refs = $this->refs->all($folderId);
		$head = $refs[NativeRepository::HEAD_REF] ?? null;
		$out = '';
		$caps = 'symref=HEAD:' . NativeRepository::HEAD_REF . ' ' . self::AGENT;
		if ($head === null) {
			// empty repository advertisement
			$out .= PktLine::line('0000000000000000000000000000000000000000 capabilities^{}' . "\0" . $caps . "\n");
		} else {
			$out .= PktLine::line($head . ' HEAD' . "\0" . $caps . "\n");
			ksort($refs);
			foreach ($refs as $ref => $sha) {
				$out .= PktLine::line($sha . ' ' . $ref . "\n");
			}
		}
		return $out . PktLine::FLUSH;
	}

	public function advertiseReceivePack(int $folderId): string {
		$refs = $this->refs->all($folderId);
		$caps = 'report-status delete-refs ' . self::AGENT;
		$out = '';
		if ($refs === []) {
			$out .= PktLine::line('0000000000000000000000000000000000000000 capabilities^{}' . "\0" . $caps . "\n");
		} else {
			$first = true;
			ksort($refs);
			foreach ($refs as $ref => $sha) {
				$line = $sha . ' ' . $ref;
				if ($first) {
					$line .= "\0" . $caps;
					$first = false;
				}
				$out .= PktLine::line($line . "\n");
			}
		}
		return $out . PktLine::FLUSH;
	}

	public function uploadPack(int $folderId, string $body): string {
		$parsed = PktLine::parse($body);
		$wants = [];
		$haves = [];
		foreach ($parsed['lines'] as $line) {
			if ($line === null) {
				continue;
			}
			$line = trim($line);
			if (str_starts_with($line, 'want ')) {
				$wants[] = substr($line, 5, 40);
			} elseif (str_starts_with($line, 'have ')) {
				$haves[] = substr($line, 5, 40);
			}
		}
		if ($wants === []) {
			return PktLine::line("NAK\n");
		}
		// acknowledge the first common commit so clients skip the
		// "no common commits" path; NAK when there is none
		$ackLine = PktLine::line("NAK\n");
		foreach ($haves as $have) {
			if ($this->objects->has($folderId, $have)) {
				$ackLine = PktLine::line('ACK ' . $have . "\n");
				break;
			}
		}
		$objects = $this->repo->objectsForPack($folderId, $wants, $haves);
		return $ackLine . Pack::write($objects);
	}

	/**
	 * @return list<array{ref: string, old: string, new: string}> applied updates
	 */
	public function receivePack(int $folderId, string $body, string &$response): array {
		$parsed = PktLine::parse($body);
		$commands = [];
		foreach ($parsed['lines'] as $line) {
			if ($line === null) {
				break;
			}
			$line = rtrim($line, "\n");
			$nul = strpos($line, "\0");
			if ($nul !== false) {
				$line = substr($line, 0, $nul);
			}
			[$old, $new, $ref] = explode(' ', $line, 3);
			$commands[] = ['old' => $old, 'new' => $new, 'ref' => $ref];
		}

		// store incoming objects
		if (str_starts_with($parsed['rest'], 'PACK')) {
			$incoming = Pack::read($parsed['rest'], function (string $sha) use ($folderId): ?array {
				return $this->objects->read($folderId, $sha);
			});
			foreach ($incoming as $object) {
				$this->objects->write($folderId, $object['type'], $object['content']);
				if ($object['type'] === 'commit') {
					$this->refs->indexCommit($folderId, $object['sha'], Objects::decodeCommit($object['content']));
				}
			}
		}

		$zero = str_repeat('0', 40);
		$applied = [];
		$results = [];
		foreach ($commands as $command) {
			// the git-annex branch is server-owned bookkeeping: Nextcloud is
			// the annex store and sole writer of location logs (issue 29)
			if ($command['ref'] === 'refs/heads/git-annex' || str_ends_with($command['ref'], '/git-annex')) {
				$results[] = 'ng ' . $command['ref'] . ' the git-annex branch is maintained by the server';
				continue;
			}
			$current = $this->refs->get($folderId, $command['ref']) ?? $zero;
			if ($current !== $command['old']) {
				$results[] = 'ng ' . $command['ref'] . ' non-fast-forward';
				continue;
			}
			if ($command['new'] === $zero) {
				if ($command['ref'] === NativeRepository::HEAD_REF) {
					$results[] = 'ng ' . $command['ref'] . ' refusing to delete the default branch';
					continue;
				}
				$this->refs->delete($folderId, $command['ref']);
			} else {
				if (!$this->objects->has($folderId, $command['new'])) {
					$results[] = 'ng ' . $command['ref'] . ' missing objects';
					continue;
				}
				$this->refs->set($folderId, $command['ref'], $command['new']);
			}
			$applied[] = $command;
			$results[] = 'ok ' . $command['ref'];
		}

		$response = PktLine::line("unpack ok\n");
		foreach ($results as $result) {
			$response .= PktLine::line($result . "\n");
		}
		$response .= PktLine::FLUSH;
		return $applied;
	}
}
