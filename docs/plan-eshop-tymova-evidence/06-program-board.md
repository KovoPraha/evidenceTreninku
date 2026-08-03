# 06 – Program board

Aktualizováno: 3. 8. 2026 (první paralelní M1 přírůstek)
Aktuální produkční brána: **F0 – červená**
Aktuální lokální produktový milník: **M1 – integrovaný testovatelný prototyp**
Povolená práce: localhost přírůstky M1 podle
[08 – Milník M1](08-milnik-m1-integrovany-prototyp.md), testy a dokumentace
Zakázaný start: produkční aktivace nových funkcí, Stripe, automatické Fio,
wallet, exporty a ostrý KIS/Shoptet cutover

## Ověřený výchozí stav

| Položka | Stav |
|---|---|
| produkční commit | `58ec8ec985d447dfe901481ac8bb24b944b03d08` |
| poslední ověřený deploy | GitHub run `30668559417`, úspěšný |
| produkční schema/PHP | `2.20.2` / `8.2.32` |
| vzdálený `main` | PR #1 až #6 sloučeny po vrstvách; `7f48b50b128b65f7340442ba33bfb9c66c27703a`, finální run `30743017895` úspěšný |
| lokální práce | první paralelní M1 přírůstek: rodinný portál `d42a30c`, beneficiary snapshot `82b42a3` a série/rollover preview `18b81a3`; bez produkčních změn |
| bezpečnostní snapshot | `d2b3c56` na `codex/pre-reconcile-20260801`, pouze lokálně |
| odchylka lokálního main | odstraněna fast-forwardem; unikátní práce je zachována ve snapshot větvi |
| syntax | všechny soubory jednotlivých paralelních přírůstků a integrační PHP soubory prošly lintem |
| dependency audit | 0 advisories lokálně; produkční stav nebyl v tomto přírůstku měněn |
| automatické testy | 213 testů / 1583 assertions lokálně; poslední vzdálený důkaz zůstává GitHub CI run `30743017895` |
| migrace | 22 číslovaných migrací; obě nové M1 migrace byly aplikovány pouze na localhost a katalog je 22/22 current |
| deploy/backup | fail-closed záloha, preflight pepperu a pořadí release → migrace → aktivace PHP jsou v `main`; chybí GitHub Secret `SSH_KNOWN_HOSTS` a produkční pepper nebyl ověřen |
| restore drill | post-migration localhost backup má 102 tabulek / 1 trigger a ověřený checksum; ownership kontrakt `2026-08-03.2` zahrnuje `club_team_series`; produkční artefakt nebyl testován |
| KIS matcher | dále zpřísněn: jméno-only ani e-mail-only se automaticky nepřijmou, rozdílné datum narození je konflikt; ostrý import zůstává blokovaný |
| Shoptet katalog | reálných 241/807 bylo po auditované kontrole transakčně převedeno do izolovaného draft katalogu; opakování bez duplicity, veřejně aktivních produktů 0 |
| Aktivace katalogu | `5500927`: jednotlivé `goods` lze ručně aktivovat s plain-text veřejným snapshotem a auditem; K3 typy jsou fail-closed; veřejný storefront stále neexistuje |
| K3 akce | `4ef5690`, `88f5b97`, `e5fcaa0`, `a949c38`: navíc FIFO čekací listina a atomické povýšení nejstarší oprávněné osoby po stornu; bez objednávky, plateb, soupisky a KIS zápisu |
| K2 identita | `8c374a4`, `d32fc08`: účet je oddělen od sportovce; veřejný claim neenumeruje osoby, vazby `self`/`guardian` schvaluje pouze admin s důvodem a auditem; neověřený účet ani zrušená vazba účastníka nezpřístupní |
| lokální data | localhost demo obsahuje rodiče se dvěma oprávněnými profily, školní i kalendářní sezonu, věkovou a disciplínovou soupisku a cíle read-only rolloveru |

## První paralelní přírůstek M1

| Proud | Commit | Přijatý důkaz |
|---|---|---|
| M1.1 rodinný sportovní přehled | `d42a30c` | guardian/self rozsah, IDOR testy, soupisky, události a docházka |
| M1.1 příjemce objednávkové položky | `82b42a3` | autorizovaný beneficiary v košíku, neměnný snapshot v objednávce, běžné zboží smí zůstat bez příjemce |
| M1.2 série a sezony | `18b81a3` | school/calendar sezony, čtyři politiky a read-only preview s `mutation_count=0` |
| integrační brána | tento commit | migration check 22/22, 213/1583, audit 0 advisories a browser průchod rodiče i KIS admina |

Hodnoty lokální DB nejsou produkční statistika. Slouží pouze k posouzení, zda
současné vývojové prostředí dokáže ověřit navrhované scénáře.

## Aktivní rozhodnutí

Zdroj pravdy je tabulka D-001 až D-015 v [02 – Zadání a rozhodnutí](02-zadani-a-rozhodnuti.md).

| Rozhodnutí | Stav | Vlastník |
|---|---|---|
| D-001 až D-004: tvar aplikace, shop, Shoptet a KIS přechod | doporučeno | vlastník produktu |
| D-005 až D-006: účet a rodina | čeká na potvrzení | produkt + bezpečnost |
| D-007: případné budoucí sdílení uživatelů | potvrzeno: pouze identita, mimo MVP | vlastník produktu |
| D-008: nová doména klubových akcí | doporučeno | vlastník produktu |
| D-009: reward vs cash kredit | blokující | ekonom + právní/účetní konzultace |
| D-010 až D-013: platby, částky, checkout a doprava | čeká na potvrzení | produkt + ekonom |
| D-014: hranice vůči Velocotě | potvrzeno: žádná širší integrace | vlastník produktu |
| D-015: číslované migrace | technicky přijato ve W0-E; produkční ověření čeká | technický vlastník |

## Backlog F0

| ID | Úkol | Závisí na | Smí běžet paralelně | Hotovo znamená |
|---|---|---|---|---|
| W0-A | Repo a deploy reconciliation | nic | dokončeno | lokální práce zachována, main sjednocen, workflow pravdivě zdokumentovaný |
| W0-B | KIS matcher safety | dokončeno `7106930` | přijato | pořadově nezávislé testy a neměnný cache snapshot |
| W0-C | Dependencies a auth hardening | částečně v `main`, včetně tokenového přírůstku PR #5 | ano, jediný vlastník auth | session lifecycle, DB revokace, limiter, expirované single-use tokeny a POST+CSRF logout hotové; zbývá permission cache, reset hesla a produkční odstranění legacy hesel |
| W0-D | Test harness a CI | dokončeno `0d50584`, remote run `30718098799` zelený | přijato | PHPUnit + GitHub workflow + test gate před deploy SSH |
| W0-E | Migrace a deploy hardening | PR #6 v `main`, produkční ověření čeká | produkční ověření čeká | runner/check, fail-closed backup, lokální restore drill, preflight pepperu a migrace před aktivací PHP jsou doloženy; zbývá Secret/config a autorizovaný první deploy |
| W0-F | ADR identity/KIS/wallet | produktová odpověď | ano, bez kódu | D-004 až D-011 mají schválený stav a důvod |
| W0-G | Realistické anonymizované fixtures | částečně: Shoptet `f0370a3`/`3845eab`/`b77f8c3`, K2 `8c374a4`/`d32fc08`, aktivace `5500927`, K3 `4ef5690`/`88f5b97`/`e5fcaa0`/`a949c38`; matice `168d132`, `8f0cbe8` | ano | Shoptet, K2, goods aktivace a bezplatný K3 průchod včetně čekání pokryty; zbývá reálný KIS formát a platby |

## Bezpečný merge order

1. W0-A – vytvoří jediný důvěryhodný base.
2. W0-B a W0-C – bezpečnost dat a přístupu.
3. W0-D – rozšíří CI nad sjednoceným základem.
4. W0-E – zavede migrace a release safety.
5. W0-F a W0-G – mohou vznikat průběžně, ale musejí být uzavřené před Bránou F0.
6. Integrační task spustí všechny kontroly a aktualizuje tento board.

## Brána F0 – aktuální checklist

- [x] lokální a vzdálený main bezpečně sjednocen,
- [x] KIS matcher opraven a otestován na foundation,
- [x] dependency audit foundation bez advisories,
- [ ] legacy hesla a session mají schválenou a ověřenou nápravu; tokeny, logout, lifecycle, DB revokace a limiter jsou hotové lokálně, ale permission cache, reset hesla a produkční password apply zbývají,
- [x] unit/integration testy a migrační fixture existují; první GitHub běh `30718098799` je zelený,
- [x] číslované migrace a read-only `--check` existují lokálně,
- [ ] staging/test DB a kompletní realistické fixtures existují; rozšířené syntetické KIS/Shoptet matice a read-only dry-run už jsou doložené,
- [ ] deploy kód selže při chybě zálohy a v `main` ověřuje pepper i pořadí migrace před aktivací PHP; provozně chybí `SSH_KNOWN_HOSTS`, produkční pepper a autorizovaný první release,
- [x] lokální restore drill je doložen; před prvním finančním schématem zopakovat s produkčním backup artefaktem,
- [ ] identity, KIS ownership a wallet pravidla jsou schválena.

Pokud chybí jediná položka, F0 zůstává červená. „Deploy proběhl“ není náhradou
za splnění této brány.

Auth F0 větve přidávají pouze bezpečnostní schéma a přihlašovací infrastrukturu;
nepřidávají košík, platby ani produkční import. Hashované, expirované a atomicky
jednorázové e-mailové/booking tokeny jsou implementované v `main`.
Shoptet export a bezpečný katalogový staging jsou doložené. Produktově stále
chybí potvrzení stabilního KIS identifikátoru a retenční doby preview dat.
Před budoucím auth deployem musí být mimo Git nastaven `AUTH_RATE_LIMIT_PEPPER`.

## Pokyn pro příští řídicí task

```text
Pracuj jako řídicí task programu Evidence e-shop + týmová evidence.
Nezačínej produktovou implementaci. Nejdřív načti dokumenty v
docs/plan-eshop-tymova-evidence a ověř git status, HEAD a origin/main.
Zachovej všechny cizí změny. Řiď Backlog F0 z 06-program-board.md.
W0-A až W0-E nejdřív živě ověř; znovu je neimplementuj. Potom zadávej pouze
nekolidující tasky pro zbývající W0-C, W0-F a W0-G podle 05-rizeni-vlaken.md.
Každý task musí vrátit base/commit SHA, jmenované
soubory, migrace, testy, rizika a akceptační důkaz. Produkční deploy spouští
ručně vlastník až po integrační kontrole; pracovní task jej nespouští.
```

## Podmínka pro změnu boardu

Stav lze posunout pouze na základě důkazu: commit/diff, výsledek testu, DB/schema
post-check, restore záznam nebo výslovně potvrzené produktové rozhodnutí. Pouhý
odhad nebo zelený syntax lint nestačí.
