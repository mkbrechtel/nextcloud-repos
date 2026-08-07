---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Native PHP Git Backend — No Binary, No Worktree

## Category
Core Business Logic

## Current State
Repositories are real on-disk git repos: a bare repo plus a checked-out worktree on a POSIX volume, all operations shelling out to the `git`/`git-annex` binaries through the `GitCli` command layer. That layer was designed as the seam for exactly this change.

## Plan
An internal, deliberately minimal git implementation in PHP — enough for this app, not a general library. It removes the git binary, the worktree, and the POSIX requirement for the git side:

- **Objects by hand.** Blobs, trees and commits are constructed and parsed in PHP (`<type> <size>\0` + zlib + sha1). Loose objects only; packs exist solely at the wire boundary.
- **Objects in Nextcloud storage.** The object store lives in the app's appdata area, which follows the instance's primary storage — including object stores like S3. No symlinks, no worktree, the repo folder is plain Nextcloud storage on any backend.
- **Refs and commit index in the database.** Branch refs live in a `repos_refs` table; a `repos_commits` index carries author/subject/parents for fast history queries. The commit objects themselves live in the object store like everything else.
- **Wire protocol in PHP.** Smart HTTP v0 with a minimal capability set: upload-pack serves clones/fetches with a delta-free pack (valid, just larger); receive-pack parses incoming packs including ofs/ref deltas, updates refs, and materializes the new tree into the folder storage.
- **Commit-on-write natively.** A Files-app save reads the written content from storage, builds blob → tree → commit, updates the ref and index. No lock dance with an external process.

**Rollout.** Per-repository option `backend=native` (`occ repos:create --native`); existing binary-backed repos keep working unchanged. Once annex interop (below) lands, native becomes the default and the binary backend retires.

### Phasing
1. Native vertical slice: init, commit-on-write, clone, push, history. **Done.**
2. Native git-annex interop: pointer blobs on large writes (SHA256E keys and branch hashdirs verified byte-exact against the real binary), annex content in appdata, a hand-constructed `git-annex` branch (uuid.log, per-key location logs), uuid advertisement via the synthesized repo config, content served over the clone URL. **Done** — stock `git annex get` and datalad retrieve content from a native repo (e2e green).
3. The git shellout layer (`GitCli`, `RepoGitService`, worktrees) is **removed**; native is the only backend. The `git`/`git-annex` binaries remain in the container solely as e2e test clients.

### Status
All repos are native. e2e passes including annex and datalad; the real-repo test pushes and re-clones the pinned git v2.47.3 release tree (4537 files) fsck-clean.

## Nextcloud is a store, not an annex peer
The instance serves its files, presents the annex, and passively relays annex state; it performs no sync logic of its own. Content enters through Nextcloud's own interfaces (Files app, WebDAV, pushes of git data) — never by the server fetching from other remotes; the native code performs no outbound requests at all. Clients sync their annex *through* the server the standard way: they push `synced/git-annex` (and other branches), which the server stores as plain refs; other clients fetch and union-merge locally. The server merges nothing, fetches nothing, and never copies content on its own initiative. Only `refs/heads/git-annex` is server-owned — it carries the store's own location log and uuid, so client pushes to that one ref are rejected; the server's records stay authoritative about what the server holds.

# Considerations

## Why the server never union-merges client annex state
Merging is a client duty in git-annex's own design for dumb remotes: `git annex sync` fetches, union-merges locally, and pushes fast-forwards to `synced/git-annex`, so all clients converge without any server logic. Keeping the server out of it preserves provenance — `refs/heads/git-annex` contains only claims the server verified by storing the bytes, so client gossip can never masquerade as store knowledge (a malicious client cannot inject "the server holds key X"). It also keeps the one piece of interpretive annex semantics (union merge, with its edge cases and growth behavior) out of the server entirely. The cost is that the server's own UI only knows its own holdings; if global whereabouts are ever wanted in the sidebar, read `synced/git-annex` passively at presentation time and label it as client-reported — no merge required.

## Pointer files stay
Annexed files are represented in git history as small pointer blobs in both backends — that is git-annex's own design and what clients require. The native backend changes where object bytes live and how they are produced, not the annex data model.

## Why deltas are skipped on send
A pack of plain zlib-compressed objects is protocol-valid; delta compression only saves bandwidth. Skipping it makes upload-pack tractable and keeps clone correctness independent of delta heuristics. Receive must still *apply* deltas, since clients send them.
