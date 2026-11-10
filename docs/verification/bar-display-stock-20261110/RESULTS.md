# BAR-DISPLAY-STOCK — provozní výsledek 10. 11. 2026

**Technická oprava, nasazení, převod zásob a přepnutí původních TV URL dokončené. Vizuální a lidská provozní přejímka zůstávají otevřené.** [Plán](../../BAR_DISPLAY_STOCK_PARITY_2026-11-10.md), [aktuální provozní stav](../../CURRENT_STATE.md), [další kroky BAR](../../SESSION_HANDOFF_BAR_2026-11-06.md).

## Obrazovky a vydání

- [PR #440](https://github.com/KovoPraha/evidenceTreninku/pull/440): 12 kontrol PASS na `7acf051d`, 2451 testů / 56206 assertions. Sloučen jako `9ef52268e1f5a8925cb62af6ab6b59ba27f75ac6`; [deploy 42863352343](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42863352343) SUCCESS, oba UAT/import flagy false. Zachované souběžné schválené změny kalendářů, testerky a trenérských soupisek.
- Původní dvousloupcový textový ceník a tři střídající fotografické karty, původní velikosti/barvy/přechody, přizpůsobení výšce a responzivní chování těchto TV šablon. [Bajtová shoda obou původních CSS](original-css-parity.json). Foto skript zachovává desetisekundové střídání, 720 ms přechod a neopakování předchozí trojice. Tiskový PDF tok zůstává oddělený.
- [PR #443](https://github.com/KovoPraha/evidenceTreninku/pull/443): 11 kontrol PASS na `e9b5f194`, 2453 testů / 56219 assertions; sloučen jako `58848900`. Přesná signed počáteční zásoba a bezpečné fázové souhrny. Prověřený pomocník byl spuštěn chráněným workflow mimo webroot; kvůli tomuto kroku se web znovu nenasazoval.
- [Konečná živá kontrola](http-final.json) a [opakovatelný čtecí postup](check-live.py): 2 stránky, přesné 4 soubory CSS/JS proti Git blobům vydání, 45 produktů / 45 fotografií, neplatný pohled odmítnut 400 a 2 původní TV odkazy PASS. [První kontrola po deployi](http-deployed.json) je zachovaná. Výslednou CSP spravuje společný výstupní filtr KIS: nonce/strict-dynamic a zakázané inline handlery ověřené.

Veřejné vstupy: [textová obrazovka](https://kis.kovopraha.cz/bar/menu.php?view=text), [fotografická obrazovka](https://kis.kovopraha.cz/bar/menu.php?view=photos). Oba přímé původní odkazy `public/obrazovka.php` a `public/obrazovka2.php` mají odpovídající odlišné cíle.

## Skutečný převod zásob

| Krok | Výsledek a důkaz |
| --- | --- |
| Nový čtecí snímek [42868726163](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42868726163) | [PASS](stock-inspect-42868726163.json): 68 jednoznačných source_id, cílové počty nula, zdroj stánku 1073 / předskladu 0, 5 záporných zůstatků stánku. |
| Zápis [42868993077](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42868993077) | [PASS](stock-apply-42868993077.json): committed=true, první provedení, 136 auditovaných počátečních operací, 68 produktů a oba sklady v jedné transakci, rozdíly 0. |
| Nezávislé čtení [42869293812](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42869293812) | [PASS](stock-status-42869293812.json): všech 68 produktů v obou místech přesně odpovídá zdroji, rozdíly 0. Čtecí status sám nic nezapisuje. |

Pět záporných hodnot bylo zachovaných přesně; nevzniklo vynulování ani zaokrouhlení. Běžné prodejní/skladové ochrany zůstaly. Převod zároveň převzal pouze povolená veřejná nastavení původní obrazovky. Ceny, katalog, fotografie, účty, role, dosavadní KIS prodeje/kasa a platební konfigurace se znovu neimportovaly. Zdrojová DB zůstala beze změny.

Konkrétní snímek je uchovaný soukromě v `data/.kis-backups/bar-stock-42868726163/snapshot.json`; jeho SHA-256 je v čtecím výsledku. Bezpečné výsledky v tomto adresáři prošly místní kontrolou shody počtů, otisků obnovy, navazujících běhů a obou TV cílů.

**Další skladové operace patří pouze do KIS. Původní BAR se znovu nesynchronizuje a počáteční převod se neopakuje novým snímkem.** Shoda se zdrojem je důkaz okamžiku převodu; následný skutečný prodej smí počty v KIS změnit. Fyzická inventura a potvrzení pěti záporných stavů jsou otevřené.

## Body obnovy a staré adresy

- Před skladovým zápisem skutečná úplná záloha a obnova [42860986643](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42860986643) SUCCESS: 389 tabulek / 4597 souborů. [Přesné názvy a SHA-256](recovery-42860986643.json) byly ověřené i při apply; hodinový limit zůstal zachovaný. Dřívější bod 42851495227 byl po vypršení nahrazen tímto čerstvým bodem.
- Čerstvý čtecí katalogový balíček [42855976161](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42855976161) a izolovaný nácvik PASS: [příprava](prepare-42855976161.json), [nácvik](rehearsal-42855976161.json). Katalogový import se neopakoval.
- Před úpravou URL [inspect 42869546748](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42869546748) a skutečná obnova všech 501 souborů starého baru PASS: [záloha](url-inspect-42869546748.json), [obnova](url-restore-42869546748.json), shodný SHA-256.
- [Apply 42870089056](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42870089056) [PASS](url-apply-42870089056.json) a nezávislý [status 42870356938](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42870356938) [PASS](url-status-42870356938.json): 44 HTTP kontrol, bez DB zápisů nebo přehrávání původních požadavků. Závěrečný externí GET obou TV aliasů potvrdil správné odlišné cíle.

## Průběh validace a meze

První pouze čtecí [inspect 42864249836](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42864249836) byl [odmítnut před zápisem](stock-inspect-42864249836.json). Původní rozsahový validátor nepřipouštěl záporný počátek. Nový snímek doložil pět takových hodnot; signed přenos je zachoval beze změny. Doplněné fáze/souhrny neobsahují konfiguraci ani text výjimek. Zlomky, null a nadlimitní hodnoty se dále odmítají. Místních 12 testů / 34 assertions, PHPStan celé aplikace, styl a dokumentační kontrakt PASS.

Původní globální kompresní test proti historickému commitu byl upraven na totožná aktuální aktiva před/po gzip, s možností explicitně reprodukovat historický zdroj. Všechny HTTP/cache/byte kontroly zůstaly; skutečný Apache/Chrome profil PASS. První integrační opomenutí nonce a registrace izolovaných TV šablon byla opravena před vydáním, CSP nebyla oslabena.

Vizuální přejímka TV v řízeném prohlížeči **NOT RUN**: otevření karty opakovaně vypršelo; poslední Computer Use pokus skončil politikou, protože nebylo možné spolehlivě určit aktuální URL Windows prohlížeče. Další UI vstupy se neprováděly a nesouvisející snímek nebyl uložen do repozitáře. HTTP a přesná shoda CSS jsou doložené samostatně.

Zbývá skutečný login Lucky, fyzická inventura, počáteční hotovost a první prodej/uzávěrka, reálná karta/QR a vratka a [účetní adaptér](../../BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md). Tyto body vede [provozní handoff](../../SESSION_HANDOFF_BAR_2026-11-06.md); tento převod je neoznačuje za přijaté. Starší WIP původního checkoutu i odděleného pilotu zůstalo zachované.

Konečné souborové předání je ve větvi `codex/bar-display-stock-release-20261110`; samostatný dokumentační PR a jeho stav budou doplněné po vytvoření. Doklady nevyžadují další produkční deploy.
