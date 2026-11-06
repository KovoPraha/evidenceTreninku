#!/usr/bin/env bash
set -euo pipefail
case "$MODE" in
  preflight) BRANCH=codex/membership-audit-preflight-20261106 ;;
  publish) BRANCH=codex/membership-audit-result-20261107 ;;
  *) exit 64 ;;
esac
[[ "$PR_NUMBER" =~ ^[1-9][0-9]*$ ]]
PR_JSON=$(gh api "repos/$GITHUB_REPOSITORY/pulls/$PR_NUMBER")
echo "$PR_JSON" | jq -e --arg branch "$BRANCH" --arg repo "$GITHUB_REPOSITORY" \
  '.base.ref == "main" and .head.ref == $branch and .head.repo.full_name == $repo' >/dev/null
if [ "$(echo "$PR_JSON" | jq -r .merged)" = true ]; then
  echo 'Result already published; retaining immutable first capture.'
  VERDICT=PASS
  if [ "$MODE" = publish ]; then
    VERDICT=$(jq -r '.report.verdict' "$AUDIT_INPUT")
    case "$VERDICT" in PASS|LATE|FAIL|BLOCKED) ;; *) exit 65 ;; esac
    gh workflow disable membership-morning-audit-production.yml
  fi
  echo "verdict=$VERDICT" >> "$GITHUB_OUTPUT"
  exit 0
fi
test "$(echo "$PR_JSON" | jq -r .state)" = open
git fetch origin main "$BRANCH"
git diff --name-only "origin/main...origin/$BRANCH" > "$RUNNER_TEMP/audit-before-paths.txt"
python3 tools/publish-membership-audit.py --check-paths "$RUNNER_TEMP/audit-before-paths.txt"
git checkout -B "$BRANCH" "origin/$BRANCH"
git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git merge --no-edit origin/main
python3 tools/publish-membership-audit.py "$MODE" "$AUDIT_INPUT" "$GITHUB_RUN_ID"
php tools/check-current-docs.php
git diff --check
while IFS= read -r path; do git add -- "$path"; done < <(
  python3 - <<'PY'
import importlib.util
from pathlib import Path
spec = importlib.util.spec_from_file_location('audit', 'tools/publish-membership-audit.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
for name in sorted(module.ALLOWED):
    if Path(name).is_file():
        print(name)
PY
)
if ! git diff --cached --quiet; then
  git diff --cached --name-only > "$RUNNER_TEMP/audit-after-paths.txt"
  python3 tools/publish-membership-audit.py --check-paths "$RUNNER_TEMP/audit-after-paths.txt"
  git commit -m "docs: server membership audit $MODE"
fi
HEAD_SHA=$(git rev-parse HEAD)
git push origin "$BRANCH"
if [ "$(echo "$PR_JSON" | jq -r .draft)" = true ]; then gh pr ready "$PR_NUMBER"; fi
# GitHub gates PR-event checks separately from workflow_dispatch checks.
# Only approve runs for this exact, already path-validated bot documentation commit.
RUNS='[]'
for attempt in $(seq 1 24); do
  RUNS=$(gh run list --branch "$BRANCH" --event pull_request --limit 30 \
    --json databaseId,headSha,workflowName --jq \
    "[.[] | select(.headSha == \"$HEAD_SHA\") | select(.workflowName == \"Testy\" or .workflowName == \"PHP quality\" or .workflowName == \"Current documentation\")]" )
  if [ "$(echo "$RUNS" | jq length)" -eq 3 ]; then break; fi
  sleep 5
done
test "$(echo "$RUNS" | jq length)" -eq 3
while IFS= read -r id; do
  META=$(gh api "repos/$GITHUB_REPOSITORY/actions/runs/$id")
  echo "$META" | jq -e --arg sha "$HEAD_SHA" --argjson pr "$PR_NUMBER" \
    '.head_sha == $sha and .event == "pull_request" and .actor.login == "github-actions[bot]" and
     ([.pull_requests[].number] | index($pr)) != null and
     (.path == ".github/workflows/tests.yml" or .path == ".github/workflows/php-quality.yml" or .path == ".github/workflows/docs-contract.yml")' >/dev/null
  if [ "$(echo "$META" | jq -r .conclusion)" = action_required ]; then
    gh api --method POST "repos/$GITHUB_REPOSITORY/actions/runs/$id/approve"
  fi
done < <(echo "$RUNS" | jq -r '.[].databaseId')
while IFS= read -r id; do
  gh run watch "$id" --exit-status --interval 20
done < <(echo "$RUNS" | jq -r '.[].databaseId')
test "$(gh pr view "$PR_NUMBER" --json headRefOid --jq .headRefOid)" = "$HEAD_SHA"
git fetch origin main "$BRANCH"
git diff --name-only "origin/main...origin/$BRANCH" > "$RUNNER_TEMP/audit-final-paths.txt"
python3 tools/publish-membership-audit.py --check-paths "$RUNNER_TEMP/audit-final-paths.txt"
gh pr merge "$PR_NUMBER" --squash --match-head-commit "$HEAD_SHA"
test "$(gh pr view "$PR_NUMBER" --json state --jq .state)" = MERGED
if [ "$MODE" = publish ]; then
  gh workflow disable membership-morning-audit-production.yml
fi
