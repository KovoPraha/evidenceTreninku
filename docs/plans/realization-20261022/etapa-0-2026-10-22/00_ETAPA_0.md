# Etapa 0 zadání sjednoceného systému oddílu

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Stav k 22. 10. 2026. Návrh pro vedení oddílu, hospodáře, účetní a vývoj. Nejde o schválení implementace ani migrace.

**KIS má potřebné funkční základy, ale zatím nemá převzatou tréninkovou a závodní historii původní Evidence ani její odměny sportovců. Účetní doklady a jednotné předání účetní potřebují samostatnou vrstvu nad objednávkami, příspěvky a platbami.**

## Výstupy

1. [Zadání účetních dokladů a exportu](01_UCETNI_DOKLADY_A_EXPORT.md) – druhy dokladů, vznik a opravy, datová pole, struktura XLSX, kontroly a přejímací příklady.
2. [Inventura papírových a ručních agend](02_PAPIROVE_AGENDY.md) – potvrzené agendy i další kandidáti, potřebné údaje, odpovědnosti, existující podpora a priority.
3. [Porovnání původní Evidence a KIS](03_EVIDENCE_VS_KIS.md) – aktuální agregované výsledky, pravidla párování a konkrétní zadání bezpečného převodu.
4. [Agregované výsledky měření v JSON](LOCAL_EVIDENCE.md) – kontrolní počty bez seznamů osob a kontaktních údajů.

## Potvrzený rozsah

- Oddíl není plátcem DPH a vede jednoduché účetnictví, potvrzeno uživatelem 22. 10. 2026. Účetní dostává XLSX a faktury; používaný účetní program zatím není znám.
- Uživatel potvrdil papírovou nebo samostatnou excelovou evidenci hotovosti a výdejů oblečení, přihlášek a souhlasů, docházky a omluvenek, členských příspěvků, výdajů a záloh na akce, odměn trenérů a sportovců a zdravotních prohlídek. Konkrétní média, správci a rozsah historie zbývají doplnit.
- Prodej oblečení a dalšího zboží členům i veřejnosti, kurzy, kroužky a běžná klubová administrativa.
- Osobní vyřízení zůstává běžnou cestou; systém musí následně zachytit peníze, výdej, doklad a odpovědnou osobu.
- Původní Evidence je na `https://data.kovopraha.cz/evidence/`, cílová aplikace na `https://kis.kovopraha.cz/`.
- Půjčování kol a vybavení je mimo rozsah. Evidence vlastních provozních výdajů a již existujících jízd vozidel tím není automaticky zrušena.
- Dosavadní a stálé variabilní symboly se zachovají.

## Nejpodstatnější výsledky porovnání

| Údaj z nefiltrovaného přehledu | Původní Evidence | KIS |
|---|---:|---:|
| Záznamy osob | 10 095 | 1 504 |
| Osoby s alespoň jednou vazbou na trénink | 405 | 1 |
| Tréninky v administrátorském přehledu | 1 651 | 1 |
| Závody v přehledu | 50 | 0 |
| Aktivní období odměn sportovců | 27 | 0 |
| Průběžně vypočtené odměny | 20 910 Kč | 0 Kč |

Jde o rozsah viditelných přehledů, nikoli o audit všech databázových tabulek. V Evidenci jsou také budoucí tréninky; počet neoznačuje výhradně již uskutečněné lekce. Podrobná metodika a omezení jsou v porovnání.

Pro 1 242 osob KIS existuje jediný kandidát se shodným jménem a vyplněným datem narození odlišným od zástupné hodnoty 1. 1. 1900 na obou stranách. Z nich 324 má v Evidenci tréninkovou historii. Dalších 81 zdrojových osob s tréninky tímto pravidlem jednoznačně spárovat nelze. **Před převodem historie je potřeba schválená převodní mapa.**

U akce Soustředění Harrachov se liší záloha: Evidence 0 Kč, KIS 2 000 Kč. Pět účtenek má shodné částky v celkové výši 1 237 Kč, ale samotná shoda neprokazuje správnost zaúčtování ani totožnost příloh.

## Úkoly pro uzavření etapy 0

Odpovědnosti v tabulce jsou návrh rolí, nikoli přidělení konkrétním lidem.

| ID | Úkol | Odpovědnost | Výstup a podmínka dokončení | Návaznost |
|---|---|---|---|---|
| E0-01 | Doplnit účetní subjekt a metodiku | Účetní + hospodář | Jednoduché účetnictví potvrzeno. Zbývá právní vystavovatel, účetní období, program, čísla řad a pravidla členění příjmů/výdajů | Doklady a export |
| E0-02 | Odsouhlasit účetní balík | Účetní | Jeden anonymizovaný skutečný měsíční XLSX + faktury porovnané s navrženými poli; rozhodnutí, které listy účetní využije | Implementace exportu |
| E0-03 | Doplnit provozní podrobnosti papírových agend | Vedení + držitelé agend | Základní agendy potvrzeny. Doplnit kde se vedou, správce, historii a datum přechodu; další kandidáty inventury potvrdit ano/ne | Rozsah klubové administrativy |
| E0-04 | Doplnit úplnou inventuru zdrojových dat | Správce dat | Konzistentní export nebo ověřená záloha obou instalací, soupis tabulek a příloh, kontrolní součty a čas pořízení | Technická migrace |
| E0-05 | Vyřešit identity s historií | Registrář + vedoucí sportu | Schválené vazby stará osoba → cílová osoba; výjimky bez odhadnutých narození, bez automatické aktivace | Přenos tréninků, závodů a odměn |
| E0-06 | Rozhodnout finanční rozpory a zůstatky | Hospodář + účetní | Autoritativní záloha Harrachov; odměny, nedoplatky, přeplatky a neuzavřené akce ověřené proti prvotním dokladům | Otevření finanční evidence |
| E0-07 | Schválit migrační kontrakt | Vedení + správce dat | Rozsah historie, pravidla souběžných zápisů, rozhodný okamžik, návratový postup a přejímací protokol | Pozdější zkušební převod |

E0-01 až E0-03 lze řešit souběžně s E0-04. E0-05 vyžaduje úplné podklady z E0-04; současné párování je výchozí analýza. E0-07 navazuje na E0-05 a E0-06.

## Co je připravené a co zůstává otevřené

Připraveno je konkrétní funkční zadání pro jednoduché účetnictví, inventura s potvrzenými základními papírovými agendami a živé porovnání hlavních evidencí. Etapa 0 ještě není provozně uzavřena: chybí účetní program a podrobnosti metodiky, vlastníci a podoba jednotlivých papírových agend i rozhodnutí o sporných identitách a finančních rozdílech. Tyto body mají výše určený výstup; nebrání posouzení návrhu.

V této práci vznikly pouze místní dokumenty. Nebyl proveden import, úprava aplikačního kódu, vystavení dokladu, změna obchodních dat ani nasazení. Pro čtení přehledů se dočasně přepínala existující pracovní pozice přihlášeného uživatele; původní kontext Správce systému byl obnoven.
