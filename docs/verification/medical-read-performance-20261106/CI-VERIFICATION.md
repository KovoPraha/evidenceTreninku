# Párové CI ověření R13 — 6. 11. 2026

[Běh 42278997750](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42278997750), hlava PR `940cbcd1`.
Oba párové profily MariaDB 10.3/11.4 SUCCESS: 9 původních a 24 nových scénářů,
z nich devět srovnatelných R13, 270 časových vzorků v každé zdravotní fázi,
parita doménových výsledků a obnova vlastního schématu. Nový reader odpovídá
zdrojovými otisky místní finální implementaci. Čtyři autorizační negativa
a 36 kombinací původního/nového algoritmu PASS v obou prostředích.

Celý tento první běh skončil červeně ve starší integrační kontrole, která
napevno vyžadovala dva SQL dotazy i pro neprázdnou stránku. Původní služba
načetla všechny řádky; nová má samostatný COUNT a omezenou stránku s metadaty.
Kontrola opravena na dva dotazy pro prázdný a tři pro neprázdný výsledek,
navíc nyní přesně vyžaduje nejvýše 51 načtených řádků v této fixture místo
původního neomezeného načítání a paměť do 1 MiB místo 16 MiB. Parita,
soukromé údaje, historie a čerstvá revokace zůstávají kontrolované.

Finální stav všech povinných kontrol a začlenění vede [PR #352](https://github.com/KovoPraha/evidenceTreninku/pull/352).
Následující předání a oprava rozpočtu nemění měřené aplikační zdroje.

| MariaDB / 10k sledování | Výběr | Medián ms před → po | Zrychlení | PHP MiB před → po |
| --- | --- | ---: | ---: | ---: |
| 10.3 | Vše | 161.80 → 3.34 | 48.38× | 43.94 → 0.18 |
| 10.3 | Osoba | 35.62 → 3.23 | 11.03× | 6.24 → 0.15 |
| 10.3 | Platné | 165.53 → 96.32 | 1.72× | 43.94 → 0.17 |
| 11.4 | Vše | 136.21 → 17.75 | 7.68× | 43.94 → 0.18 |
| 11.4 | Osoba | 21.80 → 8.79 | 2.48× | 6.24 → 0.15 |
| 11.4 | Platné | 140.24 → 83.72 | 1.68× | 43.94 → 0.17 |

Časy porovnávat vždy uvnitř stejného prostředí; nejsou produkční SLA. Všech
devět případů a ostatní metriky jsou v trvalých JSON souborech:

- MariaDB 10.3: [porovnání](ci-10.3/comparison.json), [parita](ci-10.3/parity.json), [původ a úklid](ci-10.3/provenance.json).
- MariaDB 11.4: [porovnání](ci-11.4/comparison.json), [parita](ci-11.4/parity.json), [původ a úklid](ci-11.4/provenance.json).

Skutečný SHA v novém profilu je GitHubem vytvořený testovací merge; nejde o
nasazené vydání. Trvalý původ obsahuje přesné SHA/hashes. Plné jednotlivé
Linux vzorky, SQL a EXPLAIN jsou v artifactech běhu (retence 14 dnů):

- `synthetic-read-pages-mariadb-10.3`, ID `13533659876`, archiv SHA256
  `43d14ea2e3971171a79cabcf9a4fe586e9aed0beaeb25d45728e76e8f0bf7ec6`.
- `synthetic-read-pages-mariadb-11.4`, ID `13535136778`, archiv SHA256
  `31a44a6ab15e53d9fb36d5c9deb83f4b0f9fdc033e90a60fcff06c3cb4ebd2c8`.

Plné místní vzorky jsou současně verzované u hlavního protokolu. Produkční
workflow ani produkční data tento CI běh nemění.
