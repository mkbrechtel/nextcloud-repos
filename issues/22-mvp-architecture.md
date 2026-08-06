---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: MVP Architecture — Direct Git Execution, Same-Channel Annex Access

## Category
Key Architecture Patterns

## Current State
The refactored app manages repo folders (Nextcloud storage) and serves git repositories over smart HTTP (bare repos in a configured directory), but the two are unconnected. Architecture decisions were postponed in issue 20 and are now settled here.

## Plan
The MVP is a pure PHP Nextcloud app plus the stock `git` and `git-annex` binaries. No separate backend service, no protocol reimplementation, no storage abstraction layer.

**Repository store.** All repositories live under one configured directory (`repos_directory`) on a POSIX filesystem, one subdirectory per repository holding the authoritative bare repo and its checked-out working tree:

```
<repos_dir>/<id>/repo.git    bare repository — clients clone/push here
<repos_dir>/<id>/worktree    linked working tree — mounted as the repo folder
```

**Git execution.** The app invokes the `git` and `git-annex` binaries directly against these directories (`proc_open`, streaming). Every subprocess invocation is wrapped in a named PHP function on one command layer class — one function per git operation, no shell strings at call sites. That layer is the seam for a possible later native git implementation (objects in DB or storage backend) without touching callers. Beyond it, deliberately no backend interface or wrapper hierarchy in the MVP. The binaries are a hard server dependency, declared in the app documentation and present in the container image.

**One credential.** Every endpoint — smart HTTP, WebDAV, annex object access — authenticates with the user's Nextcloud credentials or app password. A user enters one app password at `git clone` and git's credential helper reuses it for everything else.

**Configuration.** Repository metadata moves from the JSON file (`ConfigManager`) to Nextcloud appconfig so that all app processes share one source of truth.

**Deployment.** Single container is the primary target: one volume for the repos directory. Multi-node deployments must share the repos volume (NFS/CephFS) — a documented requirement, same as every git forge. Scaling refinements (stateless nodes, annex content on object storage) are explicitly out of MVP scope.

**README.** The "Component 2: Git-annex special remote" (Go client tool) section is replaced by a description of same-channel access: stock git/git-annex clients, one URL, one app password.

### Implementation order
Issues 23 (worktree bridge), 24 (commit-on-write), 25 (annex endpoint), then 26–27 (UI, acceptance).

# Considerations

## Dropped: separate Go backend service (reposd)
A Gitaly-style Go daemon owning the repo store would give stateless Nextcloud nodes and could front `git annex p2phttp` for full protocol support with content locking. Dropped from the MVP for deployment and development simplicity — one component, app-store installable, fastest path to a working prototype. Revisit when stateless multi-node deployment or annex locking semantics become real requirements; nothing in the MVP layout blocks it.

## Rejected: git objects in the database or object store
Git's object store assumes filesystem semantics (packfile random access, atomic ref renames, gc) and every tool in the ecosystem — including git-annex — speaks filesystem. Databases and S3 would require reimplementing the git stack and losing binary interop. No production git forge stores git objects this way; repos are sharded across POSIX volumes instead.

## Rejected: pure-PHP git or libgit2 bindings
No maintained production-grade pure-PHP git implementation exists (glip, git4p, Rodziu/php-git are read-only fragments). libgit2 PHP extensions are unmaintained or require compiled extensions a Nextcloud app cannot demand; PHP FFI cannot support libgit2 custom backends. A JGit-DFS-style "objects on distributed storage, refs in DB" design is proven in Java but would have to be built from scratch in PHP, including pack generation — a project in itself.

## Deferred: git-LFS endpoint and p2phttp
A git-LFS batch endpoint under the repo URL would give authenticated same-channel uploads via git's credential helper; `git annex p2phttp` behind a proxy would give the full annex protocol with locking. Both are compatible upgrades of this architecture, not MVP requirements. The built-in `webdav` special remote against Nextcloud remains a zero-code fallback for direct annex uploads from clones.
