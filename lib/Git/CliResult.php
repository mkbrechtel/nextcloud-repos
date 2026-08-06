<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git;

class CliResult {
	public function __construct(
		public readonly int $exitCode,
		public readonly string $stdout,
		public readonly string $stderr,
	) {
	}
}
