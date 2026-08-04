# Přehled kontrolních auditů

Aktualizováno: 4. 8. 2026, Europe/Prague

Auditní zprávy jsou neměnné historické snímky stavu na uvedeném commitu. Pokud
byl nález později opraven, je u zprávy validační dodatek a níže odkaz na opravu.
Aktuální provozní stav vždy určuje `CURRENT_STATE.md`.

| Audit | Snapshot | Výsledek | Aktuální stav |
|---|---|---|---|
| [První AI simulace](AUDIT-M2-AI-SIMULACE.md) | `12232eb` | statická revize a plán nápravy | historický podklad; následné ověření je v re-auditu |
| [Druhý AI re-audit](AUDIT-M2-AI-SIMULACE-RE2.md) | `cd38f85` | potvrzené M2.3 jádro, nálezy N-H1/N-M1/N-L1 | nálezy uzavřeny commity `7c8b444`, `281fcd0` a dokumentací `b7d1cef` |
| [Adversariální průchod aplikace](AUDIT-M2-ADVERSARIAL.md) | `cd38f85` | přibližně 45 živých útokových vektorů, bez CRITICAL/HIGH/MEDIUM webové chyby | webové výsledky evidovány; tehdy otevřený N-H1 je opraven v `281fcd0` |
| [Audit příležitostí a nápadů](AUDIT-PRILEZITOSTI-A-NAPADY.md) | stav 4. 8. 2026 | produktové náměty nad existujícími daty | inspirativní backlog, nikoli schválená roadmapa; právní, účetní a kapacitní předpoklady je nutné potvrdit před implementací |
| [Hloubkový audit příležitostí](AUDIT-PRILEZITOSTI-HLOUBKOVE.md) | stav 4. 8. 2026 | datová připravenost a tři podrobnější produktové směry | návrhový podklad; fakta a odhady je nutné znovu ověřit proti aktuálnímu schématu a prioritám |

Produktové návrhy z posledních dvou řádků jsou vytříděné do kanonického backlogu
`plan-eshop-tymova-evidence/11-backlog-hodnota-pro-cleny.md`. První přijatý řez
je veřejný ICS kalendář M2.7; přijetí jednoho řezu neznamená automatické schválení
ostatních auditních návrhů.

## Jak audity používat

- Zjištění vždy vztahovat ke snapshot commitu, ne automaticky k dnešnímu HEAD.
- Opravu považovat za uzavřenou až po testu na současném kódu a aktualizaci
  `CURRENT_STATE.md` nebo validačního dodatku.
- Produkční bezpečnost nelze odvodit jen z localhost auditu; před deployem je
  nutná samostatná kontrola konfigurace hostingu, tajemství, záloh a návratu.
