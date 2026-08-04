# Aktuální stav projektu pro AI a vlastníka

Aktualizováno: 4. 8. 2026, Europe/Prague

Tento soubor je krátký vstupní rozcestník. Přesný historický ledger a poslední
důkazy jsou v `docs/plan-eshop-tymova-evidence/SESSION_HANDOFF.md`; produktová
autorita M2 je `10-milnik-m2-provozni-pilot.md`.

## Identita projektu

Samostatná aplikace Evidence tréninků + e-shop + KIS v
`C:\xampp\htdocs\evidencePavel`. Není submodulem Velocoty. Případné budoucí
sdílení uživatelů je oddělené rozhodnutí, nikoli současná závislost.

## Poslední přijatý technický stav

- větev `main`, vzdálený repozitář `KovoPraha/evidenceTreninku`,
- poslední implementace před tímto dokumentem: `5829171` – read-only přehled
  členských předpisů pro rodiče, sportovce a administrátora s izolačními testy,
- předchozí infrastruktura: `281fcd0` – oprava zálohovacího ownership kontraktu
  `.9`, úplnost všech migračních tabulek a skutečný MariaDB backup smoke,
- předchozí funkční řez: `7c8b444` – M2.3g auditovaný localhost přenos členských
  předpisů, historických plateb a bezpečný rollback,
- CI infrastruktura: `ef5ec21` – MariaDB smoke job v CI,
- migrace localhostu 45/45,
- automatické testy 398/3510,
- first-party PHP syntaxe 412 souborů bez chyby,
- Composer audit bez bezpečnostního nálezu,
- izolovaný MariaDB backup smoke vytvořil ověřenou zálohu 90 tabulek a potvrdil
  všech 12 tabulek doplněných po kontrolním auditu,
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
  `http://localhost/evidencePavel/member_charges_admin.php`.

Rodič vidí předpisy u každého schváleného dítěte v rodinném sportovním přehledu
a sportovec ve svém omezeném přístupu. Oba pohledy jsou read-only a odvozují
osobu výhradně z aktivní session a schválené vazby.

Testovací rozcestník je localhost-only a vyžaduje administrátora. Ke každému
scénáři ukládá `PASS / PARTIAL / FAIL / BLOCKED`, důležitost a dvě krátké poznámky.
Data jsou v ignorovaném `var/acceptance-feedback.json`. Tlačítko exportu vytvoří
Markdown bez automaticky načtených osobních dat; před commitem se musí ručně
zkontrolovat, že poznámky neobsahují hesla ani ostré osobní údaje.

## Aktuální orientační stav

- celý M2: 78 %,
- M2.3 zkouška migrace KIS: 99 %; archiv, fingerprintovaný preview, izolovaný
  sandbox, `kis-import-field-v1`, paritní report, cílový model, staging i auditovaný
  localhost přenos a rollback jsou hotové; run #13 přesně spároval dvě osoby,
  přenesl 2/2 předpisy včetně jedné samostatné historické platby a po ověření byl
  bezpečně vrácen na 0/2 při zachování auditní historie,
- M2.6 integrovaná akceptace: 98 %; technické scénáře jsou připravené, zbývá
  vlastníkův průchod a vypořádání připomínek,
- KIS/K5: 98 % technického prototypu; ostrý import a cutover nejsou hotové,
- e-shop: 97 % technického localhost řešení; produkční aktivace a automatické
  platby nejsou součástí hotového stavu.

Procenta neznamenají připravenost k produkčnímu deployi. Produkce, ostrý import,
Stripe, Fio auto-confirm, wallet a TrainingPeaks zůstávají samostatně blokované.

## Kontrolní audity

Rozcestník je v `docs/AUDITY.md`. Druhý AI re-audit a živý adversariální průchod
jsou uložené jako historické snapshoty `cd38f85`; jejich validační dodatky
zaznamenávají, že dřívější nálezy N-H1, N-M1 a N-L1 byly následně opraveny a
ověřeny. Živý webový audit přibližně 45 útokových vektorů neprokázal CRITICAL,
HIGH ani MEDIUM zneužitelnou webovou chybu. Nejde o náhradu produkčního
penetračního testu.

Produktový `AUDIT-PRILEZITOSTI-A-NAPADY.md` je uložen jako zdroj návrhů, ale
nenahrazuje schválený plán. Jednotlivé nápady se do roadmapy přesunou teprve po
potvrzení priority, právních a účetních dopadů a očekávaného rozsahu.

## Doporučené pořadí další práce

1. Vlastník nebo Cowork projde A01–A10 v prohlížeči a uloží výsledky do rozcestníku.
2. Export výsledků se zkontroluje a případně přidá do Gitu jako auditní artefakt.
3. Blokující chyby a důležité UX připomínky se opraví před novými funkcemi.
4. Potom dokončit M2.3: získat reprezentativní anonymizovaný KIS export, potvrdit
   aliasy ID osoby, ID předpisu, částky a data úhrady a zopakovat celý cutover i
   rollback nad testovací kopií. Ostrý import zůstává samostatně blokovaný.
5. Produkční deploy připravit až po samostatném výslovném souhlasu vlastníka.
