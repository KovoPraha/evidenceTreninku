# Návrat integrovaného vydání — 25. 10. 2026

Tento postup je připravený pro vydání `115a617d3660bc9e3cba48891737b56fc6ab068f`.
Není záznamem provedeného návratu. Účetní záznamy, osoby, soubory a objednávky
se při běžném návratu kódu neobnovují ze starého dumpu.

## Ověřené podklady

- Předchozí živá verze: `6bf57d6fe4c20fd9d9e182d3bde4253b1abc4a51`, deploy
  `40131981680`. Předletová čtecí kontrola ověřila metadata a všechny kontrolované
  otisky 54 cest v živém i uloženém předchozím release, včetně očekávané absence
  nových souborů. Úložiště release: `data/.kis-deploy/releases/<sha>-<run_id>`.
- [Připravená návratová větev](https://github.com/KovoPraha/evidenceTreninku/tree/codex/integration-rollback-ready-20261025):
  `4a7485c74db10a765478bc2f69cb6cc790641d3e`. Má integrovaný main jako předka,
  takže případný PR skutečně vrací změněné aplikační soubory; nejde o opětovné
  sloučení staré větve, které by nové chování ponechalo.
- Aplikační a testovací obsah odpovídá `2f2f6004`; pozdější změny jsou pouze
  historie Git a zachování aktuální dokumentace. [CI návratové varianty](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40189347280):
  **1 685 testů / 33 638 assertions PASS**, MariaDB **10.3 a 11.4 PASS**, na každé
  navíc 23 kontrol zachování plateb a migrační evidence.
- [Obnova předchozí produkční zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40188912376)
  do oddělené MariaDB ověřila checksum, manifest, počty všech **330 tabulek**
  a oba triggery. Nešlo o přepsání produkce. Konkrétní nová záloha před tímto
  vydáním a její následné ověření jsou uvedené v připojeném protokolu nasazení.

## Proč nepoužít prostý deploy starého commitu

Vydání přidává dopřednou migraci
`20261024200000_payment_variable_symbol_index_repair`. Starý migration runner
by její existující položku v ledgeru odmítl jako neznámou. Návratová větev proto
vrací předchozí aplikaci, ale zachovává přesné soubory této migrace,
`includes/payment_variable_symbol_schema.php` a kompatibilní migration runner.
Historická čísla a VS se nemění, ledger se nemaže a unikátní index VS se znovu
nevytváří. Nové kódy migrací nejsou při návratu znovu interpretovány jako import.

Deploy používá rsync bez `--delete`. Návratová větev proto přidává do úzkého
`bin/deploy-delete-manifest.txt` přesně **24 nových PHP souborů** integrace,
které ve starší aplikaci nemají zůstat dostupné. Seznam neobsahuje přílohy,
konfiguraci, databázová data ani zachované migrační soubory.

## Postup při závadě

1. Zaznamenat závadu, omezit dotčené zápisy a uchovat aktuální diagnostiku.
   Rozlišit chybu kódu od poškození dat; pokračující objednávky či webhooky
   nejsou automaticky zastavené tím, že se zavře administrační stránka.
2. Ověřit přesný živý SHA a aktuální `main`. Pokud od tohoto vydání přibyla
   další aplikační změna, tuto návratovou větev nepoužít bez nového porovnání.
   Pouhá změna dokumentace není důvod přepisovat provozní data.
3. Otevřít PR návratové větve do aktuálního main, zkontrolovat skutečný diff,
   zachování migračního katalogu a úzký seznam odstraněného kódu. Dodržet
   povinné CI kontroly a chráněné sloučení; žádný reset ani force push main.
4. Spustit standardní `deploy-production.yml` z main s `potvrzeni=NASADIT`,
   `uat_schvaleno=false`, `importovat_krouzky=false`. Workflow vytvoří další
   zálohu současného stavu, ověří migrace a zachování dosavadních VS. Žádné
   aktivace poskytovatelů, plánovačů ani doplňování skutečných dat.
5. Ověřit živý release, konfiguraci, anonymní přístupové hranice a čtecí
   databázové/účetní/skladové invarianty. Zkontrolovat odstranění nových cest
   a zachování všech zápisů vzniklých od nasazení.

## Obnova dat je samostatná větev řešení

Samotná chyba kódu není důvod pro SQL restore. Pokud by bylo poškozené i datum
či obsah záznamů, nejprve uchovat současný stav a určit rozdíl proti záloze,
včetně nových plateb, objednávek, souborů a příchozích událostí. Nejdříve obnovit
zálohu do izolace a připravit ověřenou nápravu. Přepsání starou databází bez
zachování nových zápisů není součástí běžného návratu a nesmí proběhnout automaticky.

Ověření v CI a izolaci není nácvik skutečné odstávky hostingu ani záruka RTO/RPO.
Čtyřhodinový/hodinový plánovací cíl D14 se tím nemění na provozní garanci.
