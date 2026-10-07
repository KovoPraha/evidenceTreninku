# Datové toky EvidencePavel / KIS – aktuální technická mapa

Datum kontroly: 7. 10. 2026
Zdrojový základ: pracovní strom nad `5512f39ce9d80cc88824c4fd7548ced4a95c43e5`; vzdálený `origin/main` při zahájení kontroly `f5619222402436d7a00e7e720318886bcaffb97a`
Rozsah: statická kontrola zdrojového kódu, dokumentace a verzovaných workflow. Produkční tvrzení jsou převzata jen z doložených GitHub Actions běhů uvedených v `docs/CURRENT_STATE.md`.

Historický audit z 24. 8. 2026 zůstává beze změny v sousední složce `data-flow-audit-2026-08-24`. Tento dokument je jeho aktuální nástupce, nikoli přepis auditní stopy.

## 1. Kontrolní inventura

- 377 PHP souborů první strany po vyloučení `vendor`, `tests`, `migrations`, `docs`, `outputs`, `var`, `.git` a `.agents`;
- 245 heuristických vstupních nebo obslužných bodů mimo `includes/`;
- 64 ručně vedených hlavních datových toků v sešitu;
- 12 souborů první strany obsahuje přímé `mail()`; část jsou řízené worker transporty, část starší requestové cesty;
- 0 otevřených HIGH nálezů, 1 otevřený MEDIUM nález, 0 nerozhodnutých položek.

Automatický inventář je kontrolní síť. Neprokazuje dostupnost URL, produkční oprávnění ani skutečné databázové schéma.

## 2. Společný kontrakt toku

Kritické toky mají používat tuto posloupnost:

`vstup → identita/oprávnění → serverová validace → doménová služba → transakce a audit → fronta nebo výstup`

Peníze, kapacita, soupiska a osobní identita nesmí být potvrzeny pouze návratem z prohlížeče, klientským parametrem nebo úspěšným HTTP stavem.

## 3. Identity a hranice důvěry

| Identita | Session / capability | Rozsah |
|---|---|---|
| Trenér a správce | `trener_id`, revokační verze, role a pracovní pozice | evidence, administrace, finance podle oprávnění |
| Veřejný účet / rodič | `verejny_uzivatel_id`, schválené vazby `self`/`guardian` | rodina, objednávky, programy, akce a rezervace |
| Sportovní účet | `sportovec_pristup_id`, oddělená session | omezený přehled jedné osoby a RSVP |
| Veřejná karta | náhodný 256bitový `sportovci.hash` | pouze čtení; zápis poznámky vyžaduje přihlášený odpovídající účet |
| CLI / workflow | `PHP_SAPI`, explicitní host a chráněná konfigurace | migrace, zálohy, workery, readiness, invarianty |
| Platební webhook | podpis poskytovatele a serverový snapshot | návrh kanonického platebního přechodu |

## 4. Osoby, registrace a přihlášení

- Veřejná registrace vytváří účet s ověřovacím tokenem; sportovní registrace a rychlá programová přihláška vytvářejí kontrolovatelnou žádost osoby se snapshoty souhlasů.
- Jeden veřejný účet může mít více schválených vazeb na děti. Samostatný sportovní účet je bezpečnostně oddělený od rodičovské a trenérské identity.
- Obnova hesla používá jednorázové hashované tokeny, expiraci a revokaci session.
- Otevřený spolehlivostní dluh: několik registračních a resetovacích cest stále odesílá e-mail přímo, mimo společnou trvalou frontu.

## 5. Sportovní evidence

- Kanonické vytvoření a editace tréninku používají společný parser měření, transakci, vazby na soupisky/podskupiny a kompenzační souborový plán.
- Kanonické vytvoření a editace závodu používají stejný kontrakt. Legacy endpointy `edit_zavod.php` a `import_vysledku_zavodu.php` jsou fail-closed.
- Veřejná karta může číst tréninky přes bearer token. Poznámku lze nově změnit jen po přihlášení odpovídajícím sportovním účtem nebo účtem se schválenou rolí `self`/`guardian`.
- Plánovaný trénink kopíruje očekávanou soupisku do evidence a RSVP zachovává poslední odpověď sportovce/rodiče.

## 6. E-shop, produkty a platby

- Katalog rozlišuje zboží, programy, členství, klubové akce a velodromové vstupy. E-shopová navigace používá spravované viditelné kategorie.
- Zboží lze koupit zrychleně bez předchozí registrace. Produkty spojené se sportovcem používají přihlášený účet nebo rychlou registraci osoby.
- Checkout znovu načítá ceny, sklad, kapacity a aktivní pravidla pod zámky. Ukládá snapshoty, fingerprint a idempotency klíč.
- Bankovní potvrzení, SumUp a Stripe končí ve stejné kanonické platební změně, která aktivuje navázaný program, akci nebo velodrom pouze uvnitř transakce.
- SumUp a Stripe jsou dostupné podle administrační politiky. Samotný návrat z platební brány úhradu nepotvrzuje.
- Fio kód je read-only a automatické potvrzení neprovádí. Od 7. 10. 2026 nemá produkční workflow časový plán a lze jej spustit jen ručně; `FIO_IMPORT_ENABLED` má zůstat `false`.

## 7. Kroužky, členství, soupisky a akce

- Stabilní program má sezonní nabídky. Celoroční, první pololetní a druhá pololetní nabídka mohou mít různé ceny a stejnou cílovou soupisku.
- Kapacita se počítá podle unikátní osoby, takže stejné dítě ve více obdobích stejné skupiny není započítáno dvakrát.
- Veřejný rozcestník kroužků zobrazuje publikované nabídky a vede na kanonické KIS produkty.
- Klubová akce musí před otevřením projít preflightem termínů, stavu, registračního okna, kapacity, cílů a podmínek. Bezplatná registruje přímo, placená pokračuje přes košík.
- Komunikace se soupiskou používá fingerprint příjemců, neměnný snapshot, trvalou frontu, retry a produkční worker každých pět minut.
- Členské předpisy, příspěvky a párování platby zůstávají oddělené od objednávkového lifecycle, ale používají kanonické osoby a účty.

## 8. Importy, soubory a migrace

- KIS a Shoptet používají fáze zdroj → archiv/hash → parse → staging/preview → explicitní promote. Starý přímý KIS writer je odstraněný.
- Citlivé přílohy a dočasný UCI export používají soukromé úložiště mimo webroot a autorizovaný výdej.
- `db.php` schéma nemění. Legacy baseline je zmrazený a nové změny vlastní pouze číslované migrace spuštěné CLI/deploy krokem.
- Produkční deploy nejprve vytváří a ověřuje databázovou zálohu, potom aplikuje migrace a až následně aktivuje release.

## 9. Komunikace

Moderní toky pro platby, klubové akce, členské připomínky, týdenní souhrny a soupiskové zprávy mají trvalé fronty nebo auditované workery. Produkčně je automaticky naplánovaný pouze schválený worker soupiskových zpráv.

Otevřený nález M2 se týká starších cest s přímým `mail()`: registrace, obnova hesla, individuální lekce a čekací listina, cron starých upomínek a hromadný e-mail. Jejich přesun do existující společné fronty vyžaduje jeden koordinovaný řez a provozní schválení textů a workeru; není bezpečné je pouze přepnout bez ověření doručování.

## 10. Produkční důkaz a omezení

Dokumentace nyní rozlišuje kód, deploy a UAT. Aktuálně evidovaný produkční release je commit `49c38c72c6704826182aff7f59855f47b5818bb8`, deploy běh `37636084395` s `uat_schvaleno=true`, readiness `37636447561` s `ready=true` a invarianty `37636671525` s 16 kontrolami bez porušení.

Tato kontrola sama nespouštěla produkční zápisy, banku ani skutečný e-mail. Úspěšný deploy nebo HTTP 200 nejsou důkazem celého životního cyklu; zapisovací UAT se řídí samostatnou bránou.
