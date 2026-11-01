# Produkční nasazení opravy obnovovacího odkazu

1. 11. 2026, Europe/Prague. **PR #269 sloučen, oprava nasazena a omezeně ověřena v produkci.**
Žádné skutečné heslo, obnovovací odkaz nebo účet se při ověření neměnily.

- Vlastník výslovně schválil review, merge, deploy a ověření.
- [PR #269](https://github.com/KovoPraha/evidenceTreninku/pull/269) prošel review
  bez blokujícího nálezu a všech 11 CI kontrol pro head
  `3d4d40dd3d3b9495db5277dbb931f31550e51f15` SUCCESS.
- Squash na main: `acce3c1351cddbcad12fb5fea11afe6ae8936001`; strom je shodný
  s ověřeným headem. Z původního živého vydání se mění pouze resetovací PHP
  a dokumentace/testy, žádná nová migrace.
- [Předprodukční čtecí audit 41349042948](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41349042948)
  SUCCESS, produkce dosud `87596760f4267c9db378e8a4062e604fd22d68fa`, run 41330031591.
- [Nasazení 41349120615](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41349120615)
  **SUCCESS**, spuštěno z main, `uat_schvaleno=false`, `importovat_krouzky=false`.
  Běh 21:53:05–21:57:14 Europe/Prague, deploy znovu provedl 1900/38192.
  Metadata vydání uvádějí `deployed_at=2026-11-01T20:55:25+00:00`.
- Browser před aktivací: `/booking/nove_heslo.php?pro=trener`, nadpis
  „Nastavení nového hesla“, dvě heslová pole a žádný předběžný kontrolní formulář.
  Žádné heslo ani skutečný obnovovací odkaz se nepoužily.

## Doklady nasazení a zachování stavu

- [Review a CI](ci-review.json): přesný head, shodné stromy po squash,
  všech 11 kontrol SUCCESS, Linux 1900/38192 a POSIX 3/45 bez skipů.
- [Deployment](deployment.json): standardní preflight a syntaxe PASS,
  ověřená záloha **337 tabulek / 2 triggery**, SHA256
  `329eb7cac243938547bbbf74202b2d309867e585f371043b7509f7c4bc71125a`.
  Komprese/hash/manifest ověřeny workflow; tato nová záloha se samostatně
  neobnovovala. Poslední předchozí doložená obnova zůstává v CURRENT_STATE.
- Migrace apply/check: `current=true`, pending `[]`, katalog 142 položek;
  čtecí audit potvrdil **ledger 143 beze změny**. Žádná nová migrace.
- Otisky dosavadních variabilních symbolů beze změny. Soukromé úložiště
  zachovalo konfiguraci (`config_appended=false`), žádné přílohy se nepřesouvaly.
  Dvě historicky chybějící přílohy přetrvávají; candidates 0, errors 0.
- [Čtecí audit po vydání 41349491104](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41349491104)
  SUCCESS; [před](pre-verification.json), [po](post-verification.json),
  [porovnání](release-comparison.json). Živé SHA/run odpovídají nasazení.
  Config hash, flags integrací, kandidáti příloh, canonical-write flag a ledger shodné.
- Fio import/auto-confirm, SumUp, Stripe a Velocota false; Packeta nedefinovaná,
  canonical writes false. UAT false, import false. Žádné aktivace integrací.
- Identita vydání je doložena chráněným workflow, jeho metadata a pozorovaným
  novým UI; není tvrzena kompletní inventura hashů všech runtime souborů.

## Funkční ověření po nasazení

| Kontrola | Výsledek | Skutečný důkaz |
|---|---|---|
| Veřejná dostupnost, ochrana privátních cest a veřejný katalog | PASS | Anonymní GET [před](pre-http.json) i [po](post-http.json) **26/26**, stejné vybrané veřejné produkty. Bez cookies, bez uloženého HTML, bez zápisů. [Použitý skript](http-smoke.py). |
| Stránka trenérského resetu bez fragmentu | PASS | Chrome 1536 × 674; před aktivací 2 heslová pole, po aktivaci 0 a text „Odkaz není platný nebo už byl použit.“ [Snímek](trainer-missing-link.jpg). |
| Trenérský režim, syntetický neexistující odkaz | PASS | Skutečný automatický CSRF chráněný check POST, odmítnutí bez heslových polí a bez formuláře změny hesla. Nová žádost zachovává `?pro=trener`. [Snímek](trainer-invalid-link.jpg). |
| Veřejný/sportovní režim, syntetický neexistující odkaz | PASS | Stejný automatický check POST a odmítnutí před heslovými poli; odkaz na novou žádost v příslušném režimu. [Snímek](public-invalid-link.jpg). |
| Změna hesla platným odkazem, opakované odeslání a oprava chybně vyplněných hesel | PASS v izolovaných testech | Skutečná PHP stránka a zápisové služby nad syntetickými SQLite daty, zahrnuto v deploy 1900/38192. Nejde o produkční změnu hesla. [Implementační protokol](../trainer-reset-replay-20261101/RESULTS.md). |
| Skutečný použitý odkaz v produkci po opravě | BLOCKED / odloženo | Vyžaduje vlastníka, jeho odkaz a ruční práci s heslem. Nepoužil se historický odkaz ani DB token. |
| T07 — revokace dvou skutečných relací po změně hesla | BLOCKED / odloženo | Bez nové součinnosti vlastníka se neprovádělo. Jednorázovost a změna verze relace jsou pokryty izolovanými testy, nikoli novým lidským UAT. |

Browser pozorování jsou shrnuta v [bezpečném záznamu](browser-verification.json).
Testy neplatných odkazů začínaly plným načtením stránky z jiné cesty a čekaly
na dokončení automatického POST; změna samotného fragmentu existující stránky
nebyla započtena jako důkaz serverové kontroly. Použil se pouze pevný syntetický
neexistující vstup, který není ukládán do dokladů. Žádné skutečné tokeny se nečetly.

Deploy runner měl při závěrečném smoke síťový timeout; standardní workflow
provedl tentýž HTTP test ze serveru a dostal 200. Samostatné externí HTTP
kontroly 26/26 a Chrome následně uspěly také. Nejde o důkaz HTTP chyby aplikace.

## Limity a konečný stav

Potvrzený problém zavádějícího zobrazení formuláře je opravený, nasazený
a pro neplatné odkazy ověřený. Druhá úspěšná změna hesla se v kódu/testech
nepotvrdila; zápisová služba již dříve opakování odmítala. Tento závěr
zpětně nepřepisuje [původní UAT hlášení](../trainer-browser-uat-20261101-1955-a7c2/RESULTS.md).

Skutečný použitý odkaz a T07 se tímto během nepovažují za splněné. Nové heslo
nebylo zadáváno, obnova se nevyžadovala, e-mail se neodesílal, žádné další
přihlášení se neprovádělo a trénink 1604 se neměnil. Chrome zůstal anonymní,
ověřovací karta se uzavřela a viewport se v tomto běhu neměnil.
