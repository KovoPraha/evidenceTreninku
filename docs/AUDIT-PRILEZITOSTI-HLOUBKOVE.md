# Hloubkový funkční audit příležitostí

> **Status v projektu (4. 8. 2026):** Informační návrhový podklad, nikoli
> schválená architektura ani roadmapa. Datové, ekonomické, právní a kapacitní
> předpoklady je nutné znovu ověřit proti aktuálnímu schématu a rozhodnutím
> vlastníka před zahájením kterékoli navržené funkce.

**Projekt:** Evidence tréninků + e‑shop + KIS (TJ Kovo Praha)
**Datum:** 2026‑08‑04
**Navazuje na:** `docs/AUDIT-PRILEZITOSTI-A-NAPADY.md` — tady jdu o úroveň hlouběji: od seznamu nápadů k **datové připravenosti, konkrétním návrhům, ekonomice a strategické sázce.**

Postavil jsem to na skutečném schématu (~110 tabulek). Nejdůležitější zjištění nejsou „co přidat", ale **jaká data už máte, v jaké jsou kvalitě, a co to znamená pro to, co je levné a co drahé postavit.**

---

## ČÁST 1 — Datová připravenost (to, co většina auditů přeskočí)

Než navrhnu funkce, je potřeba pravdivě pojmenovat, jaké signály aplikace drží a jak jsou „čisté". To rozhoduje o ceně každého nápadu.

### 1.1 Co už měříte a v jaké kvalitě

| Signál | Kde | Kvalita / stav | Důsledek pro produkt |
|---|---|---|---|
| Docházka na tréninky | `treninky` × `trenink_sportovec` | strukturovaná, spolehlivá | **hotový základ** pro série, retenci, odměny, digesty |
| Odměna Kč/trénink | `sportovec_obdobi` (sazba × počet, `vyplaceno`) | strukturovaná, ale je to *evidence výplaty*, ne zůstatek | pro „wallet" je třeba zavést **čerpatelný ledger** (viz Část 2‑I) |
| Tréninková měření | `mereni_zaznamy` (vzdálenost, převod, cvik/váha/opakování/RPE, segment) | částečně strukturovaná; **`cas` je `varchar` — volný text** | osobáky/žebříčky **napřed potřebují normalizaci času** (datová dluha) |
| Výkon / tep / kadence | — | **nesbírá se vůbec** | prostor pro **.FIT/Strava import** = nová dimenze dat, ne jen přepis |
| Segmenty (trasy) | `segmenty` (foto, **`odkaz_1` = Strava/mapy.cz**) | strukturované, už mají odkaz na Stravu | **klubové segmentové žebříčky jsou skoro na dosah** |
| Závody a výsledky | `zavody`, `zavod_mereni`, UCI export | strukturované | napojení na časomíru = auto‑výsledky (Část 3) |
| Soupisky napříč sezonami | `club_team_series/seasons/teams/roster_members` + rollover | bohatá historie U13→U15→U17 | základ pro **retenci a auto‑obnovu** (Část 2‑III) |
| Rodinné vazby | `account_person_roles` (self/guardian) | ověřené, s historií | základ pro rodičovské digesty a rodinné slevy |
| Platby / předpisy / odměny | `payments`, `shop_orders`, `club_member_charges`, `shop_*` | strukturované, auditované | základ pro daňová potvrzení a wallet |
| Komunikační engine | **`oznameni` + `oznameni_targets`** + `push_subscriptions` | cílení + web push už existují | **nemusíte stavět notifikace — jsou tu**; stačí je „krmit" událostmi |
| Export do Google Sheets | `gs_kategorie`, `google_sheets_linky.php` | funkční most | rychlá cesta pro reporty vedení bez nového UI |
| Story/obsah | `generuj_story.php` | generátor už existuje | základ pro „sezónní shrnutí" a marketing |

### 1.2 Dvě datové dluhy, které blokují „chytré" funkce

1. **`mereni_zaznamy.cas` je volný text.** Dokud čas není strukturovaný (sekundy jako číslo + jednotka), nejdou spolehlivě dělat osobáky, žebříčky ani trendy. **První, levný, ale nutný krok** před B‑řadou nápadů: normalizační sloupec `cas_ms INT` + parser stávajících hodnot (a validace při zápisu). Bez toho je „progres/PB" křehký.
2. **Chybí objektivní intenzita.** RPE (subjektivní) máte jen u posilovny; není výkon ani tep. `.FIT`/Strava import (Část 3) tuhle mezeru zaplní a teprve pak dávají smysl „tréninková zátěž" a „wellness/prevence zranění" jako datové, ne odhadované funkce.

> Tohle je hlavní „deeper" poznatek: **než přidávat, vyplatí se pár dní investovat do kvality času a do jednoho zdroje objektivní intenzity.** Zlevní to půlku ambiciózních nápadů.

---

## ČÁST 2 — Tři vlajkové návrhy, promyšlené do hloubky

### I. Klubová kreditní smyčka (odměna → čerpatelný wallet → e‑shop)

**Proč to je nejsilnější tah.** Klub dnes odměnu za docházku *eviduje k výplatě* (`sportovec_obdobi.vyplaceno`). Peníze klubu odtékají ven. Když z odměny uděláte **kredit čerpatelný uvnitř** (dres, soustředění, startovné, velodrom), peníze v ekosystému zůstanou a vznikne návyk: *trénuj → vydělej → utrať v klubu → zůstaň*. Je to zároveň retenční i cash‑flow nástroj.

**Proč to nejde udělat „jen sloupcem `kredit_zustatek`" (jak naznačuje roadmapa).** Zůstatek jako editovatelné číslo je účetně i technicky křehký. Správně je **neměnný ledger**:

```
wallet_accounts        (sportovec/účet, typ: 'reward' | 'cash')
wallet_entries         (account_id, amount_minor, kind, ref_type, ref_id,
                        idempotency_key, created_at, actor)  -- append-only
```

Zůstatek = součet položek. Připsání z uzavřeného období, čerpání při checkoutu (rezervace → finální strhnutí), storno = kompenzační zápis. Idempotence přes klíč (žádné dvojí připsání).

**Kritické oddělení (D‑009):** `reward` (klubová odměna, čerpatelná jen ve shopu, nevratná na účet) vs. `cash` (peněžní dobití) **musí zůstat oddělené kapsy** kvůli účetnictví a daním. Míchání a vratku cash povolit až po účetním/právním posvěcení. Tohle je brána, ne technický detail.

**Minimální řez (co postavit první):** připsat `reward` z `sportovec_obdobi` při uzavření období → zobrazit zůstatek v profilu → umožnit uplatnit na jednu kategorii (např. startovné/soustředění) v `shop_checkout`. Rozšíření: kombinace kredit+převod (odloženo dle D‑012), rodinné sdílení kreditu mezi sourozenci.

**Druhý řád:** kredit mění chování — děti chodí víc (odměna), rodiče utrácejí v klubu (retence tržeb), a máte měřitelný nástroj „kolik nás retence stojí a vynáší". Pozor na **etiku u dětí**: odměna za docházku ať nevede k tréninku přes únavu — proto ji párovat s wellness signálem (návrh II) a stropem.

### II. Vrstva sportovcova pokroku (měření → osobáky, trendy, zátěž)

**Problém, který řešíte.** Data se zapisují, ale **nikdo z nich nic nevidí** — ani sportovec, ani rodič, ani trenér v čase. Evidence je „zápis", ne „zpětná vazba".

**Předpoklad (datová dluha z Části 1):** normalizovat `cas` → `cas_ms`. Teprve pak:

- **Osobní rekordy (PB)** na segment/typ, s datem a „o kolik lepší než minule".
- **Trend formy** přes sezonu (klouzavý průměr výkonu/času na segmentu).
- **Tréninková zátěž**: týdenní objem + (po zavedení výkonu/tepu z .FIT) acute:chronic poměr → **vlajka přetížení** trenérovi. U dětí a dorostu je prevence zranění reálná hodnota, ne marketing.
- **Volitelný check‑in** „jak se dnes cítíš" (0–10) od sportovce → wellness signál, který se páruje se zátěží.

**Povrchy podle persony** (stejná data, jiný pohled):
- *Sportovec/rodič:* jednoduchý graf pokroku + osobáky + odznaky.
- *Trenér:* skupinový přehled zátěže + vlajky + kdo stagnuje.
- *Vedení:* agregát do KPI (Část 3‑III).

**Rozšíření = napojení navenek:** `.FIT/.GPX` upload (výkon, tep, kadence) a Strava (segmenty už mají `odkaz_1`). Začít **read‑only importem**, ať to není velký závazek — a ať sportovec nemusí nic přepisovat.

**Etický mantinel:** žebříčky a „talent" u dětí opatrně — nabídnout i „soukromý režim", nesrovnávat věkově nevhodně, a nikdy z toho nedělat veřejný tlak.

### III. Retenční engine (docházka + rollover + oznameni_targets)

**Signál, který už máte a nevyužíváte.** Z docházky se **pár týdnů dopředu pozná, kdo z klubu odejde** (klesající účast je nejlepší prediktor churnu). Dnes to nikdo nevidí, dokud dítě prostě nepřestane chodit.

**Návrh:**
1. **Churn radar** — pravidelný job spočítá „skóre rizika" (např. účast posledních 3× vs. historie) a trenérovi/vedení pošle list „3 děti ohrožené odchodem" **přes existující `oznameni`/`oznameni_targets`** — nemusíte stavět nový kanál.
2. **Auto‑nabídka další sezony** — napojit na **rollover** (U13→U15→U17 už umíte): komu končí členství, tomu automaticky nabídnout přihlášku + předpis na další sezonu, s jedním klikem a připomínkou. Obnova členství je největší jednorázová retenční páka v roce.
3. **Rodičovský týdenní digest** — „Anička 2/2 tréninky, nový osobák, v sobotu výjezd — potvrďte účast" přes push. Drží rodiče v obraze a snižuje no‑show (which také kazí kapacitu a plánování).

**Proč je to levné:** komunikační engine (`oznameni_targets`, `push_subscriptions`), docházka i rollover **už existují**. Skládáte je, nestavíte znovu.

**Měřitelnost:** zaveďte jednu metriku — *retence mezi sezonami* — a sledujte, jestli radar+auto‑obnova čísla hýbou. Tím se z „pocitu" stane řízení.

---

## ČÁST 3 — Strategická sázka: „Club OS"

Tady je out‑of‑the‑box myšlení na úrovni modelu, ne funkce.

**Teze:** nestavíte tři aplikace (evidence + e‑shop + KIS). Stavíte **jeden operační systém sportovního klubu** — jednu identitu, jednu platební vrstvu, jeden audit, jednu komunikaci, přes celý životní cyklus člena: *nábor → tréninky → soupisky → akce → platby → odměny → obnova.* To je vzácné; většina klubů to lepí z pěti nástrojů.

Z toho plynou tři strategické příležitosti, které jednotlivé funkce přesahují:

**1. Unikátní napojení na časomíru (Velocota Timing běží vedle na stejném stroji).**
Máte po ruce zdroj **oficiálních výsledků závodů**. Automatický import výsledků/měření do profilu sportovce, žebříčků a story místo ručního přepisu je věc, kterou skoro žádný klubový systém neumí — a vy jste na ni jako jediní připraveni (`zavod_mereni`, `export_uci`, sousední timing). To je „moat", ne feature.

**2. Multi‑klub / federační potenciál (opatrně, později).**
Až Club OS jednou zvládne celý životní cyklus jednoho klubu čistě, je to **šablona pro další oddíly**. KIS nahrazujete pro sebe; stejný model (import → sandbox → parita → promote) je zobecnitelný. Neznamená to hned SaaS — znamená to stavět hranice domén tak, aby „další klub" nebyl přepis. (Vy už doménové hranice a číslované migrace máte, což je přesně ten základ.)

**3. Data jako služba členům, ne jako sklad.**
Hodnota není v tom, že data máte, ale že je **vracíte**: rodiči daňové potvrzení a kalendář, sportovci pokrok, trenérovi zátěž a churn, vedení KPI dashboard. Každé takové „vrácení" zvyšuje důvod zůstat a snižuje důvod hledat jinde. To je obranná strategie postavená na už nasbíraných datech.

**Sponzor/CRM úhel (bonus):** jakmile máte engagement data (docházka, výsledky, story), vzniká i hodnota pro sponzory a granty — doložitelná aktivita mládeže, dosah story obsahu, reporty pro dotace. Nenásilně, ale je to reálný příjmový vektor pro neziskový klub.

---

## ČÁST 4 — Sekvenování (co v jakém pořadí a proč — závislosti, ne jen priorita)

```
FÁZE 0 — datová hygiena (odemyká zbytek)
  0a. Normalizace mereni_zaznamy.cas → cas_ms (+ validace při zápisu)
  0b. Jeden objektivní zdroj intenzity: .FIT/GPX read-only upload

FÁZE 1 — rychlá hmatatelná hodnota (nezávislé, hned)
  1a. Roční daňové potvrzení (PDF)         [data hotová]
  1b. Kalendář ICS (tréninky/akce/velodrom)[data hotová]
  1c. Rodičovský digest přes oznameni+push [engine hotový]

FÁZE 2 — smyčky nad daty (staví na Fázi 0/1)
  2a. Athlete progress + PB (potřebuje 0a)
  2b. Kreditní wallet ledger + čerpání ve shopu (potřebuje účetní D-009)
  2c. Retenční radar + auto-obnova sezony (staví na rolloveru + oznameni)

FÁZE 3 — napojení navenek
  3a. Import výsledků z Velocota časomíry
  3b. Strava/TrainingPeaks read-only (segmenty už mají odkaz_1)
  3c. Auto-párování Fio plateb (po finanční akceptaci)

FÁZE 4 — vize
  4a. KPI dashboard vedení (agregace existujícího)
  4b. AI asistent nad daty / predikce progrese (po Fázi 0+2)
```

Pravidlo: **nic z Fáze 2 nespouštět bez Fáze 0.** Jinak stavíte chytré funkce na volném textu a subjektivním RPE.

---

## ČÁST 5 — Rizika a mantinely specifické pro mládežnický sport

- **Data nezletilých:** jakékoli žebříčky, „talent" a veřejné profily řešit s opt‑in/soukromým režimem a souhlasem zástupce. Etika před gamifikací.
- **Odměna za docházku ≠ tlak na přetrénování:** kreditní smyčku párovat s wellness signálem a stropem; nikdy neodměňovat objem přes únavu.
- **Účetnictví a daně:** reward vs. cash kapsa oddělené; daňové potvrzení musí sedět s reálnými platbami (audit už máte — využít ho jako zdroj pravdy).
- **GDPR:** self‑service export a retenční doby (u importních preview i u wellness check‑inů); minimalizace v exportech (to už děláte v M2.1 exportu — držet ten standard).
- **Integrace opatrně:** Strava/timing/Fio začít **read‑only**, ať se nezaváže víc, než je ověřeno; každý externí vstup idempotentní (to už je princip vaší architektury).

---

## Jedna věta na závěr

Nejhlubší poznatek není další nápad, ale pořadí: **napřed zpevněte kvalitu času a přidejte jeden objektivní zdroj intenzity, pak vracejte hodnotu z dat, která už roky sbíráte** — daňové potvrzení a kalendář rodiči, pokrok sportovci, zátěž a churn trenérovi, KPI vedení — a z odměny za docházku udělejte kredit, který drží peníze i lidi v klubu. To je z „evidence" plnohodnotný operační systém klubu.
