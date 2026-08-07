---
title: Development
description: Container-based dev environment, tests, and the demo recorder.
---

Everything builds and runs in containers — no host dependencies beyond podman.

## Dev server

```bash
make dev            # build + start Nextcloud 32 with the app live-mounted
                    # → http://localhost:8067 (admin/admin)
make dev-logs       # follow logs
make occ ARGS="repos:list"
```

The app source is volume-mounted into the container, so PHP changes are live.

## Tests

```bash
make test-e2e       # end-to-end acceptance test against the running dev server
```

`tests/e2e.sh` exercises the founding user story inside the container: create a
repo folder, anonymous 401, clone over HTTP, push visible in Files, WebDAV edit
as attributed commit, large upload annexed, `git annex get` with checksum
verification, `datalad clone`, and the history API.

## Demo recorder

`test/demo/` contains a self-contained recorder: a Debian trixie container
(apt packages only) that drives Chromium via Selenium and a `ttyd` web terminal
(xterm.js), screen-records with Xvfb + ffmpeg, and produces the walkthrough
video shown on the [demo page](/demo/) — against a real, running instance.

```bash
make demo-video     # records test/demo/out/demo.webm against the dev server
```

## Issue tracking

Issues are markdown files in `issues/`; done ones move to `issues/done/`.
Architecture decisions are recorded in the issue that made them.
