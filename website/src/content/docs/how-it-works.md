---
title: How it works
description: The worktree bridge, commit-on-write, and same-channel annex access.
---

## The bridge

Each repository folder is two views of one repository on the server:

```
<datadir>/__repos/<id>/repo.git    bare, authoritative — clients clone/push here
<datadir>/__repos/<id>/files       linked working tree — mounted as the folder
```

A **push** updates the bare repository; the app then updates the working tree
and rescans the file cache, so the Files app reflects the push immediately.

A **write through Nextcloud** (Files app, WebDAV, sync client) is intercepted by
a storage wrapper: the operation lands in the working tree, gets staged —
through `git annex add`, which routes small files to git and large ones to the
annex — and is committed with the acting Nextcloud user as author. A per-repo
lock serializes pushes against browser edits.

## Same-channel annex access

git-annex clients fetch content **over the clone URL** — no special remote, no
client-side setup:

- The app serves the repository's `config` over dumb HTTP, so a stock client
  discovers the annex UUID and treats the origin as an annex peer.
- Annex objects are served at their standard in-repo paths
  (`…/repos/<name>/annex/objects/…`), streamed straight from the object store.
- Authentication is the same app password the clone already stored in git's
  credential helper.

The endpoint is read-only: the Nextcloud instance is the trusted, always-present
central store. Content enters through pushes and Nextcloud-side uploads.

## Command layer

Every git operation the app performs is one named PHP function on a single
command layer (`GitCli`) that spawns the real `git`/`git-annex` binaries. No
shell strings at call sites, no reimplemented protocols — and one seam to swap
in a native implementation later.
