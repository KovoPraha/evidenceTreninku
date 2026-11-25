# SERVER-STORAGE-01 — inventura kapacity hostingu (25. 11. 2026)

**Stav:** ROZPRACOVÁNO; [PR #530](https://github.com/KovoPraha/evidenceTreninku/pull/530) a [#531](https://github.com/KovoPraha/evidenceTreninku/pull/531) sloučeny, druhý čtecí drill PASS, úklid NOT RUN. **Vlastník:** Marek / provoz hostingu; technická analýza vývoj KIS. **Větev a worktree soupisu kandidátů:** `codex/server-storage-cleanup-plan-20261125`, `C:\Users\Marek\Documents\Codex\evidencePavel-server-storage-audit-20261125`, základ `origin/main` `a9bd46a1`. [Registr](WORK_PLAN_CURRENT.md#server-storage-01--inventura-kapacity-hostingu-25-11-2026), [výsledky](verification/server-storage-20261125/RESULTS.md).

## Cíl, oprávnění a meze

Vlastník hlásí docházející místo na `kovopraha.cz` a požaduje zjistit velikost aplikace a odstranit zbytečné soubory. Následně výslovně upřesnil, že kontrola má pokrýt **celou doménu, ne pouze KIS**. Souhlasí s odstraněním vlastních záloh, pokud zálohování zajišťuje hosting. Tento pokyn sám nepotvrzuje rozsah, obnovitelnost ani stáří hostingových záloh. Přihlášená administrace 25. 11. potvrdila WWW 22 773 / 20 000 MB, e-maily 5 669 / 9 330 MB, DB 70 / 500 MB a 11 vypsaných generací WWW i DB; jejich obsah/obnova zatím NOT RUN. Čtecí soupis musí pokrýt dostupný účet včetně všech subdomén, skrytých složek, release adresářů, záloh, soukromých příloh a logů. Složky sdílené s Velocotou posuzovat zvlášť.

## Postup a varianty

1. Spustit již existující chráněný čtecí provozní drill `overit-dopad-kluboveho-vydani`; zaznamenat jeho omezení a souhrn bez osobních dat.
2. Získat rozpis hostingu po souborech a DB; ověřit, zda SSH chroot ukazuje úplnou kvótu. Výsledky s bezpečnými agregáty uložit do protokolu.
3. Pro každého kandidáta k úklidu doložit velikost, vlastníka, vazbu na běžící aplikaci a obnovu. U záloh před odstraněním ověřit hostingový snapshot, retenci a možnost obnovy konkrétní DB i příloh. U release adresářů ověřit aktivní/rollback verzi. Při nejistotě ponechat.
4. Připravit konkrétní reverzibilní dávku a následně změřit volné místo i funkčnost. Trvalé odstranění produkčních dat provádět pouze proti ověřenému manifestu a přes chráněný postup.

Možné varianty: redukce počtu starých release, přesun nebo odstranění vlastních záloh po ověření hostingové obnovy, vyčištění prokazatelně dočasných souborů, změna retenční politiky. Soukromé přílohy a kanonické DB řádky nejsou obecní kandidáti k mazání. Předchozí [obnova místa ve Velocotě](verification/db-temp-recovery-20261030/reclaim-result.json) uvolnila 3,35 GB dne 30. 10.; není to dnešní měření ani povolení opakovat zásah.

## Akceptace a další krok

PASS: aktuální měření celku a hlavních kategorií, seznam konkrétních kandidátů s odhadovanou úsporou, doložené zálohování/obnova, stav před a po bezpečném úklidu, kontrola služby. [Čtecí výsledky](verification/server-storage-20261125/RESULTS.md) ukazují překročenou WWW kvótu celé domény a dva dominantní stromy: `.kis-deploy` 13,89 GiB a `.kis-backups` 4,70 GiB. KIS má 280 release adresářů; živý web je samostatná kopie aktivovaná rsyncem, nejnovější úspěšný deploy je `dabb1d71` / 45602767579. Před odstraněním je třeba úplný součet a seznam release, jasně ponechat návratové verze, ověřit obsah hostingové zálohy a schválit přesný manifest. Uvolněné místo a funkční kontrola po úklidu **NOT RUN**. Další krok: rozšířený čtecí drill s úplným seznamem release/záloh, konkrétní úklidová dávka, kontrola obnovy a teprve potom chráněný úklid.
