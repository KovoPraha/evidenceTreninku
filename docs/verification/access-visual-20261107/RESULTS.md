# ROLE01-VIS — vizuální produkční kontrola

7. 11. 2026, Europe/Prague. [Plán a oprávnění](../../ACCESS_OVERVIEW_2026-11-07.md).
Skutečný přihlášený Chrome, produkční stránka
`https://kis.kovopraha.cz/sprava_pracovnich_pozic.php`, kontext Správce systému.
Větev `codex/access-visual-uat-20261107`, základ `5cca5526`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-access-overview-20261107`.

## Výsledek

Vizuální průchod dokončený. Desktop je přehledný; mobilní přehled je dostupný
s vodorovným posunem, ale jeho čitelnost má výhradu. Editor se na mobilu skládá
do čitelných karet. Nejde o bezvýhradnou přejímku pohodlí mobilního přehledu.

| Kontrola | Výsledek a důkaz pozorovaný v prohlížeči |
| --- | --- |
| Desktop, viewport 1536 × 730 | PASS: přehledné nadpisy, skupiny rolí vedle sebe, zvláštní granty oddělené. Všech sedm sloupců se vejde; tabulka měla clientWidth i scrollWidth 1262 px. |
| Filtr oblasti Bar | PASS: odeslaný GET formulář, 6 z 48 účtů; zahrnuje superadminy s dostupnými rolemi. Žádný zápis oprávnění. |
| Hledání #64 + deaktivované účty | PASS: 1 z 48, již existující neaktivní technický účet po předchozí přejímce; bez rolí a bez vlastní spotřeby. Nevznikl nový účet. |
| Mobil 390 × 844 | PASS s výhradou: menu se sbalí, filtry se skládají pod sebe; tabulka potřebuje posun do stran. U skutečných účtů s více rolemi se štítky výrazně zalamují a řádky jsou vysoké. |
| Vodorovný posun | PASS: skutečným posunem doprava se zobrazí zvláštní oprávnění a tlačítko Upravit přístupy. Jméno při posunu odjede mimo obrazovku. |
| Otevření editoru | PASS: klik na Upravit přístupy technického účtu #64 otevřel editor a ukotvil stránku do jeho sekce. Formuláře neodeslány. |
| Editor 390 × 844 a 360 × 800 | PASS pro zobrazení: jednotlivé role s popisy čitelné pod sebou, spodní části formuláře dostupné svislým posunem. Ovládací prvky nebyly měněny. |
| Přehled 360 × 800 | PASS s výhradou: filtry čitelné a dostupné; tabulka zůstává široká a vyžaduje vlastní vodorovný posun. |
| Návrat velikosti prohlížeče | PASS: dočasná změna viewportu zrušena, desktop znovu vizuálně ověřen, nová kontrolní karta zavřena. |

## Zjištění a hranice

- Doporučené zlepšení: na mobilu zobrazit osobu a její role jako kartu, případně
  při vodorovném posunu ponechat jméno viditelné. Jde o návrh další úpravy,
  nikoli implementovanou nebo nově nasazenou změnu.
- Použita emulace rozměrů v desktopovém Chrome, nikoli fyzický telefon či Safari.
- Průchod byl pouze čtecí: navigace, filtry GET, posuny a otevření editoru.
  Žádné uložení, aktivace, změna role, výchozí pozice, grantu ani superadmina;
  žádné přepnutí pracovní role a žádné založení účtu. Nebyla opakována zapisující
  produkční přejímka. Ta má samostatný [starší protokol](../access-release-20261107/RESULTS.md).
- Spojení s první kontrolní kartou se během posunu přerušilo. Po obnovení
  v nové kartě byl posun i editor skutečně dokončen a vizuálně zkontrolován.
- Snímky byly prohlédnuté přímo v nástroji. Obrazy skutečných jmen/kontaktů
  nebyly vloženy do Gitu; protokol obsahuje jen bezpečný souhrn.
- Aplikační kód, schéma a konfigurace beze změny; žádný další deploy.
  Dokumentační kontrola: `php tools/check-current-docs.php`, `git diff --check`.

## Předání

Výsledky připravené pro dokumentační PR z výše uvedené větve; sdílený stav
určuje skutečné přijetí PR. [Deník](../../EVENT_LOG.md),
[rozhodnutí a uzavřené práce](../../DECISION_LOG.md).
