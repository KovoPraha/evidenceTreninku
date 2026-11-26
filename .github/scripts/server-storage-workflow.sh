#!/usr/bin/env bash
set -euo pipefail

test "$GITHUB_REF" = refs/heads/main
test "$APP_HOST" = kis.kovopraha.cz
test "$REMOTE_DIR" = kis.kovopraha.cz
case "$STORAGE_PHASE" in preview|apply|inspect_www|verify_www|inspect_db|restore_db|restore_www) ;; *) exit 2;; esac

TASK_TMP="$RUNNER_TEMP/storage-plan-$GITHUB_RUN_ID"
install -d -m 700 "$TASK_TMP"
source .github/scripts/production-ssh-retry.sh
production_ssh_init

if [ "$STORAGE_PHASE" = inspect_www ]; then
  production_retry_capture "${SSH[@]}" "
    set -eu
    test -d backups
    echo '=== mounted backup root ==='
    find backups -mindepth 1 -maxdepth 2 -type d | sort | head -80
    echo '=== KIS web and private directory matches ==='
    find backups -maxdepth 6 -type d \( -name kis.kovopraha.cz -o -name kis-private \) | sort | head -30
    echo '=== deployment marker matches ==='
    find backups -maxdepth 6 -type f -name deployment.json | sort | head -30
  "
  exit 0
fi

if [ "$STORAGE_PHASE" = verify_www ]; then
  production_retry_capture "${SSH[@]}" '
    set -eu
    test -d backups/kis.kovopraha.cz
    test -d backups/data/kis-private
    test -s backups/kis.kovopraha.cz/var/deployment.json
    for path in kis.kovopraha.cz data/kis-private kis.kovopraha.cz/uploads kis.kovopraha.cz/nahrane_obrazky; do
      test -d "backups/$path"
      test -d "$path"
      echo "=== $path ==="
      du -sk -- "$path" "backups/$path"
      printf "live_files="; find "$path" -type f | wc -l
      printf "snapshot_files="; find "backups/$path" -type f | wc -l
      printf "snapshot_over_1GiB="; find "backups/$path" -type f -size +1G | wc -l
    done
    echo "WWW snapshot structure and nonempty deployment marker: PASS"
  '
  exit 0
fi

if [ "$STORAGE_PHASE" = inspect_db ]; then
  production_retry_capture "${SSH[@]}" '
    set -eu
    test -d backups
    echo "=== mounted database snapshot directories ==="
    find backups -mindepth 1 -maxdepth 3 -type d | sort | head -80
    echo "=== mounted database snapshot files ==="
    find backups -mindepth 1 -maxdepth 4 -type f | sort | head -80
    echo "=== total snapshot size KiB and file count ==="
    du -sk -- backups
    find backups -type f | wc -l
  '
  exit 0
fi

if [ "$STORAGE_PHASE" = restore_db ]; then
  # The mounted hosting generation is read-only. Import its KIS dump into an
  # isolated runner container, never into production or the runner host DB.
  production_retry_capture "${SSH[@]}" 'test -s backups/kovoprahacz09.sql.gz'
  RESTORE_TMP="$RUNNER_TEMP/hosting-db-$GITHUB_RUN_ID"
  CONTAINER="hosting-restore-$GITHUB_RUN_ID"
  install -d -m 700 "$RESTORE_TMP"
  trap 'docker rm -f "$CONTAINER" >/dev/null 2>&1 || :; rm -f -- "$RESTORE_TMP/kovoprahacz09.sql.gz" "$RESTORE_TMP/import.out" "$RESTORE_TMP/import.err"' EXIT
  production_retry "${SCP[@]}" "$SSH_USER@$SSH_HOST:backups/kovoprahacz09.sql.gz" "$RESTORE_TMP/kovoprahacz09.sql.gz"
  gzip -t "$RESTORE_TMP/kovoprahacz09.sql.gz"
  RESTORE_PASSWORD="$(openssl rand -hex 24)"
  # The hosted dump contains a legacy stored generated column on a foreign
  # key with ON DELETE SET NULL, rejected by newer MariaDB releases. Restore
  # in the older isolated engine used by the project's compatibility CI.
  docker run -d --name "$CONTAINER" --network none -e MARIADB_ROOT_PASSWORD="$RESTORE_PASSWORD" mariadb:10.3 >/dev/null
  READY=0
  for i in $(seq 1 60); do
    if docker exec "$CONTAINER" mysql -uroot -p"$RESTORE_PASSWORD" -Nse 'SELECT 1' >/dev/null 2>&1; then READY=1; break; fi
    sleep 2
  done
  test "$READY" = 1
  # Hosting dump includes USE kovoprahacz09; create that schema only inside
  # the isolated container before importing it.
  docker exec "$CONTAINER" mysql -uroot -p"$RESTORE_PASSWORD" -e 'CREATE DATABASE kis_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE kovoprahacz09 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
  if ! gzip -dc "$RESTORE_TMP/kovoprahacz09.sql.gz" | docker exec -i "$CONTAINER" mysql -uroot -p"$RESTORE_PASSWORD" kis_restore >"$RESTORE_TMP/import.out" 2>"$RESTORE_TMP/import.err"; then
    grep -m 1 '^ERROR ' "$RESTORE_TMP/import.err" || echo 'Hosting snapshot import failed; details kept off logs.'
    exit 1
  fi
  TABLES=$(docker exec "$CONTAINER" mysql -uroot -p"$RESTORE_PASSWORD" -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ('kis_restore','kovoprahacz09') AND TABLE_TYPE='BASE TABLE'")
  TRIGGERS=$(docker exec "$CONTAINER" mysql -uroot -p"$RESTORE_PASSWORD" -Nse "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA IN ('kis_restore','kovoprahacz09')")
  test "$TABLES" -ge 390
  test "$TRIGGERS" -ge 2
  echo "Hosting KIS database snapshot isolated restore PASS: $TABLES tables, $TRIGGERS triggers"
  exit 0
fi

if [ "$STORAGE_PHASE" = restore_www ]; then
  # Restore all 57 private files to the ephemeral runner and check their
  # aggregate size against the immutable mounted snapshot.
  SOURCE_KIB=$(production_retry_capture "${SSH[@]}" 'test -d backups/data/kis-private; du -sk -- backups/data/kis-private' | awk '{print $1}')
  SOURCE_FILES=$(production_retry_capture "${SSH[@]}" 'find backups/data/kis-private -type f | wc -l' | tr -d '[:space:]')
  test "$SOURCE_KIB" -ge 100000
  test "$SOURCE_FILES" -ge 57
  RESTORE_TMP="$RUNNER_TEMP/hosting-www-$GITHUB_RUN_ID"
  install -d -m 700 "$RESTORE_TMP"
  trap 'rm -rf -- "$RESTORE_TMP/kis-private"' EXIT
  production_retry "${SCP[@]}" -r "$SSH_USER@$SSH_HOST:backups/data/kis-private" "$RESTORE_TMP/kis-private"
  RESTORED_KIB=$(du -sk -- "$RESTORE_TMP/kis-private" | awk '{print $1}')
  RESTORED_FILES=$(find "$RESTORE_TMP/kis-private" -type f | wc -l)
  test "$RESTORED_FILES" -eq "$SOURCE_FILES"
  # Filesystem allocation may differ between the host and the runner. Check
  # bytes for every file instead of comparing du block allocation.
  SOURCE_BYTES=$(production_retry_capture "${SSH[@]}" 'find backups/data/kis-private -type f -exec wc -c -- {} + | tail -n 1' | awk '{print $1}')
  RESTORED_BYTES=$(find "$RESTORE_TMP/kis-private" -type f -exec wc -c -- {} + | tail -n 1 | awk '{print $1}')
  test "$RESTORED_BYTES" = "$SOURCE_BYTES"
  echo "Hosting KIS private files isolated restore PASS: $RESTORED_FILES files, $RESTORED_BYTES bytes, $RESTORED_KIB runner KiB"
  exit 0
fi

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
