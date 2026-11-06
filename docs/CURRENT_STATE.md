# AktuĂˇlnĂ­ stav projektu

AktualizovĂˇno 6. 11. 2026 (Europe/Prague), po skuteÄŤnĂ©m pĹ™evodu katalogu BAR a schvĂˇlenĂ˝ch pĹ™Ă­stupĹŻ; dĹ™Ă­vÄ›jĹˇĂ­ nasazenĂ­ a aktivace zachovanĂ©.

- **[Omluvenky a nĂˇhrady jiĹľ nasazenĂ© a znovu ovÄ›Ĺ™enĂ©](verification/makeup-audit-20261106/RESULTS.md):** 6. 11. proĹˇlo 22 testĹŻ / 139 assertions, 23 HTTP scĂ©nĂˇĹ™ĹŻ a skuteÄŤnĂ˝ mĂ­stnĂ­ browser (omluva, ÄŤekĂˇnĂ­ na dochĂˇzku, rezervace a storno s vrĂˇcenĂ­m nĂˇroku). MobilnĂ­ zobrazenĂ­ a uloĹľenĂˇ historie PASS. Bez novĂ© chyby ÄŤi opravy, nenĂ­ potĹ™eba dalĹˇĂ­ nasazenĂ­. OvÄ›Ĺ™enĂ­ zĂˇpisĹŻ pouze na oddÄ›lenĂ˝ch syntetickĂ˝ch datech; pĹ™ihlĂˇĹˇenĂ© produkÄŤnĂ­ UAT se nynĂ­ neprovĂˇdÄ›lo.

- **[PravidelnĂ© zpracovĂˇnĂ­ ÄŤlenstvĂ­ i poĹˇta zapnutĂ©](MEMBERSHIP_MAIL_ACTIVATION_2026-11-05.md):** automatizace, plĂˇn/zĂˇpis a poĹˇta aktivnĂ­, dennÄ› 05:23 UTC. OstrĂ˝ apply --send `42108711699` PASS (0 ÄŤlenstvĂ­/pĹ™edpisĹŻ/dokladĹŻ/zprĂˇv, ĹľĂˇdnĂˇ chyba), kontrola `42108836694` potvrdila ĂşspÄ›ĹˇnĂ˝ heartbeat v 21:01:18 UTC. SkuteÄŤnĂ© doruÄŤenĂ­ pĹ™i prĂˇzdnĂ© frontÄ› neovÄ›Ĺ™enĂ©. ZĂˇvodnĂ­ tĂ˝m zĹŻstĂˇvĂˇ konceptem.

- **[KlubovĂ© tarify #316 nasazenĂ©](verification/membership-catalog-release-20261105/RESULTS.md):** vĂ­ce disciplĂ­n za jednu cenu, mÄ›sĂ­ÄŤnĂ­/roÄŤnĂ­ interval a formulĂˇĹ™ fakturaÄŤnĂ­ch ĂşdajĹŻ.
  ProdukÄŤnĂ­ **koncept ZĂˇvodnĂ­ tĂ˝m / 2200 KÄŤ mÄ›sĂ­ÄŤnÄ›**, varianta 951, kategorie ÄŚlenstvĂ­ v klubu; skupina 86 â†’ MTB 87 / Silnice 88 / DrĂˇha 89. PĹ™ihlĂˇĹˇenĂ˝ zĂˇpis a kontrola UI PASS.
  **Prodej zatĂ­m neaktivovanĂ˝:** chybĂ­ skuteÄŤnĂ© Ăşdaje a schvĂˇlenĂ­ vystavovatele, tarifu a publikace.
  Linux 2064/39368, obÄ› MariaDB matice a vĹˇech 11 kontrol PASS. Live `ad09174e`, deploy `42076138376` SUCCESS. StĂˇvajĂ­cĂ­ platby/VS a Fio/SumUp zachovanĂ©; novĂˇ automatizace i poĹˇta nĂˇslednÄ› zapnuty podle zĂˇznamu vĂ˝Ĺˇe.

- **BAR â†’ KIS: katalog, pĹ™Ă­stupy i domĂ©na pĹ™evedenĂ©:** [Protokol B7](verification/bar-cutover-20261106/RESULTS.md), import `42128255522` a nezĂˇvislĂ˝ status `42128603361` PASS. PĹ™esnÄ› 68 produktĹŻ / 5 kategoriĂ­ / 51 fotografiĂ­, zĂˇsoby a historie nula. Pouze lucka jako obsluha s pĹŻvodnĂ­m heslem; jedinĂ˝ schvĂˇlenĂ˝ sprĂˇvce mĂˇ explicitnĂ­ vlastnĂ­ spotĹ™ebu. ĂšplnĂˇ obnova KIS `42126301391` a 500 pĹŻvodnĂ­ch BAR souborĹŻ `42130849719` PASS. PĹ™epnutĂ­ `42131182982` a status `42131378682`: 44 HTTP kontrol; aliasy 14/14 i pĹ™ihlĂˇĹˇenĂ˝ Chrome PASS. PÄ›t pĹŻvodnĂ­ch produkÄŤnĂ­ch workflow pozastaveno. VeĹ™ejnĂˇ nabĂ­dka, lidskĂˇ finanÄŤnĂ­ pĹ™ejĂ­mka a ĂşÄŤtovĂˇnĂ­ BAR ÄŤekajĂ­ na samostatnĂ© dokonÄŤenĂ­.

- **[OstrĂ© Fio a SumUp zapnutĂ©](verification/live-payments-20261105/ACTIVATION.md):** karta pro 223 aktivnĂ­ch produktĹŻ / novĂ© objednĂˇvky, 8 starĂ˝ch plateb zachovĂˇno.
  **[Fio hostingovĂ˝ plĂˇn ovÄ›Ĺ™enĂ˝](verification/fio-followup-20261105/RESULTS.md):** dva skuteÄŤnĂ© importy, vĹˇech 45 pohybĹŻ bez shodnĂ©ho VS. Ĺ˝ĂˇdnĂ© vynucenĂ© pĹ™iĹ™azenĂ­; skuteÄŤnĂˇ placenĂˇ Ăşhrada stĂˇle nedoloĹľenĂˇ.

- **[PR #305 slouÄŤenĂ˝ a nasazenĂ˝](verification/functional-round10-release-20261105/RESULTS.md):** vratky zachovĂˇvajĂ­ objednĂˇvku a strĂˇnkujĂ­ Ăşplnou historii. Linux i deploy 2009/38764, HTTP pĹ™ed/po 48/48 a izolovanĂˇ obnova PASS. PĹ™ihlĂˇĹˇenĂ˝ ÄŤtecĂ­ Chrome PASS v dostupnĂ©m rozsahu; heslo/T07 vynechanĂ©.

- **[OsmĂ˝ funkÄŤnĂ­ prĹŻchod](verification/functional-uat-round8-20261104/RESULTS.md):** pĹ™Ă­prava obdobĂ­, hierarchie ke dni lekce, vÄ›kovĂ© nĂˇvrhy a strĂˇnkovĂˇnĂ­. SkuteÄŤnĂ˝ mĂ­stnĂ­ browser, 25 kontrol HTTP/model/persistence a cĂ­lenĂ© 21/190 PASS; vĹˇech 12 domĂ©novĂ˝ch snapshotĹŻ shodnĂ˝ch. Bez novĂ© chyby runtime.

- **[PĂˇtĂ˝](verification/functional-uat-round5-20261104/RESULTS.md) a [ĹˇestĂ˝ prĹŻchod](verification/functional-uat-round6-20261104/RESULTS.md):** 73 HTTP kontrol souhlasĹŻ, papĂ­rovĂ˝ch podkladĹŻ a vyĹ™Ă­zenĂ­ osob; nĂˇslednĂ˝ skuteÄŤnĂ˝ Chrome a 16 kontrol persistence PASS. Bez dalĹˇĂ­ chyby runtime; vĂ˝sledky jsou v nasazenĂ©m PR #295, heslo/T07 vynechanĂ©.

- **[TĹ™etĂ­ funkÄŤnĂ­ vlna slouÄŤena a nasazena](verification/functional-round3-release-20261104/RESULTS.md):** opravenĂ© nĂˇhledy ÄŤlenstvĂ­ a ÄŤeskĂ© ÄŤasovĂ© stavy rodinnĂ©ho pĹ™ehledu. PĹ™ihlĂˇĹˇenĂ˝ produkÄŤnĂ­ nĂˇhled PASS bez potvrzenĂ­ zmÄ›ny; rodinnĂˇ strĂˇnka naÄŤtena, ĂşÄŤet nemĂˇ propojenĂ© sportovce pro ovÄ›Ĺ™enĂ­ ĹˇtĂ­tkĹŻ. MĂ­stnĂ­ matice 119/119 PASS; ĂşplnĂ© UAT vĹˇech rolĂ­ nenĂ­ uzavĹ™enĂ©. Heslo/T07 nadĂˇle odloĹľenĂ©.

- **Evidence ÄŤlenĹŻ bez sezon: aplikace nasazena a schvĂˇlenĂˇ data pĹ™evedena.**
  [PĹ™evod 41542954307](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41542954307)
  potvrdil 435 vĂ˝chozĂ­ch zaĹ™azenĂ­ od **2. 11. 2026**, odstranÄ›nĂ­ 560 historickĂ˝ch
  intervalĹŻ a 560 importnĂ­ch udĂˇlostĂ­. Osoby, doklady a dosavadnĂ­ trenĂ©rskĂ© rozsahy zachovanĂ©.
  [SchvĂˇlenĂˇ mapa](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md) je ĹľivĂˇ: sedm provoznĂ­ch
  skupin / 41 soupisek a TEST / 3 soupisky. Rexonix zĂˇvodnĂ­, KDC a Bikros samostatnÄ› `other`.
  ÄŚtecĂ­ inventura 41545932556 potvrdila poÄŤĂˇtky vĹˇech 435 intervalĹŻ i neaktivnĂ­ technickou identitu bez oprĂˇvnÄ›nĂ­.
  BÄ›h skonÄŤil ÄŤervenÄ› aĹľ na HTTP timeoutu runneru po commitu; nĂˇslednĂ© nezĂˇvislĂ©
  HTTP 40/40 a ÄŤtecĂ­ inventura vĹˇech 44 soupisek / 8 skupin PASS.
  [DĹŻkazy a meze](verification/member-evidence-cutover-20261102/RESULTS.md).
  Jedna poslednĂ­ synchronizace ÄŤekĂˇ na novĂ˝ export, poÄŤĂˇteÄŤnĂ­ den se neposouvĂˇ.
  PĹ™ihlĂˇĹˇenĂ© UAT a E8 nejsou dokonÄŤenĂ©; pĹ™evodnĂ­ mapy a bod nĂˇvratu zĹŻstĂˇvajĂ­ zachovanĂ©.

- [DruhĂˇ funkÄŤnĂ­ vlna nasazena](verification/functional-round2-release-20261102/RESULTS.md): oprava pĹ™Ă­pravy localhost dema, [35 syntetickĂ˝ch PASS](verification/functional-uat-round2-20261102/RESULTS.md). Seed se v produkci nespouĹˇtÄ›l.

- **TrenĂ©rskĂˇ obnova hesla nasazena:** samostatnĂ˝ reĹľim obnovy, ĹˇifrovanĂˇ fronta,
  odvolĂˇnĂ­ relacĂ­ a vĂ˝hradnÄ› trenĂ©rskĂ˝ worker. [PĹ™Ă­prava pilotu](TRAINER_READINESS_2026-10-31.md).
  [OddÄ›lenĂ˝ syntetickĂ˝ pilot](verification/trainer-pilot-20261101/RESULTS.md) je zaloĹľen:
  trenĂ©r 56, soupiska 77, dvÄ› syntetickĂ© osoby, tĹ™etĂ­ mimo rozsah. Grant konÄŤĂ­
  15. 11. 2026 v 18:17:31 databĂˇzovĂ©ho ÄŤasu; obnova hesla zpĹŻsobilĂˇ.
  Audit 4. 11. potvrdil 30 aktivnĂ­ch pracovnĂ­ch ĂşÄŤtĹŻ s pozicĂ­ trenĂ©ra, 29 bez rozsahu.
  HistorickĂ© poÄŤty zpĹŻsobilosti k obnovÄ› urÄŤuje datovanĂˇ pĹ™Ă­prava pilotu.
  [ProvoznĂ­ auditnĂ­ identita](TRAINER_PILOT_OPERATIONAL_2026-11-01.md) je neaktivnĂ­ a bez oprĂˇvnÄ›nĂ­;
  veĹ™ejnĂ© ĂşÄŤty se nepropojovaly ani nepovyĹˇovaly. ĂšplnĂ© lidskĂ© UAT jeĹˇtÄ› neprobÄ›hlo.
  PĹŻvodnĂ­ testovacĂ­ e-mail transport pĹ™ijal a pĹ™Ă­jemce 31. 10.
  [potvrdil doruÄŤenĂ­](verification/trainer-recovery-20261031/mailbox-confirmation.json).
  [AutonomnĂ­ ovÄ›Ĺ™enĂ­ 1. 11. 23:42](verification/trainer-recovery-autonomous-20261101-2341/RESULTS.md)
  potvrdilo doruÄŤenĂ­ novĂ© ĹľĂˇdosti ve webmailu, platnĂ˝ odkaz i anonymnĂ­/mobilnĂ­ formulĂˇĹ™;
  produkÄŤnĂ­ heslo nezmÄ›nÄ›no, skuteÄŤnĂ© spotĹ™ebovĂˇnĂ­ a T07 nadĂˇle nejsou ovÄ›Ĺ™eny.
  HostingovĂ˝ minutovĂ˝ cron znovu ovÄ›Ĺ™en
  [1. 11. v 17:22:03 UTC](verification/trainer-pilot-20261101/trainer-readiness.json). TĹ™i starĂ© klubovĂ© zprĂˇvy nedotÄŤeny.

- **W6â€“W15 implementovĂˇny, slouÄŤeny, nasazeny a technicky ovÄ›Ĺ™eny.**
  [Rozsah a omezenĂ­ pĹ™ejĂ­mky](REFACTORING_CLOSURE_2026-10-30.md).
- **Ĺ˝ivĂ© vydĂˇnĂ­:** `76bf0370a06e0ab80bab5ee9af1815835ac24efc`, [deploy 42084130988](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42084130988) SUCCESS.
  [BAR protokol](verification/bar-release-20261105/RESULTS.md): otestovanĂ˝ a slouÄŤenĂ˝ strom shodnĂ˝; pĹ™edchozĂ­ ÄŤlenstvĂ­ #316 a pĹ™edĂˇnĂ­ #317 zachovanĂ©.
- **OvÄ›Ĺ™enĂ­:** vĹˇech 11 kontrol PR, CI main i deploy PASS; Linux 2203/41286 bez skipĹŻ, samostatnĂ© unixovĂ© hranice 3/45, MariaDB 10.3/11.4, PHPStan/styl/JS PASS.
  HTTP vydĂˇnĂ­ pĹ™ed 71/71, po 77/77; pĹ™ihlĂˇĹˇenĂ˝ Chrome Finance/SprĂˇva baru/Obsluha i nĂˇslednĂ˝ skuteÄŤnĂ˝ katalog PASS. PĹ™ihlĂˇĹˇenĂ­ lucky a skuteÄŤnĂˇ barovĂˇ platba NOT RUN.
  [ÄŚtecĂ­ porovnĂˇnĂ­](verification/bar-release-20261105/release-comparison.json): ĹľivĂ© SHA a shodnĂˇ konfigurace/flags v okamĹľiku BAR vydĂˇnĂ­; pozdÄ›jĹˇĂ­ samostatnĂˇ aktivace ÄŤlenstvĂ­ viz vĂ˝Ĺˇe. HistorickĂ˝ manifest nenĂ­ Ăşplnou inventurou BAR.
- **ZĂˇloha a schĂ©ma:** [ĂşplnĂˇ izolovanĂˇ obnova 42080871410](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42080871410) PASS: 357 tabulek / 2 triggery a 4341 existujĂ­cĂ­ch souborĹŻ vÄŤetnÄ› konfigurace a pĹ™Ă­loh.
  [NejnovÄ›jĹˇĂ­ DB zĂˇloha](verification/bar-release-20261105/deploy-database.json) vznikla pĹ™ed migracemi; [obnova 42085710492](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42085710492) PASS. Schema current / pending [], ledger 159 a VS zachovanĂ©.
- **DB kapacita obnovena:** po ĂşplnĂ© externĂ­ zĂˇloze a skuteÄŤnĂ© obnovÄ› Search
  byl autorizovanÄ› vyprĂˇzdnÄ›n pouze odvozenĂ˝ index Velocoty; +3,35 GB,
  31. 10. v 09:34 CET pĹ™ibliĹľnÄ› 4,64 GB volnĂ˝ch na systĂ©movĂ©m oddĂ­lu. DiskovĂ© temporary tables fungujĂ­. PĹ™Ă­ÄŤina kolĂ­sĂˇnĂ­ kapacity nenĂ­ definitivnÄ› urÄŤena. GlobĂˇlnĂ­ hledĂˇnĂ­ Velocoty a jeho cron zĹŻstĂˇvajĂ­ doÄŤasnÄ› vypnutĂ©;
  nĂˇvrat vyĹľaduje trvalou kapacitu nebo ovÄ›Ĺ™enĂ˝ ĂşspornÄ›jĹˇĂ­ index.
  [PĹ™edĂˇnĂ­ Velocoty](https://github.com/velocota/velocota-docs/pull/93).
- **Konfigurace a flags:** Fio import/auto-confirm a SumUp true; vlastnĂ­ hostingovĂ˝ cron s minimĂˇlnĂ­m odstupem 600 s.
  Dva skuteÄŤnĂ© importy PASS; redundantnĂ­ GitHub plĂˇn vypnutĂ˝ repozitĂˇĹ™ovou promÄ›nnou, ruÄŤnĂ­ spuĹˇtÄ›nĂ­ zachovanĂ©.
  Stripe/Velocota false, Packeta nedefinovanĂˇ, KIS canonical writes false; UAT/import false.
- **OtevĹ™enĂ© provoznĂ­ pĹ™evzetĂ­:** lidskĂˇ/ĂşÄŤetnĂ­ pĹ™ejĂ­mka D12â€“D14, skuteÄŤnĂˇ
  inventura/ocenÄ›nĂ­, vazby a trenĂ©rskĂ© rozsahy, dohody/ÄŤĂˇstky a dvÄ› pĹ™Ă­lohy.
  [RozhodnutĂ­](CLUB_COMPLETION_DECISIONS.md), [pilot](CLUB_PILOT_D12_D14.md).
  TechnickĂ© dokonÄŤenĂ­ nenĂ­ odstranÄ›nĂ­ vĹˇech legacy nĂˇlezĹŻ ani schvĂˇlenĂ­ UAT.

[PĹ™evzetĂ­](HANDOFF_CURRENT.md) Â· [Historie](CHANGELOG-PROVOZ.md) Â· [Dokumentace](README.md)
