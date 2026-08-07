---
title: Getting started
description: Install the app, create a repository folder, clone it.
---

## Requirements

- Nextcloud 32 with the `repos` app enabled
- `git` and `git-annex` on the server (hard dependency — the app orchestrates the real binaries)
- A POSIX filesystem for the repository store (`<datadir>/__repos`)

## Create a repository folder

```bash
occ repos:create mydata
occ repos:group <id> mygroup write
```

The folder appears in the Files app for every member of `mygroup`, backed by a
git working tree on the server.

## Clone it

```bash
git clone https://cloud.example.org/apps/repos/mydata.git
```

Authenticate with your Nextcloud username and an app password
(Settings → Security → Devices & sessions). Git's credential helper stores it
once; every later `fetch`, `push` and `git annex get` reuses it.

## Work from both sides

- **Push:** commits appear in the Files app immediately.
- **Edit in the browser:** each save becomes a git commit attributed to the
  Nextcloud user who made it.
- **Large files:** uploads beyond the configured threshold (default 100 MB,
  `annex.largefiles`) are annexed; clones receive lightweight pointers and fetch
  content on demand:

```bash
git annex get bigfile.h5
```

- **Datalad:**

```bash
datalad clone https://cloud.example.org/apps/repos/mydata.git
```

## File history in the Files app

Every file in a repository folder has a **History** tab in the details sidebar:
commit messages, authors, dates, and for annexed files whether their content is
on the server.
