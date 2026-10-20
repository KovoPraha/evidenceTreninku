# Cesta rodiče — produkční ověření 20. 10. 2026

Produkce: <https://kis.kovopraha.cz/>. Tento záznam rozlišuje nasazený kód,
skutečně pozorované chování v prohlížeči a dosud neověřenou část cesty.
Navazuje na [lokální dokončení](PARENT_JOURNEY_2026-10-20.md).

## Nasazení a rozsah

- [PR #91](https://github.com/KovoPraha/evidenceTreninku/pull/91) nasadil
  rodičovskou cestu jako commit `d6b10e7`.
  [Produkční běh 39400982446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39400982446)
  skončil úspěšně; ověřil všech 94 migrací.
- [PR #92](https://github.com/KovoPraha/evidenceTreninku/pull/92) opravuje
  rozlišení příslušenství od účastnického produktu. Závěrečná lokální sada
  prošla: **1 019 testů / 23 532 kontrol**.
  [Produkční běh 39402251446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402251446)
  úspěšně nasadil výsledný commit
  `fd6ccc1584beb3a3266e8147497263cb8a38044f`.
  [CI stejného commitu 39402250654](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39402250654)
  rovněž prošlo. Toto je poslední zde ověřený aplikační release;
  následný dokumentační commit může mít jiné SHA. Závěrečný deploy log
  potvrzuje 94 migrací, prázdný seznam čekajících migrací (`pending: []`)
  a HTTP smoke s odpovědí 200.
- Fio, Stripe ani SumUp se při této práci nezapínaly. Nebyla provedena
  skutečná platba ani změna vazeb skutečných kroužků.
- Nasazení kódu a průchod automatických testů nejsou potvrzením úplného
  produkčního UAT. Veřejná registrace, skutečné doručení e-mailu a celá
  zaplacená cesta až do soupisky zatím nejsou prokázané.

## Co bylo ověřeno v prohlížeči

| Krok | Pozorovaný výsledek | Co z toho plyne |
|---|---|---|
| Veřejné účastnické produkty | Všech 52 zkontrolovaných variant zůstává veřejně viditelných, ale jejich nákup je zablokovaný kvůli chybějícím vazbám. | Ochrana funguje; produkt nelze koupit jako běžné zboží a obejít registraci účastníka. |
| Administrátorský průvodce | Všech 52 variant se nachází mezi existujícími nepropojenými produkty. | Nejde jen o text na veřejné stránce: importované varianty skutečně čekají na explicitní přiřazení. |
| Kanonické kroužky | Existuje 26 zveřejněných programů, 74 aktivních nabídek a jejich soupisky. | Programy a soupisky nechybějí plošně; chybí návaznost importovaného prodejního katalogu na existující strukturu. |
| Podmínky a kapacita | U všech 74 nabídek správce hlásí chybějící platný souhlas se storno podmínkami; kapacita se všude zobrazuje jako neomezená (`∞`). | Před otevřením registrací je nutné doplnit schválené podmínky a potvrdit kapacity. Neomezená kapacita je současné nastavení, nikoli důkaz skutečně neomezených míst. |
| Přihlášení rodiče | Přihlášení syntetického rodiče Tester Karel prošlo a přehled zobrazil jeho existující rodinu. | Přihlášená část rodičovské cesty funguje pro existující účet. |
| Návrat z registrace / přihlášení | Z produktu `143` šlo přejít na přihlášení a registrační formulář s předvoleným účelem Rodina. Oba odkazy zachovaly produkt, variantu `671` i kotvu `#varianta-671`. Po návratu na přihlášení a autentizaci existujícího testovacího rodiče byla výsledná URL přesně `https://kis.kovopraha.cz/booking/produkt.php?id=143&variant_id=671#varianta-671`. | Návrat k vybrané variantě prošel. Registrační formulář nebyl odeslán; nové heslo ani e-mailový token v tomto scénáři ověřeny nebyly. |
| Přidání dítěte | Nová žádost o sportovce je blokována chybějící platnou informací o zpracování osobních údajů. | Samotné přihlášení neodstraňuje právní blok registrace nové osoby. |
| Moje kroužky | Přehled odděleně a srozumitelně zobrazil prázdné objednané období a prázdné účasti / soupisky. | Stránka je dostupná; účet nyní nemá aktivní účast. Zobrazení skutečně aktivované účasti zde ověřeno nebylo. |
| Moje objednávky | Starší syntetické objednávky byly čitelně zobrazeny ve stavech čekání na platbu a zrušeno. | Přehled existujících objednávek prošel; při dnešní kontrole nebyla vytvořena objednávka ani platba. |
| Příslušenství po posledním deployi | Trička (produkt `29`) nabízí výběr varianty. Uskladnění (produkt `198`) nabízí výběr varianty a uvádí „Momentálně vyprodáno“, 500 Kč. | Obě položky již nejsou nesprávně blokovány požadavkem na program. Uskladnění má samostatný stav vyprodaného zboží. |
| Skutečný kroužek po posledním deployi | Aligátoři (produkt `143`) zůstávají správně blokováni pro nepřihlášeného i přihlášeného rodiče. | Oprava příslušenství neodstranila ochranu účastnických produktů. |
| Syntetický pozitivní scénář | Testovací program má aktivní nabídku `1`, zobrazenou kapacitu `20/20`, platné podmínky a prodejní okno 17.–22. 10. Veřejná prezentace TEST programu je koncept; rodičovský katalog jej nenabízí a hledání „TEST - Cyklistický kroužek“ vrací nula výsledků. | Bez explicitního zpřístupnění testovací nabídky nelze projít pozitivní nákup z rodičovského katalogu. V této kontrole se nic nepublikovalo. |

Administrátorský průvodce současně uvádí, že globální podmínky jsou připravené,
ale přehled stávajících programových nabídek hlásí chybějící platný souhlas se
storno podmínkami. Globální hláška tedy nedokládá připravenost každé nabídky;
je nutné vyřešit konkrétní vazby podmínek.

Lokální obrazový důkaz poslední kontroly Aligátorů je uložen mimo Git jako
`.codex-tmp/parent-aligatori-production.jpg`.

## Konkrétní chybějící nastavení

Společný problém 52 účastnických variant je chybějící explicitní propojení
produktu s programem, obdobím a soupiskou. Soubor tvoří 49 variant pro
26 cyklistických skupin a tři kurzy pro nejmenší. Neznamená to, že chybí
52 programů: několik prodejních variant může mířit na tentýž program
nebo soupisku a lišit se obdobím.

Následující tabulka obsahuje všech 52 konkrétních variant pozorovaných ve
veřejném katalogu a mezi nepropojenými existujícími produkty administrátorského
průvodce. Zápis je **ID produktu / ID varianty / SKU**. Pomlčka znamená, že
taková varianta nebyla součástí tohoto ověřeného souboru; není to doporučení
ji automaticky vytvořit.

| Skupina | 1. pololetí | Celý rok | Jiné období / termín |
|---|---|---|---|
| Aligátoři | `143 / 671 / 675` | `177 / 726 / 789` | — |
| Cyklistická přípravka | `178 / 727 / 792` | `129 / 657 / 633` | — |
| Kunratice | — | `227 / 793 / 952` | — |
| Jezevci | `185 / 734 / 813` | `135 / 663 / 651` | — |
| Kamzíci | `132 / 660 / 642` | `180 / 729 / 798` | — |
| Kapybary | `141 / 669 / 669` | `186 / 735 / 816` | — |
| Kroužek+ | `136 / 664 / 654` | `237 / 803 / 982` | — |
| Lamy | `145 / 673 / 681` | `189 / 738 / 825` | — |
| Lemuři | `148 / 676 / 688` | `192 / 741 / 834` | — |
| Lenochodi | `142 / 670 / 672` | `187 / 736 / 819` | — |
| Lvíčci | `147 / 675 / 687` | `191 / 740 / 831` | — |
| Mývalové | `146 / 674 / 684` | `190 / 739 / 828` | — |
| Papuchálci | `131 / 659 / 639` | `181 / 730 / 801` | — |
| Pásovci | `144 / 672 / 678` | `188 / 737 / 822` | — |
| Předpřípravka | `153 / 681 / 705` | `238 / 804 / 985` | — |
| Pumptrack | — | — | Září–leden: `290 / 941 / 997`; září–červen: `279 / 893 / 1000` |
| Sloni | `149 / 677 / 693` | `195 / 744 / 843` | — |
| Sportovní přípravka | `128 / 656 / 630` | `179 / 728 / 795` | — |
| Sumci | `183 / 732 / 807` | `133 / 661 / 645` | — |
| Surikaty | `193 / 742 / 837` | `150 / 678 / 696` | — |
| Svišti | `130 / 658 / 636` | `182 / 731 / 804` | — |
| Točňáci 1× týdně | — | — | Září–leden: `154 / 682 / 708` |
| Točňáci 2× týdně | — | — | Září–leden: `155 / 683 / 709` |
| Zebry | `239 / 805 / 988` | `240 / 806 / 991` | — |
| Žirafy | `151 / 679 / 699` | `194 / 743 / 840` | — |
| Zubříci | `184 / 733 / 810` | `134 / 662 / 648` | — |
| Děti 1–2 roky | — | — | Čtvrtek 10:40–11:20: `158 / 686 / 718` |
| Děti 2–3 roky | — | — | Čtvrtek 11:30–12:10: `159 / 687 / 719` |
| Děti 8–14 měsíců | — | — | Čtvrtek 9:50–10:30: `157 / 685 / 717` |

U všech tří kurzů pro nejmenší byla zobrazena cena 250 Kč. Samotná cena
neurčuje cílový program, období ani soupisku; tyto varianty vyžadují stejné
explicitní propojení jako cyklistické skupiny.

Pro všech 74 existujících kanonických nabídek je dále nutné vyřešit platný
souhlas se schválenými storno podmínkami a ověřit, zda nastavená neomezená kapacita odpovídá
provoznímu záměru. Tyto údaje se nemají odhadovat z názvu či ceny produktu.

| Kroužek | Konkrétní rozpor nebo chybějící rozhodnutí |
|---|---|
| Pumptrack | Kanonická veřejná nabídka uvádí ceny 6 500 / 11 500 Kč, zatímco importované varianty v průvodci 4 400 / 8 500 Kč. Před propojením je třeba určit správný ceník a období. |
| Točňáci 1× týdně | Není dořešen výběr konkrétního dne. Mapování musí určit, jak se vybraný den projeví v účasti a cílové soupisce. |
| Kunratice | Chybí přesný čas a popis uvádí dvě věkové skupiny, ale nabídka má jedinou variantu. Je třeba potvrdit rozdělení skupin a odpovídající vazby. |
| Všechny nové žádosti o dítě | Chybí platná informace o zpracování osobních údajů; bez ní se rodič nedostane přes založení nové žádosti. |

## Testovací účty a meze důkazů

Použity byly výhradně syntetické testovací účty. Report neobsahuje hesla,
ověřovací odkazy ani údaje skutečných dětí.

- Původní pokus o obnovu UAT
  ([běh 39401443002](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39401443002))
  selhal na dříve zodpovězeném testovacím tréninku.
- Úzce vymezená obnova testovacích přihlašovacích údajů
  ([běh 39401590361](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39401590361))
  prošla; administrátorský účet byl následně obnoven
  [během 39401616672](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39401616672).
- Přihlášení existujícího testovacího rodiče není důkaz průchodu veřejnou
  registrací, jednorázového ověření e-mailu ani nastavení prvního hesla.
- Lokální akceptační test dokládá logiku syntetické platby, schválení dítěte,
  zařazení do soupisky a zařazení oznámení do fronty. Produkční prohlížečový
  průchod těmito kroky zde dokončený není. Doručení zpráv do skutečné schránky
  se neprohlašuje za ověřené.

Dalším krokem je rozhodnout a uložit skutečné vazby, podmínky, kapacity,
ceny a časy. Poté lze na vyhrazeném testovacím programu projít celý řetězec
rodiče až do soupisky a ověřit doručení oznámení. Zprovoznění poskytovatelů
plateb zůstává samostatným odloženým úkolem.
