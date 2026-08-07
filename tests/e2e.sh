#!/bin/bash
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# End-to-end acceptance test (issue 27). Runs INSIDE the dev container:
#   podman exec nextcloud-repos-dev bash /var/www/html/custom_apps/repos/tests/e2e.sh
#
# Exercises the founding user story: create a repo folder, clone it over
# Nextcloud HTTP, push, edit via WebDAV (attributed commit), upload a large
# file (annexed), and git annex get it from the clone — one credential.

set -euo pipefail

# pin to IPv4: dual-stack localhost lets the git-annex http client alternate
# between ::1 and 127.0.0.1, which interacts badly with per-IP throttling state
NC_HOST="127.0.0.1"
NC_URL="http://$NC_HOST"
USER=admin
PASS=admin
REPO=e2e
CLONE=/tmp/e2e-clone
OCC="runuser -u www-data -- php /var/www/html/occ"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; exit 1; }

# --- client setup: identity + one stored credential (app password UX) -------
git config --global user.email tester@example.org
git config --global user.name "E2E Tester"
git config --global credential.helper store
# test-only: git-annex refuses localhost by default (SSRF protection)
git config --global annex.security.allowed-ip-addresses all
printf "protocol=http\nhost=$NC_HOST\nusername=%s\npassword=%s\n" "$USER" "$PASS" | git credential approve

# --- create repo folder ------------------------------------------------------
cd /
EXISTING=$($OCC repos:list --output=json 2>/dev/null | php -r '$d=json_decode(stream_get_contents(STDIN),true)?:[]; foreach($d as $r) if(($r["mount_point"]??"")==="'"$REPO"'") echo $r["id"];' || true)
if [ -n "$EXISTING" ]; then
	$OCC repos:delete "$EXISTING" -f >/dev/null
fi
ID=$($OCC repos:create "$REPO")
$OCC repos:group "$ID" admin write >/dev/null
# test-only: lower the annex threshold so a 300KB file gets annexed
$OCC config:app:set repos annex_threshold --value=100000 >/dev/null
pass "repo folder created (id $ID)"

# --- unauthenticated access is refused --------------------------------------
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$NC_URL/apps/repos/$REPO.git/info/refs?service=git-upload-pack")
[ "$CODE" = "401" ] || fail "expected 401 for anonymous, got $CODE"
pass "anonymous access gets 401 challenge"

# --- clone over Nextcloud HTTP -----------------------------------------------
rm -rf "$CLONE"
git clone -q "$NC_URL/apps/repos/$REPO.git" "$CLONE"
cd "$CLONE"
git log --oneline | grep -q "Initialize repository" || fail "seed commit missing in clone"
pass "clone over Nextcloud HTTP"

# --- push appears in the Files app (WebDAV) ----------------------------------
echo "# E2E project" > README.md
git add README.md
git commit -qm "Add README"
git push -q origin main
curl -s -u "$USER:$PASS" -X PROPFIND -H 'Depth: 1' "$NC_URL/remote.php/dav/files/$USER/$REPO/" \
	| grep -q "README.md" || fail "pushed file not visible over WebDAV"
pass "pushed file visible in Files app"

# --- WebDAV edit becomes an attributed commit --------------------------------
curl -s -f -u "$USER:$PASS" -X PUT --data 'edited in the browser' \
	"$NC_URL/remote.php/dav/files/$USER/$REPO/notes.txt" > /dev/null
git pull -q origin main
[ "$(cat notes.txt)" = "edited in the browser" ] || fail "WebDAV edit content mismatch"
git log -1 --format='%an' -- notes.txt | grep -q "$USER" || fail "commit not attributed to $USER"
pass "WebDAV edit became a commit by $USER"

# --- large upload gets annexed ------------------------------------------------
head -c 300000 /dev/urandom > /tmp/e2e-big.bin
curl -s -f -u "$USER:$PASS" -X PUT --data-binary @/tmp/e2e-big.bin \
	"$NC_URL/remote.php/dav/files/$USER/$REPO/big.bin" > /dev/null
git fetch -q origin && git -C . show origin/main:big.bin | head -1 | grep -q '^/annex/objects/' \
	|| fail "large upload was not annexed (no pointer blob in git)"
pass "large upload annexed (pointer blob in git history)"

# --- git annex get over the clone URL ----------------------------------------
git pull -q origin main
git fetch -q origin
git annex init e2e-client >/dev/null 2>&1
SIZE_BEFORE=$(stat -c%s big.bin)
[ "$SIZE_BEFORE" -lt 1000 ] || fail "expected pointer file before annex get"
git annex get big.bin >/dev/null 2>&1 || fail "git annex get failed"
SERVER_MD5=$(md5sum /tmp/e2e-big.bin | cut -d' ' -f1)
CLIENT_MD5=$(md5sum big.bin | cut -d' ' -f1)
[ "$SERVER_MD5" = "$CLIENT_MD5" ] || fail "annexed content checksum mismatch"
pass "git annex get over the clone URL, checksum verified"

# --- passive annex-state relay ------------------------------------------------
# clients sync annex state through the server via synced/git-annex; the
# server's own git-annex branch stays server-owned
git push -q origin git-annex:synced/git-annex || fail "synced/git-annex push not accepted"
REJECT_OUT=$(git push origin git-annex 2>&1 || true)
echo "$REJECT_OUT" | grep -q "maintained by the server" \
	|| fail "direct git-annex branch push was not rejected: $REJECT_OUT"
pass "annex state relays passively; server branch stays protected"

# --- datalad end to end -------------------------------------------------------
if command -v datalad >/dev/null; then
	rm -rf /tmp/e2e-datalad
	# datalad wires its own askpass into git-annex, which can feed empty
	# credentials and make git erase the shared store on the resulting 401;
	# embed credentials in the URL for this step (test environment only)
	datalad clone "http://$USER:$PASS@$NC_HOST/apps/repos/$REPO.git" /tmp/e2e-datalad >/dev/null 2>&1 \
		|| fail "datalad clone failed"
	# Credentials embedded in the URL break git-annex's auth flow (and
	# datalad's askpass breaks the probe during clone, leaving annex-ignore
	# set). Switch to the clean URL + stored credential, which is the
	# documented client setup anyway; datalad-native credentials are
	# follow-up work (issue 27).
	git -C /tmp/e2e-datalad remote set-url origin "$NC_URL/apps/repos/$REPO.git"
	git -C /tmp/e2e-datalad config --unset-all remote.origin.annex-ignore >/dev/null 2>&1 || true
	printf "protocol=http\nhost=$NC_HOST\nusername=%s\npassword=%s\n" "$USER" "$PASS" | git credential approve
	(cd /tmp/e2e-datalad && git annex get big.bin >/dev/null 2>&1) || fail "annex get in datalad dataset failed"
	DL_MD5=$(md5sum /tmp/e2e-datalad/big.bin | cut -d' ' -f1)
	[ "$SERVER_MD5" = "$DL_MD5" ] || fail "datalad content checksum mismatch"
	pass "datalad clone + annex get in dataset, checksum verified"
	# restore the credential in case a 401 during the datalad step erased it
	printf "protocol=http\nhost=$NC_HOST\nusername=%s\npassword=%s\n" "$USER" "$PASS" | git credential approve
else
	echo "SKIP: datalad not installed"
fi

# --- history API --------------------------------------------------------------
HIST=$(curl -s -u "$USER:$PASS" "$NC_URL/apps/repos/api/history/$ID?path=notes.txt")
echo "$HIST" | grep -q "notes.txt via Nextcloud" || fail "history API missing commit: $HIST"
pass "history API returns file history"

echo
echo "ALL E2E TESTS PASSED"
