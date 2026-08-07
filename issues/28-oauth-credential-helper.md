---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: OAuth2 Login for Git Clients — Alongside App Passwords

## Category
REST API Layer

## Current State
Clients authenticate with a Nextcloud app password via git's credential helper: typed once at clone, stored, reused by git, git-annex and datalad. This works and stays supported. The remaining friction is the first step — creating an app password in the web UI and typing it into a terminal.

## Plan
Support browser-based OAuth2 login as a second method: `git clone` triggers the system browser once, the user clicks "allow" in their Nextcloud, and tokens land in git's credential storage with automatic refresh. No secrets typed, revocable in Nextcloud like any session.

**Client side** needs no custom software: [git-credential-oauth](https://github.com/hickford/git-credential-oauth) is packaged in Debian trixie (0.15.0) and supports custom hosts via git config (`credential.<url>.oauthClientId`, `oauthAuthURL`, `oauthTokenURL`, `oauthRedirectURL`). The app documents a copy-paste config block per instance; a later nicety could serve it as a ready-made snippet from the instance itself.

**Server side**, in order:

1. Register/document an OAuth2 client in Nextcloud (Settings → Security → OAuth 2.0) for git access, with the localhost redirect URI git-credential-oauth uses.
2. Verify what token form the endpoints accept. Confirmed already: Bearer tokens authenticate against the app's git endpoints (Nextcloud core processes the header before the controller; app passwords as Bearer return 200). Open: whether an OAuth2 *access token* is accepted as a basic-auth password (git sends basic) or as Bearer — needs a registered client to test.
3. If OAuth2 access tokens are not accepted on the basic path, extend the controller's `authenticate()` to validate them (via the oauth2 app's token storage) and map to the user — same permission checks as today.
4. Document both methods side by side in the website's getting-started page and record the OAuth flow as a demo scene.

### Acceptance
On a fresh machine with `git-credential-oauth` installed and the documented config applied: `git clone` opens the browser, one click authorizes, the clone completes, and `git annex get` works — no password ever typed. App-password auth continues to work unchanged.

# Considerations

## Why not only OAuth
App passwords work on headless machines (HPC nodes, CI) where no browser exists, and they are what datalad's credential tooling handles today. Both methods share the same server-side permission model, so supporting both costs little.

## Nextcloud Login Flow v2
The desktop client's login flow (`/login/v2`) could mint app passwords from a CLI with one browser confirmation — a ~50-line `git-credential-nextcloud` helper. It overlaps with git-credential-oauth; deferred unless the OAuth token path turns out not to work for basic auth.
