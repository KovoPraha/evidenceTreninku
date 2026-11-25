# SERVER-STORAGE-01 — výsledky

## Čtecí průběh 25. 11. 2026

| Kontrola | Výsledek | Omezení |
| --- | --- | --- |
| [Existující provozní drill 45607308483](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607308483), `overit-dopad-kluboveho-vydani` na `main` | PASS; pouze čtení, dočasné diagnostické skripty uklizeny | Při úspěchu nevypisuje inventuru disku; žádné dnešní velikosti z něj nelze odvodit. |
| [Diagnostický drill 45607924536](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45607924536) z pracovní větve | FAIL před spuštěním jobu: ochrana prostředí `production` nepovoluje tuto větev | Ochranu neobcházet; nový čtecí krok musí projít PR/merge do `main`. |
| Místní worktree `origin/main` `dabb1d71` | `docs/` 147,3 MiB; `assets/` 15,0 MiB; `includes/` 4,1 MiB; 246 souborů v kořeni 12,25 MiB | `docs/` se v obou krocích produkčního rsync explicitně vylučuje; místní worktree není měření produkce. Místní `vendor/` v jiném checkoutu 117,4 MiB obsahuje i vývojové závislosti, neslouží jako velikost nasazení. |
| Historický [diagnostický JSON z 30. 10.](../refactoring-final-release-20261030/post-failure-diagnostics.json) | 184 release adresářů; 9 z nich změřeno na 290,9 MiB dohromady, medián 28,6 MiB; 60 souborů vlastních záloh 116,0 MiB | Inventura release byla `inventory_complete=false`; nelze z ní vypočítat přesnou tehdejší ani dnešní celkovou velikost. |
| Deploy workflow | Každý deploy připravuje úplný release v `data/.kis-deploy/releases/<sha>-<run>`; v obou rsync krocích vylučuje `docs/`; záloha používá `BACKUP_KEEP=20` | V workflow není doložená pravidelná retence release adresářů. Aktivní/rollback kopie nejsou zatím inventarizované. |

Český hosting uvádí [noční zálohování souborů, databází a e-mailů](https://www.cesky-hosting.cz/napoveda/zalohovani-a-obnoveni-dat/), 7 denních a nejméně 3 týdenní generace. Výjimky: adresáře `cache`, `sessions`, `temp` a soubory větší než 1 GB. Zálohu lze připojit v klientské sekci pro obnovu. [Kvóty WWW, e-mailů a DB jsou oddělené](https://www.cesky-hosting.cz/napoveda/objednavky-a-zrizeni-sluzeb/doplnkove-sluzby/), proto čtecí SSH rozpis WWW sám neprokáže příčinu upozornění. Existence konkrétní použitelné hostingové generace pro tento účet je zatím **NOT RUN**.

**Předběžné pořadí kandidátů:** staré release kopie po ověření aktivní a rollback verze; vlastní DB zálohy podle přesného seznamu a ověřené hostingové obnovy; prokazatelně dočasné skripty. Soukromé přílohy, produkční DB a novější release nejsou kandidáti bez samostatného důkazu. Dnešní kvóta, velikosti, uvolněné místo a produkční funkční kontrola **NOT RUN**.
