---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Revive the Containerized Development Environment

## Category
Development Environment

## Current State
The `vibe-code` branch (October 2025) contains the containerized development workflow: a multi-stage `Containerfile`, a `Makefile` wrapping all build/test/run tasks, file-based repository configuration, the Git smart-HTTP controller, and Playwright/PHPUnit test scaffolding. It was never merged and had bitrotted.

## Plan
Merge `vibe-code` into a current branch and make `make dev` work again against Nextcloud 32.

### Changes
1. Merge `vibe-code` (19 commits) onto `main`.
2. Pin `nextcloud/ocp` to `dev-stable32` — `dev-master` requires PHP 8.3+ and broke the container build after lock files were removed.
3. Make the dev server port configurable (`DEV_PORT`, default 8067).
4. `chown` `config/` and `data/` to `www-data` after build-time initialization; wrong ownership prevented Nextcloud from starting.

## Result
Verified working: `make dev` boots Nextcloud 32 with the app enabled; `occ repos:create` / `repos:group` manage repo folders visible over WebDAV with per-group read/write permissions; `git clone` and `git push` work over the app's smart-HTTP endpoint with Nextcloud credentials. Verified missing: the repo folder storage and the served git repositories are unrelated on disk — the bridge between them is the next milestone.
