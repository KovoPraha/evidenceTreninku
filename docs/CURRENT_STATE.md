# Aktuální stav projektu pro AI a vlastníka

Aktualizováno: 5. 8. 2026, Europe/Prague

Tento soubor je krátký vstupní rozcestník. Přesný historický ledger a poslední
důkazy jsou v `docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md`; produktová
autorita M2 je `10-milnik-m2-provozni-pilot.md`.

## Identita projektu

Samostatná aplikace Evidence tréninků + e-shop + KIS v
`C:\xampp\htdocs\evidencePavel`. Není submodulem Velocoty. Případné budoucí
sdílení uživatelů je oddělené rozhodnutí, nikoli současná závislost.

Evidence, e-shop a KIS nejsou tři nasazené aplikace. Jde o jednu aplikaci, jeden
webroot, databázi, migrační katalog, session a kanonickou tabulku `sportovci`.
Názvy modulů zachovávají historické zadání a funkční orientaci v obrazovkách.

## Poslední přijatý technický stav

- pracovní řez sjednocení aplikace zavádí jeden UI základ pro všech 127 aktivních
  PHP HTML stránek: společné pozadí a formuláře, stav načítání a ochranu proti
  dvojímu odeslání, bezpečné toast zprávy a jednu veřejnou navigaci pro e-shop,
  tréninky, kroužky, velodrom a účty; podrobnosti jsou v
  `docs/shared-ui-foundation.md`,

- aktuální funkční řez dokončuje M3.2: uživatel má výchozí opt-out, dobrovolné
  zapnutí a odhlášení jedním krokem; idempotentní týdenní fronta, audit a společný
  localhost-only outbox jsou ověřené. Produkční e-mailový transport ani CRON
  nejsou implementované,

- aktuální M3.5a přidává admin-only read-only inventuru kvality tréninků,
  strukturovaných i historických měření, závodních výsledků a zátěžových testů;
  zobrazuje pouze agregované počty bez jmen, ID a naměřených hodnot,

- navazující M3.5b přidává aditivní kontrakt `sports-measurement-v1`: výslovnou
  jednotku vzdálenosti a metry, čas v milisekundách, číselné RPE a uzavřené stavy
  závodu. Historii nepřevádí a produkci nemění; nový zápis/import jej použije až
  v M3.5c,

- větev `main`, vzdálený repozitář `KovoPraha/evidenceTreninku`,
- poslední implementace: `12c2300` – M3.4 přidává administrátorský read-only
  provozní přehled plateb, vratek, kapacit, přihlášek a provozních výjimek; browser
  ověřil stránku syntetickým administrátorem bez konzolové chyby,
- bezpečnostní infrastruktura: `6655a39` – kanonická `APP_BASE_URL`, soukromé
  přílohy mimo webroot, autorizovaný výdej, migrátor existujících souborů, opravy
  známých XSS sinků a chybějícího CSRF; produkce se nezměnila,
- předchozí implementace: `63c8ec1` – první řez M3.3 přidává
  přihlášený roční přehled skutečně uhrazených členských předpisů a e-shopových
  položek všech schválených profilů; oba zdroje i měny drží odděleně a výslovně
  nejde o účetní ani daňový doklad,
- předchozí implementace: `82d41ac` – M3.2 přidává
  přihlášený týdenní náhled rodinného programu s bezpečným listováním po týdnech,
  prostým textem a výslovně vypnutým odesíláním,
- předchozí implementace: `1510c20` – první řez M3 přidává
  do rodinného sportovního přehledu společný read-only program nejbližších
  30 dní nad stejnými oprávněními jako soukromý ICS kalendář,
- předchozí implementace: `9a04c3c` – localhostová
  závěrečná brána M2 automaticky ověřuje cesty A01–A10, migrace a úplnost demo
  dat a odděluje je od vlastníkových výsledků PASS/PARTIAL/FAIL/BLOCKED,
- předchozí implementace: `6d290cc` – administrátor může
  jednu čekající připomínku na localhostu auditovaně zpracovat do chráněného
  souborového outboxu, ověřit stav „Odesláno“ a ukázku opakovaně obnovit;
  skutečný e-mail se nepoužije,
- předchozí implementace: `5843f70` – jedním potvrzeným localhostovým tlačítkem
  připraví opakovatelný syntetický předpis, čekající připomínku a její okamžitý
  náhled bez odeslání,
- předchozí implementace: `68e1199` – bezpečný
  administrátorský náhled uloženého textu připomínky a localhost-only
  testovací outbox, který nic neposílá na internet,
- předchozí implementace: `66b4241` – administrátorský
  přehled fronty připomínek členských plateb, bezpečné ruční opakování bez
  odesílání z webu a audit konkrétního administrátora,
- předchozí implementace: `29e3d5d` – dobrovolné e-mailové připomínky blížící
  se splatnosti členského předpisu s idempotentní frontou, auditem,
  opakovanými pokusy a omezením četnosti,
- předchozí implementace: `004e4a6` – soukromý rodinný
  ICS kalendář tréninků, přihlášených akcí, rezervací a splatností pro
  všechny aktuálně schválené profily účtu,
- předchozí funkční řez: `3aa39f8` – veřejný ICS kalendář zveřejněných
  tréninků, otevřených klubových akcí a veřejných hodin velodromu,
- předchozí funkční řez: `5829171` – read-only přehled členských předpisů pro
  rodiče, sportovce a administrátora s izolačními testy,
- předchozí infrastruktura: `281fcd0` – oprava zálohovacího ownership kontraktu
  `.9`, úplnost všech migračních tabulek a skutečný MariaDB backup smoke,
- KIS funkční řez: `7c8b444` – M2.3g auditovaný localhost přenos členských
  předpisů, historických plateb a bezpečný rollback,
- CI infrastruktura: `ef5ec21` – MariaDB smoke job v CI,
- migrace localhostu 50/50,
- automatické testy 477/4107,
- first-party PHP syntaxe 466 souborů bez chyby,
- Composer audit bez bezpečnostního nálezu,
- izolovaný MariaDB backup smoke vytvořil ověřenou zálohu 100 tabulek;
  aktuální ownership kontrakt `2026-08-05.3` zahrnuje i tři tabulky týdenních
  souhrnů,
- produkce se při těchto změnách neměnila.

Čísla jsou snapshot a nový agent je musí levně ověřit. Cowork bridge kopie může
být zastaralá; nepoužívej ji jako důkaz proti skutečnému lokálnímu Gitu.

## Funkční localhost vstupy

- společná homepage: `http://localhost/evidencePavel/`,
- testovací scénáře: `http://localhost/evidencePavel/testovaci_scenare.php`,
- KIS M2.3g preview, parita a předpisy: `http://localhost/evidencePavel/kis_sync_center.php?run_id=13`,
- A07 docházka: `http://localhost/evidencePavel/kis_training_a07_admin.php`,
- A10 audit osoby: `http://localhost/evidencePavel/person_audit_admin.php?sportovec_id=1`,
- e-shop: `http://localhost/evidencePavel/booking/eshop.php`,
- detail demo produktu: `http://localhost/evidencePavel/booking/produkt.php?id=1`,
- administrativní přehled členských předpisů:
  `http://localhost/evidencePavel/member_charges_admin.php`,
- veřejný ICS kalendář:
  `http://localhost/evidencePavel/booking/verejny_kalendar.php`.
- nastavení soukromého rodinného kalendáře:
  `http://localhost/evidencePavel/booking/sportovni_prehled.php#rodinny-kalendar`.
- dobrovolné připomínky splatnosti:
  `http://localhost/evidencePavel/booking/sportovni_prehled.php#pripominky-plateb`.
- administrátorská fronta připomínek:
  `http://localhost/evidencePavel/member_charge_reminders_admin.php`.
- roční přehled uhrazených služeb:
  `http://localhost/evidencePavel/booking/sportovni_prehled.php?year=2026#rocni-prehled-uhrad`.
- administrátorský provozní přehled:
  `http://localhost/evidencePavel/provozni_prehled_admin.php`.
- nastavení týdenního souhrnu:
  `http://localhost/evidencePavel/booking/sportovni_prehled.php#tydenni-souhrn`.
- administrátorská fronta týdenních souhrnů:
  `http://localhost/evidencePavel/family_weekly_summaries_admin.php`.
- kvalita sportovních dat M3.5a:
  `http://localhost/evidencePavel/sports_data_quality_admin.php`.

Rodič vidí předpisy u každého schváleného dítěte v rodinném sportovním přehledu
a sportovec ve svém omezeném přístupu. Oba pohledy jsou read-only a odvozují
osobu výhradně z aktivní session a schválené vazby.

Testovací rozcestník je localhost-only a vyžaduje administrátora. Ke každému
scénáři ukládá `PASS / PARTIAL / FAIL / BLOCKED`, důležitost a dvě krátké poznámky.
Data jsou v ignorovaném `var/acceptance-feedback.json`. Tlačítko exportu vytvoří
Markdown bez automaticky načtených osobních dat; před commitem se musí ručně
zkontrolovat, že poznámky neobsahují hesla ani ostré osobní údaje.

## Aktuální orientační stav

- celý M2: 87 %,
- M2.3 zkouška migrace KIS: 99 %; archiv, fingerprintovaný preview, izolovaný
  sandbox, `kis-import-field-v1`, paritní report, cílový model, staging i auditovaný
  localhost přenos a rollback jsou hotové; run #13 přesně spároval dvě osoby,
  přenesl 2/2 předpisy včetně jedné samostatné historické platby a po ověření byl
  bezpečně vrácen na 0/2 při zachování auditní historie,
- M2.6 integrovaná akceptace: 99 %; závěrečná brána živě potvrzuje technickou
  připravenost 3/3, dostupnost A01–A10 10/10, migrace 50/50 a úplná demo data;
  vlastníkem je zatím potvrzeno 0/10, takže zbývá jeho průchod a vypořádání
  připomínek,
- M2.7 hodnota pro členy: 94 %; veřejný i soukromý rodinný ICS feed, opt-in
  fronta připomínek, její auditovaná administrátorská obsluha, náhled textu a
  bezpečný lokální testovací outbox, jedním tlačítkem obnovitelná browserová
  ukázka i plný přechod Čeká → Odesláno s auditem administrátora jsou technicky
  hotové; zbývá ověřit
  kalendář v reálné aplikaci a po schválení textu provést kontrolované doručení
  na určenou testovací adresu; produkční CRON zůstává vypnutý,
- KIS/K5: 98 % technického prototypu; ostrý import a cutover nejsou hotové,
- e-shop: 97 % technického localhost řešení; produkční aktivace a automatické
  platby nejsou součástí hotového stavu.
- M3: 65 % technického localhost řešení; M3.1 rodinný program, M3.2 týdenní
  souhrn a M3.4 read-only provozní přehled správce jsou technicky hotové. M3.2
  má bezpečný přihlášený náhled, opt-in/opt-out, idempotentní frontu, audit a
  localhost-only outbox;
  M3.3 má oddělený read-only roční přehled skutečně uhrazených členských
  předpisů a e-shopových položek. U M3.3 zbývá vlastníkova kontrola obsahu a
  rozhodnutí o exportu. M3.5a má read-only inventuru pěti sportovních zdrojů bez
  osobních a naměřených hodnot; M3.5b již definuje aditivní verzi jednotek,
  normalizovaného času, RPE a výsledkových stavů bez převodu historie. M3.5c má
  kontrakt zapojit do budoucího zápisu a jednorázových importů,
  produkční brána zůstává podmíněná vlastníkovým dokončením A01–A10.

Procenta neznamenají připravenost k produkčnímu deployi. Produkce, ostrý import,
Stripe, Fio auto-confirm, wallet a TrainingPeaks zůstávají samostatně blokované.

## Kontrolní audity

Rozcestník je v `docs/AUDITY.md`. Druhý AI re-audit a živý adversariální průchod
jsou uložené jako historické snapshoty `cd38f85`; jejich validační dodatky
zaznamenávají, že dřívější nálezy N-H1, N-M1 a N-L1 byly následně opraveny a
ověřeny. Pozdější audit z 5. 8. 2026 potvrdil dva nové HIGH nálezy v legacy
infrastruktuře (veřejné přílohy a Host poisoning); oba jsou opravené v `6655a39`
spolu se známými XSS a CSRF cestami. Otevřené zůstává odstranění DDL z webových
requestů a převedení request-bound e-mailů na společnou frontu. Podrobnosti jsou v
`docs/security-infrastructure-2026-08-05.md`. Žádný localhost audit nenahrazuje
produkční penetrační test ani kontrolu konfigurace hostingu.

Produktový `AUDIT-PRILEZITOSTI-A-NAPADY.md` je uložen jako zdroj návrhů, ale
nenahrazuje schválený plán. Jednotlivé nápady se do roadmapy přesunou teprve po
potvrzení priority, právních a účetních dopadů a očekávaného rozsahu.
Vytříděné návrhy a jejich brány jsou v
`docs/plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md`. Do M2.7 byl
přijat veřejný i revokovatelný rodinný ICS kalendář a opt-in připomínky
splatnosti; wallet, zdravotní predikce,
externí integrace a další personalizované feedy zůstávají za samostatnými
rozhodnutími.

Kanonický plán navazujícího milníku je
`docs/plan-eshop-tymova-evidence/12-milnik-m3-clenska-hodnota.md`. M3.1 pouze
zobrazuje již oprávněná data; nevytváří druhou identitu, kalendářovou logiku ani
finanční stav.

## Doporučené pořadí další práce

1. Vlastník nebo Cowork projde A01–A10 v prohlížeči a uloží výsledky do rozcestníku.
2. Export výsledků se zkontroluje a případně přidá do Gitu jako auditní artefakt.
3. Blokující chyby a důležité UX připomínky se opraví před novými funkcemi.
4. Na telefonu nebo počítači přidat veřejný i rodinný odebíraný kalendář a
   potvrdit české názvy, časy, místa, aktualizace položek a zneplatnění
   rodinného odkazu po jeho zrušení.
5. Na testovací e-mailové adrese ověřit text a doručení jedné připomínky;
   produkční CRON ani `--send` zatím nezapínat.
6. Potom dokončit M2.3: získat reprezentativní anonymizovaný KIS export, potvrdit
   aliasy ID osoby, ID předpisu, částky a data úhrady a zopakovat celý cutover i
   rollback nad testovací kopií. Ostrý import zůstává samostatně blokovaný.
7. Produkční deploy připravit až po samostatném výslovném souhlasu vlastníka.
