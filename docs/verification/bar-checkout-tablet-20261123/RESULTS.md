# BAR-CHECKOUT-TABLET-01 — ověření kompaktní pokladny

23. 11. 2026. Větev `codex/bar-checkout-tablet-20261123`, základ `origin/main` `71c80d7e`. [Plán](../../BAR_INTEGRATION_PLAN_2026-11-05.md#bar-checkout-tablet-01--kompaktní-pokladna-na-tabletu-23-11-2026).

| Kontrola | Stav |
| --- | --- |
| Syntetický tablet 768 × 1024 | PASS: původní 7 → nové 17 viditelných řádků, první řádek 285 → 199 px od horního okraje, výška řádku 108 → 52 px. [Před](before-768x1024.png), [po](after-768x1024.png). |
| Syntetický tablet 1024 × 768 | PASS: původní 5 → nové 12 viditelných řádků, pole počtu 44 px. [Po](after-1024x768.png). |
| Syntetický úzký tablet 600 × 960 | PASS: 15 viditelných řádků, šířka stránky i tabulky 600/548 px bez přetékání, pole počtu 44 px. |
| Mobil 390 × 844 | PASS pro šířku celé stránky 390 px; tabulka zachovává vnitřní horizontální posun jako dosud. Přihlášená mobilní přejímka NOT RUN. |
| Formulář a čitelnost | PASS v syntetickém náhledu: 45 polí počtu, název prvního `quantities[1]`, `action=quote`, sleva a kelímky přítomny; ceny a dostupnost čitelné. Odeslání skutečného prodeje NOT RUN. |
| Syntaxe PHP, styl, dokumentace a `git diff --check` | Dva PHP lint PASS; PHP CS Fixer nad oběma změněnými soubory PASS. Kontrola dokumentace: 22 řádků CURRENT, 5865 místních odkazů, 0 chyb; diff PASS před předáním. PHPUnit v izolovaném worktree NOT RUN, protože zde není `vendor/`; CI zůstává brána PR. |
| PR/CI | [PR #519](https://github.com/KovoPraha/evidenceTreninku/pull/519) OPEN. Commit `5703074b`: [Current documentation 45149178800](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45149178800), [PHP quality 45149178774](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45149178774), [JS parity 45149178796](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45149178796), [Testy 45149178845](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45149178845) — vše PASS. Testy: PHPUnit a obě varianty MariaDB PASS. |
| Merge, deploy, přihlášené produkční UAT obsluhy | NOT RUN; toto doplnění CI je nový dokumentační commit. |

Metoda: Playwright CLI proti lokálnímu PHP náhledu se 45 syntetickými produkty. Původní vykreslení formuláře pochází z `origin/main` `71c80d7e`, nové ze změněné větve; oba náhledy mají stejná data a simulovanou 64px hlavičku KIS. Náhled nepoužil produkční účty, databázi ani zásoby; nenahrazuje skutečnou stránku s přihlášenou obsluhou.
