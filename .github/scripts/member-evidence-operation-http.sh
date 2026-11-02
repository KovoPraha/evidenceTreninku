#!/usr/bin/env bash
set -euo pipefail
source .github/scripts/production-ssh-retry.sh
production_ssh_init
test "$APP_HOST" = kis.kovopraha.cz
[[ "$REMOTE_DIR" =~ ^[A-Za-z0-9._/-]+$ ]] && [[ "$REMOTE_DIR" != *..* ]]
[[ "$GITHUB_RUN_ID" =~ ^[1-9][0-9]{0,19}$ ]]
umask 077
LOCAL="/tmp/kis-member-http-$GITHUB_RUN_ID.php"
REMOTE="$DEPLOY_BASE/member-http-$GITHUB_RUN_ID.php"
cleanup() {
  rm -f -- "$LOCAL"
  production_retry "${SSH[@]}" "rm -f -- '$REMOTE'"
}
trap cleanup EXIT
# The hosting can time out connections from datacenter IPs. Use its established server-side deploy probe.
for SPEC in index.php:200 kis_rosters_admin.php:302; do
  ROUTE="${SPEC%:*}"
  EXPECTED="${SPEC#*:}"
  cat > "$LOCAL" <<EOF
<?php
putenv('DEPLOY_PROBE=1');
putenv('SMOKE_URL=https://kis.kovopraha.cz/$ROUTE');
require '$REMOTE_DIR/bin/deploy-smoke.php';
EOF
  production_retry "${SCP[@]}" "$LOCAL" "$SSH_USER@$SSH_HOST:$REMOTE"
  RESULT=$(production_retry_capture "${SSH[@]}" "php '$REMOTE'")
  jq -e --argjson expected "$EXPECTED" '.ok == true and .http == $expected' <<< "$RESULT" >/dev/null
  echo "HTTP $ROUTE: $EXPECTED PASS (server-side deploy probe)"
done
