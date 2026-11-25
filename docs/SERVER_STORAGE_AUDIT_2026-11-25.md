# SERVER-STORAGE-01 — inventura kapacity hostingu (25. 11. 2026)

**Stav:** ROZPRACOVÁNO, čtecí inventura. **Vlastník:** Marek / provoz hostingu; technická analýza vývoj KIS. **Větev a worktree:** `codex/server-storage-audit-20261125`, `C:\Users\Marek\Documents\Codex\evidencePavel-server-storage-audit-20261125`, základ `origin/main` `dabb1d71`. [Registr](WORK_PLAN_CURRENT.md#server-storage-01--inventura-kapacity-hostingu-25-11-2026), [výsledky](verification/server-storage-20261125/RESULTS.md).

## Cíl, oprávnění a meze

Vlastník hlásí docházející místo na `kovopraha.cz` a požaduje zjistit velikost aplikace a odstranit zbytečné soubory. Výslovně souhlasí s odstraněním vlastních záloh, pokud zálohování zajišťuje hosting. Tento pokyn sám nepotvrzuje rozsah, obnovitelnost ani stáří hostingových záloh. Nejprve čtecí soupis kvóty, aplikačních stromů, release adresářů, záloh, soukromých příloh, logů a databáze. Složky sdílené s Velocotou posuzovat zvlášť.

## Postup a varianty

1. Spustit již existující chráněný čtecí provozní drill `overit-dopad-kluboveho-vydani`; zaznamenat jeho omezení a souhrn bez osobních dat.
2. Získat rozpis hostingu po souborech a DB; ověřit, zda SSH chroot ukazuje úplnou kvótu. Výsledky s bezpečnými agregáty uložit do protokolu.
3. Pro každého kandidáta k úklidu doložit velikost, vlastníka, vazbu na běžící aplikaci a obnovu. U záloh před odstraněním ověřit hostingový snapshot, retenci a možnost obnovy konkrétní DB i příloh. U release adresářů ověřit aktivní/rollback verzi. Při nejistotě ponechat.
4. Připravit konkrétní reverzibilní dávku a následně změřit volné místo i funkčnost. Trvalé odstranění produkčních dat provádět pouze proti ověřenému manifestu a přes chráněný postup.

Možné varianty: redukce počtu starých release, přesun nebo odstranění vlastních záloh po ověření hostingové obnovy, vyčištění prokazatelně dočasných souborů, změna retenční politiky. Soukromé přílohy a kanonické DB řádky nejsou obecní kandidáti k mazání. Předchozí [obnova místa ve Velocotě](verification/db-temp-recovery-20261030/reclaim-result.json) uvolnila 3,35 GB dne 30. 10.; není to dnešní měření ani povolení opakovat zásah.

## Akceptace a další krok

PASS: aktuální měření celku a hlavních kategorií, seznam konkrétních kandidátů s odhadovanou úsporou, doložené zálohování/obnova, stav před a po bezpečném úklidu, kontrola služby. Dokud chybí dnešní serverové měření, uvolněné místo = **NOT RUN**. Další krok: čtecí drill a analýza jeho výstupu.
