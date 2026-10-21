# Vlastní souhlasy při přihlášení do kroužku

Datum: 21. 10. 2026. Rozšíření správy registračních dokumentů pro pracovní
pozici **Registrář členů a KIS**.

## Postup obsluhy

1. Otevřít **Členové → Registrační texty a souhlasy → Přidat vlastní souhlas**.
2. Vyplnit název, znění a důvod změny. Zaškrtnutí **Povinný** vynutí
   potvrzení před dokončením objednávky; nezaškrtnutý souhlas je volitelný.
3. Volba **Zobrazovat při přihlášení do kroužku** určuje viditelnost.
4. Uložit koncept, zkontrolovat náhled, výslovně potvrdit a zvolit
   **Zveřejnit tuto verzi**. Uložení konceptu samo nemění veřejný formulář.
5. **Skrýt souhlas** s potvrzením jej odstraní z nových formulářů. Historie
   a staré odpovědi zůstanou zachované. Pro obnovení uložit a zveřejnit
   nový koncept se zapnutou viditelností.

Vlastní souhlasy platí pro **všechny kroužky**: rychlou přihlášku bez účtu
i nákup kroužku přihlášeným účtem. Necílí na jednotlivé termíny. Pět
dosavadních registračních dokumentů má nadále vlastní původní pravidla;
obecná registrace osoby bez nákupu kroužku vlastní souhlasy nepoužívá.

## Uložení a kontrola

- Texty zůstávají ve společném `club_event_term_versions`; koncepty ve
  společném `athlete_registration_text_drafts`. Nová definice s ukazatelem
  na aktuální verzi umožňuje zamykání publikace proti souběžnému nákupu.
- Publikuje se název, text, povinnost a viditelnost jako jedna neměnná verze.
  Zastaralý koncept nelze přepsat přes mezitím zveřejněnou verzi.
- Nová tabulka odpovědí uchovává konkrétní položku objednávky, účet, verzi,
  přesný název/text, povinnost a výsledek potvrzeno/nepotvrzeno. Nepotvrzení
  volitelného souhlasu je výslovně zaznamenáno; checkbox není předvybraný.
- Formulář posílá otisk vykreslených verzí. Změna znění, povinnosti nebo
  viditelnosti vyžaduje novou kontrolu aktuálního formuláře. Potvrzení se při
  změně otisku nepřenáší. Povinné souhlasy kontroluje i server.
- Kontrola probíhá před zápisem účtu/objednávky a odpovědi se ukládají ve
  stejné transakci. Chyba zápisu vrací celou objednávku zpět. Opakování
  již dokončeného nákupu nevytváří další odpovědi.
- Přidána opakovatelná migrace `20261021190000_custom_club_registration_consents`.
  Zálohovací kontrakt `2026-10-21.3` vlastní obě nové tabulky i nové sloupce.

## Ověření před nasazením

- PHPUnit: **1 167 testů / 26 335 kontrol**, bez selhání.
- Cíleně: vlastní souhlasy a celý životní cyklus kroužku **28 testů / 670 kontrol**.
- MariaDB 10.4.32: souběžné publikování dvou konceptů přijme právě jeden,
  snapshoty odpovědí přežijí skrytí; migrace aplikována opakovaně.
- Záloha a obnova na MariaDB: úspěch, **188 tabulek** čistého testovacího schématu.
- Lokální Chrome: vytvoření skrytého počátečního dokumentu/konceptu, výslovné
  zveřejnění, skrytí, obnovení jako volitelného souhlasu a historie nastavení.
- `composer validate`, PHP syntaxe, `git diff --check`, katalog migrací 109 bez
  čekajících kroků. Známé varování starší migrace členských trvalých příkazů
  o klíči `columns` nebránilo úspěšnému čistému bootstrapu/obnově.

Produkční důkazy budou doplněny po chráněném nasazení. Syntetické souhlasy
se vytvářejí pouze v odděleném místním prostředí; produkční kontrola je read-only.
