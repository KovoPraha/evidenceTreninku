# Předání P11-SCALE — větší zdravotní přehled a plánovač

7. 11. 2026. HOTOVO v tomto rozsahu; [plán a další priority](MEDICAL_PLANNER_SCALE_2026-11-07.md).
Větev `codex/medical-planner-scale-20261107`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-medical-planner-scale-20261107`.
Předání/finální CI/začlenění: [PR #375](https://github.com/KovoPraha/evidenceTreninku/pull/375).
**Žádný produkční deploy, aktivace nebo zápis touto relací.**

## Dokončené měření

- Primární69f49033:72kladných/28záporných,2160časů. 100k sledování/300k záznamů,
  520týdnů/7280plánů a1000členství/50hran, jednotlivé osy popsané v protokolu.
- Aktuálníe36c9ba6 po#374:18kladných/8záporných,540časů na planner520/roster1000.
  Všech18 úplných domén přesně shodných s původní sadou i kanonickým readerem.
- Celkem2700časů,90doménových/DOM parit a90projekcí READ ONLY; všechny
  diagnostické HTML shodné a v každé sadě21 stejných doménových checksumů.
- Zdravotní Platné100k:1300558 Rows_examined, přes300k metadatových řádků,
  PHP2,16MiB/HTML49,9kB. HTTP4,50s, běžný seznam0,56s orientačně.
- Aktuální plánovač520, vedoucí/kořen: helper14554→3706 řádků,
  PHP10,33→3.38MiB. Přenos historie opravený #374;
  naše časové poměry nejsou řízený párový odhad zrychlení.

[Primární protokol](verification/medical-planner-scale-20261107/RESULTS.md),
[aktuální planner](verification/medical-planner-scale-20261107/current-planner/RESULTS.md).
Raw data, manifesty, prostředí, správné zdrojové piny, kontrolní body a JUnit v repu.

## Úklid a omezení

Obě vlastní náhodná schémata/účty/configy/credentials/cookies a26/13syntetických
relací uklizené podle skutečných protokolů; vlastní web/DB zastavené a oba porty
33479/18115 odmítají spojení. Runtimeee3c4eee ponechaný zastavený, žádné rekurzivní mazání.
Primární per-row/role/FK přípravy a TEMP chyba jsou oddělené v kalibraci;
celá neúplná100k fáze zopakovaná na stejných nezměněných datech s vlastním tmpdir.
Aktuální první serverový start vyžadoval readiness; měřené vzorky se neztratily.
Zdrojové otisky normalizované LF; Windows Git archive může vracet CRLF.
Testovací schéma neobsahuje všechny produkční legacy indexy. Složitá historie
náhrad, historicky proměnlivá topologie, fyzický mobil a souběh nejsou velkou sadou pokryté.
Původní WIP beze změny, kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

## Další kroky

1. R13 úspornější stavový přehled a COUNT/picker čtení: nový návrh s měřením,
   skutečné indexy a zachování úplného kanonického výpočtu. Žádná další oprava už implementovaného R05.
2. R13 100k řízený Linux10.3/11.4, složitější historie, jednotný tmpdir a metadatový preflight.
3. R05 mnoho členství/RSVP a režie sond: u1000celkový Rows_examined17382→18895,
   přestože helper1499→479. Neopakovat hotovou opravu historie, posoudit jinou část čtení.
   Mobil/síť/CPU/souběh, ostatní K0.2; produkční picker UAT z #377 blokovaný nástrojem.
4. Produkční release #374 vlastní jiná relace a CURRENT; nepřebírat její deploy pověření.
   P05/P07, exportP06 a heslo/T07 ponechat dle [registru](WORK_PLAN_CURRENT.md).
