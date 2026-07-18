# Evidence Tréninků — Dokumentace

Webová aplikace pro správu tréninků, sportovců a závodů cyklistického klubu. Sub-modul platformy **Velocota** (Kovopraha).

## Dokumenty

| Dokument | Popis |
|----------|-------|
| [Uživatelská příručka](uzivatelska-prirucka.md) | Návod pro trenéry a administrátory — přihlášení, tréninky, sportovci, reporty, exporty |
| [Technická dokumentace](technicka-dokumentace.md) | Architektura, AJAX endpointy, autentizace, CSRF, export systém, audit log, Web Push, SSO |
| [Databázové schéma](databazove-schema.md) | Popis všech tabulek, sloupců a vztahů |
| [Vývojářský průvodce](vyvojarsky-pruvodce.md) | Instalace, konvence kódu, přidání nových stránek, nasazení |
| [Instalace](instalace.md) | Krok za krokem — XAMPP (Windows), Linux/Apache, shell příkazy |
| [Integrace Velocota](integrace-velocota.md) | SSO bridge, session kontrakt, fáze integrace s klubovým portálem |
| [Roadmapa rozšíření](roadmapa-rozsireni.md) | Plánované změny — profily sportovců, kreditní wallet, e-shop API |
| [Implementační prompt: funkční vylepšení 1-8](implementacni-prompt-funkcni-vylepseni-1-8.md) | Připravené zadání pro kartu člena, KIS centrum, chytré párování, workflow aktivity, historii, hromadné akce a dashboard |

## Technologie

- **Backend:** PHP 8+ (procedurální, PDO)
- **Databáze:** MySQL / MariaDB (`utf8mb4`)
- **Frontend:** Bootstrap 5.3.3, Bootstrap Icons 1.11.3, vanilla JavaScript
- **Exporty:** PhpSpreadsheet ^5.3 (Composer, aktuálně 5.8.0)
- **Obrázky:** GD knihovna (story generátor)
- **Server:** Apache (XAMPP / Linux)
- **Web Push:** minishlink/web-push (Composer)

## Hlavní moduly

| Modul | Popis | Přístup |
|-------|-------|---------|
| Tréninky | Evidence tréninků, měření, sportovců | Všichni trenéři |
| Sportovci | Profily sportovců, veřejné profily, záložka závodů | Všichni trenéři |
| Závody | Evidence závodů (kategorie, měření, účastníci, výsledky), detail závodu | Všichni trenéři |
| Plánovač tréninků | Plánování tréninků dopředu, drag & drop, série, nástěnka skupiny | Všichni trenéři |
| Rezervace sportovišť | Interní kalendář obsazenosti (kapacita 1–5/5), rezervace pro tréninky | Všichni trenéři |
| Individuální lekce | Vypisování placených lekcí (zelená/žlutá), slot-based booking, čekací listina | Všichni trenéři |
| Booking (veřejné) | Zákazníci si registrují účet a rezervují lekce na velodromu / posilovně | Veřejnost |
| Exporty | Excel/CSV exporty (dráha, UCI, seznam sportovců, měsíční) | Všichni trenéři |
| Story generátor | Instagram story obrázky z tréninků | Všichni trenéři |
| Synchronizace evidence | KIS sync ze tří XLSX exportů, mapování soupisek, platební stav bez automatické archivace | Správce+ |
| Segmenty | Správa segmentů na kole (kroužek / silnice / MTB) | Správce+ |
| Správa | Skupiny, podskupiny, sportovci, závody, tréninky, sportoviště | Správce+ |
| Oprávnění | Nastavení přístupu dle rolí (per-funkce) | Admin |
| Vozidla | Vozový park, jízdy, servis | Admin |
| Účtenky | Evidence dokladů a účtenek | Admin |
| Události | Závody, soustředění, vyúčtování | Admin |
| Trenéři | Správa trenérů a přidělování rolí | Admin |

## Role

3 hierarchické úrovně: **Trenér** < **Správce** < **Administrátor**. Oprávnění jsou konfigurovatelná — admin nastavuje minimální roli pro každou funkci v `nastaveni_opravneni.php`.

## Bezpečnost

- CSRF ochrana na všech formulářích (`csrf_helper.php`)
- Prepared statements (PDO) proti SQL injection
- XSS ochrana (`htmlspecialchars()`)
- MIME validace uploadů (`finfo_file()`)
- Konfigurovatelná oprávnění (`canAccess()` + tabulka `opravneni`)
- Audit logging všech změn v účetním modulu

## Integrace

- **Velocota SSO** — `auth/sso_bridge.php` mapuje Velocota session na Evidence session; přepínač v `config.php` (`VELOCOTA_INTEGRATION`)
- **Web Push** — Service Worker `sw.js` + `push_subscribe.php`; push notifikace při nové rezervaci lekce
- **E-shop** *(plánováno, Fáze 2)* — API bridge pro kredity a SSO tokeny

---

## Novinky 2.20.0

- Administrační karta člena: `sportovec_karta.php`
- Veřejná karta sportovce zůstává `sportovec_treninky.php?hash=...`
- KIS centrum: `kis_sync_center.php`
- Hromadne akce clenu: `sportovci_hromadne.php`
- Admin dashboard: `admin_dashboard.php`

*Verze dokumentace: 2.20.0 — červen 2026*
