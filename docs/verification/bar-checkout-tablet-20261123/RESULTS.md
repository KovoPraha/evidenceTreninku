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
| PR/CI | [PR #519](https://github.com/KovoPraha/evidenceTreninku/pull/519) sloučený jako `e647b7bf`. Finální head `14ea19f5`: [Current documentation 45150719805](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45150719805), [PHP quality 45150719853](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45150719853), [JS parity 45150719724](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45150719724), [Testy 45150720158](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45150720158) — vše PASS. Testy zahrnují PHPUnit a MariaDB 10.3/11.4. |
| Merge a deploy | PASS: vlastník schválil nasazení 23. 11.; [deploy 45156963794](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45156963794) SUCCESS přesně na `e647b7bf`. Produkční testy, záloha, migrace a HTTP smoke ve workflow PASS; `uat_schvaleno=false`, import kroužků false. |
| Živý HTTP | PASS: `GET /assets/bar-checkout.css` 200, obsah přesně odpovídá repozitáři po sjednocení CRLF/LF (SHA-256 `49bb3c2cde4d29f2091406865faff7860f73537c4d23368d65faf5042cae8605`). Nepřihlášený `GET /bar/checkout.php` vrací 302 na přihlášení. |
| Přihlášené produkční UAT obsluhy a skutečný prodej | NOT RUN; vyžaduje obsluhu a skutečný tablet. |

Metoda: Playwright CLI proti lokálnímu PHP náhledu se 45 syntetickými produkty. Původní vykreslení formuláře pochází z `origin/main` `71c80d7e`, nové ze změněné větve; oba náhledy mají stejná data a simulovanou 64px hlavičku KIS. Náhled nepoužil produkční účty, databázi ani zásoby; nenahrazuje skutečnou stránku s přihlášenou obsluhou.
