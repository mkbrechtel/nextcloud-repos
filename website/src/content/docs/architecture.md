---
title: Architecture decisions
description: What we chose, what we rejected, and why.
---

The full decision record lives in the repository's `issues/` folder (issue 22
and its Considerations). The short version:

**Fork of Team Folders.** The app is a hard fork of Nextcloud's Team Folders
app — it inherits admin-defined folders mounted into many accounts with group
permissions and ACLs, which is exactly the management model repositories need.

**Stock binaries, no abstraction tax.** The server invokes real `git` and
`git-annex`. Protocols stay upstream-maintained; the app is orchestration.

**POSIX repository store.** Git's object store assumes filesystem semantics,
and every git forge scales the same way: repositories on POSIX volumes. The
bulk data goes to the annex, which is the part that can move to object storage.

**Rejected: git objects in the database or S3.** Would mean reimplementing the
git stack in PHP and losing interop with every git tool — including git-annex,
which is the point of the project.

**Rejected for now: pure-PHP git.** No production-grade implementation exists
(the JGit-DFS model is proven in Java but would have to be built from scratch).
The `GitCli` command layer keeps that door open.

**Dropped from MVP: a separate backend daemon.** A Gitaly-style Go service
would enable stateless multi-node deployments and full annex locking via
`git annex p2phttp`. It is a compatible upgrade, not a prerequisite — the MVP
is one PHP app you install like any other.

**One credential everywhere.** Smart HTTP, WebDAV and annex objects all
authenticate with Nextcloud credentials/app passwords. Type it once at clone;
git's credential helper covers the rest.
