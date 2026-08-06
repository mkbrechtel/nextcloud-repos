---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Worktree Bridge — Repo Folders Backed by Git Working Trees

## Category
File System Integration

## Files Affected
- `lib/Folder/RepoManager.php`
- `lib/Mount/` (mount provider, storage)
- `lib/Controller/GitRepoController.php`

## Current State
`repos:create` provisions plain Nextcloud storage; the smart-HTTP endpoint serves bare repos from `repos_directory`. The folder a user sees and the repository a developer clones share no data.

## Plan
A repo folder's storage is the working tree of the repository the HTTP endpoint serves. One history, two views.

**Create.** `repos:create` initializes `<repos_dir>/<id>/repo.git` (bare, `git annex init`) with a linked working tree at `<repos_dir>/<id>/worktree` (`git worktree add`), then mounts the worktree as the folder's storage backend (local storage mount, honoring the existing group/ACL machinery).

**Push.** After `receive-pack` completes on the bare repo, the app updates the worktree to the new branch head and runs a targeted file-cache scan on the mount so the Files app reflects the push immediately. Annexed files present in the repository appear with their content when available and as placeholders when not (refined in issue 25).

**Branch model.** The worktree tracks a single configured branch (default: the repo's default branch). Other branches are fully served over HTTP but not materialized in the folder.

**Concurrency.** A per-repository lock file under `<repos_dir>/<id>/` serializes worktree updates (push-triggered checkouts vs. commit-on-write from issue 24).

### Acceptance
`git push` of a new file makes it appear in the Files app; a file visible in the Files app is identical to the file at the same path in a fresh clone.

# Considerations

## Bare + linked worktree over non-bare with `receive.denyCurrentBranch=updateInstead`
`updateInstead` couples push acceptance to the working tree state (a dirty worktree rejects pushes) and interleaves poorly with app-driven commits. A bare authoritative repo with an explicitly managed worktree keeps push acceptance and folder materialization as two separate, lockable steps.
