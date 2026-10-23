# Sklady, prodej a vratky R18–R24

Lokální implementace 22. 10. 2026 navazuje na účetní základ `fd402b6d9e12a86d932f376c8eaff76877d9a029`.
Pracovní kopie: `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, větev
`codex/inventory-r18-r24-20261022`. Vlastník autorizoval implementaci a ověření
se subagenty. Produkce, vzdálený Git, nasazení a skutečné odesílání zůstaly mimo úkol.

## Dodaný rozsah

| Úkol | Existující základ | Doplnění |
|---|---|---|
| R18 | Produkty, SKU, varianty a staré číslo dostupnosti. | Jeden výslovně schválený fyzický sklad, kusové varianty, ověřený fyzický počátek a konkrétní otevřené rezervace. Skladová historie chrání SKU, jednotku a převod zboží na službu. Schválení FIFO vyžaduje hospodáře a podklad. |
| R19 | Staré dostupné množství snižované při rezervaci. | Samostatné fyzické kusy P, rezervace R a blokace B; dostupnost A = P − R − B. Neměnné pohyby a FIFO dávky, explicitní neznámé ocenění. Katalogová dostupnost je pouze odvozená projekce. |
| R20 | Ruční změna množství bez samostatné dodávky. | Příjemky s dodavatelem, dokladem, očekávaným/skutečným množstvím a blokovanou částí. Doplnění či oprava pořizovací ceny samostatnou historií. Vratka dodavateli odkazuje na původní dávku; nevytváří finanční výdaj. |
| R21 | Webový/hostovský checkout, expirace a celé dokončení objednávky. | Společné rezervace ve všech prodejních cestách, položkový částečný výdej, audit příjemce a skutečného času. Ochrana posledního kusu, duplicit a starých obcházejících akcí; finanční opravy omezují množství k výdeji. |
| R22 | Veřejný prodej a původní doprava. | Prodej obsluhou členu i veřejnosti přes původní cenová pravidla. Osobní nákup bez vymyšleného účtu či e-mailu. Obsluha nepotvrzuje peníze; úhradu ověřuje hospodář. Předání dopravci a výsledný stav doručení mají oddělenou historii; ztráta zásilky automaticky nevrací sklad. |
| R23 | Finanční opravy a vratky R17. | Vratkový případ od konkrétního skutečného výdeje, odpovědná osoba, další termín a kontakt. Částečné vratky, vlastní blokované zboží versus zákazníkovo zboží v reklamační péči. Výměna rezervuje novou variantu bez druhé plné tržby, pouze rozdíl má vlastní finanční vazbu. Návrat náhradní varianty zachovává její identitu a vazby na původní i rozdílové doklady. |
| R24 | Staré pohyby a první účetní export. | Inventurní soupis s revizí skladu a schválením jinou oprávněnou osobou, vysvětlené rozdíly bez rušení rezervací. Soukromá archivovaná PDF fyzických dokladů a účetní XLSX/JSON s historickými P/R/B/A, dávkami, čerpáním, cenovými opravami a výjimkami. |

## Obsluha a hranice agend

- **Správce katalogu → Skladové doklady a inventura**: ověřit počátek každé
  varianty a všechny dosud nevydané otevřené položky, přijmout dodávku,
  posoudit blokaci, založit inventurní soupis. Počátek se neodvozuje z historie
  starých rezervací. Technické JSON vstupy nejsou součástí formuláře.
- **Hospodář a platby** v témže pracovním prostoru: doložené schválení skladu
  a FIFO, pořizovací ceny v Kč a nezávislé schválení inventury. Neznámá cena
  zůstává neznámá; ceny se nedoplňují podle prodejních cen.
- **Objednávky → Prodej na místě / Položkové výdeje**: založení nákupu,
  následné ověření platby hospodářem a skutečný výdej po položkách.
- **Vratky a výměny**: nejprve případ a fyzické převzetí, samostatné posouzení
  skladníkem a samostatné finanční vyřešení hospodářem. Převzetí zboží
  nepotvrzuje vrácení peněz. Samotné rozhodnutí o vratce nestačí k uzavření
  zaplaceného případu; doloží se skutečné výplaty.
- **Účetní podklady**: nový archiv přidává skladová PDF a list Sklad. Stavy,
  pohyby a analytické vazby dávek se nesčítají jako další peněžní obrat.
  Kladný doplatek výměny je samostatný zdroj `shop_return`, nikoli druhá plná
  objednávka. U původní slevy se používá doložený snímek kupónu a přesné
  celočíselné rozdělení; chybějící historický rozpis vyžaduje ověření.

Částečně uhrazenou objednávku nelze automaticky expirovat. Po částečném
výdeji lze nevydaný zbytek uvolnit až proti vydanému opravnému dokladu;
přihlíží se k předchozím opravám a fyzickým vratkám. Uvolnění mění pouze
rezervaci, nikoli původní množství objednávky, fyzické kusy nebo peníze.
Přehled výslovně uvádí vypořádané předání a počet nevydaných kusů; staré
hromadné dokončení již nevytváří zdání dalšího předání.

Fyzická PDF jsou bez pořizovacích cen a dostupná skladové/objednávkové obsluze.
Ocenění zůstává ve finančním pracovním prostoru a exportu hospodáře. Vydaný
soubor má otisk a opakované stažení vrací stejné bajty, i když se později změní
katalog nebo pořizovací cena. Finanční doklady R15 se tím nenahrazují.

## Datový a transakční kontrakt

Čtyři nové migrace `20261022230000` až `20261022233000` vytvářejí 16 vlastněných
tabulek; zálohovací kontrakt je `2026-10-22.4`. Schválení inventury je optimistické:
pokud po soupisu vznikl jiný pohyb, původní soupis nelze schválit a pořídí se nový.
Nejde o dlouhodobé zastavení prodeje. Inventura nesmí snížit stav pod rezervace
a blokace. Přebytek bez ceny zůstává neoceněný.

Skladové změny mají transakční zámky, identitu požadavku a kontrolu jeho obsahu.
Pohyby se zpětně nepřepisují ani nevkládají před již evidované pohyby varianty;
opožděný podklad se řeší doloženou aktuální opravou. Ocenění lze doplnit k
doloženému původnímu datu, v monotónním pořadí oprav dané dávky. Původní ocenění
výdeje a starý účetní archiv zůstávají beze změny. Nový export uvádí opravu a
vazby na dávku/výdej. Export si pro svou transakci vynucuje REPEATABLE READ,
aby nespojil starý pohyb s nově zapsanou dávkou ze souběžné příjemky.

Původní číselné skladové varianty zůstávají v kompatibilním režimu, dokud se
výslovně neověří jejich fyzický počátek. Neověřený `NULL` u fyzického zboží není
neomezená zásoba a po dostupnosti nového schématu není prodejný. Služby mají
nadále samostatnou kapacitu. Jednorázový import nesmí přepsat převzatou variantu.

## Ověření a další návaznosti

Konkrétní přejímací důkazy a příkazy jsou v
[protokolu R18–R24](verification/r18-r24/RESULTS.md). Testy používají výhradně
syntetickou DB `evidence_b1` na `127.0.0.1:33426`, web `127.0.0.1:18086` a
samostatná testovací schémata. Neobsahují reálné objednávky ani platby.

Před skutečným přechodem zbývá odsouhlasit metodiku FIFO a údaje účetní,
provést fyzickou inventuru a převzetí otevřených objednávek, přiřadit skutečná
oprávnění a projít uživatelskou přejímku. To je součást navazující migrační
etapy, nikoli této lokální implementace. Nevznikl další sklad, agenda půjčování
vybavení ani aktivace dopravních či platebních API.

Účetní export stále výslovně uvádí **NEÚPLNÝ** rozsah celého oddílu. Výdaje,
zálohy, odměny a konečné měsíční předání dokončí R31–R36. Následující pracovní
balík podle plánu je členská a sportovní agenda R25–R30. Tento dokument
neautorizuje její implementaci ani nasazení R18–R24.
