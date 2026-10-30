# W8.3 — cílené historické členství rodinného kalendáře

30. 10. 2026, základ `df09eab94ccc6545e57b71a41bc24aa0011850c0`.
[Plán před implementací](../../REFACTORING_W83_2026-10-30.md).
Změna sdílí historickou čtecí projekci s W8.1, nemění autorizaci ani writery.

## Charakterizace a kontrakt

[RED](red-budget.txt): 208 členských dotazů pro rok místo jedné dávky.
Po čerstvém `familyPortalAuthorizedPeople` se načítají jen explicitní ID
dané rodiny, nejvýše 400 na dávku. Stavové predikáty zůstávají v SQL kvůli
kolacím/NULL. Hrany jednou; obrácený průchod pro datum zachovává cykly,
více rodičů, odstraněné historické členství a inkluzivní konec platnosti.

Feed záměrně nepoužívá dolní hranici intervalu pro historické členství:
dlouhá akce může začít před požadovaným obdobím. Test dokládá účast podle
členství z roku 2025 i při feedu pro leden 2026. Timeline nadále používá
přesný roční interval. Žádný cache objekt mezi voláními/PDO, žádná změna
tokenů, registrací, účasti, finančních položek ani odesílání týdenních zpráv.

Kontrakty: přesná shoda polí i ICS, pořadí/UID, cizí data nepřítomna,
čerstvá revokace a neověřený účet, rotace tokenů a týdenní preview z původní
sady. Nově hranice dávek 0/1/400/401 a historické konce; původní timeline
kontrakty se opakují. [Nativní MariaDB](native-contracts.txt): 11 testů /
94 assertions včetně timeline. Dodatečný legacy test zachovává i explicitně
autorizované ID 0, které původní feed neodmítal; roční timeline má nadále
svou původní podmínku kladného ID.

## Migrované měření

[Profil](calendar-profile.json): celý migrační katalog, FK aktivní,
20 týmů a 1000 syntetických členů cizích rodin. Matice 0/1/12/52 týdnů
× 0/1/5/20 osob. Čas/paměť bez SQL instrumentace, jeden warmup + pět
střídavých vzorků pro 1/20 osob; pro 0/5 se čas netvrdí.

| Rok | SQL před → po | Členské SQL | Členské řádky | Medián ms | Špička paměti B |
| --- | --- | --- | --- | --- | --- |
| 1 osoba | 4199 → 39 | 1041 → 1 | 102000 → 1 | 3870,71 → 74,35 | 5618848 → 1349472 |
| 20 osob | 4199 → 39 | 1041 → 1 | 102000 → 20 | 5519,95 → 302,41 | 7768864 → 3556352 |

Výchozí rodinná aktivita existuje i při nule přidaných týdnů. Lokální
MariaDB 10.4.32; ostatní validační procesy mohou ovlivnit absolutní časy.
Nejde o produkční latenci. Brány jsou shoda, nejvýše `ceil(osoby/400)`
členských dotazů, nejvýše počet rodinných řádků ve fixture, žádný růst SQL
a paměť nejvýše baseline +64 KiB. Prázdná rodina nenačítá členství.

Dodatečných 1000 historických intervalů: shodný výstup, špička 4 032 320 B
pod samostatnou hranicí 8 MiB. Historie je nutná pro staré začátky akcí.
[Regrese timeline](timeline-regression-profile.json): roční SQL stále
4176 → 16, členské 2082 → 2, stejná data, špička 1 327 720 → 1 282 144 B.
Sdílení projekce tedy nezrušilo výkonový přínos W8.1.

## Kontroly a hranice

[AST](feed-ast.txt) proti [snapshotu před formátováním](feed-before-format.txt)
potvrzuje zachování ostatního kódu/SQL při nutném formátování. Funkční
změnu samostatně pokrývá zmrazený původní feed a chování na obou DB.
[PHPStan](phpstan.txt), style/YAML PASS; baseline neroste.
[Plná lokální sada](phpunit-windows.txt): 1836 testů / 36278 assertions,
3 POSIX skips. CI/release se doplní po ověření přesného commitu.
CI opakuje kalendář i timeline na 10.3/11.4.

Reprodukce: `php tests/Support/FamilyCalendarReadProfile.php`,
`php tests/Support/AthleteTimelineProfile.php`; vlastní lokální port přes
`EVIDENCE_MARIADB_SMOKE_PORT=33426`, v CI 3306. Kontrakty mají přepínače
`EVIDENCE_CALENDAR_READ_MARIADB=1` a `EVIDENCE_TIMELINE_MARIADB=1`.
Všechny náhodné vlastní DB se po skončení uklidí.

W8.2 a W8.3 lze vydat společně po úspěšných PR a přesném výsledném main.
Další sportovní/provozní kandidáti a W9–W15 nejsou tímto balíkem uzavřeni.
Lidské UAT a skutečné importy/integrace zůstávají samostatné.
