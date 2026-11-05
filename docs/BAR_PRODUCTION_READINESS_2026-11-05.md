# BAR → KIS: připravenost a budoucí postup B7

Příprava po technické přejímce B6, 5. 11. 2026. **Tento dokument není souhlas k nasazení.** Větev i PR #306 zůstávají oddělené, skutečný zdroj ani produkční cíle se nemění.

## Podklady před rozhodnutím o přechodu

1. Zelené kontroly přesného headu PR včetně MariaDB 10.3/11.4 a naplněné obnovy. Zkontrolovat nové změny main a při rozdílu zopakovat dotčené brány; nepoužít staré CI.
2. Čtecí inventura skutečného zdroje, export katalogového balíčku a manifestu soukromých fotografií podle [runbooku B2](BAR_TRANSFER_RUNBOOK_2026-11-05.md). Konkrétní počty, kontrolní součty a kolize musí být doložené, ne odhadnuté. Žádná stará provozní historie ani zásoby.
3. Nácvik tohoto konkrétního balíčku v nové izolované DB a úložišti. Porovnat produkty, kategorie, ceny, receptury, příznaky/archivaci a fotografie; počáteční množství nula. Ověřit pouze účet lucka s technickou adresou info1@kovopraha.cz, bez hromadného převzetí účtů/adminů nebo trenérských oprávnění. Správce nastaví a soukromě předá nové heslo, pokud původní ověřování nevyhoví.
4. Vlastník určí konkrétní KIS účty pro administrátory baru a samostatné příjemce práva vlastní spotřeby. Staré jméno/role trebe není důvodem automatického grantu. Každé přidělení audituje správce systému.
5. Lidská přejímka obsluhy a správce: najít produkt, hotovost, čekající karta/QR a její doložení, kelímek, storno, vlastní spotřeba jen s grantem, uzávěrka/rozdíl, sklad/faktura/nabídka a výpadek spojení. Hospodář zkontroluje export bez zaúčtování. Podpis zde dosud není.

## Až po novém výslovném povolení

- Před zásahem pořídit čerstvou úplnou zálohu cílové DB a všech soukromých souborů, doložit jejich izolovanou obnovu a konkrétní bod návratu. Připravit chráněné vydání s neměnným SHA; neposuzovat dostupnost jen podle HTTP 200.
- Řízeně aplikovat migrace. Před první kasou ověřit `clock_contract=utc-v1`, nulovou barovou finanční historii a cílové množství nula. Stav `legacy-local` je důvod přechod zastavit, nikoli ručně přeznačit historii.
- Importovat právě schválený balíček opakovatelným nástrojem, ověřit manifest a účet. Přidělit pouze schválené pracovní pozice a explicitní vlastní spotřebu. Převod a role musí mít vlastní auditní doklady.
- Připravit kis.kovopraha.cz, TLS, cookies, APP_BASE_URL, soukromé úložiště a odpovídající odkazy. Starý bar.kovopraha.cz přesměrovat až po úspěšné přejímce cíle. Starou přihlašovací/session vrstvu nepřenášet. Skutečné přepnutí zatím neproběhlo.
- Ověřit přihlášené role na živém cíli, soukromí fotografií/PDF, veřejné menu a zákaz změn bez oprávnění, sklad nula a první vlastní kasu. Případný řízený test musí mít konkrétní schválená data a výsledek, ne předstíranou skutečnou platbu.
- Zaznamenat živé SHA, migrační ledger, manifest, přístupy a přejímku do CURRENT_STATE a samostatného protokolu. Účetní posting, nové bankovní párování a poskytovatelé se tímto přechodem samy neaktivují.

## Při problému

Zastavit další zápisy a zachovat nové doklady/audit; neobnovovat starou zálohu přes mezitím vzniklou skutečnou kasu bez odsouhlasení dopadů. Před první skutečnou operací lze použít připravený bod návratu. Po jejím vzniku je nutné určit osud nových finančních dat a souborů před rozhodnutím o návratu. DB a přílohy se posuzují jako jeden celek; chybějící nebo poškozený soubor znamená neúplnou obnovu.

## Zahájené vydání po souhlasu vlastníka

5. 11. 2026 vlastník v chatu výslovně povolil produkční nasazení („ok, tedy nasadit“). Následuje chráněné vydání modulu z ověřeného PR #306; není tím doložená lidská přejímka ani parita dosud nepřipraveného skutečného katalogového balíčku. Před vydáním se novou operací `vytvorit-uplnou-zalohu-a-obnovit` v chráněném `production-drills.yml` vytvoří čerstvá DB záloha a soukromý ZIP nynější aplikace včetně konfigurace a celého soukromého úložiště. ZIP se nepublikuje jako artefakt; na izolovaném runneru se obnoví každý soubor a porovná jeho velikost a SHA-256. SQL se obnoví do samostatné MariaDB včetně všech počtů a triggerů. Cílová aplikace ani zdrojový BAR se tím nemění. Skutečný import, přidělení konkrétních dalších účtů a přesměrování původního BAR se řídí samostatným konkrétním převodním podkladem, nevznikají automaticky při nasazení kódu.
