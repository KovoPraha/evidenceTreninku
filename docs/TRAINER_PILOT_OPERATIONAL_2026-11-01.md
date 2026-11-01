# Provozní založení samostatného syntetického pilotu

Dohledání potvrdilo veřejný účet bez explicitní pracovní vazby. Vlastník přesto
výslovně autorizoval samostatný testovací účet a testovací skupinu; třetí jím
dodaná adresa je podle read-only kontroly volná. Není důvod veřejnému účtu
přidávat správní práva nebo připisovat operaci cizímu trenérovi.

Chráněné workflow na main proto dostane samostatný provozní režim. Vyžaduje
stávající potvrzení vytvoření, produkční environment, přesný host, ověřené
nasazené soubory a čerstvou zálohu. Audit obsahuje GitHub actor, run ID a přesnou
workflow/ref identitu. Pro povinné FK auditních tabulek vznikne pouze neaktivní
technická identita: bez e-mailu, pozic, superadmina, přihlášení a obnovy hesla.
Není to pracovní oprávnění uživatele ani obejití oprávnění webových stránek.

V jediné transakci se založí technická auditní identita, coach-only testovací
účet s neznámým náhodným heslem, vlastní sezona/soupiska, dvě syntetické osoby
v rozsahu a třetí mimo něj. Grant trvá 14 dní od databázového času. Stávající
účty, veřejné vazby, skuteční sportovci i integrační přepínače se nemění.

Ověřit: běžná cesta stále vyžaduje oprávněného vlastníka; provozní cesta je
CLI-only s konkrétním dokladem chráněného workflow; neaktivní auditní identita
nikdy nezíská oprávnění; kolize, chybějící/změněný marker i zásah člověka jsou
odmítnuty; rollback vrátí i technickou identitu. Idempotentní opakování nic
neobnovuje ani neprodlužuje. Ověřit SQLite i celé migrované MariaDB schéma.

Po CI sloučit a nasadit, založit pilot, ověřit rozsah a obnovu hesla pouze
čtením a obnovit čerstvou zálohu v izolaci. Nastavení vlastního hesla a úplné
interaktivní UAT provede vlastník sám. Nová rozesílka není součástí operace.

## Provedení

PR #266 je nasazeno. Pilot vytvořil chráněný běh 41330611475, opakování
41330782300 potvrdilo `changed=false` a vytvořilo zálohu již obsahující pilot.
Trenér 56 / soupiska 77, dvě syntetické osoby v rozsahu, třetí odmítnuta.
Auditní identita je neaktivní a bez pozic. [Výsledky a důkazy](verification/trainer-pilot-20261101/RESULTS.md).
