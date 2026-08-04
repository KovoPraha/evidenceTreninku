# Funkční audit příležitostí — co by aplikace mohla umět navíc

> **Status v projektu (4. 8. 2026):** Informační produktový podklad, nikoli
> schválená roadmapa. Tvrzení o právu, účetnictví, přínosu a náročnosti jsou
> hypotézy k ověření. Implementace se řídí kanonickým milníkem M2 a rozhodnutími
> vlastníka.

**Projekt:** Evidence tréninků + e‑shop + KIS (TJ Kovo Praha)
**Datum:** 2026‑08‑04
**Charakter:** produktově‑inovativní úhel. Ne chyby, ale **hodnota** — co ještě propojit, dotáhnout a přidat, aby uživatelé (rodiče, sportovci, trenéři, vedení) měli lepší službu. Každý nápad je zakotvený v tom, co aplikace **už dnes sbírá nebo umí**, aby to nebyly plané sny, ale realizovatelné kroky.

---

## Vůdčí myšlenka

Aplikace už dnes sbírá nečekaně bohatá data: **docházku, tréninková měření (výkon, čas, převod, RPE, segmenty), závody, soupisky napříč sezonami, rodinné vazby, platby a odměny za docházku (Kč/trénink), rezervace sportovišť.** Většina téhle hodnoty se ale nikam „nevrací" uživateli. Největší příležitost není přidávat nové moduly, ale **uzavřít smyčky** nad daty, která už máte: *trénuj → vidíš pokrok → jsi motivovaný → zůstáváš v klubu → utrácíš získaný kredit → klub roste.*

Nápady jsou seřazené podle poměru hodnota/úsilí do čtyř skupin: **Rychlé výhry**, **Větší sázky**, **Napojení navenek** a **Moonshoty**.

---

## A. Rychlé výhry (nízké úsilí, využívají existující data)

**A1 — Roční potvrzení o zaplacení pro slevu na dani / příspěvek zaměstnavatele.**
V Česku si rodiče mohou nechat proplatit sportovní aktivity dětí (zaměstnavatel, benefity, případně daňové úlevy). Aplikace už zná všechny platby a účastníky. Jedno tlačítko „Stáhnout potvrzení za rok 2026" → PDF se jménem dítěte, částkami a účelem. **Extrémně žádaná věc rodiči, minimální práce** (data máte, přidá se jen generátor PDF). Skvělý „aha" moment.

**A2 — Kalendář ke stažení / synchronizaci (ICS).**
Tréninky, události, rezervace velodromu a závody export do `.ics` + odkaz „Přidat do Google/Apple kalendáře". Rodič má termíny dítěte automaticky v telefonu. Data máte, jde jen o ICS feed s tokenem. Obrovský UX skok za pár hodin práce.

**A3 — „Sezónní shrnutí" à la Spotify Wrapped.**
Už máte `generuj_story.php` (generování story obsahu) i tréninková měření a docházku. Na konci sezony/pololetí vygenerovat sportovci a rodiči hezkou vizuální kartu: počet tréninků, najeté km, nejlepší segment, docházková série, posun oproti loňsku. **Motivace + sdílitelný marketing klubu zadarmo.**

**A4 — Rodičovský týdenní přehled (push/e‑mail digest).**
Push notifikace už v aplikaci jsou (`push_helper`). Jednou týdně: „Anička byla na 2/2 trénincích, nový osobák na 200 m, v sobotu výjezd — potvrďte účast." Drží rodiče v obraze bez jejich námahy a snižuje no‑show.

**A5 — Docházkové série a odznaky.**
Z docházky, kterou stejně počítáte kvůli odměnám, udělejte i motivaci: série („5 tréninků v řadě"), odznaky, měsíční žebříček skupiny. Nulová nová data, čistě prezentace nad existujícím.

**A6 — Připomínky nezaplacených předpisů a expirujících objednávek.**
Expirace pending objednávek už existuje; přidejte jemné automatické připomenutí platby (predpis/QR) 2 dny před splatností. Méně ruční práce ekonoma, méně propadlých plateb.

---

## B. Větší sázky (vyšší hodnota, střední úsilí)

**B1 — Uzavřít kreditní smyčku: odměna za docházku → útrata v e‑shopu.**
Tohle je podle mě **nejsilnější nápad**. Už dnes evidujete odměnu Kč za trénink (`sportovec_obdobi`, `prehled_kreditu`) a máte e‑shop s klubovými cenami. Propojte to: nasbíraný kredit jde utratit za dres, soustředění, startovné nebo pronájem velodromu. Vznikne návykový flywheel (trénuj → vydělej → utrať v klubu → zůstaň). Kreditní wallet už máte na roadmapě (Změna 2) — tímhle mu dáte smysl a tah. Pozor: účetní/právní rozhodnutí D‑009 to musí posvětit (odměna vs. peníze), takže rozdělte na „reward" kapsu (čerpatelná ve shopu) a „cash" zvlášť.

**B2 — Osobní progres a osobáky (athlete dashboard).**
Máte měření kolo/běh/posilovna (vzdálenost, čas, převod, váha, opakování, RPE) i segmenty. Postavte sportovci/rodiči jednoduchý graf pokroku a tabulku osobních rekordů (PB). Dnes se data zapisují, ale nikdo z nich nevidí trend. **Z evidence uděláte trenérský i motivační nástroj.**

**B3 — Klubové segmentové žebříčky.**
Segmenty s časy (a fotkami) už máte (`sprava_segmentu`). Udělejte z nich klubové KOM/QOM a věkové žebříčky — zdravá soutěživost, obsah pro nástěnku i sociální sítě. Skvěle sedí na dráhový/silniční klub.

**B4 — Monitoring tréninkové zátěže a wellness.**
RPE už sbíráte. Agregujte týdenní zátěž na sportovce a upozorněte trenéra na skok/přetížení (jednoduchý acute:chronic poměr). Volitelně krátký „jak se dnes cítíš" check‑in od sportovce. **Prevence přetrénování a zranění = velká hodnota u dětí a dorostu.**

**B5 — Retenční radar (kdo odpadá).**
Z docházky poznáte, komu klesá účast — první signál odchodu z klubu. Trenér/vedení dostane list „3 děti, které v posledních 3 týdnech vynechaly", než je ztratíte. Napojení na rollover: automaticky nabídnout přihlášku na další sezonu těm, co končí (rollover už umíte).

**B6 — Digitální souhlasy, zdravotní způsobilost a GDPR self‑service.**
Souhlasy k událostem už máte verzované. Rozšiřte na: souhlas s fotkami, prohlášení o zdravotní způsobilosti (s expirací a připomínkou obnovy), a self‑service export osobních dat pro rodiče. **Méně papírů, splněné compliance, méně administrativy.**

**B7 — Členská karta do peněženky telefonu (Apple/Google Wallet pass).**
Z jednotného profilu vygenerovat digitální členskou kartu s QR — vstup na velodrom, identifikace u trenéra, sleva ve shopu. Moderní dojem, drobná integrace.

---

## C. Napojení navenek (integrace)

**C1 — Napojení na časomíru závodů (máte Velocota Timing na stejném stroji!).**
Unikátní pozice: vedle běží Velocota Timing (`/velocota/…/mereni`). Automatický import výsledků závodů/měření do evidence sportovce místo ručního přepisu → okamžité výsledky v profilu, žebříčcích i story. Máte i `export_uci` — dá se propojit obousměrně.

**C2 — Strava / TrainingPeaks (TrainingPeaks už máte jako budoucí záměr).**
Import aktivit sportovce (km, čas, výkon) automaticky doplní tréninková měření — sportovec nemusí nic přepisovat. Segmenty se dají párovat se Stravou. Začněte read‑only importem, ať to není velký závazek.

**C3 — Upload .FIT/.GPX a rozbor souboru z cyklopočítače.**
I bez Stravy: nahraj soubor z Garminu/Wahoo → parser vytáhne výkon, tep, kadenci, čas do měření. Napojení na B2/B4 (progres, zátěž).

**C4 — Automatické párování plateb z Fio (dnes shadow) + chytré upomínky.**
Fio read‑only import už existuje jako návrh. Další krok (po samostatné finanční akceptaci): automaticky spárovat příchozí platbu s předpisem podle VS a označit „uhrazeno" — ušetří ekonomovi hodiny. Kombinovat s A6.

**C5 — Doprava Packeta pro e‑shop (už na roadmapě F7).**
Dresy a zboží doručit domů/na výdejní místo. Uzavře e‑shopovou zkušenost.

**C6 — Počasí pro venkovní tréninky a velodrom.**
K plánovanému tréninku/rezervaci ukázat předpověď; automatické upozornění „zítra déšť, trénink v hale?". Drobnost s velkým UX efektem u venkovního sportu.

**C7 — Sdílení a komunikace: carpooling a týmový feed k výjezdům.**
Události/výjezdy už máte. Přidejte ke každé akci jednoduchou koordinaci odvozů („kdo koho veze") a týmový feed s výsledky/fotkami. Komunita = retence.

---

## D. Moonshoty (velká vize, delší horizont)

**D1 — AI trenérský asistent nad vlastními daty.**
Nad měřeními, docházkou a závody: shrnutí sezony sportovce v lidské řeči, návrh, na co se zaměřit, upozornění na anomálie. Ne „chatbot", ale asistent, který čte data, která už máte.

**D2 — Predikce progrese a talentu napříč věkovými kategoriemi.**
Máte historii U13→U15→U17 a měření. Dlouhodobě lze modelovat vývoj a pomáhat trenérům s výběrem i individualizací — s velkou opatrností a etikou u dětí.

**D3 — Dashboard pro vedení klubu (KPI).**
Jedna obrazovka pro výbor: členská základna, docházka, obsazenost, příjmy z e‑shopu/předpisů, retence, naplněnost velodromu. Data máte roztroušená; agregace z nich udělá řídicí nástroj.

**D4 — Klubový „marketplace" a bazar.**
Rodiče prodávají/darují dětská kola a vybavení uvnitř klubu přes existující e‑shop infrastrukturu. Komunitní hodnota + oběh vybavení.

---

## Prioritizace (můj návrh pořadí)

| Pořadí | Nápad | Proč teď | Úsilí | Nové vs. plán |
|---:|---|---|---|---|
| 1 | A1 Roční potvrzení o platbě (daně) | Okamžitá hmatatelná hodnota rodiči, data hotová | Nízké | **Nové** |
| 2 | A2 Kalendář ICS | Velký UX skok za málo práce | Nízké | **Nové** |
| 3 | B1 Kredit → e‑shop (uzavřít smyčku) | Retenční flywheel, staví na existující odměně | Střední | Rozšiřuje plán #2 |
| 4 | B2 Osobní progres/PB | Zhodnotí data, co se stejně zapisují | Střední | **Nové** |
| 5 | A4 Rodičovský týdenní digest | Drží rodiče, snižuje no‑show | Nízké | **Nové** |
| 6 | C1 Napojení na Velocota časomíru | Unikátní výhoda, konec ručního přepisu výsledků | Střední | **Nové** |
| 7 | B5 Retenční radar + auto‑nabídka další sezony | Přímý dopad na členskou základnu | Střední | **Nové** (staví na rolloveru) |
| 8 | A3 Sezónní shrnutí (Wrapped) | Motivace + marketing zdarma, máte generuj_story | Nízké | **Nové** |
| 9 | B4 Monitoring zátěže/wellness | Prevence zranění u dětí | Střední | **Nové** |
| 10 | A6/C4 Upomínky + auto‑párování Fio | Šetří ekonoma | Nízké→Střední | Rozšiřuje shadow Fio |

---

## Jedna věta na závěr

Nemusíte stavět nové velké moduly — stačí **vrátit uživatelům hodnotu z dat, která už sbíráte**: dát rodiči potvrzení a kalendář, dát sportovci vidět svůj pokrok, dát odměně smysl v e‑shopu a dát trenérovi včas vědět, kdo odpadá. To jsou levné kroky s velkým dopadem na spokojenost i na to, jestli lidé v klubu zůstanou.
