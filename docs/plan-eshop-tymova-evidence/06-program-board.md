# 06 – Program board

Aktualizováno: 1. 8. 2026  
Aktuální programová brána: **F0 – červená**  
Povolená práce: plánování a odstranění blokátorů F0  
Zakázaný start: shop, Stripe, Fio, wallet a ostrý KIS cutover

## Ověřený výchozí stav

| Položka | Stav |
|---|---|
| produkční commit | `58ec8ec985d447dfe901481ac8bb24b944b03d08` |
| poslední ověřený deploy | GitHub run `30668559417`, úspěšný |
| produkční schema/PHP | `2.20.2` / `8.2.32` |
| lokální commit | `c4617330499076bf052f0078a60137fc7692aaeb` |
| foundation base | `58ec8ec985d447dfe901481ac8bb24b944b03d08` na `codex/foundation` |
| bezpečnostní snapshot | `d2b3c56` na `codex/pre-reconcile-20260801`, pouze lokálně |
| odchylka lokálního main | odstraněna fast-forwardem; unikátní práce je zachována ve snapshot větvi |
| syntax | 167 PHP souborů viditelných v aktuálním worktree prošlo lintem |
| dependency audit | 12 advisories: 3 HIGH, 9 MEDIUM |
| automatické testy | chybí |
| KIS matcher | kritická chyba shrinking-cache, ostrý import STOP |
| lokální data | 253 sportovců, 0 e-mailů, 0 veřejných účtů, 0 KIS runů |

Hodnoty lokální DB nejsou produkční statistika. Slouží pouze k posouzení, zda
současné vývojové prostředí dokáže ověřit navrhované scénáře.

## Aktivní rozhodnutí

Zdroj pravdy je tabulka D-001 až D-015 v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md).

| Rozhodnutí | Stav | Vlastník |
|---|---|---|
| D-001 až D-004: tvar aplikace, shop, Shoptet a KIS přechod | doporučeno | vlastník produktu |
| D-005 až D-007: účet, rodina a budoucí Velocota login | čeká na potvrzení | produkt + bezpečnost |
| D-008: nová doména klubových akcí | doporučeno | vlastník produktu |
| D-009: reward vs cash kredit | blokující | ekonom + právní/účetní konzultace |
| D-010 až D-013: platby, částky, checkout a doprava | čeká na potvrzení | produkt + ekonom |
| D-014: integrační hranice Velocoty | doporučeno | technický vlastník |
| D-015: číslované migrace | blokující | technický vlastník |

## Backlog F0

| ID | Úkol | Závisí na | Smí běžet paralelně | Hotovo znamená |
|---|---|---|---|---|
| W0-A | Repo a deploy reconciliation | nic | dokončeno | lokální práce zachována, main sjednocen, workflow pravdivě zdokumentovaný |
| W0-B | KIS matcher safety | base po W0-A | W0-C | oprava + pořadově nezávislé testy `matched/new/ambiguous/conflict` |
| W0-C | Dependencies a auth hardening | base po W0-A | W0-B | 0 HIGH advisories; plán/migrace hesel; bezpečné session a tokeny |
| W0-D | Test harness a CI | base po W0-A | po rozdělení souborů s W0-C | unit, DB integration a migration fixture běží v GitHub Actions |
| W0-E | Migrace a deploy hardening | W0-A, návrh D-015 | ne se změnou workflow jinde | numbered runner, backup fail-closed, pinned host key, restore drill |
| W0-F | ADR identity/KIS/wallet | produktová odpověď | ano, bez kódu | D-004 až D-011 mají schválený stav a důvod |
| W0-G | Realistické anonymizované fixtures | pravidla privacy | ano | pokrývají rodinu, duplicitu, KIS konflikt, shop varianty a platby |

## Bezpečný merge order

1. W0-A – vytvoří jediný důvěryhodný base.
2. W0-B a W0-C – bezpečnost dat a přístupu.
3. W0-D – rozšíří CI nad sjednoceným základem.
4. W0-E – zavede migrace a release safety.
5. W0-F a W0-G – mohou vznikat průběžně, ale musejí být uzavřené před Bránou F0.
6. Integrační task spustí všechny kontroly a aktualizuje tento board.

## Brána F0 – aktuální checklist

- [x] lokální a vzdálený main bezpečně sjednocen,
- [ ] KIS matcher opraven a otestován,
- [ ] dependency audit bez HIGH,
- [ ] legacy hesla a session mají schválenou a ověřenou nápravu,
- [ ] automatické unit/integration/migration testy běží v CI,
- [ ] číslované migrace a `--check` existují,
- [ ] staging/test DB a realistické fixtures existují,
- [ ] deploy selže při chybě zálohy a host key je připnutý,
- [ ] restore drill je doložen,
- [ ] identity, KIS ownership a wallet pravidla jsou schválena.

Pokud chybí jediná položka, F0 zůstává červená. „Deploy proběhl“ není náhradou
za splnění této brány.

## Pokyn pro příští řídicí task

```text
Pracuj jako řídicí task programu Evidence e-shop + týmová evidence.
Nezačínej produktovou implementaci. Nejdřív načti dokumenty v
docs/plan-eshop-tymova-evidence a ověř git status, HEAD a origin/main.
Zachovej všechny cizí změny. Řiď Backlog F0 z 06-program-board.md.
Jako první bezpečně vyřeš W0-A. Potom zadávej pouze nekolidující pracovní tasky
podle 05-rizeni-vlaken.md. Každý task musí vrátit base/commit SHA, jmenované
soubory, migrace, testy, rizika a akceptační důkaz. Produkční deploy spouští
ručně vlastník až po integrační kontrole; pracovní task jej nespouští.
```

## Podmínka pro změnu boardu

Stav lze posunout pouze na základě důkazu: commit/diff, výsledek testu, DB/schema
post-check, restore záznam nebo výslovně potvrzené produktové rozhodnutí. Pouhý
odhad nebo zelený syntax lint nestačí.
