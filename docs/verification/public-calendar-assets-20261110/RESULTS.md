# P11-ASSET5 — veřejný kalendář a velodrom

10. 11. 2026. ROZPRACOVÁNO, base37f1ba8f / runtime94c10708.
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset5--veřejný-kalendář-a-velodrom-10-11-2026).

Živý read-only Chrome158 [before](live-before.json): kalendář individuálních
lekcí i velodrom desktop/mobile mají Bootstrap CSS2 a encoded payload53957 B
(CDN22709 + lokální31248); klubový kalendář control1/31248 B. Všech6 GET200,
bez JS/network chyby, pouze oddělená volitelná favicon404. Nejde o časový benchmark.
Celé HTML otisky zachovávají skripty, data i všech5 obsahových verzí; maskuje se
pouze přesný jeden CDNtag a request nonce/CSRF, žádný obecný whitespace.

[Přesný source kontrakt](local-source-contract.json) PASS: z immutable Git37f1ba8f
odstraněn jediný známý CDNtag na každé stránce. Veškeré další PHP tokeny a celý
emisní HTML/JS text jsou přesně shodné, kromě16 vyjmenovaných vložených guard
závorek kalendáře (8 párů). Dlouhé šablonové řádky jsou rozdělené nulovými PHP
hranicemi, které nic nevypisují; nejde o změnu normalizace skutečného HTML.
Jednotkový negativní kontrakt:1 test/12 assertions PASS; odlišná cena/čas/kapacita/
skript nesmí projít, chybný SRI/verze/počet kopií se neodstraňuje.

Vlastní fixture přidá syntetická veřejná sportoviště a oddělené lesson/velodrome
termíny pouze v ověřeném disposable schématu. MariaDB10.3/11.4 wholeHTTP,
checksumy, anonymní POSTdenials, skutečný Apache/Chrome/cold/warm/pixels,
CI a release zatím NOT RUN. Původní WIP7a7afd75 zachovaný.


ASSET5 checkpoint / PR #439: první přísný HTTP diff byl4 mezery za inline SEO.
PHP uzavírající značka spotřebuje zdrojový newline; porovnávač proto nyní
odstraňuje přesně4 zdrojové mezery a jeden newline patřící jedinému známému
CDNtagu, bez obecné whitespace normalizace. Regresní unit1/13 PASS. Nový živý
before používá stejný přesný postup; skripty/data/verze assetů zůstávají celé.
Native42851391494 na38f6e9fb prošlo na obou DB včetně Chrome. Následně do main
přibylo povolené #436 /4e3df207; zachované oba souběžné plány a nové ověření
se zopakuje na tomto společném základu. Společný produkční běh jiného řezu
42852365591 běží na4e3df207; neslučovat další cizí větev ani ho nepřehrávat.
Dosavadní aplikační diff tohoto PR proti main jsou jen dva zvolené kalendáře.
