# SERVER-STORAGE-01 — výsledky

## Správa celé domény 25. 11. 2026

Přihlášená [správa hostingu kovopraha.cz](https://muj.cesky-hosting.cz/index.php?page=domeny-statistiky&id_domain=70155) ukázala oddělené kvóty. Údaj WWW byl ručně aktualizován tlačítkem „Aktualizovat údaje“ a stránka potvrdila aktualizaci; ostatní údaje jsou poslední hodnoty z administrace.

| Segment domény | Obsazeno / limit | Závěr |
| --- | --- | --- |
| WWW prezentace (FTP), všechny subdomény a další adresáře domény | **22 773 / 20 000 MB**, 996 361 souborů | **2 773 MB nad limitem**; vlastní soubory a release kopie hledat zde. |
| E-maily | 5 669 / 9 330 MB, 36 418 / 93 300 souborů | Mimo limit WWW; samostatná kvóta má 3 661 MB rezervu. |
| Databáze | 70 / 500 MB | Mimo limit WWW; přibližně 430 MB rezervy. |

[Hlavní SFTP/SCP/SSH účet](https://muj.cesky-hosting.cz/index.php?page=domeny-ftp&id_domain=70155) dle administrace dosáhne do adresářů všech subdomén domény. [Webserver](https://muj.cesky-hosting.cz/index.php?page=domeny-webserver&id_domain=70155) uvádí hlavní web a 13 subdomén včetně KIS, baru, registrace, výsledků a závodů. Nový drill proto vypíše součet dostupného účtu, všechny položky první úrovně včetně skrytých a detail `data/`; po běhu se musí porovnat s kvótou hostingu. Fyzické disky sdíleného serveru ani jiné domény téhož zákazníka nejsou součástí této kvóty.

V [administraci hostingových záloh](https://muj.cesky-hosting.cz/index.php?page=domeny-zalohy&id_domain=70155) jsou pro tuto doménu skutečně dostupné seznamy 11 WWW generací (31. 10.–24. 11. 2026, nejnovější 24. 11. 23:31) a 11 MariaDB generací (28. 10.–24. 11. 2026, nejnovější 24. 11. 22:14). Připojení generace a izolované ověření souborů, soukromých příloh i DB **NOT RUN**; samotný seznam nedokazuje úplnost ani obnovitelnost.

## Čtecí průběh 25. 11. 2026

| Kontrola | Výsledek | Omezení |
| --- | --- | --- |
| [Existující provozní drill 45607308483](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607308483), `overit-dopad-kluboveho-vydani` na `main` | PASS; pouze čtení, dočasné diagnostické skripty uklizeny | Při úspěchu nevypisuje inventuru disku; žádné dnešní velikosti z něj nelze odvodit. |
| [Diagnostický drill 45607924536](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607924536) z pracovní větve | FAIL před spuštěním jobu: ochrana prostředí `production` nepovoluje tuto větev | Ochranu neobcházet; nový čtecí krok musí projít PR/merge do `main`. |
| Místní worktree `origin/main` `dabb1d71` | `docs/` 147,3 MiB; `assets/` 15,0 MiB; `includes/` 4,1 MiB; 246 souborů v kořeni 12,25 MiB | `docs/` se v obou krocích produkčního rsync explicitně vylučuje; místní worktree není měření produkce. Místní `vendor/` v jiném checkoutu 117,4 MiB obsahuje i vývojové závislosti, neslouží jako velikost nasazení. |
| Historický [diagnostický JSON z 30. 10.](../refactoring-final-release-20261030/post-failure-diagnostics.json) | 184 release adresářů; 9 z nich změřeno na 290,9 MiB dohromady, medián 28,6 MiB; 60 souborů přímo v `.kis-backups` 116,0 MiB | Inventura release byla `inventory_complete=false` a zálohy počítala jen na první úrovni. Celkovou velikost tehdejších ani dnešních záloh z ní nelze odvodit. |
| Úplné souborové zálohy KIS | [Archiv 5. 11.](../bar-release-20261105/RESULTS.md) 613 288 913 B, [archiv 6. 11.](../bar-cutover-20261106/post-cutover-backup.json) 720 738 853 B | ZIP se ukládá do `.kis-backups/files/` a obsahuje aplikaci i soukromé soubory. Starší diagnostika tuto podsložku vůbec nepočítala; počet dosud uložených ZIPů není změřen. |
| Deploy workflow | Každý deploy připravuje úplný release v `data/.kis-deploy/releases/<sha>-<run>`; v obou rsync krocích vylučuje `docs/`; záloha používá `BACKUP_KEEP=20` | V workflow není doložená pravidelná retence release adresářů. Aktivní/rollback kopie nejsou zatím inventarizované. |

Český hosting uvádí [noční zálohování souborů, databází a e-mailů](https://www.cesky-hosting.cz/napoveda/zalohovani-a-obnoveni-dat/), 7 denních a nejméně 3 týdenní generace. Výjimky: adresáře `cache`, `sessions`, `temp` a soubory větší než 1 GB. Zálohu lze připojit v klientské sekci pro obnovu. [Kvóty WWW, e-mailů a DB jsou oddělené](https://www.cesky-hosting.cz/napoveda/objednavky-a-zrizeni-sluzeb/doplnkove-sluzby/); přihlášená administrace nyní potvrdila, že nad limitem je WWW. Dostupnost generací je ověřena výpisem, jejich obsah a obnova **NOT RUN**.

**Předběžní kandidáti:** staré release kopie po ověření aktivní a rollback verze; úplné ZIP archivy i DB zálohy podle přesného seznamu a ověřené hostingové obnovy; prokazatelně dočasné skripty. Soukromé přílohy, produkční DB a novější release nejsou kandidáti bez samostatného důkazu. Dnešní kvóta je změřená v administraci; rozpis WWW adresářů, uvolněné místo a produkční funkční kontrola **NOT RUN**.
