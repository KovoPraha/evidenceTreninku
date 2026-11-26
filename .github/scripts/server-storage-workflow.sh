#!/usr/bin/env bash
set -euo pipefail

test "$GITHUB_REF" = refs/heads/main
test "$APP_HOST" = kis.kovopraha.cz
test "$REMOTE_DIR" = kis.kovopraha.cz
case "$STORAGE_PHASE" in preview|apply) ;; *) exit 2;; esac

TASK_TMP="$RUNNER_TEMP/storage-plan-$GITHUB_RUN_ID"
install -d -m 700 "$TASK_TMP"
source .github/scripts/production-ssh-retry.sh
production_ssh_init

# Every top-level entry is measured. Unknown names or size drift abort planning.
production_retry_capture "${SSH[@]}" "set -eu; test -d '$DEPLOY_BASE/releases'; find '$DEPLOY_BASE/releases' -mindepth 1 -maxdepth 1 -exec du -sk -- {} +" > "$TASK_TMP/releases.tsv"
production_retry_capture "${SSH[@]}" "set -eu; test -d '$BACKUP_DIR'; find '$BACKUP_DIR' -mindepth 1 -maxdepth 1 -exec du -sk -- {} +" > "$TASK_TMP/backups.tsv"
production_retry_capture "${SSH[@]}" "set -eu; test -d '$BACKUP_DIR/files'; find '$BACKUP_DIR/files' -mindepth 1 -maxdepth 1 -exec du -sk -- {} +" > "$TASK_TMP/files.tsv"
production_retry_capture "${SSH[@]}" "set -eu; test -s '$REMOTE_DIR/var/deployment.json'; cat '$REMOTE_DIR/var/deployment.json'" > "$TASK_TMP/deployment.json"

python3 .github/scripts/server-storage-plan.py \
  --manifest docs/verification/server-storage-20261125/release-manifest-20261126.json \
  --releases "$TASK_TMP/releases.tsv" \
  --backups "$TASK_TMP/backups.tsv" \
  --files "$TASK_TMP/files.tsv" \
  --deployment "$TASK_TMP/deployment.json" \
  --output "$TASK_TMP/plan.json" \
  --shell-output "$TASK_TMP/cleanup.sh"

if [ "$STORAGE_PHASE" = preview ]; then
  echo 'Read-only cleanup plan complete. No files removed.'
  exit 0
fi

test "$STORAGE_CONFIRM" = 'SMAZAT-KIS-KOPIE-A-ZALOHY'
[[ "$STORAGE_DIGEST" =~ ^[0-9a-f]{64}$ ]]
test "$STORAGE_DIGEST" = "$(jq -r .digest "$TASK_TMP/plan.json")"

# This tracked evidence must be merged to main after checking an actual
# hosting snapshot and isolated restoration of its files and database.
PROOF=docs/verification/server-storage-20261125/hosting-restore-proof.json
test -s "$PROOF"
jq -e '.www_snapshot == true and .db_snapshot == true and .private_files == true and .isolated_restore == true and (.verified_at | type == "string")' "$PROOF" >/dev/null

REMOTE_SCRIPT="$DEPLOY_BASE/storage-cleanup-$GITHUB_RUN_ID.sh"
production_retry "${SCP[@]}" "$TASK_TMP/cleanup.sh" "$SSH_USER@$SSH_HOST:$REMOTE_SCRIPT"
# Never retry deletion after an interrupted SSH connection; inspect first.
"${SSH[@]}" "sh '$REMOTE_SCRIPT'"
production_retry "${SSH[@]}" "rm -f -- '$REMOTE_SCRIPT'"
production_retry_capture "${SSH[@]}" "set -eu; du -sk -- '$DEPLOY_BASE/releases' '$BACKUP_DIR'; find '$DEPLOY_BASE/releases' -mindepth 1 -maxdepth 1 -type d | wc -l" > "$TASK_TMP/after.txt"
cat "$TASK_TMP/after.txt"

HTTP=$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 15 --max-time 45 --retry 2 "https://$APP_HOST/index.php")
test "$HTTP" = 200 || test "$HTTP" = 302
echo "Production HTTP smoke PASS: $HTTP"
