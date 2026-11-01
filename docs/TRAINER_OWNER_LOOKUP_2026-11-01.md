# Dohledání vlastníka pilotu podle e-mailu

Vlastník požádal o vyhledání stávajícího účtu podle jím dodané adresy.
Předchozí hledání přesného jména nebylo jednoznačné. Provede se pouze čtení
přes chráněné workflow na main, nikoli odhad identity či změna oprávnění.

1. Porovnat normalizovanou adresu přesnou shodou v pracovních účtech; vrátit
   pouze identifikátor, přihlašovací jméno, aktivitu a potřebná oprávnění.
2. Ověřit jednoznačnost jména pro existující pilotní nástroj a dostupnost obnovy
   hesla. Zákaz automatického propojení či povýšení účtu zůstává zachovaný.
3. Spočítat kolize adresy i ve veřejných účtech. Obsazenou adresu nepoužít pro
   další účet; vyžádat jinou vlastníkem ovládanou adresu, alias neodhadovat.
4. Po jednoznačném výsledku pokračovat již schváleným vytvořením syntetického
   pilotu, pokud je dostupný samostatný e-mail. Jinak předat přesný zbývající údaj.

Nástroj se přenese do soukromého provozního adresáře a spustí jako PHP soubor
bez přepínačů. Databázová transakce je READ ONLY. Po běhu se odstraní pouze
vlastní pomocné soubory. Žádná hesla, tokeny, kontakty sportovců ani dumpy
se nečtou do výstupu. Osobní výsledky patří do soukromého provozního dokladu,
nikoli do Gitu. Aplikační runtime ani databázové schéma se nemění.
