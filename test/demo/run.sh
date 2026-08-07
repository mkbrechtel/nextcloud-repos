#!/bin/bash
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Orchestrates the recording: virtual screen, web terminal, screen grabber,
# then the Selenium screenplay. Output lands in /out/demo.webm.

set -euo pipefail

: "${TARGET_URL:?set TARGET_URL to the Nextcloud instance under test}"
: "${DEMO_USER:=admin}"
: "${DEMO_PASS:?set DEMO_PASS}"
: "${REPO:=demo}"

SCREEN=:99
SIZE=1920x1080

# --- git client setup for the terminal scenes --------------------------------
git config --global user.name "Demo User"
git config --global user.email demo@example.org
git config --global credential.helper store
git config --global color.ui always
HOST_PART=$(echo "$TARGET_URL" | sed -E 's|^https?://||; s|/.*||')
PROTO=$(echo "$TARGET_URL" | sed -E 's|^(https?)://.*|\1|')
printf 'protocol=%s\nhost=%s\nusername=%s\npassword=%s\n' "$PROTO" "$HOST_PART" "$DEMO_USER" "$DEMO_PASS" | git credential approve
# test targets may be plain http on loopback
git config --global annex.security.allowed-ip-addresses all

# --- virtual screen -----------------------------------------------------------
Xvfb "$SCREEN" -screen 0 "${SIZE}x24" &
XVFB_PID=$!
export DISPLAY="$SCREEN"
sleep 1

# --- web terminal (xterm.js served by the aiohttp PTY bridge) -----------------
mkdir -p /work
python3 /demo/term_server.py &
TTYD_PID=$!
sleep 1

# --- screen grabber -----------------------------------------------------------
mkdir -p /out
ffmpeg -loglevel error -f x11grab -framerate 25 -video_size "$SIZE" -i "$SCREEN" \
	-c:v libvpx -b:v 2M -auto-alt-ref 0 -y /out/demo.webm &
FFMPEG_PID=$!
sleep 1

# --- the screenplay -----------------------------------------------------------
set +e
python3 /demo/demo.py
RESULT=$?
set -e

# stop the grabber cleanly so the container is finalized
kill -INT "$FFMPEG_PID"
wait "$FFMPEG_PID" || true
kill "$TTYD_PID" "$XVFB_PID" 2>/dev/null || true

ls -la /out/
exit "$RESULT"
