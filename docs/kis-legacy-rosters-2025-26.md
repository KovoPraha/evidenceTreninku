# Soupisky ze starého KIS, sezona 2025/26

Zdroj je výhradně šifrovaný archiv schváleného úplného importu osob ze starého
KIS (`servis.kovopraha.cz/2026-10-20`). Identita se propojuje přes tabulku
`kis_person_aliases` podle původního `id_hash`; shoda jména ani licence se
nepoužívá. Pole `Soupisky` obsahuje 33 doslovných názvů, všechny s označením
2025/2026. Sezona 2026/27 v novém systému je jiná. Samostatný převod do
aktuálních soupisek je popsán v `kis-current-rosters-2026-27.md`.

Stránka `kis_legacy_rosters_admin.php` pro administrátora zobrazí otisk
kontrolního náhledu, po potvrzení atomicky vytvoří uzavřenou historickou
sezonu a 33 uzavřených soupisek. Zcela jednoznačné vazby uloží do
`club_roster_members` se zdrojem `kis_shadow` a stavem `removed`, takže
nevytvářejí aktuální nárok na členskou cenu, plánovaný trénink ani registraci.
Zdroj nezná datum přihlášení a ukončení jednotlivce. Hodnoty `valid_from` a
`valid_to` jsou **hranice označené sezony**, nikoli tvrzení o přesném období
účasti. Doklad o konkrétním řádku zdroje je v tabulce
`kis_legacy_roster_claims`; audit přidání je v `club_roster_events`.

Náhled se znovu počítá z ověřeného archivu a databázových aliasů; rozdílný
otisk zápis zastaví. Jediná transakce a aplikační zámek brání částečnému
importu a duplicitnímu běhu. Případy trenéra bez role hráče, nesouladu ročníku
v názvu skupiny, chybějícího data narození a rozporu sloučených zdrojových
účtů zůstanou v administraci ve stavu `review`. Správce u každé vazby ručně
zvolí potvrzení či ponechání bez vazby a uvede důvod. Stažitelný protokol
obsahuje stav všech nároků. Tento historický krok neposílá zprávy ani nemění účty.

Lokální zkouška nad všemi 1529 původními řádky a přesnou produkční převodní
mapou z 20. 10. 2026: 454 zdrojových řádků se soupiskou, 452 výsledných osob,
653 zdrojových výskytů, 650 unikátních vazeb osoby a soupisky. Návrh přiřadí
560 vazeb a 90 označí ke kontrole (55 ročník, 30 pouze trenér, 4 neznámé
narození, 1 rozporné aliasy). Tato čísla jsou lokální náhled; závazný výsledek
je až protokol živého zápisu.
