# Srovnání dokumentace s vývojem — 12. 11. 2026

Rozsah: vstupní dokumentace, registr, plán UX01 a vývojový kontext. Výchozí
`origin/main` `4b9bcb1a8c828663ea556f50ec2f5ed5695fb8d9`.
Práce proběhla ve worktree `codex/docs-current-state-20261112`; původní
`codex/fio-auto-reconciliation-recovery` s rozpracovaným `kis_rosters_admin.php`
nebyl upraven.

| Zjištění | Ověřený zdroj | Oprava / hranice |
| --- | --- | --- |
| Nejnovější produkční kód | [deploy 43215069511](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43215069511) SUCCESS na `87171a67`; [výsledek kopírování](../training-copy-20261112/RESULTS.md) | Stav a rozcestník rozlišují `main` od nasazeného commitu; přihlášené UAT NOT RUN. |
| Styly přihlášení a tří tréninkových tras | [přihlášení](../signin-bootstrap-20261111/RESULTS.md), [formuláře](../training-form-bootstrap-20261112/RESULTS.md), deploy 43104809135 a 43164465663 SUCCESS | V přehledech doplněna vydání #453/#455; syntetická shoda a veřejné HTTP neznamenají přihlášenou přejímku. |
| Průvodce celoroční nabídkou | [PR #395](https://github.com/KovoPraha/evidenceTreninku/pull/395) MERGED jako `1c828fbf`, [deploy 42492821311](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42492821311) SUCCESS na stejném SHA | Plán a registr už neuvádějí čekání na merge/deploy. Skutečné ceny, nové nabídky, nákup a přihlášené UAT nejsou potvrzené. Podrobný dokumentační [PR #401](https://github.com/KovoPraha/evidenceTreninku/pull/401) je stále otevřený. |
| Zápis tréninku | Kanonický [release](../training-form-resilience-release-20261109/RESULTS.md) uvádí UAT NOT RUN; otevřený [PR #417](https://github.com/KovoPraha/evidenceTreninku/pull/417) dokumentuje 3/3 přihlášené scénáře bez platného odeslání | Oba stavy jsou uvedeny odděleně; před uzavřením UAT převzít a posoudit PR a neověřený úklid syntetického konceptu. |
| Fio/SumUp | [Aktivace](../live-payments-20261105/ACTIVATION.md), [CURRENT](../../CURRENT_STATE.md) | Vývojový kontext již nepopisuje aktivaci jako budoucí krok. Nové finanční operace zůstávají samostatné. |

Předání: [PR #460](https://github.com/KovoPraha/evidenceTreninku/pull/460)
sloučen 12. 11. jako `ca95f6c3` po 11 úspěšných CI kontrolách včetně
PHPUnit, CodeQL, dokumentace a MariaDB 10.3/11.4.
Místní `php tools/check-current-docs.php` PASS (80 řádků CURRENT,
5 393 odkazů, 108 archivních bloků), `git diff --check` PASS.
Sloučení dokumentace nemění nasazenou aplikaci ani provozní přepínače.
