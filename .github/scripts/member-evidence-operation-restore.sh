#!/usr/bin/env bash
set -euo pipefail
source .github/scripts/production-ssh-retry.sh
production_ssh_init
LOCAL="/tmp/kis-member-operation-$GITHUB_RUN_ID"
PRIVATE="$DEPLOY_BASE/member-cutover-$GITHUB_RUN_ID"
production_retry "${SCP[@]}" "$SSH_USER@$SSH_HOST:$PRIVATE/backup-result.json" "$LOCAL/backup-result.json"
BACKUP=$(jq -r '.backup' "$LOCAL/backup-result.json")
[[ "$BACKUP" =~ ^evidence_[0-9A-Za-z_-]+\.sql\.gz$ ]]
STEM="${BACKUP%.sql.gz}"
for FILE in "$BACKUP" "$STEM.sha256" "$STEM.manifest.json"; do
  production_retry "${SCP[@]}" "$SSH_USER@$SSH_HOST:$PRIVATE/backup/$FILE" "$LOCAL/$FILE"
done
cd "$LOCAL"
sha256sum -c "$STEM.sha256"
test "$(jq -r '.sha256' "$STEM.manifest.json")" = "$(sha256sum "$BACKUP" | awk '{print $1}')"
CONTAINER="kis-member-operation-$GITHUB_RUN_ID"
PASSWORD=$(openssl rand -hex 24)
docker run -d --name "$CONTAINER" -e MARIADB_ROOT_PASSWORD="$PASSWORD" mariadb:11.4 >/dev/null
READY=0
for i in $(seq 1 60); do
  if docker exec "$CONTAINER" mariadb -uroot -p"$PASSWORD" -Nse 'SELECT 1' >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 2
done
test "$READY" = 1
docker exec "$CONTAINER" mariadb -uroot -p"$PASSWORD" -e 'CREATE DATABASE kis_member_operation_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
gzip -dc "$BACKUP" | docker exec -i "$CONTAINER" mariadb -uroot -p"$PASSWORD" kis_member_operation_restore
CHECKED=0
while IFS=$'\t' read -r TABLE EXPECTED; do
  [[ "$TABLE" =~ ^[a-z0-9_]+$ ]]
  ACTUAL=$(docker exec "$CONTAINER" mariadb -uroot -p"$PASSWORD" -Nse "SELECT COUNT(*) FROM \`$TABLE\`" kis_member_operation_restore)
  test "$ACTUAL" = "$EXPECTED"
  CHECKED=$((CHECKED + 1))
done < <(jq -r '.tables | to_entries[] | [.key,.value] | @tsv' "$STEM.manifest.json")
EXPECTED_TRIGGERS=$(jq -r '.triggers | sort | join("\n")' "$STEM.manifest.json")
ACTUAL_TRIGGERS=$(docker exec "$CONTAINER" mariadb -uroot -p"$PASSWORD" -Nse "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='kis_member_operation_restore' ORDER BY TRIGGER_NAME")
test "$ACTUAL_TRIGGERS" = "$EXPECTED_TRIGGERS"
docker rm -f "$CONTAINER" >/dev/null
echo "Čerstvá záloha před převodem obnovena v izolaci: $CHECKED tabulek, $(jq '.triggers | length' "$STEM.manifest.json") triggerů."
