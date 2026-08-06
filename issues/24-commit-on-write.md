---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Commit-on-Write — Nextcloud Edits Become Git Commits

## Category
File System Integration

## Files Affected
- `lib/Mount/` (storage wrapper)
- `lib/Folder/RepoManager.php`

## Current State
With issue 23, the Files app operates directly on a git working tree, but Nextcloud writes leave the worktree dirty and invisible to git history.

## Plan
Every write, move, copy, and delete arriving through Nextcloud (Files app, WebDAV, sync clients) on a repo folder produces a git commit attributed to the acting user.

**Mechanism.** A storage wrapper on the repo mount intercepts mutating operations. After the operation lands in the worktree, the app stages the affected paths and commits with `--author="<display name> <email or user@instance>"` and a generated message naming the operation. Commits go through the shared per-repo lock from issue 23, then propagate to the bare repo (worktree commits advance the shared branch ref directly).

**Granularity.** One commit per completed Nextcloud operation. Chunked uploads commit once on final assembly, not per chunk. Nextcloud's own file locking (Files app / WebDAV locks) continues to govern concurrent editing above the git layer.

**Large files.** Files matching the annex policy (size threshold via `annex.largefiles`, configured per repository) are committed through `git annex add` instead of `git add`: content moves to the annex object store, the tree records the pointer, and the Files app continues to serve the content transparently through the worktree.

### Acceptance
Saving a document in Nextcloud Text produces a commit visible in a clone with correct author attribution; uploading a large file via WebDAV produces an annexed file whose content `git annex get` can retrieve (issue 25); a push and a WebDAV upload racing on the same repo both land without corrupting the worktree.
