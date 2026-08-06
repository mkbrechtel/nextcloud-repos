---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: History Sidebar — Git Context in the Files App

## Category
Files App Integration

## Files Affected
- `src/` (sidebar tab component)
- `lib/Controller/` (history API)

## Current State
Files in a repo folder look like ordinary Nextcloud files. Commit messages, authors, and annex state are invisible to Files app users.

## Plan
A "History" tab in the file-details sidebar for files inside repo folders, showing the file's commit log — message, author, date — and for annexed files their key and content presence. Data comes from a small read-only OCS API backed by `git log --follow` and `git annex info` on the repository.

This is intentionally read-only and small: the first visible payoff of the integration for non-technical users, answering "what happened to this file and who did it".

### Acceptance
A file edited both via push and via the Files app shows both commits with correct attribution in the sidebar.

## Status
The history API is implemented (`/apps/repos/api/history/{repo}`, addressable by folder id or mount point, covered by the e2e test) and a build-free vanilla JS sidebar tab (`js/repos-history.js`) is registered and served on the Files page. Outstanding: verify the tab renders correctly in an actual browser session and refine the presentation.
