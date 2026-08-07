#!/bin/bash
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Real-repo verification (issue 29): import the pinned upstream git release
# tree, push it into a Nextcloud repo, clone it back, stack an artificial
# commit on top, and verify round-trips. Runs INSIDE the dev container:
#   podman exec nextcloud-repos-dev bash /var/www/html/custom_apps/repos/test/git/test.sh
#
# BACKEND=native (default) exercises the native PHP backend; BACKEND=binary
# runs the same flow against the git-binary backend.

set -euo pipefail

GIT_TAG="${GIT_TAG:-v2.47.3}"
GIT_UPSTREAM="${GIT_UPSTREAM:-https://github.com/git/git.git}"
BACKEND="${BACKEND:-native}"
NC_HOST="127.0.0.1"
NC_URL="http://$NC_HOST"
USER=admin
PASS=admin
REPO="gitmirror-$BACKEND"
OCC="runuser -u www-data -- php /var/www/html/occ"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; exit 1; }

git config --global user.email tester@example.org 2>/dev/null || true
git config --global user.name "Git Mirror Tester" 2>/dev/null || true
git config --global credential.helper store 2>/dev/null || true
printf "protocol=http\nhost=%s\nusername=%s\npassword=%s\n" "$NC_HOST" "$USER" "$PASS" | git credential approve

# --- source tree: pinned upstream release ------------------------------------
SRC=/tmp/git-upstream-$GIT_TAG
if [ ! -d "$SRC" ]; then
	git clone --depth 1 --branch "$GIT_TAG" "$GIT_UPSTREAM" "$SRC" 2>&1 | tail -1
fi
FILE_COUNT=$(cd "$SRC" && git ls-files | wc -l)
[ "$FILE_COUNT" -gt 3000 ] || fail "unexpectedly small upstream tree ($FILE_COUNT files)"
pass "pinned upstream $GIT_TAG fetched ($FILE_COUNT files)"

# --- repo folder ---------------------------------------------------------------
cd /
EXISTING=$($OCC repos:list --output=json 2>/dev/null | php -r '$d=json_decode(stream_get_contents(STDIN),true)?:[]; foreach($d as $r) if(($r["mount_point"]??"")==="'"$REPO"'") echo $r["id"];' || true)
if [ -n "$EXISTING" ]; then
	$OCC repos:delete "$EXISTING" -f >/dev/null
fi
NATIVE_FLAG=""
[ "$BACKEND" = "native" ] && NATIVE_FLAG="--native"
ID=$($OCC repos:create "$REPO" $NATIVE_FLAG)
$OCC repos:group "$ID" admin write >/dev/null
pass "repo folder created (id $ID, backend $BACKEND)"

# --- import the release tree as one commit and push ---------------------------
WORK=/tmp/gitmirror-work-$BACKEND
rm -rf "$WORK"
git init -q -b main "$WORK"
(cd "$SRC" && git archive "$GIT_TAG") | tar -x -C "$WORK"
cd "$WORK"
git remote add nextcloud "$NC_URL/apps/repos/$REPO.git"
git fetch -q nextcloud
git reset -q --soft nextcloud/main
git add -A
git commit -qm "Import git $GIT_TAG release tree"
time git push -q nextcloud main
pass "release tree pushed ($FILE_COUNT files in one commit)"

# --- clone it back and verify --------------------------------------------------
CLONE=/tmp/gitmirror-clone-$BACKEND
rm -rf "$CLONE"
time git clone -q "$NC_URL/apps/repos/$REPO.git" "$CLONE"
cd "$CLONE"
git fsck --strict 2>&1 | grep -vE '^Checking' || true
CLONED_COUNT=$(git ls-files | wc -l)
[ "$CLONED_COUNT" = "$FILE_COUNT" ] || fail "file count mismatch: pushed $FILE_COUNT, cloned $CLONED_COUNT"
SRC_MD5=$(cd "$SRC" && md5sum README.md | cut -d' ' -f1)
CLONE_MD5=$(md5sum README.md | cut -d' ' -f1)
[ "$SRC_MD5" = "$CLONE_MD5" ] || fail "README.md content mismatch after round-trip"
pass "fresh clone matches: $CLONED_COUNT files, fsck clean, content verified"

# --- artificial commit on top ---------------------------------------------------
echo "This release now lives in a Nextcloud. ($(git rev-parse HEAD))" > NEXTCLOUD.md
git add NEXTCLOUD.md
git commit -qm "Artificial commit on top of $GIT_TAG"
git push -q origin main
pass "artificial commit pushed"

cd "$WORK"
git pull -q nextcloud main
[ -f NEXTCLOUD.md ] || fail "artificial commit did not round-trip to the first repo"
git log --format='%s' -1 | grep -q "Artificial commit" || fail "unexpected tip commit"
pass "artificial commit pulled back into the pushing repo"

# --- the Files app sees the tree ------------------------------------------------
COUNT=$(curl -s -u "$USER:$PASS" -X PROPFIND -H 'Depth: 1' "$NC_URL/remote.php/dav/files/$USER/$REPO/" | grep -o '<d:href>' | wc -l)
[ "$COUNT" -gt 10 ] || fail "Files app does not show the imported tree (only $COUNT entries)"
curl -s -u "$USER:$PASS" "$NC_URL/remote.php/dav/files/$USER/$REPO/NEXTCLOUD.md" | grep -q "lives in a Nextcloud" \
	|| fail "artificial commit not materialized into the Files app"
pass "Files app shows the tree and the artificial commit"

echo
echo "ALL GIT-MIRROR TESTS PASSED ($BACKEND backend, $GIT_TAG)"
