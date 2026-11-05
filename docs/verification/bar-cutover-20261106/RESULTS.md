# Skutečný převod BAR do KIS

6. 11. 2026 Europe/Prague. Navazuje výslovné povolení nasazení a pokračování v chatu. Vlastník určil pro správu baru i vlastní spotřebu prozatím pouze svůj existující účet; přihlášený Chrome ověřil přesné přihlašovací jméno před přidělením. Lucka zůstává pouze obsluhou. Zdrojová data ani původní účty baru se neměnily.

## Dokončené produkční operace

| Operace | Důkaz |
| --- | --- |
| Čerstvá úplná záloha a skutečná izolovaná obnova | [42126301391](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42126301391), 385 tabulek / 2 triggery, 4464 fyzických souborů |
| Nový stabilní soukromý balíček a nácvik skutečných dat | [42127720339](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42127720339), [inventura](prepare.json), [izolovaný import a bezpečné opakování](rehearsal.json) |
| Čtecí kontrola produkčního cíle a konkrétního správce | [42127994924](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42127994924), [výsledek](inspect.json) |
| Skutečný jednorázový import a auditovaná přístupová práva | [42128255522](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42128255522), [výsledek](apply.json) |
| Samostatný následný čtecí status | [42128603361](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42128603361), [přesná parita](status.json) |

Veškeré běhy SUCCESS z main v chráněném prostředí. Pomocníky #322, #324–#327 ověřilo vždy všech 11 kontrol před sloučením. Nemění nasazenou webovou aplikaci: živá verze zůstává stejná jako při [vydání modulu](../bar-release-20261105/RESULTS.md). Soukromý skutečný balíček ani zdrojové obrázky, konfigurace, SQL nebo přihlašovací hash nejsou součástí Gitu či veřejných artefaktů.

Balíček SHA-256 `69ab2afa528e87261cd23171eacdb07e3e40c108fabd532bcedc9c0bd755589d`. Přesně **68 produktů, 5 kategorií, 0 receptur, 51 fotografií / 107625689 B**. Všechna katalogová pole, archivace, ceny a soubory souhlasí; předimportní i poimportní čtení potvrzují stabilní zdroj. Počáteční sklad i barová provozní historie jsou nulové, časový kontrakt `utc-v1`.

Pouze Lucka, technický e-mail `info1@kovopraha.cz`, pozice `bar_operator`, bez zákaznického propojení, správce, superadmina nebo vlastní spotřeby. Její moderní původní hash je převzat přesně; heslo se neměnilo a žádné nové tajemství nevzniklo. Samostatně se přidaly pouze schválenému existujícímu správci `bar_admin` a explicitní `own_consumption`, s auditem a zachováním ostatních pozic a výchozího nastavení. Status prokazuje, že žádný další explicitní příjemce těchto dvou práv neexistuje. Původní účty adminů ani `trebe` se nepřeváděly.

## Bod návratu

SQL `evidence_2026-11-05_230139_0993efbf.sql.gz`, SHA-256 `b6372b8f7d251eee44b4d15760742e9d086127e3ec86947e996e57d8128134c4`. ZIP `kis-files-42126301391.zip`, **613560107 B**, SHA-256 `0f2c350f0b82b6c1c72733a9a260dfbcbf07c3bcad746e71929e55cf3d1ac769`. Před importem se ověřil čerstvý soubor, oba otisky, prázdné BAR tabulky v manifestu i úspěšný běh obnovy. ZIP obsahuje aplikaci včetně konfigurace a celé soukromé úložiště; na runneru byly skutečně obnoveny všechny fyzické soubory a ověřeny jejich velikosti i otisky. Dvě dříve chybějící přílohy původní Evidence tím nevznikly; nejde o BAR fotografie.

Po prvním skutečném prodeji nebo skladové změně **neopakovat počáteční import ani neobnovovat starou DB automaticky**. Přísný status je důkazem počátečního převodu, nikoli požadavkem zachovat navždy nulové zásoby.

## Přihlášené rozhraní a meze

Skutečný Chrome pod existujícím vlastníkem ověřil naplněný katalog včetně kategorií, cen, archivace a nulových skladů. Pozice Obsluha načetla pokladnu, která správně vyžaduje vlastní kasu. Vlastní spotřeba je nyní dostupná s převedenými produkty a fotografiemi; nic se neodesílalo, neotevírala se kasa ani neprováděl odběr. Snímek `output/playwright/bar-consumption-grant-20261106.png` zachycuje oprávnění a skutečné fotografie. Původní pracovní kontext Finance byl obnoven.

Přihlášení Lucky jejím heslem a lidská finanční přejímka dosud neproběhly. Testy hashe a oprávnění je nenahrazují. Neaktivovalo se nové účtování baru, bankovní párování ani poskytovatel; stávající Fio, SumUp a pošta členství se neměnily.

## Následné přepnutí starých URL

Připravena samostatná chráněná operace `bar-legacy-url.yml`: vyžaduje úspěšný čerstvý status konkrétního převodu, prokáže skutečný webroot podle podávaného service workeru a vytvoří úplný soukromý archiv staré aplikace. Před přepnutím skutečně obnoví každý soubor do izolace. Staré nasazovací a účtové produkční workflow musí být před přepnutím pozastavené, aby nemohlo obnovit staré zapisující stránky.

GET/HEAD používají dočasné 302 na pevné adresy KIS bez starých parametrů a s `no-store`; API i ostatní metody končí 410 bez přeposlání požadavku. Obaly vůbec nenačítají původní konfiguraci/session/databázi. Původní databáze, includes i obrázky zůstávají zachované; každý měněný vstupní soubor má soukromý původní otisk a zálohu, kořenový index se přepíná poslední. Změna souboru od zálohy zastaví zápis. Neurčitý či částečný výsledek se čte přes status, nikdy se automaticky neopakuje writer. Přesměrování zatím nebylo provedeno.
