# P11 R06 — předání výběru osob, 7. 11. 2026

HOTOVO: #366 commitnuté/sloučené, shoda výsledků a nové společné měření s #367,
schválené nasazení i čtecí kontrola. [Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[integrovaná parita/měření](verification/attendance-person-picker-20261107/integrated/RESULTS.md),
[produkční protokol a přesné vydání](verification/attendance-picker-release-20261107/RESULTS.md).

- 50 výsledků plus všichni vybraní; hosté zachovaní, počet účastníků bez limitu 50.
- Jeden společný nativní formulář přenáší obě rozepsané části. Původní API,
  CSRF, verze/původ a kanonické zápisy/transakce zůstávají.
- Nový stejný seed před/po s aktuálním #367: 10221 osob / 205 stran obou rolí,
  540 HTTP časů / 78 Chrome, 13 checksumů. Vybrané hodnoty i úplná množina osob stejné.
- HTML 1368679 → 91620 B (−93,3 %), HTTP trenéra 48,40 → 27,87 ms,
  Chrome warm 445,65 → 82,60 ms. Syntetický Linux CI, nikoli produkční SLA.
  SQL trenéra 56 → 72; jde hlavně o přenos/DOM. Rows examined neprokázané.
- 44 HTTP kontrol a následné oddělené syntetické writer UAT/revokace PASS.
  Původní JS/noJS a 390 px mají dřívější důkazy nad shodnými UI zdroji.
- Linux 2362 testů / 46386 assertions a obě MariaDB PASS; všech 12 kontrol
  hlavy 50a0c811 SUCCESS. Osm hashů aplikace odpovídá #366 i nasazené verzi.
- Produkční veřejný katalog 5/5 shodný včetně normalizovaného DOM, soukromá trasa
  anonymně 302 login. Přihlášené produkční picker UAT NOT RUN, skutečné zápisy se neprováděly.

Původní WIP v htdocs i původní picker worktree/výstupy zachované. Dokončení
`codex/attendance-picker-completion-20261107`, vlastní worktree stejného názvu v Documents/Codex.
Linux prostředí uklizené. Vlastní neúspěšný Windows Aria datadir zůstává zastavený;
neúspěšné kalibrace jsou v protokolu, žádný pokus o plošné/rekurzivní mazání.
Přijaté #370 priority/obnova a registry zachované; výsledkové sdílení [#372](https://github.com/KovoPraha/evidenceTreninku/pull/372).

Nasazení neopakovat. Release/flags vlastní [CURRENT_STATE](CURRENT_STATE.md).
Další P11 jsou R13/R05, rows examined a fyzický mobil/souběh; [registr](WORK_PLAN_CURRENT.md), K0.2 otevřené.

Navazující rozšířené Windows měření všech9scénářů HOTOVO: [předání](SESSION_HANDOFF_ATTENDANCE_HTTP_2026-11-07.md), [důkazy](verification/attendance-integrated-http-20261107/RESULTS.md). Má jiný baseline než Linux #372, 234Chrome/540HTTP a čerstvé JS/noJS. Obě studie a provozní důkaz zůstávají samostatné; žádné nové nasazení touto měřicí relací.
