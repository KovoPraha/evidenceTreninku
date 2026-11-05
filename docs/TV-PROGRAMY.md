# TV programy

Modul spravuje veřejné informační carousely na televizích v areálu. Pracovní
pozice **Obsluha TV** má přístup ke všem programům. Správce systému ji přiřazuje
v administraci pracovních pozic; migrace ji běžným uživatelům sama nepřiděluje.

## Příprava vysílání

1. Přepněte pracovní pozici na **Obsluha TV** a otevřete **TV programy**.
2. Vytvořte pojmenovaný program, například Recepce nebo Bar.
3. Přidejte jeden či více snímků. Nastavte pořadí přetažením nebo šipkami a
   délku jednotlivých snímků v sekundách (výchozí hodnota 10).
4. Nahrajte obrázek, případně přidejte textové bloky. Vyberte zobrazení celého
   obrázku nebo vyplnění plochy ořezem. Text může být i bez obrázku.
5. Každému textovému bloku nastavte velikost písma, barvu, zarovnání a podklad.
   Blok přesunete myší nebo zadáním X/Y. Šířku upravíte modrým úchytem nebo
   číselně. Vybraný blok lze posouvat šipkami (se Shiftem po deseti bodech).
6. **Uložit koncept** uloží práci bez změny veřejného programu.
   **Přehrát náhled** ukáže aktuálně rozepsaný carousel včetně neuložených změn.
7. **Zveřejnit změny** současně uloží koncept a vydá jeho novou veřejnou verzi.
   Vypnuté snímky se nezveřejní. Odkaz je veřejný bez přihlášení.

## Odkazy na televizích

Adresa `https://kis.kovopraha.cz/tv/1` přehrává program číslo 1. Čísla přiděluje
aplikace a nemění se při přejmenování programu. Nejde o čísla fyzických TV:
stejnou adresu lze otevřít na libovolném počtu televizí. Sdílí obsah a pořadí,
ale začátek smyčky ani okamžiky přepnutí nejsou mezi zařízeními synchronizované.

Obraz má poměr 16 : 9 a návrhové plátno 1920 × 1080. Všechny rozměry v editoru
jsou body tohoto plátna. Celé plátno se proporcionálně přizpůsobí obrazovce;
jiný poměr stran přidá černé okraje. Text přesahující spodní okraj editor označí.
Pořadí textových bloků určuje jejich vrstvení: později přidaný blok je navrchu.

Přehrávač kontroluje novou verzi každých 15 sekund a převezme ji na nejbližším
konci právě zobrazeného snímku. Jeden snímek zůstává zobrazený bez opakovaného
překreslování. Prázdný zveřejněný program zobrazuje neutrální obrazovku KOVO
Praha. Chybějící, nezveřejněný nebo archivovaný program vrací HTTP 404, také
zobrazuje neutrální obrazovku a dál kontroluje případné zveřejnění.

Tlačítko **Celá obrazovka** se ukáže po pohybu ukazatele nebo stisku klávesy.
Po třech sekundách nečinnosti se skryje. Prohlížeč musí fullscreen podporovat;
automatický start do fullscreen či kiosku po zapnutí je nastavením konkrétní TV.
Offline režim, video, externí stránky a kalendář vysílání nejsou součástí modulu.

## Historie a archivace

Editor uchovává historii zveřejněných verzí a audit změn. Starší verzi lze
načíst do konceptu a následně znovu zveřejnit. Archivace zastaví veřejnou
dostupnost programu; již otevřený přehrávač přejde na neutrální obrazovku při
další kontrole a konci snímku. Obnovení programu z archivu vyžaduje nové
zveřejnění. Veřejné číslo se zachová.

Souběžné ukládání chrání číslovaná verze konceptu. Starší otevřený editor
nepřepíše cizí novější práci a ukáže výzvu k obnovení stránky.

## Technické předání

- Migrace `20261104180000_tv_programs` vytváří pět vlastněných tabulek a pozici
  `tv_operator`. Stávající migrace zůstávají neměnné.
- Administrace `tv_programs_admin.php` vyžaduje aktivní pozici Obsluha TV a
  CSRF pro všechny změny včetně nahrávání. Superadmin do této pozice přepíná.
- Apache přepisuje `/tv/{kladné číslo}` na veřejný přehrávač `tv.php`. Koncové
  lomítko je podporované, dodané query parametry nenahrazují číslo v cestě.
- `tv.php?id=1&format=json` vrací jen aktuální veřejnou verzi. Obsluha zařízení
  nepotřebuje zaměstnanecké přihlašovací údaje a přehrávač nezakládá session.
- `tv_image.php?id=…` vydá obrázek pouze při použití v aktuální zveřejněné verzi
  některého nearchivovaného programu. `preview=1` vyžaduje přihlášenou obsluhu.
- JPG/PNG/WEBP/GIF (nejvýše 5 MB, rozměr nejvýše 6000 v každém směru a celkem
  24 milionů bodů) se dekódují a znovu ukládají jako JPEG bez metadat. Průhledné
  pozadí se převede na bílé; animace je statická. Obrázky leží mimo webroot v
  `APP_PRIVATE_STORAGE_ROOT/tv-images`. Na tuto složku se vztahuje souborové
  zálohování soukromého úložiště; samotná databázová záloha obrázky neobsahuje.
- Databázový backup ownership kontrakt zahrnuje všechny TV tabulky.
  Staré obrázky a verze se automaticky nemažou, aby zůstal možný návrat.
- Limity: 100 snímků na program, 20 textových bloků na snímek, 4000 znaků na
  blok, délka snímku 1–3600 sekund. Texty jsou prostý text, nikoli HTML/JS.

Před nasazením použijte standardní CI, databázovou a souborovou zálohu,
verzované migrace a samostatný produkční deploy. Před provozním převzetím
ověřte čitelnost a fullscreen na fyzické TV; test v desktopovém prohlížeči
nenahrazuje tento krok.
