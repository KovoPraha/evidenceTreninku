# Uživatelská příručka — Evidence Tréninků

## Obsah

1. [Přihlášení](#1-přihlášení)
2. [Nástěnka](#2-nástěnka-dashboard)
3. [Navigace](#3-navigace)
4. [Tréninky](#4-tréninky)
5. [Sportovci](#5-sportovci)
6. [Skupiny a podskupiny](#6-skupiny-a-podskupiny)
7. [Přehledy a reporty](#7-přehledy-a-reporty)
8. [Závody](#8-závody) (přehled, nový závod, detail, závodní karta sportovce)
9. [Exporty](#9-exporty)
10. [Zátěžové testy](#10-zátěžové-testy)
11. [Story generátor](#11-story-generátor)
12. [Emaily](#12-emaily)
13. [Oznámení](#13-oznámení)
14. [Nastavení a správa](#14-nastavení)
15. [Účetní modul (firemní evidence)](#15-účetní-modul-firemní-evidence--pouze-admin)
16. [Audit log](#16-audit-log)
17. [Synchronizace evidence](#17-synchronizace-evidence-admin)
18. [Správa segmentů](#18-správa-segmentů)
19. [Role a oprávnění](#19-role-a-oprávnění)
20. [Rezervace sportovišť](#20-rezervace-sportovišť)
21. [Veřejný booking — zákazníci](#21-veřejný-booking--zákazníci)
22. [Plánovač tréninků](#22-plánovač-tréninků)

---

## 1. Přihlášení

Aplikace vyžaduje přihlášení. Na úvodní stránce klikněte na **Přihlásit se**.

**Přihlašovací formulář:**
- **Přihlašovací jméno** — vaše uživatelské jméno přidělené administrátorem
- **Heslo** — vaše heslo

Po úspěšném přihlášení jste přesměrováni na nástěnku.

### Role uživatelů

| Role | Označení | Oprávnění |
|------|----------|-----------|
| Trenér | `trener` | Vkládání tréninků, prohlížení svých dat, přehledy |
| Hlavní trenér | `hlavni` | Vše výše + administrace, správa trenérů, skupin, odesílání emailů, kredity, firemní evidence (vozidla, účtenky, události) |

Hlavní trenér vidí navíc sekci **Administrace** na nástěnce a položku **Admin** v menu. Moduly firemní evidence (vozidla, účtenky, události) jsou přístupné **výhradně** hlavním trenérům.

---

## 2. Nástěnka (Dashboard)

Po přihlášení se zobrazí hlavní stránka rozdělená do tří sloupců.

### Uvítací banner
- Pozdrav s vaším jménem
- Aktuální datum a den v týdnu
- Rychlé tlačítka: **Nový trénink** a **Moje tréninky**

### Levý sloupec — Vkládání
| Položka | Popis |
|---------|-------|
| Nový trénink | Formulář pro zadání nového tréninku |
| Duplikovat trénink (soustředění) | Vytvoření tréninku na základě existujícího |
| Další činnost | Záznam netréninových aktivit (schůzky, semináře) |
| Nový zátěžový test | Formulář pro zátěžový test sportovce |
| Plánovač tréninků | Přehled a správa plánovaných tréninků; číslo plánů čekajících na evidenci se zobrazuje jako odznak |

> Pokud máte naplánované tréninky bez zadané evidence, zobrazí se na nástěnce zlatý informační banner s odkazem do Plánovače.

### Levý sloupec — Přehledy
| Položka | Popis |
|---------|-------|
| Moje tréninky | Seznam tréninků, které jste zadali |
| Moje skupiny | Tréninky filtrované podle skupin |
| Přehled tréninků | Měsíční přehled s možností exportu |
| Přehled sportovců | Seznam všech sportovců s vyhledáváním |
| Kalendář tréninků | Kalendářové zobrazení tréninků |
| Výkaz činností | Měsíční výkaz pro export |

### Střední sloupec — Závodní sekce
| Položka | Popis |
|---------|-------|
| Skupiny a podskupiny | Hromadná správa skupin a podskupin |
| Export – dráha (XLSX) | Export přihlašovací tabulky pro dráhu |
| Export – UCI přihláška (XLSX) | Export přihlášky ve formátu UCI |
| Export – Seznam sportovců (XLSX) | Export vybraných sportovců do Excel souboru |
| Google Sheets evidence | Správa odkazů na Google Sheets |
| Oznámení a zprávy | Vytváření a zobrazení oznámení |

### Střední sloupec — Nastavení
| Položka | Popis |
|---------|-------|
| Cviky v posilovně | Správa cviků pro měření v posilovně |
| Segmenty (Kroužek / Silnice / MTB) | Správa segmentů tras na kole s fotkami a odkazy |
| Nastavení odměn | Konfigurace odměn pro sportovce |

### Střední sloupec — Rezervace sportovišť

| Položka | Popis |
|---------|-------|
| Kalendář sportovišť | Týdenní přehled obsazenosti všech sportovišť |
| Nová rezervace | Formulář pro interní rezervaci sportoviště |
| Individuální lekce | Správa vypsaných lekcí a jejich rezervací |
| Nová lekce | Formulář pro novou individuální lekci |
| Veřejný kalendář | Odkaz na booking pro zákazníky (otevře se v nové záložce) |

### Pravý sloupec — Administrace (pouze hlavní trenér)
| Položka | Popis |
|---------|-------|
| Správa tréninků | Zobrazení a správa všech tréninků — hledání, filtry (skupina, podskupina, kategorie, trenér) |
| Okno pro zadávání tréninků | Nastavení, kolik dní zpětně mohou trenéři zadávat |
| Správa skupin | Přidávání, úprava a mazání skupin |
| Správa podskupin | Přidávání, úprava a mazání podskupin |
| Správa sportovišť | CRUD správa sportovišť (název, kód, veřejné ano/ne, kapacita, pořadí) |
| Správa trenérů | Správa uživatelských účtů trenérů (přidání, úprava, smazání) |
| Správa závodů | Vytváření a editace závodů |
| Veřejný přehled | Veřejně přístupný přehled tréninků |
| Všechny výkazy | Přehled výkazů všech trenérů |
| Odeslat emaily sportovcům | Hromadné odesílání emailů s profilem |
| Přehled kreditů sportovců | Přehled odměn a kreditů |
| Správa kreditních období | Vytváření a uzavírání kreditních období |
| Synchronizace evidence | KIS synchronizace ze tří XLSX exportů: uživatelé, platby, soupisky |
| **Vozidla** | Správa vozového parku klubu |
| **Účtenky** | Evidence dokladů a účtenek |
| **Události** | Závody, soustředění, vyúčtování |

---

## 3. Navigace

Horní tmavý navigační panel obsahuje:

- **Domů** — návrat na nástěnku
- **Vložit** (dropdown):
  - Nový trénink
  - Další činnost
  - Zátěžový test
- **Přehledy** (dropdown):
  - Moje tréninky
  - Moje skupiny
  - Přehled tréninků
  - Přehled sportovců
  - Kalendář tréninků
  - Výkaz činností
  - Přehled závodů
- **Plánovač** (dle oprávnění) — přímý odkaz na `planovac.php`; zobrazuje plánované tréninky a umožňuje jejich správu
- **Rezervace** (dropdown, dle oprávnění):
  - Kalendář sportovišť — přehled obsazenosti sportovišť
  - Nová rezervace — interní rezervace sportoviště
  - Individuální lekce — správa placených lekcí
  - Nová lekce — vytvoření nové individuální lekce
  - Veřejný kalendář — odkaz na booking pro zákazníky (nová záložka)
- **Admin** (dropdown, pouze hlavní trenér):
  - Správa tréninků
  - Správa skupin
  - Správa podskupin
  - Správa sportovišť
  - Správa trenérů
  - Veřejný přehled
  - Všechny výkazy
  - *(oddělovač)*
  - **Firemní evidence:**
    - Vozidla
    - Účtenky
    - Události
- **Globální hledání** — vyhledávací pole uprostřed navbaru (zobrazeno po přihlášení):
  - Hledá napříč celou aplikací: sportovci, tréninky, závody
  - Výsledky se zobrazí v rozbalovacím panelu pod polem
  - Navigace klávesami ↑↓, potvrzení Enterem, zavření Escapem
- **Jméno trenéra** — zobrazeno vpravo
- **Odhlásit** — odhlášení z aplikace

---

## 4. Tréninky

### 4.1 Nový trénink

Stránka: **Vložit → Nový trénink**

#### Základní údaje
- **Datum** — výběr data tréninku
  - Běžný trenér: dropdown s omezeným rozsahem (dle nastavení administrátora)
  - Hlavní trenér: libovolné datum
- **Skupina** — výběr skupiny z dropdown menu
- **Délka** — délka tréninku v minutách
- **Náplň** — textový popis obsahu tréninku (víceřádkový)
- **Místo / Poznámka** — doplňující informace

#### Účastníci
- **Sportovci** — vyhledávání podle jména s automatickým napovídáním, vybraní sportovci se zobrazují jako štítky
- **Podskupiny** — zaškrtávací políčka (načítají se dynamicky podle vybrané skupiny)
- **Trenéři** — výběr přiřazených trenérů
- **Tagy** — volitelné štítky tréninku

#### Měření
Ke každému tréninku lze přidat měření. Typy měření:

| Typ | Pole |
|-----|------|
| **Kolo** | Vzdálenost (km), Čas, Převod |
| **Kolo - Kroužek** | Segment (dropdown), Čas, Poznámka |
| **Kolo - Silnice** | Segment (dropdown), Čas, Poznámka |
| **Kolo - MTB** | Segment (dropdown), Čas, Poznámka |
| **Běh** | Vzdálenost (km), Čas |
| **Posilovna** | Cvik (dropdown), Váha (kg), Opakování, RPE |

Segmenty se spravují v **Nastavení → Segmenty**. Každý segment má kategorii (Kroužek / Silnice / MTB), název, popis, fotografii a dva URL odkazy (např. mapy.cz).

Měření se přidávají tlačítkem **+** a odebírají tlačítkem **×** u každého řádku.

#### Obrázky
- Možnost nahrát více obrázků k tréninku

#### Uložení
- Klikněte na **Uložit trénink**
- **Zrušit** — návrat bez uložení

### 4.2 Úprava tréninku

Ze seznamu tréninků klikněte na tlačítko **Upravit**. Otevře se stejný formulář jako pro nový trénink, předvyplněný aktuálními daty. Upravit trénink může pouze přiřazený trenér nebo hlavní trenér.

### 4.3 Duplikace tréninku

Stránka: **Vložit → Duplikovat trénink (soustředění)**

1. Vyberte skupinu (volitelně) pro filtrování
2. Ze seznamu posledních 200 tréninků klikněte na **Duplikovat**
3. Otevře se formulář nového tréninku s předvyplněnými daty:
   - Skupina, podskupiny a účastníci se přenesou
   - Datum se nastaví na dnešní den
   - Zelený banner upozorní na předvyplnění

### 4.4 Smazání tréninku

Ze seznamu tréninků klikněte na **Smazat**. Smazání vyžaduje potvrzení.

### 4.5 Seznam tréninků (Moje tréninky)

Stránka: **Přehledy → Moje tréninky**

**Filtry:**
- **Rok** — aktuální rok nebo starší (výchozí: aktuální rok)
- **Měsíc** — Leden až Prosinec (výchozí: aktuální měsíc)
- **Skupina** — filtr podle skupiny
- **Hledat** — fulltext vyhledávání v náplni a poznámce tréninku
- **Zrušit filtr** — reset všech filtrů

Tréninky se zobrazují jako rozbalovací záznamy (accordion). U každého tréninku vidíte:
- Datum a den v týdnu
- Náplň
- Délku
- Počet sportovců
- Měření
- Obrázky

**Inline editace poznámky:** U každého tréninku můžete kliknout na **✏️**, upravit text a uložit přímo v seznamu.

---

## 5. Sportovci

### 5.1 Přehled sportovců

Stránka: **Přehledy → Přehled sportovců**

**Filtry:**
- **Vyhledávání** — zadejte jméno nebo příjmení
- **Skupina** — filtr podle skupiny
- **Podskupina** — filtr podle podskupiny (závisí na vybrané skupině)

**Tabulka sportovců zobrazuje:**
- Příjmení
- Jméno
- Tlačítko **Veřejný profil** — odkaz na veřejně přístupný profil
- Tlačítko **Profil** — detailní profil sportovce

### 5.2 Detail sportovce

Stránka se otevře z přehledu sportovců klikem na **Profil**.

**Vyhledání:** AJAX live search (debounce 300 ms) s filtrem skupiny.

**Souhrnné statistiky** (4 stat karty):
- Tréninků celkem, Měření celkem, Tréninků v období, Poslední trénink

**Záložky** (URL hash persistence — odkaz `?id=5#tab-treninky` otevře přímo tréninky):
- **Profil** — editace UCI ID, email, kategorie, datum narození, oddíl; zobrazení skupin a aktuálního kreditu; AJAX ukládání s toast notifikací
- **Tréninky** — tabulka s datem, délkou, kategorií (barevný odznak), náplní, počtem měření, poznámkou; klient-side filtr
- **Měření** — tabulka s filtrem podle typu (kolo/běh/posilovna); barevné badge typů
- **Zátěžové testy** — tabulka s datem, věkem, váhou, výškou, popisy a soubory (obrázky/přílohy)
- **Nadcházející tréninky** — plánované tréninky z Plánovače přiřazené skupině sportovce; zobrazuje datum, čas, kategorii, trenéra, sportoviště; u evidovaných tréninků odkaz na záznam, u neevidovaných tlačítko "Zadat evidenci"

Notifikace (toast) se zobrazí po úspěšném uložení profilu.

### 5.3 Veřejný profil sportovce

Přístupný bez přihlášení přes unikátní odkaz (hash). Zobrazuje historii tréninků sportovce pro sdílení s rodiči, klubem apod.

---

## 6. Skupiny a podskupiny

### 6.1 Správa skupin (pouze hlavní trenér)

Stránka: **Admin → Správa skupin**

Tabulka zobrazuje existující skupiny s ID, názvem, pořadím a zkráceným hashem. Všechny operace jsou chráněny CSRF tokeny.

- **Přidat skupinu:** Název, Pořadí (pro řazení v seznamech), Hash (volitelný — auto-generace při ponechání prázdného)
- **Upravit:** Klikem na ikonu tužky u skupiny se formulář předvyplní a posune na správné místo
- **Smazat:** Tlačítko smazání s potvrzovacím dialogem (POST metoda)
- **Flash zprávy:** Po úspěšné akci se zobrazí zelená potvrzovací zpráva, při chybě červená

### 6.2 Správa podskupin (pouze hlavní trenér)

Stránka: **Admin → Správa podskupin**

- **Přidat podskupinu:** Název, Pořadí, Nadřazená skupina (výběr ze seznamu)
- Podskupiny jsou řazeny nejdříve podle skupiny, poté podle pořadí
- Stejné UX jako u skupin — editace klikem na ikonu tužky, smazání s potvrzením, flash zprávy

### 6.3 Hromadná správa

Stránka: **Skupiny a podskupiny** (závodní sekce)

Umožňuje editovat více skupin a podskupin na jedné stránce. Obsahuje archivní skupinu pro neaktivní podskupiny.

---

## 7. Přehledy a reporty

### 7.1 Přehled tréninků (měsíční)

Stránka: **Přehledy → Přehled tréninků**

**Filtry:**
- **Měsíc** — výběr měsíce a roku
- **Skupina** — volitelný filtr podle skupiny

**Souhrnné statistiky** (zobrazeny nad tabulkou):
- Počet tréninků v daném měsíci
- Celkový počet hodin
- Průměrná délka tréninku (hod)
- Počet unikátních sportovců

**Tabulka tréninků** obsahuje:
- Datum a den v týdnu
- **Kategorie** — barevný odznak (Silnice, MTB, Dráha, Cyklokros, Posilovna, Atletika, Cvičení, Plavání)
- Délka (hod)
- Náplň (zkrácený náhled, plný text v tooltip)
- Poznámka (zkrácený náhled)
- Počet sportovců a měření
- Tlačítko **Upravit** — přímý odkaz na editaci tréninku

**Legenda** barevných kategorií se zobrazuje pod tabulkou.

Tlačítko **Export XLSX** — stáhne měsíční přehled jako Excel soubor.

### 7.2 Kalendář tréninků

Stránka: **Přehledy → Kalendář tréninků**

Kalendářové zobrazení tréninků po skupinách.

### 7.3 Výkaz činností

Stránka: **Přehledy → Výkaz činností**

Měsíční výkaz aktivit trenéra s možností exportu do Excelu.

**Filtr:**
- **Měsíc** — výběr měsíce a roku

**Souhrnné statistiky** (zobrazeny nad tabulkami):
- Hodin celkem — součet tréninků + dalších činností
- Počet tréninků
- Počet dalších činností
- Položek celkem

**Tréninky** — tabulka s datem, dnem, náplní, kategorií (barevný odznak) a délkou. Součtový řádek zobrazuje celkové hodiny tréninků.

**Další činnosti** — tabulka s datem, dnem, názvem, poznámkou a délkou. Součtový řádek zobrazuje celkové hodiny činností.

**Celkový součet** — zvýrazněná karta s celkovým počtem odpracovaných hodin za měsíc.

Tlačítko **Export XLSX** — stáhne měsíční výkaz jako Excel soubor.

### 7.4 Přehled kreditů (pouze hlavní trenér)

Stránka: **Administrace → Přehled kreditů sportovců**

Zobrazuje:
- **Aktivní období** — s průběžným počtem tréninků a kalkulací částky
- **Neuhrazená uzavřená období** — čekající na vyplacení
- **Historie vyplacených období** — za posledních 12 měsíců

Rychlé akce:
- **Uzavřít období** — spočítá tréninky a celkovou částku k dnešnímu datu
- **Označit jako vyplaceno** — označí období jako uhrazené

### 7.5 Správa kreditních období (pouze hlavní trenér)

Stránka: **Administrace → Správa kreditních období**

Vytváření nových kreditních období pro sportovce:
- Datum od
- Sazba (Kč za trénink)

Editace a uzavírání existujících období.

---

## 8. Závody

### 8.1 Přehled závodů

Stránka: **Přehledy → Přehled závodů**

**Filtry:**
- **Skupina** — filtr podle skupiny (dynamicky načítá podskupiny)
- **Podskupina** — volitelný filtr podle podskupiny
- **Kategorie** — Silnice / Dráha / MTB / vše
- **Interval** — 1 měsíc / 2 měsíce / 6 měsíců / 12 měsíců
- Tlačítko **Zrušit filtr** — reset na výchozí stav

**Souhrnné statistiky** (4 karty nad tabulkou):
- Závodů celkem
- Počet trenérů
- Rozsah dat (od – do)
- Nejbližší závod (budoucí datum)

**Tabulka závodů:**
- Datum (formát dd.mm.YYYY)
- Den v týdnu (víkendové dny zvýrazněny červeně)
- **Kategorie** — barevný odznak (Silnice=zelená, Dráha=modrá, MTB=žlutá)
- Popis závodu
- Počet účastníků
- **Trenéři** — zobrazeni jako barevné odznaky (badge), každý trenér má přiřazenou barvu
- Budoucí závody jsou zvýrazněny žlutým pozadím
- Tlačítko **Detail** — odkaz na detail závodu
- Správce vidí navíc tlačítko **Upravit** s odkazem na editaci závodu

**Legenda trenérů** se zobrazuje pod tabulkou pokud je trenérů více.

Tlačítko **Nový závod** (pro oprávněné) — přímý odkaz na formulář závodu.

### 8.2 Přidání nového závodu

Stránka: **Přehled závodů → Nový závod**

Formulář závodu obsahuje:

**Základní údaje:**
- **Datum** závodu
- **Kategorie** — Silnice / Dráha / MTB (barevné ikony)
- **Popis závodu** (veřejný) — zobrazuje se v přehledu a na profilu sportovce
- **Interní poznámka** — viditelná pouze trenérům
- **URL výsledků** — odkaz na výsledky na externím webu (volitelné)

**Přiřazení:**
- **Skupiny** a **Podskupiny** — checkboxy
- **Trenéři** — checkboxy přiřazených trenérů

**Účastníci:**
- Vyhledávání sportovců s automatickým napovídáním — stejný systém jako u tréninků
- Vybraní sportovci se zobrazují jako štítky

**Měření:**
- Stejný systém měření jako u tréninků — typy Kolo, Kolo-Kroužek, Kolo-Silnice, Kolo-MTB, Běh, Posilovna
- Více měření na jeden závod

**Soubory:**
- **Fotky** — JPG/PNG/WEBP (galerie v detailu)
- **Výsledkové soubory** — XLS/XLSX/PDF ke stažení

### 8.3 Detail závodu

Stránka: **Přehled závodů → tlačítko Detail** (nebo `zavod_detail.php?id=`)

Zobrazuje kompletní informace o závodě:

- **Hero panel** — kategorie badge, datum, popis, přiřazení trenéři
- **Statistiky** — počet závodníků, závodníků s výsledky, souborů, měření
- **Výsledková tabulka** — pořadí · jméno (hypertextový odkaz pro interní sportovce) · klub · startovní kategorie · čas · body
  - Interní sportovci: odkaz na jejich profil (`sportovec_detail.php`)
  - Externí závodníci: jméno bez odkazu + odznak "extern"
  - Zlaté/stříbrné/bronzové zvýraznění (top 3)
- **Měření** — tabulka měření s barevnými odznaky typů
- **Fotky** — galerie
- **Soubory** — ke stažení (PDF, XLS, XLSX)
- **URL výsledků** — odkaz na externí výsledky (je-li vyplněno)

Tlačítko **Upravit** — viditelné pouze pro správce.

### 8.4 Správa závodů (pouze správce)

Stránka: **Admin → Správa závodů**

Admin přehled závodů s kategorie badge, počtem účastníků, fotkami a soubory. Tlačítka **Detail** a **Upravit** u každého záznamu.

### 8.5 Závodní karta sportovce

**Záložka Závody** v `sportovec_detail.php` — zobrazuje všechny závody sportovce s datem, kategorií, názvem, pořadím, časem a body. Odkaz na detail každého závodu.

**Veřejný profil** `sportovec_treninky.php` — sekce Závody zobrazuje závody dle roku, bez interních poznámek.

---

## 9. Exporty

### 9.1 Export – dráha (XLSX)

Stránka: **Export – dráha (XLSX)** v závodní sekci

1. Vyberte skupinu
2. Zaškrtněte sportovce pro export
3. Klikněte na **Exportovat**

Výstup: Excel soubor s přihlašovací tabulkou pro dráhové závody (UCI ID, jméno, tým, kategorie, rok narození).

### 9.2 Export – UCI přihláška (XLSX)

Stránka: **Export – UCI přihláška (XLSX)** v závodní sekci

Třístupňový export dat sportovců ve formátu UCI pro mezinárodní registraci:

1. Nahrajte předvyplněný UCI Enrollment Form (.xlsx)
2. Vyberte skupinu a zaškrtněte sportovce (max. 11 — prvních 8 titular, 9–11 substitute)
3. Klikněte na **Exportovat do UCI formuláře** — stáhne se vyplněný formulář

Automaticky doplňuje: Příjmení, Jméno, NAT (CZE), Datum narození, UCI ID a Sport Directors (Mixa + Papík).

### 9.3 Export – Seznam sportovců (XLSX)

Stránka: **Export – Seznam sportovců (XLSX)** v závodní sekci

Jednoduchý export vybraných sportovců do čistého Excel souboru:

1. Vyberte skupinu z dropdown menu
2. Zaškrtněte sportovce pro export (bez limitu počtu, lze zaškrtnout všechny)
3. Klikněte na **Exportovat do XLSX**

**Exportované sloupce:**
| Sloupec | Formát | Popis |
|---------|--------|-------|
| Příjmení | text | Příjmení sportovce |
| Jméno | text | Křestní jméno |
| Datum narození | dd-mm-yyyy | Datum narození |
| Ročník | yyyy | Rok narození |
| Kategorie | text | Sportovní kategorie (masters, junioři, kadeti...) |
| UCI ID | text | UCI identifikátor |

Výstupní soubor: `Seznam_{nazev_skupiny}_{datum}.xlsx` se stylovanou hlavičkou.

**Funkce tabulky:**
- Řazení podle příjmení, jména, data narození nebo kategorie (klik na hlavičku)
- Hromadné zaškrtnutí / odškrtnutí všech sportovců
- Sticky počítadlo vybraných sportovců

### 9.4 Export tréninků do Excelu

Z přehledu tréninků (měsíční) klikněte na **Export do Excelu**. Stáhne se soubor s tréninky za vybraný měsíc.

---

## 10. Zátěžové testy

Stránka: **Vložit → Zátěžový test**

### Formulář zátěžového testu
- **Sportovec** — výběr sportovce (povinné)
- **Datum testu**
- **Věk, výška, váha** — volitelné údaje
- **Interní poznámky** — viditelné pouze pro trenéry
- **Popis pro sportovce** — viditelný sportovci ve veřejném profilu
- **Soubory:**
  - Soubory pro sportovce (obrázky, grafy)
  - Interní soubory (pro trenéry)
  - Ostatní soubory (FIT, XLS, PDF, CSV)

Nahrané soubory jsou organizovány do tří složek podle typu přístupu.

---

## 11. Story generátor

### 11.1 Nastavení vzhledu

Stránka: **Nastavení story** (dostupné přes administraci)

Konfigurace pro každou skupinu/podskupinu:
- **Barva pruhu** — barva horního a dolního pruhu
- **Barva textu** — barva textu v pruzích
- **Text hlavičky** — text v horním pruhu
- **Text patičky** — text v dolním pruhu
- **Logo** — nahrání loga (PNG/JPG)

### 11.2 Generování story

Z detailu tréninku klikněte na **Generovat story**. Systém vytvoří obrázek o rozměrech 1080 × 1920 px (formát Instagram story):

- **Horní pruh** (120 px) — barva + text hlavičky + logo (levý horní roh)
- **Střed** — obrázky sportovců jako pozadí (center-crop)
- **Dolní pruh** (120 px) — barva + text patičky
- **Gradientní přechody** — na okrajích obrázků plynulý přechod do barvy pruhu (neostré hrany)
- **Text s drop-shadow** — text tréninku (datum česky, náplň) vykreslený přes fotografie se stínem pro čitelnost
- **České datum** — automatické formátování data do češtiny (např. „15. března 2026")
- **Jména sportovců** — seznam účastníků tréninku vykreslený na obrázku

**Podporované formáty obrázků:** JPEG, PNG, WebP, HEIC/HEIF (vyžaduje Imagick)

**Výstup:** JPEG kvalita 90 %, uloženo do `stories/story_{id}_{timestamp}.jpg`

Vygenerovaný obrázek se uloží a nabídne ke stažení.

---

## 12. Emaily

Stránka: **Administrace → Odeslat emaily sportovcům** (pouze hlavní trenér)

Hromadné odeslání emailů sportovcům s odkazem na jejich veřejný profil.

**Šablona emailu:**
- Předmět (výchozí: „Tvůj profil v evidenci tréninků")
- Tělo emailu s proměnnými:
  - `{jmeno}` — křestní jméno sportovce
  - `{prijmeni}` — příjmení sportovce
  - `{odkaz}` — odkaz na veřejný profil
  - `{email}` — emailová adresa sportovce

Systém loguje úspěšnost odeslání (odesláno / chyba / bez emailu).

---

## 13. Oznámení

Stránka: **Oznámení a zprávy** v závodní sekci

### Vytvoření oznámení
- **Název** — titulek oznámení
- **Obsah** — text s možností základního formátování (tučné, kurzíva, seznamy, odkazy)
- **Cílení:**
  - Podle skupiny
  - Podle podskupiny
  - Podle konkrétního sportovce

### Správa oznámení
- Seznam všech oznámení
- Editace a mazání

---

## 14. Nastavení

### 14.1 Správa trenérů (pouze hlavní trenér)

Stránka: **Admin → Správa trenérů**

Přehled všech trenérů s počtem tréninků. U každého trenéra se zobrazuje:
- **Jméno** — přihlašovací jméno
- **Email**
- **Role** — barevný badge: Hlavní trenér (červený štít) / Trenér (modrý)
- **Počet tréninků** — celkový počet přiřazených tréninků
- **Vy** — označení aktuálně přihlášeného trenéra

**Operace:**
- **Přidat trenéra:** Jméno, email, heslo, role (trenér / hlavní trenér)
- **Upravit trenéra:** Klikem na ikonu tužky se formulář předvyplní — heslo lze ponechat prázdné (zůstane beze změny)
- **Smazat trenéra:** Klikem na ikonu koše s potvrzením — nelze smazat sám sebe

Všechny operace jsou chráněny CSRF tokeny. Po úspěšné akci se zobrazí flash zpráva.

### 14.2 Správa tréninků (pouze hlavní trenér)

Stránka: **Admin → Správa tréninků**

Přehled všech tréninků v systému s pokročilými filtry a fulltextovým hledáním.

**Filtry:**
- **Skupina** — filtr podle skupiny (dynamicky načítá podskupiny)
- **Podskupina** — volitelný filtr
- **Kategorie** — filtr podle typu tréninku (Silnice, MTB, Dráha, Cyklokros, Posilovna, Atletika, Cvičení, Plavání)
- **Trenéři** — výběr jednoho nebo více trenérů
- **Hledat** — fulltext hledání v náplni a poznámce (server-side i client-side filtrování)

**Souhrnné statistiky:**
- Počet tréninků, celkové hodiny, rozložení podle kategorií (dynamické karty)

**Tabulka tréninků:**
- Datum, den, kategorie (barevný odznak), délka, fotky (thumbnaily), trenéři
- Rozbalovací detail s náplní, poznámkou a seznamem účastníků
- Tlačítka **Upravit** a **Smazat** (s potvrzením)

### 14.3 Okno pro zadávání tréninků (pouze hlavní trenér)

Stránka: **Admin → Okno pro zadávání tréninků**

Určuje, kolik dní zpětně mohou běžní trenéři zadávat tréninky. Nastavení je **dynamické (rolling)** — zadaný počet dní se automaticky odečítá od aktuálního data při každém requestu. Není tedy potřeba opakovaně měnit nastavení.

**Příklad:** Nastavíte „2 dny zpět" → trenéři vždy vidí dnešek a předchozí 2 dny, bez ohledu na to, kdy se přihlásí.

**Rychlé předvolby:**
- 2 dny zpět (výchozí)
- 7 dní
- 14 dní
- 30 dní

Nebo vlastní počet dní (0–365). Náhled ukazuje, jaká data uvidí trenéři ve formuláři k dnešnímu dni.

> Hlavní trenéři mohou zadávat tréninky na libovolné datum bez omezení.

### 14.4 Cviky v posilovně

Stránka: **Nastavení → Cviky v posilovně**

Správa cviků dostupných v měření typu „posilovna":
- Přidání nového cviku
- Úprava názvu a popisu
- Nastavení pořadí

### 14.5 Nastavení odměn

Stránka: **Nastavení → Nastavení odměn**

Konfigurace odměnového systému pro sportovce.

---

## 15. Účetní modul (firemní evidence — pouze admin)

> Celý účetní modul (vozidla, jízdy, servis, účtenky, události) je přístupný **pouze hlavním trenérům** (administrátorům). Běžní trenéři tyto sekce nevidí v navigaci a nemají k nim přístup.

### 15.1 Vozidla

Stránka: **Admin → Firemní evidence → Vozidla**

Evidence klubových vozidel s kompletním CRUD rozhraním:
- Značka a model
- SPZ (registrační značka)
- Rok výroby
- Typ paliva
- Datum STK
- Datum dálniční známky
- Poznámka

Přidání/úprava probíhá přes AJAX formulář s okamžitou odezvou. Smazání vyžaduje potvrzení a je logováno v audit logu.

### 15.2 Jízdy

Stránka: **Přes detail vozidla**

Záznam jízd s klubovými vozidly:
- Výběr vozidla a řidiče (trenéra)
- Zahájení jízdy — datum, místo, stav tachometru
- Ukončení jízdy — datum, místo, stav tachometru
- Automatický výpočet ujetých kilometrů
- Poznámka

### 15.3 Servis

Stránka: **Přes detail vozidla**

Evidence servisních záznamů vozidel:
- Datum provedení
- Popis prací
- Plánovaná další kontrola
- Příloha dokumentu (PDF, JPG, PNG) — s MIME validací

Nahrané soubory jsou validovány přes `finfo_file()` (povolené: `image/jpeg`, `image/png`, `application/pdf`). Při smazání se soubor přejmenuje s prefixem `smazano_` (soft delete).

### 15.4 Účtenky

Stránka: **Admin → Firemní evidence → Účtenky**

Evidence účtenek a dokladů s přehledným seznamem:

**Údaje účtenky:**
- **Částka** — formátovaná s oddělovačem tisíců
- **Způsob platby** — barevně odlišené badge:
  - Kartou – tým (modrý)
  - Hotově – tým (zelený)
  - Vlastní kartou (fialový)
  - Vlastní hotovost (oranžový)
- **Kategorie** — závodní oddíl / velodromní oblast / cyklistický kroužek / ostatní
- **Přiřazení k vozidlu** (volitelné) — zobrazuje značku a SPZ
- **Přiřazení k události** (volitelné) — zobrazuje název události
- **Foto účtenky** — nahrání obrázku (JPG, PNG) nebo PDF

**Filtrování:** V seznamu lze filtrovat účtenky dle kategorie přes dropdown.

**Smazání:** POST metoda s CSRF ochranou a potvrzovacím dialogem.

### 15.5 Události

Stránka: **Admin → Firemní evidence → Události**

Správa klubových událostí:
- **Název a popis**
- **Datum od/do** (datetime-local picker)
- **Typ události** — barevně odlišené badge:
  - Závod (červený)
  - Soustředění (modrý)
  - Trénink (zelený)
  - Jiné (šedý)
- **Zálohová částka** — kolik peněz bylo svěřeno
- **Komu svěřena záloha** — jméno osoby
- **Stav** — Otevřená (zelený badge) / Uzavřená (šedý badge)

V seznamu událostí se zobrazuje také **počet účtenek** a **celková částka** přiřazená k události.

### 15.6 Vyúčtování události

Stránka: **Tlačítko „Vyúčtování"** u každé události v seznamu

Přehled finanční bilance události zobrazený jako 4 karty:

| Karta | Popis |
|-------|-------|
| **Záloha** | Svěřená záloha v Kč + jméno osoby |
| **Hotově – tým** | Součet účtenek placených týmovou hotovostí |
| **Rozdíl** | Záloha minus hotovostní výdaje (zelená = přebytek, červená = přečerpání) |
| **Celkem účtenky** | Součet všech účtenek k události + počet účtenek |

Pod kartami je seznam všech účtenek přiřazených k události s datem, částkou, způsobem platby, kategorií, poznámkou a jménem zadavatele.

**Uzavření události:** Tlačítko „Uzavřít událost" změní stav na uzavřenou (s potvrzením). Uzavřená událost se označí badge „Uzavřená" a nelze ji znovu uzavřít.

---

## 16. Audit log

Stránka: dostupná přes účetní modul

Zobrazuje historii všech změn v systému:
- Kdo provedl akci
- Typ akce (přidání, úprava, smazání)
- Ovlivněná tabulka a záznam
- Detail změny
- IP adresa a čas

Lze filtrovat podle trenéra a typu akce.

---

## 17. Synchronizace evidence (admin)

Stránka: **Admin → Synchronizace evidence**

4-krokový wizard pro synchronizaci s KIS. Nahrávají se tři soubory:

1. **Uživatelé z KIS** — členské údaje a kontakty
2. **Export plateb** — zaplaceno, čeká na zaplacení, zbývá zaplatit
3. **Soupisky z KIS** — aktuální soupisky a příznak aktivní

Průběh:

1. **Upload** — nahrání všech tří XLSX exportů
2. **Mapování soupisek** — přiřazení soupisek z KIS na skupiny a podskupiny v systému; mapování se ukládá pro opakované použití
3. **Preview** — náhled změn: noví sportovci, aktualizovaní, beze změn a počet osob v DB mimo aktuální KIS import
4. **Provedení** — spuštění synchronizace

Synchronizace doplňuje nové lidi, aktualizuje kontakty, ukládá KIS aktivitu a platební stav. Automatická archivace lidí chybějících v importu je vypnutá; takové osoby se pouze započítají v preview.

---

## 18. Správa segmentů

Stránka: **Nastavení → Segmenty**

Správa segmentů cyklistických tras pro měření typu Kolo - Kroužek, Kolo - Silnice a Kolo - MTB.

- **Kategorie**: Kroužek / Silnice / MTB — filtr záložkami
- **Fotografie**: nahrání JPG/PNG/WEBP k segmentu
- **Odkazy**: dva URL odkazy (mapy.cz, Strava apod.)
- **Aktivace/Deaktivace**: neaktivní segmenty se nezobrazují ve formuláři tréninku

---

## 19. Role a oprávnění

Aplikace má 3 úrovně přístupu:

| Role | Popis |
|------|-------|
| **Trenér** | Základní — vlastní tréninky, přehledy, exporty |
| **Správce** | + správa sportovců, skupin, závodů, tréninků, kredity, synchronizace |
| **Administrátor** | + správa trenérů a rolí, firemní evidence, nastavení systému |

Roli nastavuje administrátor v **Správa → Správa trenérů**.

### Nastavení oprávnění

Stránka: **Správa → Nastavení oprávnění** (pouze administrátor)

Administrátor může u každé funkce nastavit minimální roli potřebnou pro přístup. Změny se projeví po příštím přihlášení uživatelů.

---

## 20. Rezervace sportovišť

Přidáno ve verzi 2.8.0. Trenéři rezervují sportoviště pro týmové tréninky nebo individuální lekce.

### 20.1 Kalendář sportovišť

Stránka: **Rezervace → Kalendář sportovišť**

Týdenní přehled obsazenosti všech sportovišť:
- Sportoviště jako řádky, dny v týdnu jako sloupce
- Rezervace zobrazeny jako barevné bloky (každý trenér má přiřazenou barvu)
- Pod každým blokem se zobrazuje využitá kapacita (např. „3/5")
- Ukazatel obsazenosti: zelená (< 60 %), žlutá (< 100 %), červená (100 %)
- **Navigace:** tlačítka Předchozí týden / Dnešní týden / Další týden
- **Přidat rezervaci:** ikona „+" v konkrétní buňce (sportoviště × den)
- **Smazat rezervaci:** tlačítko × na bloku (vlastní rezervaci nebo jakoukoliv jako správce)

### 20.2 Nová interní rezervace

Stránka: **Rezervace → Nová rezervace**

Formulář pro rezervaci sportoviště:
- **Sportoviště** — výběr ze seznamu aktivních sportovišť
- **Datum** — výběr data
- **Čas od / Čas do** — rozsah rezervace
- **Kapacita (1–5)** — kolik dílů sportoviště rezervace zabere; slider s textovým popisem
- **Poznámka** — volitelný interní popis

Live indikátor dostupnosti se zobrazí po vyplnění sportoviště, data a času — zobrazuje aktuálně obsazenou kapacitu **interních rezervací** (individuální lekce se do kapacity nezapočítávají).

> Rezervaci nelze uložit, pokud by `obsazeno + nová kapacita > max. kapacita` sportoviště (standardně 5).

Pokud byl formulář otevřen z Plánovače (`?plan_id=X`), je předvyplněn datem, časem a sportovištěm z plánovaného tréninku.

### 20.3 Individuální lekce — vypsání

Stránka: **Rezervace → Nová lekce**

Trenér vypíše placenou lekci pro zákazníky veřejného bookingu:
- **Sportoviště** — pouze veřejná sportoviště (Velodrom, Horní posilovna)
- **Datum, Čas od, Čas do** — časové okno celé lekce
- **Délka slotu (min)** — zákazník rezervuje úsek tohoto trvání v rámci časového okna (např. 60 min v rámci okna 8:00–12:00); 0 = zákazník rezervuje celou lekci
- **Typ lekce:**
  - **Zelená** — zákazník rezervuje ihned, trenér dostane informační email
  - **Žlutá** — zákazník odešle žádost, trenér musí potvrdit nebo zamítnout
- **Název** — zobrazovaný název lekce v bookingu
- **Popis** — detailnější popis
- **Cena (Kč)** — cena za osobu (za slot)
- **Max. osob** — maximální kapacita lekce

> Od verze 2.9.0 individuální lekce **nezabírají** kapacitu sportoviště v kalendáři. V týdenním kalendáři sportovišť se zobrazují jako informační bloky s přerušovaným rámečkem. Pokud se lekce překrývá s existující interní rezervací, zobrazí se upozornění — uložení není zablokováno.

### 20.4 Správa individuálních lekcí

Stránka: **Rezervace → Individuální lekce**

Přehled všech vypsaných lekcí s jejich rezervacemi:
- **Stav lekce:** aktivní / zrušená
- **Stav rezervace:** Čeká / Potvrzena / Zamítnuta / Zrušena
- **Platba:** badge „Zaplaceno" pro potvrzené zaplacené rezervace

Akce u každé rezervace:
- **Potvrdit** / **Zamítnout** — pro žluté lekce, pošle email zákazníkovi
- **Označit zaplaceno** / **Zrušit označení** — manuální evidence platby
- **Zrušit lekci** — celá lekce se zruší (u všech existujících rezervací se zobrazí zrušeno)

---

## 21. Veřejný booking — zákazníci

Přidáno ve verzi 2.8.0. Zákazníci si sami registrují účet a rezervují individuální lekce.

Vstup: tlačítko **Veřejný kalendář** v menu nebo přímý odkaz `/booking/`.

### 21.1 Registrace zákazníka

Stránka: `/booking/registrace.php`

Zákazník vyplní:
- **Jméno a příjmení**
- **Email** — slouží jako přihlašovací jméno (musí být unikátní)
- **Telefon** (volitelné)
- **Heslo** (a potvrzení)

Po odeslání přijde verifikační email. Zákazník musí kliknout na odkaz v emailu, aby mohl rezervovat lekce.

### 21.2 Přihlášení

Stránka: `/booking/prihlaseni.php`

Zákazník zadá email a heslo. Přihlašovací session je oddělena od trenérské části aplikace.

### 21.3 Kalendář lekcí

Stránka: `/booking/kalendar.php`

Zobrazuje dostupné lekce na velodromu a horní posilovně na příštích 14 dní:
- **Zelené lekce** — okamžitě rezervovatelné
- **Žluté lekce** — „podléhá potvrzení trenérem"
- Zobrazuje: název lekce, sportoviště, čas, cenu, jméno trenéra, zbývající kapacitu
- **„Již rezervováno"** — zobrazí se pro lekce, kde zákazník už má aktivní rezervaci

### 21.4 Rezervace lekce nebo slotu

Zákazník klikne na lekci v kalendáři. Pokud má lekce nastavenu délku slotu, zákazník si vybere konkrétní čas v rámci dostupného okna. Poté odešle žádost o rezervaci:

**Zelená lekce:**
1. Rezervace (nebo slot) je okamžitě potvrzena
2. Trenér dostane informační email

**Žlutá lekce:**
1. Rezervace čeká na schválení (`stav = ceka`)
2. Trenér dostane email s tlačítky Potvrdit / Zamítnout
3. Zákazník dostane email s výsledkem

> Minimální doba pro rezervaci: **3 dny** před konáním lekce.

### 21.5 Moje rezervace a storno

Stránka: `/booking/moje_rezervace.php`

Zákazník vidí všechny své rezervace seřazené od nejnovějších:
- Datum, sportoviště, lekce, cena
- Barevné odznaky stavu (Čeká / Potvrzena / Zamítnuta / Zrušena)
- Odznak „Zaplaceno" u potvrzených zaplacených rezervací

**Storno:** Tlačítko „Stornovat" je aktivní pouze pokud datum lekce je **alespoň 3 dny v budoucnosti** a stav je Čeká nebo Potvrzena.

---

## 22. Plánovač tréninků

Přidáno ve verzi 2.10.0. Systém pro plánování tréninků dopředu — trenér zadá plán, který se eviduje až poté, co trénink proběhne.

### 22.1 Přehled plánů

Stránka: **Plánovač** (odkaz v navigaci)

Zobrazuje všechny plánované tréninky s filtry:
- **Skupina** — filtr podle skupiny
- **Trenér** — filtr podle autora plánu
- **Stav** — Plánovaný / Evidovaný / Zrušený / Vše
- Tlačítko **Sdílet program** — pokud je vybrána skupina, otevře veřejný program skupiny v nové záložce

Každý plán zobrazuje: datum, čas, kategorii (barevný odznak), skupinu, podskupinu, sportoviště a trenéra. U evidovaných plánů je odkaz na příslušný trénink.

### 22.2 Nový plán

Stránka: **Plánovač → Nový plán** (nebo ikona + v přehledu)

Formulář plánovaného tréninku:
- **Datum** — povinné
- **Čas od / do** — volitelné
- **Skupina / Podskupina** — volitelné
- **Sportoviště** — volitelné (z číselníku sportovišť)
- **Kategorie** — Silnice / MTB / Dráha / Cyklokros / Posilovna / Atletika / Cvičení / Plavání
- **Název** — stručný název plánu
- **Popis** — podrobnosti pro trenéra

### 22.3 Zadání evidence z plánu

Klikněte na tlačítko **Zadat evidenci** u plánovaného tréninku v přehledu. Otevře se formulář evidence (`formular.php`) předvyplněný daty z plánu (datum, skupina, podskupina, kategorie, název). Po uložení se plán automaticky přepne do stavu **Evidováno** a zobrazí odkaz na uložený trénink.

### 22.4 Veřejný program skupiny

Stránka: `program_skupiny.php?hash=<skupinaHash>`

Veřejně přístupná stránka (bez přihlášení) zobrazující plánované tréninky skupiny:
- Filtr horizontu: 2 / 4 / 6 / 8 týdnů dopředu (výchozí 6 týdnů)
- Tréninky seskupeny po dnech a týdnech (s číslem týdne)
- Barevné rozlišení dle kategorie tréninku
- Evidované tréninky označeny odznakem **Evidováno**
- Přímé sdílení URL odkazu s rodiči nebo sportovci

**Odkaz na tuto stránku** najdete:
- V **Správa skupin** (ikona kalendáře u každé skupiny)
- V **Plánovači** — tlačítko "Sdílet program" při vybraném filtru skupiny

### 22.5 Kopírování týdne

Tlačítko **„Kopírovat týden"** v hlavičce plánovače zkopíruje všechny plány aktuálního týdne o 7 dní dopředu. Modal zobrazí počet plánů a cílový týden před potvrzením. Kopírování respektuje aktuální filtr (Moje/Vše/trenér).

### 22.6 Série opakujících se tréninků

Ve formuláři plánovaného tréninku lze nastavit **Opakování** (žádné / počet opakování / do data). Plány v sérii sdílí odznak „Série". Tlačítko **„Zrušit celou sérii"** zruší všechny plány série najednou.

### 22.7 Nástěnka skupiny

Collapsible widget nad týdenním gridem zobrazuje posledních 5 oznámení pro viditelné skupiny. Tlačítko **„+ Nové"** otevře modal pro rychlé vytvoření oznámení (titulek, text, datum, výběr skupin).

---

## 23. Čekací listina lekcí *(2.14.0)*

Pokud je slot lekce plně obsazen, zákazník se může zapsat na **čekací listinu**.

### 23.1 Pro zákazníky

- Na plném slotu se zobrazí tlačítko **„Čekací listina (N čeká)"** — kliknutím se zákazník zapíše
- Zákazníkova pozice je viditelná v kalendáři lekcí i v **Moje rezervace** (badge „Čekací listina #N")
- Storno z čekací listiny je povoleno vždy (bez omezení 3 dnů)
- Při uvolnění slotu zákazník automaticky dostane email o přidělení místa

### 23.2 Pro trenéry

Při zamítnutí rezervace (`individualni_lekce_sprava.php`) se slot automaticky nabídne prvnímu čekajícímu. Totéž platí při zrušení zákazníkem.

---

## 24. Výjimka třídenního limitu *(2.12.0)*

Při vypisování individuální lekce lze zaškrtnout **„Výjimka 3denního limitu"** — zákazníci pak mohou lekci rezervovat i méně než 3 dny předem. Vhodné pro náhradní termíny nebo lekce na poslední chvíli.

---

## 25. Tmavý režim a klávesové zkratky *(2.16.0)*

### 25.1 Tmavý režim

Tlačítko 🌙/☀️ v pravém rohu navigace přepíná mezi světlým a tmavým tématem. Nastavení se ukládá v prohlížeči.

### 25.2 Klávesové zkratky

| Zkratka | Akce |
|---------|------|
| `N` | Nový trénink |
| `P` | Plánovač |
| `K` | Kalendář sportovišť |
| `/` | Globální hledání |
| `D` | Přepnout tmavý/světlý režim |
| `?` | Zobrazit nápovědu zkratek |
| `Esc` | Zavřít modal |

Zkratky jsou neaktivní pokud máte fokus v textovém poli.

---

## 26. Web Push notifikace *(2.17.0)*

Kliknutím na ikonu 🔔 v navigaci aktivujete push notifikace — prohlížeč si vyžádá oprávnění. Po aktivaci dostanete push notifikaci při nové rezervaci vaší individuální lekce. Notifikaci lze kdykoli deaktivovat dalším kliknutím na 🔔.

**Poznámka:** Push notifikace fungují pouze na produkčním serveru (HTTPS). Na localhostu se aktivace uloží, ale push nedorazí.

---

## Dodatek 2.20.0 - KIS centrum a administrační karta člena

`sportovec_karta.php` je administrační karta člena pro přihlášené trenéry/správce. Slouží k provozní kontrole KIS, ručnímu stavu členství, poznámkám a historii.

Veřejná karta sportovce bez přihlášení se nemění a zůstává dostupná přes hash odkaz `sportovec_treninky.php?hash=...`.

Správci mají nově k dispozici administrační kartu člena, KIS centrum, hromadné akce a admin dashboard. Nejasné shody z KIS se automaticky nepřepisují; import je nejdříve preview a rizikové řádky se musí zkontrolovat.

*Verze dokumentace: 2.20.0 — červen 2026*
