# P11 katalog — schválené nasazení 7. 11. 2026

**NASAZENO**: #364, release `c70939d45b53c718083f01110d26b6cf12fb35b0`.
[Deploy 42372002993](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42372002993)
SUCCESS; [bezpečný přehled kroků](deployment.json). Vlastník v pokračovacím
zadání výslovně povolil vhodné změny nasadit a pokračovat optimalizacemi.
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md),
[předání](../../SESSION_HANDOFF_CATALOG_READ_PERFORMANCE_2026-11-07.md).

## Schválená cesta a kontroly

#364 se sloučil až po požadovaných kontrolách na aktuálním základu main 599979d4;
head `6fa22b8a`, Testy `42371087187`, obě integrační MariaDB PASS. Převzaté
serverové auditní předání zůstalo zachované. Předchozí zrušené běhy nejsou PASS;
integrační rozpočet zvýšen na 20 minut, žádný test se nevynechal.
Standardní deployment provedl vlastní PHP testy, ověřenou produkční zálohu,
kontroly migrací a zachování dosavadních variabilních symbolů, aktivaci a HTTP
smoke. Import false, UAT flag false, žádná aktivace plateb ani změna rolí.
Žádné přímé zápisy skutečných osob, lekcí, produktů nebo objednávek.

## Veřejný čtecí průchod

[Sonda](snapshot.php.txt), [původní snímek](before-initial.json),
[čerstvý před nasazením](before.json), [po nasazení](after.json).
Pět případů: úvodní katalog, cena, druhá stránka, hledání a prázdný výsledek.
Pouze anonymní sériové HTTPS GET, bez cookies a POST. V každém průchodu 5/5
HTTP 200. Produkty, jejich pořadí a navigace **5/5 shodné**, veřejný aktivní
katalog **222** nabídek. Skutečně používá novou větev nad 64 produkty.
Časy jednotlivých HTTP jsou orientační; není to párový produkční benchmark.

### Meze původního DOM otisku — nepřepisovat na PASS

První verze sondy odstranila `nonce`, ale přehlédla náhodný atribut
`data-app-csp-handler` u obrázků. Proto první [po nasazení](after-v1-inconclusive.json)
nesouhlasil u 3 případů s kartami, zatímco dvě prázdné stránky a navigace
souhlasily. Je to chyba normalizace sondy; nelze z ní odvodit změnu cen.
Původní raw HTML ani session se záměrně neukládaly. Z hashů nelze zpětně
normalizovat tento atribut, úplná DOM parita před/po karet je **NEPRŮKAZNÁ**.
Sonda v2 hlásí tuto mezeru explicitně jako null a porovnává pouze dostupná pole;
neoznačuje původní DOM porovnání za PASS.

Opravená normalizace odstraní oba atributy; dva samostatné následné průchody
([první v2](after-first-v2.json), [opakování](after.json), [kontrola](repeatability.json))
mají **5/5 shodné celé normalizované main i navigaci**. Jde o opakovatelnost
po nasazení, nikoli obnovu původního baseline. Striktní shodu kompletních
katalogových dat samostatně dokládá [480 nativních párů](../catalog-read-performance-20261107/RESULTS.md)
na 10.3/11.4 s ověřenými zdrojovými otisky. Přihlášené produkční UI UAT NOT RUN.

Původní WIP zůstává zachovaný. Další samostatná etapa jsou
[dávkové lekce #367](../../ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md).
