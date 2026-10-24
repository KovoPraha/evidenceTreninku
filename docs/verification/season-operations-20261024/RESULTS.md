# Balík D — Sezona, komunikace a náhrada starých postupů

24. 10. 2026. Lokální implementace v `evidencePavel-finance-b1-20261022`,
větev `codex/season-operations-transition-20261024`, navazuje na balík C
(`964d73da`). Dokončen rozsah nezávislý na klubových rozhodnutích.
Žádný deploy, push, přístup do produkce, skutečný import, přesměrování,
odstavení služby ani vnější odeslání. E-shop a jeho závěrečná přejímka nebyly
součástí práce. Není potřeba nová databázová migrace.

## Co může obsluha dokončit

### Příprava dalšího období

Nová položka **Příprava dalšího období** patří registráři a je dostupná také
vedoucímu sportu. Pracuje se současným modelem trvalých skupin i s historickými
sezonními soupiskami. Výběr skupiny a dne ukáže osoby pokračující, přibývající
a končící podle platných intervalů. Jména lze hledat a seznam stránkovat.
Jde o zařazení ve skupině, nikoli o rozhodnutí o členství v oddílu.

Průvodce zobrazuje týmová svěření trenérům k začátku vybraného dne a navazující
nezrušené lekce na dalších 90 dní. Vazba podskupiny se vyhodnocuje ke dni
každé lekce: budoucí připojení ani dřívější odpojení se nezamění za dnešní stav.
Stejná lekce s více vazbami se neopakuje. Svěření nadřazené skupině není tvrzením
o oprávnění ke všem podskupinám. K osobě, zdravotním podkladům, lekci a správě
oprávnění vedou odkazy podle pracovní role.

Historická roční obnova používá existující kanonický náhled, pravidla a výjimky.
Průvodce sám nic nepřevádí. Potvrzení zůstává v původním formuláři registráře
s novým náhledem, důvodem a kontrolou změn. Trvalé skupiny roční obnovu nepotřebují.
Zařazení s pravidlem nového přihlášení zůstává k samostatnému vyřízení; žádný
nákup, platba nebo placená účast nevzniká z pouhého pokračování soupisky.

### Komunikace a osobní kontakt

Opravena skutečná mezera: výběr a výpočet příjemců zprávy dříve vyžadovaly sezonu,
takže vynechávaly trvalé skupiny. Nyní používají časově platnou hierarchii a
zařazení. Jeden rodičovský účet dostane jednu zprávu, i když zastupuje více dětí
nebo osoba patří do více podskupin. Oprávnění musí platit v okamžiku dnešního
náhledu a zpracování; vztah končící dříve téhož dne ani začínající později nestačí.

Náhled jmenuje osoby bez platného ověřeného elektronického kontaktu a odkazuje
na osobní vyřízení registrářem. Neprohlašuje je za nedoručené zprávy. Změna osob,
kontaktů nebo oprávnění po náhledu zneplatní potvrzení. Před samotným zpracováním
zůstává původní kontrola aktuálního příjemce. Rozepsaný předmět a text se po chybě
neztrácejí. Nebyla zapnuta žádná automatická rozesílka.

Nový **Stav komunikace** ukazuje pouze souhrnné počty, nejstarší čekající zápis,
poslední uložený údaj o zpracování a zpracování starší než 15 minut. Nepřepíná
stavy ani neopakuje pokusy. Chybějící evidence není prezentována jako prázdná fronta.
Správce zde nezískává obsah zpráv ani kontakty: ty patří registráři u zpráv
skupinám a obsluze účtů u rodinných souhrnů.

Historie obou front má hledání, stav, období a stránkování místo posledních
100 záznamů. Horní datum zahrnuje celý den. Náhled rodinného souhrnu zachovává
filtr i stránku. Stav zprávy skupině se čte ze skutečných stavů příjemců,
protože zadržení příjemce nemuselo změnit původní hlavičku `queued`.
Nesouhlas počtu příjemců se označí k prověření, nikoli jako doručení.

Odstraněno nepodložené tvrzení, že produkční zprávy odesílá plánovaný worker.
Přehled rozlišuje pozorované omezení PHP `mail`, stav evidence a neověřený
plánovač/transport. `sent` může znamenat místní outbox, nikoli doručení do schránky.
Skutečná konfigurace produkčního odesílání nebyla v této práci zjišťována.

### Mapa přechodu

Rozšířen existující registr **Příprava přechodu**, bez druhé migrační agendy.
Položka agendy nově uchová původní obrazovku či papírový seznam, cílový postup,
rozsah historie a stav ověření mapy. Zachovává zdroj, konkrétního vlastníka,
jediný navržený zapisující systém, hranici přechodu, starý režim, náhradní postup
a doložení. Staré položky bez nových polí zůstávají čitelné jako nedoplněné návrhy.

Deset návrhů neobchodních postupů předvyplní formulář, ale nezapíše záznam,
neurčí konkrétního vlastníka ani termín. Pokud mapa už existuje, otevře její
uloženou verzi místo přepsání výchozím návrhem. Zdroj i odpovědný pracovník se
volí podle názvu. Cílový postup je omezen na známé aplikační stránky.

Oprava načte uložené údaje; historie zachovává starší verze, autora, datum,
důvod a ověřený otisk. Souběžná změna odmítne zápis a ponechá rozepsaný návrh
i jeho původní revizi. Pouhé opětovné odeslání starého formuláře nemůže přepsat
novější stav. Vyhledávání příprav už není omezené na posledních 100 příprav.

Konkrétní pracovní mapa je v [MAPA_PRECHODU.md](MAPA_PRECHODU.md).
Označení mapy za ověřenou je záznam obsluhy; není souhlas s produkčním přechodem.
Žádný skutečný zdroj nebyl automaticky založen či převeden.

## Ověření a jeho hranice

Probe potvrdil vlastnictví izolovaného prostředí, skutečný datadir MariaDB,
327 čitelných tabulek a blokované vnější transporty. Web běží na
`http://127.0.0.1:18086`, DB na `127.0.0.1:33426` se schématem `evidence_b1`.
HTTP přejímka odmítá jinou konfiguraci, jiného vlastníka checkoutu nebo jiný
datadir. Vkládá pouze nové syntetické záznamy `LOCALHOST NEPLATIT` a adresy
`example.invalid`. Pracovníci ani skutečná data se nepřebírají z produkce.

| Kontrola | Výsledek | Důkaz |
|---|---|---|
| Nové postupy, soupisky, časová hierarchie, roční obnova, fronty, role, případy a osobní návaznosti | **75 testů / 2166 assertions PASS** | [PHPUnit](phpunit.txt) |
| Skutečné místní HTTP formuláře, přístupy, souběh verzí a stránkování | **41 scénářů PASS** | [HTTP](http-acceptance.json) |
| PHP syntax nových a změněných zdrojů/testů | **14 souborů PASS** | Lokální `php -l` |
| Formát změn | **PASS** | `git diff --check` |

Příkaz pro automatické regresní testy:

```powershell
& C:/xampp/php/php.exe vendor/phpunit/phpunit/phpunit --filter 'SeasonOperationsTest|RosterMessageTest|KisRosterTest|KisRosterRolloverExecutionTest|RosterHierarchyTemporalTest|FamilyWeeklyDeliveryTest|ClubCutoverTest|StaffWorkspaceRegistryTest|PersonLifeDocumentsTest|OperationalCasesTest' --colors=never
& C:/xampp/php/php.exe tests/Support/SeasonOperationsHttpAcceptance.php
```

Nové scénáře ověřují také čtení bez změny doménových dat, odmítnutí POST v
průvodcích, odebrané role, neplatné datum, deduplikaci rodiče, zánik oprávnění
po náhledu, zadržení bez zavolání mock odesílače, uchování návrhu po konfliktu,
CSRF, oddělení obsahu front a zobrazení historických položek.

Neproběhla vizuální přejímka v prohlížeči ani uživatelské převzetí pracovníky
oddílu. PASS není důkaz aktivního produkčního plánovače, doručeného e-mailu,
úplnosti původní Evidence/KIS ani správnosti skutečné migrační mapy.

## Odložené podklady

- D03/D17: skutečné podmínky pokračování a účinnosti členství.
- D07/D10/D11: konkrétní staré obrazovky, sporné identity a skupiny, veřejné
  adresy, doložená historie a pravidla archivu.
- D12: jmenovaný vlastník a zástupce komunikace, četnost běhů, transport,
  povolený přejímací kontakt a postup při nejasném doručení.
- D13–D16: konkrétní obsluha každé agendy, datum a rozsah přepnutí, pilot,
  obnova, schválení skutečného převodu a ukončení starých služeb.

Zbývá potvrdit tyto podklady a později provést balík E — společnou přejímku
včetně e-shopu. Implementace tohoto balíku nic z uvedeného sama neschvaluje.
