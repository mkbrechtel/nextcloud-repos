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

### Or log in through the browser

With [git-credential-oauth](https://github.com/hickford/git-credential-oauth)
installed (`apt install git-credential-oauth` on Debian/Ubuntu), cloning opens
your Nextcloud in a browser, you click "Grant access" once, and tokens refresh
themselves from then on — no password ever typed or stored.

The administrator runs this once per instance to register the client and print
the exact setup commands:

```bash
occ repos:oauth:setup
```

Both methods stay supported: app passwords work on headless machines (HPC
nodes, CI) where no browser exists, OAuth is nicer on a workstation.

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
