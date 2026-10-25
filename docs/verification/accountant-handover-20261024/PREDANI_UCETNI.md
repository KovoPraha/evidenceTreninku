# Ukázkové předání účetní — LOCALHOST, NEPLATIT

Toto je technická ukázka, nikoli skutečné účetnictví oddílu nebo odeslaný e-mail.
Oddíl podle zadání není plátcem DPH a vede jednoduché účetnictví. Konkrétní účetní
metodika a cílový program tímto dokumentem nejsou určeny.

## Předávaný balík

- Soubor: `LOCALHOST-NEPLATIT-ucetni-podklady-r10.zip`.
- Období: 1.–31. 10. 2026, revize 10, předchůdce 9.
- Stav: **NEÚPLNÝ pracovní balík**. Chybějící odsouhlasení a podklady jsou uvedeny
  v Manifestu; simulované převzetí tento stav nemění.
- SHA-256: `bcb8287ed4b022f03bd34fdf8ae4c34f83812024ef43e6d1998288e8ed466248`.
- ZIP obsahuje XLSX, související PDF, datový snapshot a kontrolní manifest.

## Doporučený postup převzetí

1. Uložit původní ZIP, ověřit období a revizi, rozbalit do samostatné složky.
   Nová revize stejného období **nahrazuje** předchozí; nepřičítat ji podruhé.
2. Začít listem **Manifest**. Projít výjimky, nepřevzaté zdroje a rozhodný okamžik.
   Nejasné počátky a ocenění nejsou nulové částky.
3. **Doklady** a **Polozky** použít jako seznam dokladů a položek; odkazy a ID
   vedou na odpovídající PDF. Oprava navazuje na původní doklad.
4. **Penize** představují skutečné pohyby. Respektovat označení zahrnutí do období;
   historické vazby mimo období nejsou aktuální obrat. **Parovani** a
   **Cleneni_penez** popisují vazby/členění, nejsou další příjem nebo výdaj.
5. **Predpisy** a **Saldo** použít ke kontrole pohledávek, nepřidělených plateb,
   závazků a záloh. **Vydaje** obsahují také nepeněžní podklady a odměny;
   skutečnou úhradu nepřičíst znovu k pohybu na listu Penize.
6. **Sklad** projít spolu se souvisejícími doklady a výjimkami ocenění. Neznámou
   cenu nebo nepotvrzený historický počátek doplnit podle skutečných podkladů.
7. Případné nesrovnalosti vracet s revizí, listem, ID řádku/dokladu, popisem
   a požadovaným podkladem. Následně připravit novou revizi a zachovat původní.
8. V KIS zaznamenat skutečné předání, vrácení či převzetí, kdo/komu/kdy a důvod.
   Samotné stažení není předání. Zápis v KIS nikomu neodesílá e-mail.

## Vzor zpětné vazby

„Revize …, období …; list …, doklad/pohyb …; chybí …; prosím doplnit ….
Soubor technicky otevřen: ano/ne. Podklady pro zaúčtování převzaty: ano/ne,
s výhradami …; jméno a datum ….“

Pro reálné použití musí účetní potvrdit obsah předání, metodiku, úplnost a případný
formát importu svého programu. Tento test nenahrazuje její rozhodnutí.
