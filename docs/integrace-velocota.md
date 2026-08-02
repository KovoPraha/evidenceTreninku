# Hranice Evidence vůči Velocotě

> Rozhodnutí vlastníka produktu z 2. 8. 2026: Evidence je samostatná aplikace.
> Velocota není její nadřazený portál, backend ani zdroj provozních dat.

## Co se neplánuje

- sdílená PHP session nebo cookie,
- společná navigace či vložená sub-aplikace,
- přímé čtení nebo zápis aplikačních tabulek druhého projektu,
- synchronizace tréninků, výsledků, bookingu, e-shopu, plateb nebo kreditu,
- obecné obousměrné API mezi Evidence a Velocotou.

To, že aplikace mohou běžet na stejném serveru, tuto hranici nemění. Každá má
vlastní konfiguraci, databázové vlastnictví, release proces a bezpečnostní model.

## Jediná možná budoucí vazba

Výhledově lze samostatně navrhnout sdílenou nebo federovanou **identitu
uživatele**. Takový krok není součástí MVP ani aktuální roadmapy a musí mít
vlastní rozhodnutí, threat model, migrační plán a testy.

Případný návrh musí zachovat:

- vlastní Evidence role a oprávnění,
- vlastní Evidence session,
- explicitní vazbu na externí identitu,
- možnost vazbu odpojit nebo revokovat,
- nulové automatické sdílení doménových dat.

Konkrétní mechanismus zatím není zvolen. Samotné umístění na stejném serveru
není důvodem pro sdílené cookies ani přímé propojení databází.

## Stav legacy kódu

Repozitář obsahuje `auth/sso_bridge.php` a feature flag
`VELOCOTA_INTEGRATION`. Jde o starší experimentální kompatibilní povrch, nikoli
o cílovou architekturu. Flag musí zůstat `false`. Bridge se nesmí aktivovat bez
nového výslovného rozhodnutí vlastníka produktu a samostatného security review.

Budoucí úklid může legacy bridge odstranit, jakmile bude potvrzeno, že jej žádné
existující prostředí nepoužívá.
