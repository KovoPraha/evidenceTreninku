# Session handoff BAR — 6. 11. 2026

Aktualizace 10. 11.: [obnovení původního vzhledu obrazovek a řízený převod zásob](BAR_DISPLAY_STOCK_PARITY_2026-11-10.md) jsou nové zadání vlastníka; dřívější nulový sklad je historický počátek, nikoli trvalý požadavek. Starší nedokončená přejímka zůstává zachovaná.

Navazuje na [hlavní handoff](HANDOFF_CURRENT.md), [aktuální provozní stav](CURRENT_STATE.md)
a [plán začlenění](BAR_INTEGRATION_PLAN_2026-11-05.md). Souhrn další práce celého projektu je v [průběžném plánu](WORK_PLAN_CURRENT.md). Jde o datované předání této session;
nejnovější nasazení a aktivace mají souhrn výhradně v CURRENT_STATE.

## Zadání a pracovní kopie

Vlastník výslovně schválil zveřejnění nabídky „Bar KOVO – základní nabídka“ a požaduje
všechnu průběžnou i plánovanou práci zapisovat přímo do souborů repozitáře.
[Trvalé pravidlo](WORK_DOCUMENTATION_POLICY.md) je propojené s kořenovým AGENTS.md,
vývojovým kontextem, dokumentačním rozcestníkem a hlavním handoffem.

Větev `codex/bar-menu-publication-20261106`, izolovaná pracovní kopie
`C:\Users\Marek\Documents\Codex\evidencePavel-bar-integration-20261105`, základ čerstvý `origin/main`.
Původní checkout `C:\xampp\htdocs\evidencePavel` a rozpracovaný samostatný BAR se nepřepisují.

## Dokončené kroky a důkazy

- [B7: převod katalogu, Lucky a domény](verification/bar-cutover-20261106/RESULTS.md): 68 produktů,
  5 kategorií, 51 fotografií; nulové počáteční zásoby a historie; schválená správa a explicitní vlastní spotřeba.
  Import neopakovat ani neobnovovat původní DB přes novou historii.
- [Technická provozní přejímka](verification/bar-acceptance-20261106/RESULTS.md): skutečný místní browser
  nad syntetickou DB; kasa, hotovost, čekající QR/karta, kelímky, storna, uzávěrka a spotřeba podle grantu PASS.
  Produkční testovací finanční záznamy nevznikaly.
- [Zveřejnění nabídky 1](BAR_MENU_PUBLICATION_2026-11-06.md): vydání 1, 45 produktů, fotografie;
  [publikační protokol](verification/bar-menu-publication-20261106/RESULTS.md) obsahuje živé ověření,
  snímky a konkrétní meze přejímky. Nabídku znovu nezakládat ani bez důvodu nepublikovat podruhé.

## Otevřené kroky v pořadí pokračování

| Krok | Stav a podmínka dokončení | Související dokument |
| --- | --- | --- |
| Skutečné přihlášení Lucky | NOT RUN; vlastník/obsluha použije původní heslo přímo v KIS, heslo neposílat do chatu | [Přejímka](verification/bar-acceptance-20261106/RESULTS.md) |
| První skutečné zásoby | NOT RUN; fyzická inventura a doložený příjem stánku/předskladu, bez odhadu z produktového katalogu | [Provozní připravenost](BAR_PRODUCTION_READINESS_2026-11-05.md) |
| Vlastní kasa a první skutečný prodej | NOT RUN; skutečný počáteční vklad, prodej obsluhou a odsouhlasení uzávěrky; testovací částky nepřenášet | [Provozní přejímka](BAR_OPERATIONAL_ACCEPTANCE_2026-11-06.md) |
| Reálná karta/QR a vratka | NOT RUN; doložená skutečná úhrada/vratka, oddělit ruční potvrzení od automatického poskytovatele | [Účetní kontrakt](BAR_ACCOUNTING_CONTRACT_V1.md) |
| Účetní propojení BAR | PLÁN; schválit konkrétní účetní mapu, daňová a oceňovací pravidla, identifikátory a vypořádání; implementace a aktivace samostatně | [Plán adaptéru](BAR_ACCOUNTING_ADAPTER_PLAN_2026-11-05.md) |

Zveřejněné menu přebírá aktuální ceny a popisy z katalogu a určuje výběr i pro pokladnu.
Publikace neotevírá kasu, nepřijímá zásoby a nedokládá zaplacení nebo zaúčtování.
Tiskový PDF lístek zůstává chráněný pro správu baru; veřejná je obrazovka a její povolené fotografie.

## Ověření této session

Produkční publikace a veřejné fotografie mají [živé HTTP důkazy a snímky](verification/bar-menu-publication-20261106/RESULTS.md).
Místní dokumentační kontrakt: 2 testy / 14 assertions PASS, kontrola CURRENT_STATE a historie PASS,
místní odkazy všech změněných Markdown souborů i `git diff --check` bez chyby.
Tato větev mění pouze instrukce, dokumentaci a bezpečné důkazy; nasazení aplikačního kódu se neprovádí.
Předání změny: [PR #335](https://github.com/KovoPraha/evidenceTreninku/pull/335), aktuální CI a stav sloučení jsou u tohoto PR. Souběžný provozní handoff upozornění #333 byl při navázání na main zachován; společný plán obsahuje obě oblasti.

## Před dalším předáním

Zkontrolovat aktuální origin/main a [CURRENT_STATE](CURRENT_STATE.md), neodvozovat nasazení ze staré větve.
Pokračovat prvním neprovedeným provozním krokem s konkrétními skutečnými podklady;
po každém kroku aktualizovat tento handoff, příslušný plán a protokol podle [souborového pravidla](WORK_DOCUMENTATION_POLICY.md).
