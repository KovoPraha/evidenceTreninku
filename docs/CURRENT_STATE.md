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
- poslední implementace před tímto dokumentem: `95693a2` – M2.3e uložený non-PII
  paritní report osob, členství, soupisek a platebních signálů,
- předchozí infrastruktura: `ef5ec21` – MariaDB smoke job v CI,
- migrace localhostu 43/43,
- automatické testy 379/3332,
- first-party PHP syntaxe 401 souborů bez chyby,
- Composer audit bez bezpečnostního nálezu,
- produkce se při těchto změnách neměnila.

Čísla jsou snapshot a nový agent je musí levně ověřit. Cowork bridge kopie může
být zastaralá; nepoužívej ji jako důkaz proti skutečnému lokálnímu Gitu.

## Funkční localhost vstupy

- společná homepage: `http://localhost/evidencePavel/`,
- testovací scénáře: `http://localhost/evidencePavel/testovaci_scenare.php`,
- KIS M2.3e preview a parita: `http://localhost/evidencePavel/kis_sync_center.php?run_id=9`,
- A07 docházka: `http://localhost/evidencePavel/kis_training_a07_admin.php`,
- A10 audit osoby: `http://localhost/evidencePavel/person_audit_admin.php?sportovec_id=1`,
- e-shop: `http://localhost/evidencePavel/booking/eshop.php`,
- detail demo produktu: `http://localhost/evidencePavel/booking/produkt.php?id=1`.

Testovací rozcestník je localhost-only a vyžaduje administrátora. Ke každému
scénáři ukládá `PASS / PARTIAL / FAIL / BLOCKED`, důležitost a dvě krátké poznámky.
Data jsou v ignorovaném `var/acceptance-feedback.json`. Tlačítko exportu vytvoří
Markdown bez automaticky načtených osobních dat; před commitem se musí ručně
zkontrolovat, že poznámky neobsahují hesla ani ostré osobní údaje.

## Aktuální orientační stav

- celý M2: 75 %,
- M2.3 zkouška migrace KIS: 97 %; archiv, fingerprintovaný preview, izolovaný
  promote/rollback, `kis-import-field-v1` a uložený paritní report jsou hotové;
  report pravdivě blokuje dvě nové demo osoby a chybějící cílový model jednotlivých
  členských platebních předpisů,
- M2.6 integrovaná akceptace: 98 %; technické scénáře jsou připravené, zbývá
  vlastníkův průchod a vypořádání připomínek,
- KIS/K5: 98 % technického prototypu; ostrý import a cutover nejsou hotové,
- e-shop: 97 % technického localhost řešení; produkční aktivace a automatické
  platby nejsou součástí hotového stavu.

Procenta neznamenají připravenost k produkčnímu deployi. Produkce, ostrý import,
Stripe, Fio auto-confirm, wallet a TrainingPeaks zůstávají samostatně blokované.

## Doporučené pořadí další práce

1. Vlastník nebo Cowork projde A01–A10 v prohlížeči a uloží výsledky do rozcestníku.
2. Export výsledků se zkontroluje a případně přidá do Gitu jako auditní artefakt.
3. Blokující chyby a důležité UX připomínky se opraví před novými funkcemi.
4. Potom dokončit M2.3: získat reprezentativní anonymizovaný KIS export, potvrdit
   aliasy stabilního ID a rozhodnout cílový model členských platebních předpisů.
5. Produkční deploy připravit až po samostatném výslovném souhlasu vlastníka.
