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
1. Native vertical slice: init, commit-on-write, clone, push, history — this issue.
2. git-annex interop: pointer blobs on large writes, annex object serving from Nextcloud storage, and a hand-constructed `git-annex` branch (uuid.log, per-key location logs — documented text formats) so stock clients keep working. Follow-up issue.

### Acceptance
A repo created with `--native`: stock `git clone` works, a WebDAV upload appears as an attributed commit on `git pull`, `git push` shows up in the Files app, the History tab works — with the `git` binary removed from the server container for the test.

# Considerations

## Pointer files stay
Annexed files are represented in git history as small pointer blobs in both backends — that is git-annex's own design and what clients require. The native backend changes where object bytes live and how they are produced, not the annex data model.

## Why deltas are skipped on send
A pack of plain zlib-compressed objects is protocol-valid; delta compression only saves bandwidth. Skipping it makes upload-pack tractable and keeps clone correctness independent of delta heuristics. Receive must still *apply* deltas, since clients send them.
