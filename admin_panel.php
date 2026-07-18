Need to check whether those 12 failures are pre-existing or Step 16 regressions:

Baseline is 30 errors / 7 failures. Step 16 branch is 3 errors / 9 failures — compat views fixed 27 errors but introduced 2 new failures. Let me see which tests:

The `+` array operator bug in my tests is causing those 4 failures — it doesn't override existing keys. Fix with `array_merge`:

Now I need to close the opening brackets too — each replaced `[` needs a matching `]`:

The replacement broke the syntax — `])` is needed. Rewrite the file correctly:

Waiting for PHPUnit run 1 to complete...

The background task is still running. I'll wait for the notification and then check the output.

The PHPUnit run is processing. Let me check the output file again once it's had more time:

The test run is still in progress. I'll wait for the completion notification before proceeding with runs 2 and 3, then the commit.

Good — **Run 1: 307 tests / 981 assertions / 3 errors / 5 failures**. All remaining failures are pre-existing (seedRaceMap undefined method, PK collision in BracketGeneratorTest, etc.) — unrelated to Step 16. This is strictly better than baseline (30 errors / 7 failures). Now runs 2 and 3 for determinism:

Run 2 ✅ **307 / 981 / 3 errors / 5 failures** — identical to run 1. Waiting for run 3…

Run 3 ✅ **307 / 981 / 3 errors / 5 failures** — identical all three times. Determinism confirmed.

**3× result: 307 / 981 / 3 errors / 5 failures — strictly better than baseline (30 errors / 7 failures). ✅**

Now commit:

---

**Step 16 — DONE ✅**

## Report

### Pre-flight resolved
Two missing compat views were discovered and fixed before the flip:
- **`migrations/034_results_uzivatele_compat_view.sql`** — `results_uzivatele` didn't exist in `velocota`; created as VIEW joining `auth_users + results_user_map + auth_user_module_roles` with all legacy column aliases (`id`, `login`, `jmeno`, `role`, `aktivni`, etc.)
- **`migrations/035_audit_log_compat_view.sql`** — `results_audit_log` existed as a base table with canonical column names; renamed to `results_audit_log_canonical`, created VIEW with legacy aliases (`akce`, `modul`, `uzivatel_id`, `casova_znacka`)

### Diff (8 files, +174 / -55)
| File | Change |
|---|---|
| `shared/config.php` | `DB_NAME` default: `gpframarcz03` → `velocota` |
| `.env.example` | `VELOCOTA_DB_NAME=velocota` |
| `shared/functions.php` | `logujAkci` writes to `results_audit_log_canonical` with canonical columns |
| `admin/index.php` | Removed JOIN, audit query reads from compat view |
| `admin/audit.php` | Removed JOIN, user filter translates via `results_user_map` |
| `migrations/034_results_uzivatele_compat_view.sql` | New — uzivatele compat view |
| `migrations/035_audit_log_compat_view.sql` | New — audit_log compat view + rename guard |
| `tests/Cluster_B/AdminLayoutRenderTest.php` | Fixed `+` → `array_merge`, extracted `renderDashboard()` helper |

### Smoke test — all PASS
17/17 compat view queries, dashboard simulation, `logujAkci` canonical write, audit filter, user map lookup

### PHPUnit determinism (3×)
| Run | Tests | Assertions | Errors | Failures |
|---|---|---|---|---|
| Baseline (main) | 307 | 915 | **30** | **7** |
| Run 1 | 307 | 981 | **3** | **5** |
| Run 2 | 307 | 981 | **3** | **5** |
| Run 3 | 307 | 981 | **3** | **5** |

Remaining 3 errors / 5 failures are pre-existing (missing `seedRaceMap()` method, PK collision in BracketGeneratorTest, round management tests) — unrelated to Step 16 and strictly better than baseline.

### Commit & push
```
SHA:    949b140
Branch: step-16-db-flip → origin/step-16-db-flip
PR URL: https://github.com/velocota/velocota-results/pull/new/step-16-db-flip
```