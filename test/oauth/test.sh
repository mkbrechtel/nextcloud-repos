#!/bin/bash
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# OAuth2 git login end-to-end (issue 28): register the client, mint an
# authorization code the way the consent screen does, exchange it for tokens
# at the real token endpoint, authenticate git with the access token, and
# verify refresh rotation. Runs INSIDE the dev container:
#   podman exec nextcloud-repos-dev bash /var/www/html/custom_apps/repos/test/oauth/test.sh

set -euo pipefail

NC_HOST="127.0.0.1"
NC_URL="http://$NC_HOST"
USER=admin
REPO=e2e
OCC="runuser -u www-data -- php /var/www/html/occ"
HERE="$(cd "$(dirname "$0")" && pwd)"

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; exit 1; }

# --- client registration -------------------------------------------------------
SETUP=$($OCC repos:oauth:setup --recreate)
CLIENT_ID=$(echo "$SETUP" | sed -n 's/^Client ID: *//p')
CLIENT_SECRET=$(echo "$SETUP" | sed -n 's/^Client secret: *//p')
[ -n "$CLIENT_ID" ] && [ -n "$CLIENT_SECRET" ] || fail "oauth setup did not print client credentials"
echo "$SETUP" | grep -q 'credential.*oauthAuthURL' || fail "setup did not print the client git config"
pass "oauth client registered and client config printed"

# the wildcard redirect must be enabled for loopback listeners
runuser -u www-data -- php /var/www/html/occ config:system:get oauth2.enable_oc_clients | grep -q true \
	|| fail "localhost wildcard redirect not enabled"
pass "loopback redirect URIs enabled"

# --- loopback redirect shim -----------------------------------------------------
# CLI helpers listen on 127.0.0.1:<random port>; the shim normalizes that to the
# host Nextcloud's wildcard client accepts, and rejects non-loopback URIs
SHIM="$NC_URL/apps/repos/oauth/authorize?response_type=code&client_id=$CLIENT_ID&state=s1"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' "$SHIM&redirect_uri=http%3A%2F%2F127.0.0.1%3A42467")
echo "$LOC" | grep -q 'redirect_uri=http%3A%2F%2Flocalhost%3A42467' \
	|| fail "loopback redirect not normalized: $LOC"
echo "$LOC" | grep -q '/apps/oauth2/authorize' || fail "shim did not hand over to the consent screen: $LOC"
CODE_EVIL=$(curl -s -o /dev/null -w '%{http_code}' "$SHIM&redirect_uri=https%3A%2F%2Fevil.example%2Fcb")
[ "$CODE_EVIL" = "400" ] || fail "non-loopback redirect_uri was not rejected (got $CODE_EVIL)"
pass "loopback redirect normalized; external redirect_uri rejected"

# --- authorization code (as the consent screen mints it) ------------------------
CODE=$(runuser -u www-data -- php "$HERE/mint-code.php" "$CLIENT_ID" "$USER")
[ -n "$CODE" ] || fail "could not mint an authorization code"
pass "authorization code issued for $USER"

# --- code -> tokens at the real token endpoint ----------------------------------
TOKENS=$(curl -s -X POST "$NC_URL/index.php/apps/oauth2/api/v1/token" \
	--data-urlencode "grant_type=authorization_code" \
	--data-urlencode "code=$CODE" \
	--data-urlencode "client_id=$CLIENT_ID" \
	--data-urlencode "client_secret=$CLIENT_SECRET")
ACCESS=$(echo "$TOKENS" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["access_token"]??"";')
REFRESH=$(echo "$TOKENS" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["refresh_token"]??"";')
TOKEN_USER=$(echo "$TOKENS" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["user_id"]??"";')
[ -n "$ACCESS" ] && [ -n "$REFRESH" ] || fail "token exchange failed: $TOKENS"
[ "$TOKEN_USER" = "$USER" ] || fail "token belongs to '$TOKEN_USER', expected '$USER'"
pass "access + refresh tokens issued for $TOKEN_USER"

# --- git authenticates with the oauth access token ------------------------------
rm -rf /tmp/oauth-clone
git -c credential.helper= clone -q "http://$USER:$ACCESS@$NC_HOST/apps/repos/$REPO.git" /tmp/oauth-clone \
	|| fail "git clone with oauth access token failed"
[ -f /tmp/oauth-clone/README.md ] || fail "clone content missing"
pass "git clone authenticated by the oauth access token"

# bearer form, which git-credential-oauth uses with -bearer
curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $ACCESS" \
	"$NC_URL/apps/repos/$REPO.git/info/refs?service=git-upload-pack" | grep -q 200 \
	|| fail "bearer authentication rejected"
pass "bearer authentication accepted"

# --- refresh rotation ------------------------------------------------------------
TOKENS2=$(curl -s -X POST "$NC_URL/index.php/apps/oauth2/api/v1/token" \
	--data-urlencode "grant_type=refresh_token" \
	--data-urlencode "refresh_token=$REFRESH" \
	--data-urlencode "client_id=$CLIENT_ID" \
	--data-urlencode "client_secret=$CLIENT_SECRET")
ACCESS2=$(echo "$TOKENS2" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["access_token"]??"";')
[ -n "$ACCESS2" ] || fail "refresh failed: $TOKENS2"
rm -rf /tmp/oauth-clone2
git -c credential.helper= clone -q "http://$USER:$ACCESS2@$NC_HOST/apps/repos/$REPO.git" /tmp/oauth-clone2 \
	|| fail "git clone with the refreshed token failed"
pass "refresh rotates and the new access token authenticates git"

# --- app passwords still work ----------------------------------------------------
rm -rf /tmp/apppw-clone
git -c credential.helper= clone -q "http://$USER:admin@$NC_HOST/apps/repos/$REPO.git" /tmp/apppw-clone \
	|| fail "password authentication broke"
pass "password/app-password authentication still works"

echo
echo "ALL OAUTH TESTS PASSED"
