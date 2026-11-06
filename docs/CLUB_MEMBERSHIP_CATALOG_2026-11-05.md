# Členství v klubu: katalog, disciplíny a vystavovatel

Zadání vlastníka 5. 11. 2026. Navazuje na nasazené PR #314.

## Přijatá pravidla

- Kategorie e-shopu „Členství v klubu“, produkt „Závodní tým“, 2 200 Kč měsíčně.
- Skupina „Závodní tým“, podřízené soupisky „MTB“, „Silnice“, „Dráha“.
- Vlastník potvrdil více disciplín za stejnou cenu. Výběr pouze zařazuje na
  vybrané soupisky. Členství ve skupině se odvozuje z těchto soupisek.
- Měsíční tarif: plná cena prvního měsíce, ukončení na konci měsíce.
- Připravit také roční interval: plná cena za 12 kalendářních měsíců od měsíce
  sjednání, ukončení na konci probíhajícího dvanáctiměsíčního období. Cenu
  ročního produktu zadává správce; skutečný roční produkt se bez ní nezakládá.
- Vystavovatel „Tělovýchovná jednota KOVO Praha“. Adresu, IČ a bankovní údaje
  doplní vlastník v administraci. Neodhadovat ani automaticky potvrzovat režim DPH.
- Výchozí doklad k příspěvku, členění CLEN / KLUB, splatnost 15. den,
  první splatnost nejdříve sedm dní po sjednání. Řada CLEN, stávající řady
  zachovat. Nastavení dokladů se schválí společně s úplnými údaji vystavovatele.

## Postup

1. Přidat aditivní migraci intervalu tarifu, volitelných cílových soupisek
   a vazeb na skutečně udělená zařazení. Starší měsíční tarify zachovat.
2. Rozšířit sjednání, opakované předpisy, ukončení a veřejné popisy o rok;
   výběr disciplín kontrolovat na serveru, včetně opakovaného požadavku.
3. Zpřístupnit srozumitelný formulář fakturačních údajů s verzováním;
   historické doklady a sjednané tarify se nepřepisují.
4. Připravit idempotentní založení konkrétního produktu a hierarchie jako
   neveřejného konceptu do doplnění vystavovatele a schválení tarifu.
   Nepřevádět historické skupiny ani jejich členy podle podobnosti názvů.
5. Ověřit měsíční i roční předpisy, výběr více disciplín, návraty požadavků,
   izolované ukončení zařazení, účetní nastavení a zachování jednorázového prodeje.
6. Projít syntaxí, stylem, PHPStan, PHPUnit a chráněnými kontrolami. Produkční
   vydání a skutečné založení odlišit od pouhé přípravy kódu. Automatizace
   a rozesílky se tímto úkolem nezapínají.

## Stav

Dokončeno, sloučeno a nasazeno v PR #316; [původní protokol](verification/membership-catalog-release-20261105/RESULTS.md) zachycuje založení konceptu.
[Produkční kontrola 6. 11.](verification/racing-team-status-20261106/RESULTS.md) již potvrzuje aktivní schválený tarif Závodní tým za 2200 Kč/měsíc, uložené údaje vystavovatele a zveřejnění. Sjednání vyžaduje ověřeného sportovce; skutečná úhrada/PDF tím nejsou doložené. Aktuální provozní přepínače určuje CURRENT_STATE.
