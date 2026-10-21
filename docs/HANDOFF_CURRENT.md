# Aktuální předání vývoje na další stanici

Nasazeno 21. 10. 2026: stavy, oprava akcí Zásilkovny a verzovaný editor šesti
systémových e-mailů z [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156).
Aplikační SHA `574328421fff748b48f7d744a3ce454b9b50dd1e`,
[deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
je zelený: záloha 236 tabulek / 2 triggerů, 110 aktuálních migrací a HTTP 200.
PHPUnit 1 178/26 530, CI MariaDB 10.3/11.4 a CodeQL prošly. Živě ověřeny
systémové stavy, editor a syntetický náhled bez uložení a odeslání, seznam
objednávek a fronta. Starší zpráva zachovala obsah; konečná fronta 3 pending,
0 pokusů a 0 sent. Dvě další zprávy jsou z nezávislého testu před aktivací.
Simulace Zásilkovny vypnuta; ostrá doprava také zůstává neaktivní, protože
chybí cena a veřejný klíč. Žádné objednávky ani produkční šablony jsme při
této kontrole neměnili, worker nespouštěli. Fakturace je odložená.
[Plán](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md) a
[produkční report](ORDER_STATUS_EMAIL_RELEASE_2026-10-21.md) jsou aktuální
autoritou; starší zákazy push/deploy a průběžné počty níže jsou historie.
Dokumentační větev `codex/order-status-release-proof-20261021` nepřidává
aplikační změny a nevyžaduje nové nasazení; vývojový worktree zůstává stejný.

Lokální výsledek 21. 10. 2026: na větvi
`codex/order-status-system-mail-20261021` je připravena oprava Zásilkovny,
oddělené zobrazení systémového a zákaznického stavu a editor šesti existujících
systémových e-mailů s náhledem, historií a verzí uloženou ve frontě.
[Plán a výsledky](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md#úplný-lokální-browser-průchod-21-10-2026)
obsahují dokončený browser nákup/výdej osobního odběru i Z-BOXu, lokální B09
PASS a přesnou kontrolu pěti zpráv ve frontě (5 pending, 0 pokusů, 0 odesláno).
Sklad 10→9→7, oba koncové stavy, odstranění podacího kódu a zachování starší
zprávy po editaci šablony ověřeny. Nová úplná PHPUnit sada: 1 169/26 302, OK;
předchozí MariaDB souběh editorů, záloha/obnova 188 tabulek a 109 migrací platí.
Výběr boxu, štítek, platby a fyzické předání byly syntetické lokální simulace;
reálný dopravce ani doručení pošty tím nejsou ověřeny. Režim Z-BOX demo vyžaduje
výslovný příznak v ignorovaném configu a striktní loopback všech adres.
Pracovní složka: `C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Žádný push, merge, deploy ani skutečná rozesílka. Worker nespouštěn; čekající
testovací zprávy nepředávat skutečnému mail transportu. Fakturace je odložená.
Implementační commity jsou `55f43e68` a `690fefd8`. Plán eviduje i navazující
dokumentační PR #154 na `origin/main`, který zatím není do pracovní větve převzat.

## Pravidlo pro větší změny a pokračování po kompaktaci

Na výslovný pokyn vlastníka z 21. 10. 2026 ukládej před větší implementací
konkrétní plán do samostatného souboru v `docs/` a odkazuj na něj z tohoto
handoffu. Průběžně zapisuj rozsah autorizace, pracovní větev/složku,
dokončené kroky, výsledky testů, zbývající práci a přesný další krok.
Před kompaktací nebo předáním stav obnov; chat nesmí být jediným úložištěm
plánu. Po pokračování nejdřív přečti plán a aktuální stav souborů.

Lokální práce 21. 10. 2026: oprava Zásilkovny, přehled stavů a editor
systémových e-mailů se připravují na větvi
`codex/order-status-system-mail-20261021` v odděleném worktree
`C:\xampp\htdocs\evidencePavel-order-status-system-mail-20261021`.
Podrobný rozsah, průběžné úkoly a odložená rozhodnutí jsou v
[plánu stavů a e-mailů](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Vlastník schválil pouze lokální přípravu a testování; žádný push, merge,
deploy ani skutečná rozesílka. Fakturace je výslovně odložená.


Nejnovější předání: 21. 10. 2026, názvy registračních textů z
[PR #152](https://github.com/KovoPraha/evidenceTreninku/pull/152). Nasazený aplikační
SHA je `f5d6276838239ace6426aa8c326c01a213730a36`,
[deploy 39593524611](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39593524611)
prošel zálohou 234 tabulek / dvou triggerů, 108 aktuálními migracemi a HTTP smoke.
Registrář může upravit **Název souhlasu / informace** a zveřejnit i změnu samotného
názvu. Název se verzovaně ukládá s textem; nové nadpisy používají přihlášky
i historie. Původní potvrzení zachovávají vazbu na původní dokument a název.
Živě bylo ověřeno nové pole na `registration_texts_admin.php?purpose=member_data_notice`;
produkční názvy a texty se při kontrole neměnily. Podrobnosti a návod jsou v
[reportu názvů souhlasů](REGISTRATION_TEXT_TITLES_2026-10-21.md). Plné UAT zůstává otevřené.

Nejnovější předání: 21. 10. 2026, hotovostní platby objednávek z [PR #150](https://github.com/KovoPraha/evidenceTreninku/pull/150).
Nasazený aplikační SHA je `f813264b16ffd49224bd41a908adc33d9687af88`;
[deploy 39590481900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39590481900)
prošel ověřenou zálohou, 107 aktuálními migracemi, aktivací a HTTP 200.
Hospodář může současně změnit převod na hotovost a přijmout celou platbu;
objednávka má audit, navazující aktivace a podporuje ruční vratku.
CI: 1 155 testů / 26 040 kontrol, MariaDB 10.3/11.4 a CodeQL.
Postup a hranice ověření jsou v [předání plateb](ORDER_PAYMENTS_2026-10-21.md).
Přihlášená kontrola formuláře byla 21. 10. dokončena: role, přechod na správnou
platbu, volba hotovosti, povinná pole, CSRF a čistá konzole. Platební formulář
nebyl odeslán; následná kontrola potvrdila nezměněný řádek objednávky.
Podrobné ověření proběhlo na navazujícím release `f5d6276`; původní pracovní
pozice byla obnovena. Skutečné ani testovací platby nebyly potvrzovány.
`uat_schvaleno=false`, `importovat_krouzky=false`. Následující předání jsou historická.

Nejnovější předání: 21. 10. 2026, správa registračních textů a souhlasů z PR
[#148](https://github.com/KovoPraha/evidenceTreninku/pull/148). Nasazený aplikační
SHA je `3c64dc530fa8025854f2cc8be07e74bdc1a51aab`,
[deploy 39588673226](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39588673226)
prošel zálohou, 107 aktuálními migracemi a HTTP smoke. Registrář má v běžném
menu **Členové → Registrační texty a souhlasy**: neměnné koncepty, samostatné
zveřejnění, historie a audit autora/času/důvodu. Dosavadní přihláškové snapshoty
i schválené veřejné texty zůstaly zachované. Živý prohlížeč načetl všech pět
typů textů; publikační zápisy se ověřily v odděleném localhostu. Podrobný
[report a návod](REGISTRATION_TEXTS_ADMIN_2026-10-21.md) obsahuje CI, produkční
důkazy a hranice ověření. Plné UAT aplikace zůstává otevřené.

Nejnovější předání: 21. 10. 2026, oprava rychlé přihlášky z PR
[#146](https://github.com/KovoPraha/evidenceTreninku/pull/146). Nasazený aplikační
SHA je `e935950ed5be7181687dadba4b1fc2daf8dd5095`,
[deploy 39585582515](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585582515)
prošel. Formulář `booking/rychla_prihlaska.php` volal
`shopBankSettingsEffective()` bez načtení její definice a po odeslání zobrazil
obecnou chybu ukládání. Explicitní načtení `includes/shop_bank_settings.php`
je doplněno do rychlé přihlášky i rychlého nákupu bez účtu.
Oba nové testy samostatného načtení před opravou selhaly; po opravě prošlo
1 140 testů / 25 706 kontrol, MariaDB 10.3/11.4 a CodeQL.
[CI výsledného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39585403375)
je zelené. Deploy ověřil zálohu 233 tabulek, 106 migrací bez čekajících kroků,
aktivaci a HTTP smoke. Po aktivaci byla přihláška Aligátoři
`product_id=247&variant_id=813` načtena v prohlížeči; přihláška, detail produktu,
e-shop a homepage vracejí HTTP 200 bez PHP fatální chyby. Produkční přihláška
ani objednávka nebyla v rámci ověření vytvořena. `uat_schvaleno=false`,
`importovat_krouzky=false`; další plné UAT zůstává samostatné.
Následující předání jsou historické body.

Nejnovější předání: 20. 10. 2026, zbývající administrační formuláře z PR [#144](https://github.com/KovoPraha/evidenceTreninku/pull/144). Nasazený aplikační SHA je `246dee2a4df5dc3152c1a769a9af128b87a91092`, [deploy 39496342888](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39496342888) i živé ověření prošly. Navazujte podle [reportu formulářů](REMAINING_ADMIN_FORMS_2026-10-20.md): společné styly starších editorů, zelené ukládání, funkční popisky, kořenové cesty hlavičky a zachování rozepsaného tréninku při chybějící soupisce. Lokálně 1 138 testů / 25 708 kontrol; 105 adres v osmi rolích, syntetické zápisy, mobil a tmavý režim. Produkčně osm stránek a přepnutí pracovní pozice z vnořeného formuláře. B30 zůstává PARTIAL, plné UAT není schválené. Následující předání jsou historické body.

Nejnovější doplnění: 20. 10. 2026, sjednocená administrace a správa e-shopu z PR #140–142. Release `175f92c7a59294acff7e15fe09cbed258707fa2e` má úspěšný [deploy 39483597831](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39483597831) a produkční browser ověření variant, cen, skladu, archivace konceptu a společného vzhledu. Navazujte podle [produkčního reportu](UNIFIED_ADMIN_SHOP_PRODUCTION_2026-10-20.md), který uvádí také ponechanou skrytou testovací variantu se skladem 0 a archivovaný testovací koncept. Plné UAT zůstává samostatně otevřené. Níže uvedené starší SHA jsou historické body svých částí.

Produkční doplnění: 20. 10. 2026, Europe/Prague. Hierarchie skupin a soupisek,
časové členství, sjednocené cílení tréninků a akcí, opakované nabídky kroužků
a rozdělení rodinných bankovních plateb jsou nasazené z PR
[#138](https://github.com/KovoPraha/evidenceTreninku/pull/138). Přesný aplikační
SHA je `4991c91f966eb65852876fd0084dd6ad041e3b69`;
[deploy 39472709660](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472709660)
prošel zálohou, 106 migracemi bez čekajících kroků, aktivací, ochranou
soukromého klíče a HTTP smoke testem. [CI 39472026706](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39472026706)
ověřilo PHPUnit i MariaDB 10.3/11.4. Živá stránka správy hierarchie se načetla
v roli Registrář. První pokus o deploy skončil po aktivaci na síťovém timeoutu
runneru; druhý běh stejného SHA dokončil celý protokol.

**Další krok je datové zařazení, nikoli opakovaný import osob:** 43 živých
soupisek zatím nemá skupinu a read-only mapování starých skupin/podskupin
ukazuje 0 přesných shod a 46 položek bez vazby. V administraci je třeba
založit cílové skupiny, ověřit každé zařazení a použít náhled dopadu;
historické tréninky a přihlášky se nepřepisují. Generátor nových nabídek,
automatické bankovní párování a plné UAT zůstávají vypnuté.
Podrobný postup je v
[předání hierarchie](ROSTER_HIERARCHY_IMPLEMENTATION_HANDOFF_2026-10-20.md).

Následující doplnění je historický stav před PR #138.

Produkční doplnění: 20. 10. 2026, Europe/Prague

Aktuální důkazy nasazení a prohlížečového ověření shrnuje
[PARENT_JOURNEY_PRODUCTION_2026-10-20.md](PARENT_JOURNEY_PRODUCTION_2026-10-20.md).
Rodičovská cesta z PR #91 prošla nasazením a ověřením 94 migrací. Poslední
aplikační release včetně opravy příslušenství z PR #92 je
`fd6ccc1584beb3a3266e8147497263cb8a38044f`:
[deploy 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
a [CI 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
prošly. Lokální implementační a akceptační podrobnosti
zůstávají v [původním předání](PARENT_JOURNEY_2026-10-20.md).

Produkční prohlížeč potvrdil přihlášení testovacího rodiče, existující rodinu
a stránku Moje kroužky. **Úplná cesta nové registrace až do soupisky zatím
ověřena není.** Novou žádost o dítě blokuje chybějící platná informace
o zpracování osobních údajů. Všech 52 zkontrolovaných účastnických variant
je veřejně viditelných, ale čeká na propojení; přesné produktové identifikátory
jsou v novém reportu. Existujících 26 programů a 74 nabídek se soupiskami
není třeba plošně zakládat znovu: všech 74 nabídek hlásí chybějící platný
souhlas se storno podmínkami
a všude je nyní neomezená kapacita. Je třeba rozhodnout skutečné vazby
a nastavení, včetně rozdílných cen Pumptracku a termínů Točňáků / Kunratic.

Fio, Stripe a SumUp se nezapínaly. Skutečné produktové vazby nebyly měněny.
Lokální sada po opravě příslušenství v PR #92 prošla:
**1 019 testů / 23 532 kontrol**. Doručení e-mailu ani skutečná platba
se tím nepotvrzují. Po posledním deployi jsou Trička a Uskladnění správně
bez blokace na program; Aligátoři zůstávají blokováni kvůli chybějícím vazbám.
Syntetický testovací program má připravenou nabídku, ale veřejná prezentace
TEST programu je koncept; rodičovský katalog jej nenabízí. Pozitivní produkční nákup proto
nebyl dokončen.

Níže je **historický bod předání z 11. 10. 2026**. Jeho SHA, počty testů,
produkční UAT a stav nasazení nejsou aktuálním důkazem současného provozu.

Tento dokument je stabilní vstupní bod pro další počítač nebo vývojáře.
Autoritativní zdroj je vždy vzdálená větev `origin/main` repozitáře
<https://github.com/KovoPraha/evidenceTreninku>. SHA uvedené níže je kontrolní
snapshot z data aktualizace, nikoli náhrada za nový `git fetch`.

## Ověřený bod předání

| Položka | Hodnota |
|---|---|
| Větev | `main`; přesné aktuální SHA zjistí nový clone pomocí `git rev-parse origin/main` |
| `origin/main` při kontrole | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Nasazený aplikační commit | `16b7d4d60883f3f4f655c097339339c68c1be07d` |
| Otevřené pull requesty | žádné při kontrole 11. 10. 2026 |
| CI aktuálního `main` | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CI nasazeného aplikačního commitu | [běh 37886667244](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667244), úspěch |
| CodeQL nasazeného commitu | [běh 37886667406](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886667406), Actions a JavaScript/TypeScript úspěch |
| Produkční deploy | [běh 37886677875](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37886677875), úspěch, `uat_schvaleno=true` |
| Obnovení UAT účtů a dat | [běh 38093332413](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093332413), úspěch, okno do 12. 10. 2026 20:00 |
| UAT připravenost | [běh 38093377833](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093377833), `ready=true`, 10/10 oblastí |
| Produkční invarianty | [běh 38093408270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/38093408270), 16/16 bez porušení |
| Obnova zálohy | [běh 37701691548](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37701691548), 195 tabulek a 2 triggery |
| Produkční fronta zpráv | [běh 37606107788](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37606107788), úspěch |
| CodeQL default setup | [běh 37764312992](https://github.com/KovoPraha/evidenceTreninku/actions/runs/37764312992), Actions a JavaScript/TypeScript bez otevřeného nálezu |
| Produkce | <https://kis.kovopraha.cz/> |

UX navigace, kalendářů, e-shopu a pracovních přehledů je popsána v
[`UX-NAVIGACE-A-KALENDARE-2026-10-08.md`](UX-NAVIGACE-A-KALENDARE-2026-10-08.md).
Nová stanice musí před další změnou ověřit aktuální SHA a poslední produkční
běh; stavové hodnoty v tomto dokumentu se po každém release aktualizují
samostatným dokumentačním commitem.

CI nad aplikačním commitem ověřilo 877 testů / 11 995 kontrol a MariaDB 10.3 i
11.4. Produkce běží na tomto commitu; dokumentace může být na `main` novější.
Po deployi prošla z prohlížeče domovská stránka, e-shop, veřejný rozvrh,
klubový kalendář, velodrom a přihlášení bez fatálních chyb a bez chyb či
varování v konzoli. Databázové invarianty jsou zelené. Poslední deploy má
`uat_schvaleno=true`; nové časově omezené UAT okno bylo schváleno a ověřeno do
12. 10. 2026 20:00 Europe/Prague. Po jeho skončení je nutné testovací účty a
data deaktivovat auditovaným workflow.

Automatický Fio import je dočasně vypnutý. Workflow zůstává jen ručně
spustitelné a produkční `FIO_IMPORT_ENABLED` má zůstat `false`, dokud vlastník
znovu neschválí token, účet a provozní dohled.

## Nová stanice: čisté převzetí

```powershell
cd C:\xampp\htdocs
git clone https://github.com/KovoPraha/evidenceTreninku.git evidencePavel
cd evidencePavel
git fetch --prune origin
git switch main
git pull --ff-only origin main
git rev-parse HEAD
git rev-parse origin/main
```

Poslední dva příkazy musí vrátit stejné SHA. Potom spusťte
`PRIPRAVIT_LOCALHOST_TESTOVANI.cmd`. Skript připraví ignorovaný `config.php`,
oddělenou lokální MariaDB na portu 3308, migrace a syntetická demo data. Pro
další starty slouží `START_LOCALHOST_TESTOVANI.cmd`.

## Existující clone: bezpečná synchronizace

Nejdřív zjistěte, zda stanice neobsahuje rozpracovanou práci:

```powershell
git status --short --branch
git fetch --prune origin
git branch --show-current
git rev-list --left-right --count HEAD...origin/main
```

Pokud je pracovní strom čistý a lokální `main` nemá vlastní commity:

```powershell
git switch main
git pull --ff-only origin main
```

Pokud jsou přítomné lokální změny nebo vlastní commity, nepoužívejte force,
reset ani přepis souborů. Nejdřív práci uložte do samostatné větve a teprve
potom synchronizujte `main`. Cizí rozpracované změny se nesmí přidat do nového
commitu jen proto, že leží ve stejném adresáři.

## Zahájení nové práce

```powershell
git fetch --prune origin
git switch -c codex/kratky-popis origin/main
```

Před commitem zkontrolujte změněné soubory a spusťte přiměřené testy. Úplná
základní brána je:

```powershell
composer install
composer validate --strict
composer test
$env:APP_HOST='localhost'
php bin/migrate.php --check --json
git diff --check
```

Nová migrace musí být nový neměnný soubor v `migrations/`. Již přijatá migrace
se neupravuje. Pokud přidává tabulku vlastněnou aplikací, musí se současně
aktualizovat zálohovací ownership kontrakt v `bin/db-backup.php` a jeho testy.

## GitHub pracovní postup

`main` má vynucené aktuální tři CI kontroly, strict aktualizaci větve,
zamítnutí zastaralých review a pravidla platí i pro administrátora. Povinný
počet schválení zůstává dočasně `0`, protože repozitář i organizace mají pouze
jediného člověka s přístupem (`mmmixamarek-source`); hodnota `1` by znemožnila
schválit vlastní pull request. Jakmile bude přidán druhý důvěryhodný reviewer,
zvyšte počet na `1` a zapněte stejného reviewera také pro prostředí
`production`.

1. Pushnout pouze vlastní pracovní větev.
2. Otevřít pull request do `main`.
3. Počkat na PHPUnit a MariaDB 10.3/11.4 kontroly.
4. Před merge znovu ověřit, zda se `origin/main` mezitím neposunul.
5. Sloučit až po zelených kontrolách.
6. Po merge provést `git fetch --prune origin` a ověřit výsledný SHA.

Push ani merge není produkční nasazení. Produkční workflow se spouští ručně a
jen po výslovném rozhodnutí podle [`NASAZENI.md`](NASAZENI.md).

## Co není v GitHubu

- `config.php` a jakákoli tajemství;
- lokální nebo produkční databáze a obnovovací dumpy;
- skutečné osobní údaje, importy a přílohy;
- lokální outboxy, session a cache;
- `vendor/`, který obnoví Composer podle `composer.lock`.

Tyto výjimky jsou bezpečnostní hranice, nikoli chybějící část synchronizace.

## Kde hledat aktuální informace

- stav GitHubu, produkce a posledních oprav: [`CURRENT_STATE.md`](CURRENT_STATE.md);
- dokumentační rozcestník: [`README.md`](README.md);
- lokální instalace: [`localhost-testovani.md`](localhost-testovani.md);
- produkční nasazení: [`NASAZENI.md`](NASAZENI.md);
- produkční UAT: [`PRODUKCNI-UZIVATELSKE-TESTOVANI.md`](PRODUKCNI-UZIVATELSKE-TESTOVANI.md);
- pravidla migrací: [`../migrations/README.md`](../migrations/README.md).

Datované dokumenty jako `HANDOFF_2026-08-29.md`, auditní zprávy a implementační
prompty jsou historické záznamy. Při rozporu nemají přednost před aktuálním
kódem, `origin/main`, CI ani tímto dokumentem.
