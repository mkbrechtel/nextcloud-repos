<!--
SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

We are doing a hard fork of the original Nextcloud Team/Group Folders App to extend it so you can directly access Git repos with it.

## Copyright Header Guidelines

**IMPORTANT**: When editing files derived from the original Nextcloud/Team Folders codebase, ALWAYS preserve the original Nextcloud copyright headers. Add new copyright lines in addition to the existing ones, never replace them.

Example for files derived from original codebase:
```php
<?php
// SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
// SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
// SPDX-License-Identifier: AGPL-3.0-or-later
```

For new files created specifically for this fork:
```php
<?php
// SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
// SPDX-License-Identifier: AGPL-3.0-or-later
```

## Development Workflow

All development tasks use the Makefile which wraps Containerfile-based builds. **No host dependencies required** - everything builds inside containers.

### Quick Start

```bash
make dev              # Start development server
make test             # Run all tests (PHP + browser)
make build            # Build frontend assets
make release          # Create release tarball
```

### Key Principles

1. **Container-based builds**: All builds happen in containers via Containerfile stages
2. **No host dependencies**: Don't require yarn, composer, or npm on the host machine
3. **Reproducible builds**: Same Containerfile stages used for dev, testing, and release
4. **Test-driven**: Both PHP unit tests and browser tests must pass

### Common Commands

```bash
# Development
make dev-start        # Build and start dev server
make dev-logs         # Follow server logs
make dev-shell        # Shell into container
make occ ARGS="repos:list"  # Run occ commands

# Testing
make test             # All tests
make test-php         # PHPUnit tests only
make test-browser     # Playwright browser tests

# Building
make build            # Extract built JS from frontend-build stage
make release          # Build release tarball
make clean            # Clean artifacts
```

See `make help` for all available commands.

@README.md
@issues/README.md
@CODEBASE.md
