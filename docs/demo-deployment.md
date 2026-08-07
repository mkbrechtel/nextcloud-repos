<!--
SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Demo deployment on devio

Two public endpoints run on the devio host, following its container-per-site pattern (rootless podman containers on `127.0.0.1` ports, fronted by the central Caddy proxy container via `/etc/caddy/conf.d/<domain>`).

## Website — nextcloud-repos-website-demo.devio.mkbrechtel.dev

Static Starlight build served by a `caddy:2-alpine` container.

- Container: `nextcloud-repos-website`, port `127.0.0.1:8291`, serving `/srv/nextcloud-repos-website/dist` (read-only mount).
- Rebuild and redeploy:

```bash
podman run --rm -v ./website:/site:z -w /site docker.io/library/node:22-alpine \
  sh -c 'npm install && npm run build'
rsync -a --delete website/dist/ /srv/nextcloud-repos-website/dist/
```

The demo video is copied to `website/public/demo.webm` before building (produced by `make demo-video`).

## Demo instance — nextcloud-repos-demo.devio.mkbrechtel.dev

- Container: `nextcloud-repos-demo`, port `127.0.0.1:8177`, image `nextcloud-repos:dev` (the dev image; rebuild with `make dev-start` or `podman build --target dev-env`).
- Admin password: `~/.local/share/nextcloud-repos-demo/admin-password.txt` on devio (mode 600).
- Instance config: trusted domain, `overwriteprotocol https`, `overwrite.cli.url` set via occ; seeded repository `demo` (README, notes.txt, annexed 5 MB `data.bin`, annex threshold 1 MB).
- The image bakes the app at build time; redeploying code changes means rebuilding the image and recreating the container (SQLite data lives in the container — treat the instance as disposable).

## Caddy

Site files in `/etc/caddy/conf.d/`; apply with `caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile` (the proxy runs as a user podman container, not the system service — `systemctl reload caddy` does not work here).

## Recording the demo video

```bash
make demo-video DEMO_TARGET=https://nextcloud-repos-demo.devio.mkbrechtel.dev DEMO_PASS=…
```

Output: `test/demo/out/demo.webm`. The recorder container uses `--network=host` so it can reach the public hostname (rootless container networks cannot hairpin to the host's public IP).

# Considerations

## Rootless containers and reboots

The site and demo containers use `--restart=always` under the rootless user, matching the other sites on the host. Restart-on-reboot depends on the host's user lingering/podman-restart setup; if the host reboots and the demos are down, `podman start nextcloud-repos-website nextcloud-repos-demo` brings them back.
