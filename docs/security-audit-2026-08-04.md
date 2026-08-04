# Bezpečnostní audit a náprava – 4. 8. 2026

Rozsah: statická kontrola legacy evidence i nové shop/KIS vrstvy, následná lokální
implementace a runtime ověření. Produkce ani vzdálený Git nebyly změněny.

## Vyhodnocení externího auditu

| ID | Vyhodnocení proti aktuálnímu kódu | Náprava |
|---|---|---|
| S1 předvídatelný veřejný profil | potvrzeno, HIGH | všechny odkazy rotovány na 256bit náhodné tokeny; nové tokeny vznikají pouze přes `random_bytes()` |
| S2 bezpečnostní hlavičky | částečně potvrzeno; root `.htaccess` už existoval | doplněny `nosniff`, `SAMEORIGIN`, omezená CSP, Permissions-Policy a HSTS pro HTTPS mimo localhost |
| S3 spuštění PHP v upload adresářích | upload validace byla bezpečná, chyběla druhá pojistka | Apache nyní blokuje PHP/PHTML/PHAR v upload a generovaných adresářích |
| S4 zobrazení interních výjimek | potvrzeno na několika legacy cestách | detail jde do serverového logu, uživatel dostává obecnou chybu |
| S5 plaintext hesla trenérů | potvrzeno | číslovaná migrace je převádí na `password_hash`; aplikace plaintext přestala přijímat |
| S6 login bez CSRF | potvrzeno | login je chráněn CSRF tokenem |
| S7 stažení importu bez oprávnění | potvrzeno | kontrola oprávnění a bezpečný `realpath` uvnitř import adresáře |
| S8 syrový `$_POST` v auditu | potvrzeno | centrální rekurzivní redakce hesel, tokenů, cookies a authorization hodnot |
| S9 stavový GET pro story | potvrzeno | pouze POST + CSRF, GET vrací 405 |
| S10 nechráněné inline JSON | potvrzeno | doplněny `JSON_HEX_*` příznaky na nalezených inline výstupech |
| Enumerace účtu při registraci | potvrzeno, MEDIUM | nový i existující e-mail dostává stejnou neutrální odpověď; existující účet se nemění |

Nenalezená root `.htaccess` byla zastaralá informace auditu: soubor v aktuálním
HEAD existoval už před opravou. Audit také správně uváděl, že upload handlery již
kontrolují MIME i povolenou příponu; přidané Apache pravidlo je defense-in-depth.

## Migrační dopad

Migrace `20260804235500_public_profile_token_rotation` je záměrně jednorázová a
idempotentní. Na localhostu:

- otočila všech 255 veřejných profilových tokenů; všech 255 je validních a unikátních,
- převedla všechna legacy hesla trenérů; 44 z 44 záznamů nyní používá moderní hash,
- migrační katalog je `current` (36/36).

Staré veřejné odkazy sportovců po nasazení přestanou fungovat. Aplikace/e-mailový
proces musí rozeslat aktuální odkazy z databáze. Uživatelé trenérského loginu dál
použijí stejné heslo; mění se pouze způsob jeho uložení.

## Ověření

- PHPUnit: 324 testů, 2 907 assertions, vše prošlo.
- PHP lint: 361 first-party souborů, 0 chyb.
- Composer audit: 0 známých advisories.
- HTTP localhost: login vrací nové bezpečnostní hlavičky.
- HTTP localhost: pokus o PHP v upload cestě vrací 403.
- Aktuální náhodný profil vrací 200; původní odvoditelný token vrací 404.

## Zbývající rizika

- CSP je úmyslně minimální, protože legacy stránky používají inline skripty a styly.
  Přísnější CSP vyžaduje samostatný kompatibilitní průchod.
- Produkční PHP/Apache konfigurace a provedení migrace nebyly v tomto kroku měněny
  ani živě ověřeny.
- Self-service obnova sportovního hesla a permission cache zůstávají úkolem M2.5.
- Slabší historické tokeny veřejných skupin nejsou součástí potvrzeného S1; jejich
  případná rotace má být samostatná změna, aby se vědomě vyhodnotilo zneplatnění URL.
