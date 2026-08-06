---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Annex Object Endpoint — `git annex get` Over the Clone URL

## Category
REST API Layer

## Files Affected
- `lib/Controller/GitRepoController.php`
- `appinfo/routes.php`

## Current State
The smart-HTTP endpoint serves git objects. Annexed content is not reachable by clients; a clone sees pointer files it cannot fill.

## Plan
Serve annex objects at their standard in-repo HTTP paths under the same clone URL, so a stock git-annex client retrieves content from the origin remote with no special remote configured and no client-side setup — the same app password already cached from `git clone` authenticates the download.

**Endpoint.** `GET /repos/{repo}/annex/objects/{path}` streams the object for a key from the repository store, using the hashed directory layouts git-annex probes for bare HTTP remotes. Responses stream with range support; nothing buffers whole objects in PHP memory.

**Content resolution.** The controller resolves a requested key through the repository's annex (`git annex contentlocation` or direct layout lookup) and serves the bytes through Nextcloud's storage/streaming facilities. Access control follows the repository's read permissions, identical to the git endpoint.

**Semantics.** The endpoint is read-only: the Nextcloud instance is the trusted, always-present central store. Clients get and drop freely against their numcopies; content enters the server through pushes and Nextcloud-side writes (issue 24), or through the documented `webdav` special remote fallback for direct annex uploads from clones.

### Acceptance
In the dev container: `git clone http://…/repos/demo && cd demo && git annex get .` succeeds with no configuration beyond the clone itself, using the git-annex version shipped in the container. This exact invocation is the first thing to verify, before building anything else in this issue.

# Considerations

## Read-only over inventing an upload channel
Same-channel uploads require a real protocol (git-LFS endpoint or p2phttp, both deferred in issue 22). The dominant data-science workflow is asymmetric anyway — big data flows in through the instance or a few producers and out to many consumers — and the webdav fallback covers producer clones without any new code.
