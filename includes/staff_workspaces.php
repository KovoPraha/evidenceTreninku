<?php
declare(strict_types=1);

/**
 * Kanonicky registr pracovnich pozic, jejich rozcestniku a vlastnictvi tras.
 *
 * Pozice nejsou hierarchie. Uzivatel pracuje vzdy v jedne aktivni pozici;
 * superadmin smi prepnout kontext do kterekoli z nich, ale menu se neslevaji.
 */

/** @return array<string,array<string,mixed>> */
function staffPositionDefinitions(): array
{
    return [
        'coach' => [
            'label' => 'Trenér',
            'short_label' => 'Trenér',
            'description' => 'Vlastní tréninky, docházka, skupiny, výkazy a rezervace.',
            'icon' => 'person-workspace',
            'legacy_role' => 'trener',
            'sort' => 10,
            'groups' => [
                ['label' => 'Moje práce', 'icon' => 'calendar-check', 'items' => [
                    ['route' => 'club_calendar.php', 'label' => 'Klubový kalendář', 'description' => 'Plán akcí, účastníci a vozidla', 'icon' => 'calendar-event'],
                    ['route' => 'formular.php', 'label' => 'Zadat trénink', 'description' => 'Evidence tréninku a účasti', 'icon' => 'calendar-plus'],
                    ['route' => 'planovac.php', 'label' => 'Plánovač', 'description' => 'Naplánované tréninky', 'icon' => 'calendar3-week'],
                    ['route' => 'moje_treninky.php', 'label' => 'Moje tréninky', 'description' => 'Vlastní historie a úpravy', 'icon' => 'list-check'],
                    ['route' => 'moje_skupiny.php', 'label' => 'Moje skupiny', 'description' => 'Svěřené skupiny sportovců', 'icon' => 'people'],
                ]],
                ['label' => 'Evidence', 'icon' => 'clipboard-data', 'items' => [
                    ['route' => 'nova_cinnost.php', 'label' => 'Další činnost', 'description' => 'Výkaz práce mimo trénink', 'icon' => 'journal-plus'],
                    ['route' => 'zatezovy_test_form.php', 'label' => 'Zátěžový test', 'description' => 'Záznam testu sportovce', 'icon' => 'heart-pulse'],
                    ['route' => 'vypis_vykazu.php', 'label' => 'Moje výkazy', 'description' => 'Souhrn vlastní činnosti', 'icon' => 'file-earmark-bar-graph'],
                    ['route' => 'prehled_sportovcu.php', 'label' => 'Moji sportovci', 'description' => 'Sportovní přehled svěřenců', 'icon' => 'person-lines-fill'],
                ]],
                ['label' => 'Rezervace a nástroje', 'icon' => 'building', 'items' => [
                    ['route' => 'kalendar_sportovist.php', 'label' => 'Kalendář sportovišť', 'description' => 'Moje termíny a dostupnost', 'icon' => 'calendar3'],
                    ['route' => 'individualni_lekce_sprava.php', 'label' => 'Individuální lekce', 'description' => 'Moje individuální výuka', 'icon' => 'person-video3'],
                    ['route' => 'cviky.php', 'label' => 'Cviky', 'description' => 'Katalog cviků pro trénink', 'icon' => 'activity'],
                ]],
            ],
        ],
        'sports_lead' => [
            'label' => 'Vedoucí sportu',
            'short_label' => 'Vedoucí sportu',
            'description' => 'Sportovní metodika, závody, kontrola tréninků a souhrnné výkazy.',
            'icon' => 'trophy',
            'legacy_role' => 'hlavni',
            'sort' => 20,
            'groups' => [
                ['label' => 'Sportovní provoz', 'icon' => 'clipboard-check', 'items' => [
                    ['route' => 'sprava_vsech_treninku.php', 'label' => 'Všechny tréninky', 'description' => 'Kontrola klubové evidence', 'icon' => 'clipboard-data'],
                    ['route' => 'sprava_zavodu.php', 'label' => 'Závody', 'description' => 'Kalendář a správa závodů', 'icon' => 'flag'],
                    ['route' => 'sprava_segmentu.php', 'label' => 'Segmenty', 'description' => 'Správa sportovních segmentů', 'icon' => 'signpost-split'],
                    ['route' => 'nastaveni_zadavani.php', 'label' => 'Okno zadávání', 'description' => 'Pravidla evidence tréninků', 'icon' => 'calendar-lock'],
                ]],
                ['label' => 'Kontrola a výstupy', 'icon' => 'graph-up', 'items' => [
                    ['route' => 'prehled_vsech_vykazu.php', 'label' => 'Všechny výkazy', 'description' => 'Souhrn práce trenérů', 'icon' => 'graph-up'],
                    ['route' => 'sports_data_quality_admin.php', 'label' => 'Kvalita sportovních dat', 'description' => 'Chyby a neúplné záznamy', 'icon' => 'clipboard-data'],
                    ['route' => 'sports_import_review_admin.php', 'label' => 'Import měření', 'description' => 'Kontrola sportovního importu', 'icon' => 'clipboard-check'],
                    ['route' => 'odeslat_emaily.php', 'label' => 'Komunikace trenérům', 'description' => 'Hromadné provozní zprávy', 'icon' => 'envelope'],
                ]],
            ],
        ],
        'registrar' => [
            'label' => 'Registrář členů a KIS',
            'short_label' => 'Členové a KIS',
            'description' => 'Osoby, skupiny, účty, KIS importy, týmy a soupisky.',
            'icon' => 'person-vcard',
            'legacy_role' => 'admin',
            'sort' => 30,
            'groups' => [
                ['label' => 'Členové', 'icon' => 'people', 'items' => [
                    ['route' => 'sprava_sportovcu.php', 'label' => 'Sportovci', 'description' => 'Kmenová evidence členů', 'icon' => 'person-lines-fill'],
                    ['route' => 'sprava_skupin.php', 'label' => 'Skupiny', 'description' => 'Klubové skupiny', 'icon' => 'diagram-2'],
                    ['route' => 'sprava_podskupin.php', 'label' => 'Podskupiny', 'description' => 'Zařazení sportovců', 'icon' => 'diagram-3'],
                    ['route' => 'eshop_identity_admin.php', 'label' => 'Účty a osoby', 'description' => 'Rodiče, sportovci a propojení', 'icon' => 'person-badge'],
                ]],
                ['label' => 'KIS a soupisky', 'icon' => 'arrow-repeat', 'items' => [
                    ['route' => 'kis_sync_center.php', 'label' => 'KIS centrum', 'description' => 'Import a konflikty osob', 'icon' => 'arrow-repeat'],
                    ['route' => 'kis_rosters_admin.php', 'label' => 'Týmy a soupisky', 'description' => 'Sezony, týmy a členství', 'icon' => 'people-fill'],
                    ['route' => 'kis_roster_settings_admin.php', 'label' => 'Správa struktur soupisek', 'description' => 'Opravy a uzavírání sezon a týmů', 'icon' => 'pencil-square'],
                    ['route' => 'kis_transition_admin.php', 'label' => 'Přechody sportovců', 'description' => 'Auditovaný přechod do týmu', 'icon' => 'arrow-left-right'],
                    ['route' => 'kis_child_access_admin.php', 'label' => 'Přístupy sportovců', 'description' => 'Samostatné účty dětí', 'icon' => 'key'],
                ]],
                ['label' => 'Kontrola', 'icon' => 'clock-history', 'items' => [
                    ['route' => 'person_audit_admin.php', 'label' => 'Auditní osa osoby', 'description' => 'Historie změn jedné osoby', 'icon' => 'clock-history'],
                    ['route' => 'kis_rollover_a06_admin.php', 'label' => 'Roční obnova soupisek', 'description' => 'Lokální průvodce a ověření A06', 'icon' => 'arrow-clockwise', 'local_only' => true],
                    ['route' => 'sync_evidence.php', 'label' => 'Synchronizace evidence', 'description' => 'Mapování staršího importu', 'icon' => 'diagram-3'],
                ]],
            ],
        ],
        'program_coordinator' => [
            'label' => 'Koordinátor programů a sportovišť',
            'short_label' => 'Programy',
            'description' => 'Kroužky, klubové akce, kapacity, velodrom a sportoviště.',
            'icon' => 'calendar-event',
            'legacy_role' => 'admin',
            'sort' => 40,
            'groups' => [
                ['label' => 'Kroužky', 'icon' => 'calendar-range', 'items' => [
                    ['route' => 'club_program_wizard_admin.php', 'label' => 'Vypsat kroužek', 'description' => 'Průvodce novou nabídkou', 'icon' => 'magic'],
                    ['route' => 'club_program_offers_admin.php', 'label' => 'Kroužky', 'description' => 'Založení, úpravy, kapacity a přihlášky', 'icon' => 'calendar2-check'],
                    ['route' => 'club_programs_admin.php', 'label' => 'Programy a podmínky', 'description' => 'Kanonické programy a dokumenty', 'icon' => 'calendar-range'],
                    ['route' => 'club_program_settings_admin.php', 'label' => 'Správa programů', 'description' => 'Opravy názvů a archivace', 'icon' => 'pencil-square'],
                ]],
                ['label' => 'Akce a sportoviště', 'icon' => 'building', 'items' => [
                    ['route' => 'eshop_events_admin.php', 'label' => 'Klubové akce', 'description' => 'Termíny, přihlášky a čekací listiny', 'icon' => 'calendar-event'],
                    ['route' => 'verejny_velodrom_admin.php', 'label' => 'Veřejný velodrom', 'description' => 'Veřejné hodiny a rezervace', 'icon' => 'bicycle'],
                    ['route' => 'sprava_sportovist.php', 'label' => 'Sportoviště', 'description' => 'Provozní nastavení sportovišť', 'icon' => 'building-gear'],
                ]],
            ],
        ],
        'catalog_manager' => [
            'label' => 'Správce katalogu e-shopu',
            'short_label' => 'Katalog',
            'description' => 'Produkty, ceny, sklad, slevy a zveřejnění.',
            'icon' => 'boxes',
            'legacy_role' => 'admin',
            'sort' => 50,
            'groups' => [
                ['label' => 'Katalog', 'icon' => 'boxes', 'items' => [
                    ['route' => 'eshop_catalog_admin.php', 'label' => 'Hromadná správa', 'description' => 'Výjimečné hromadné opravy katalogu', 'icon' => 'boxes'],
                    ['route' => 'eshop_produkt_admin.php', 'label' => 'Katalog', 'description' => 'Produkty, ceny, sklad a zveřejnění', 'icon' => 'box-seam'],
                    ['route' => 'eshop_categories_admin.php', 'label' => 'Kategorie', 'description' => 'Hierarchie kategorií', 'icon' => 'diagram-3'],
                    ['route' => 'eshop_attributes_admin.php', 'label' => 'Parametry', 'description' => 'Číselník parametrů produktů', 'icon' => 'sliders'],
                ]],
                ['label' => 'Ceny a zveřejnění', 'icon' => 'tags', 'items' => [
                    ['route' => 'eshop_member_prices_admin.php', 'label' => 'Klubové ceny', 'description' => 'Členské ceny a pravidla', 'icon' => 'tags'],
                    ['route' => 'eshop_coupons_admin.php', 'label' => 'Kupóny', 'description' => 'Slevové kupóny', 'icon' => 'ticket-perforated'],
                    ['route' => 'eshop_catalog_publication_admin.php', 'label' => 'Hromadné zveřejnění', 'description' => 'Výjimečná publikace více nabídek', 'icon' => 'eye'],
                    ['route' => 'eshop_admin.php', 'label' => 'Jednorázový import Shoptet', 'description' => 'Jednorázový převod původního katalogu', 'icon' => 'cloud-arrow-down'],
                ]],
            ],
        ],
        'order_operator' => [
            'label' => 'Zákaznická péče a objednávky',
            'short_label' => 'Objednávky',
            'description' => 'Příprava, výdej, provozní storna a zákaznické zprávy.',
            'icon' => 'receipt',
            'legacy_role' => 'admin',
            'sort' => 60,
            'groups' => [
                ['label' => 'Objednávky', 'icon' => 'receipt', 'items' => [
                    ['route' => 'eshop_orders_admin.php', 'label' => 'Objednávky', 'description' => 'Příprava, výdej a provozní storna', 'icon' => 'receipt'],
                    ['route' => 'eshop_order_expiry_admin.php', 'label' => 'Expirace objednávek', 'description' => 'Potvrzené ukončení nezaplacených objednávek', 'icon' => 'hourglass-split'],
                ]],
                ['label' => 'Komunikace', 'icon' => 'envelope', 'items' => [
                    ['route' => 'eshop_notifications_admin.php', 'label' => 'Fronta e-mailů', 'description' => 'Selhané a čekající zprávy', 'icon' => 'envelope-exclamation'],
                    ['route' => 'family_weekly_summaries_admin.php', 'label' => 'Rodinné souhrny', 'description' => 'Týdenní zákaznické souhrny', 'icon' => 'envelope-paper'],
                ]],
            ],
        ],
        'finance_manager' => [
            'label' => 'Hospodář a platby',
            'short_label' => 'Finance',
            'description' => 'Bankovní účet, párování, vratky, členské předpisy a firemní evidence.',
            'icon' => 'cash-coin',
            'legacy_role' => 'admin',
            'sort' => 70,
            'groups' => [
                ['label' => 'Platby', 'icon' => 'bank', 'items' => [
                    ['route' => 'eshop_payments_admin.php', 'label' => 'Platby a vratky', 'description' => 'Úkoly čekající na ověření v bance', 'icon' => 'cash-coin'],
                    ['route' => 'eshop_fio_admin.php', 'label' => 'Fio párování', 'description' => 'Návrhy bankovních shod', 'icon' => 'bank'],
                    ['route' => 'eshop_bank_admin.php', 'label' => 'Bankovní účet e-shopu', 'description' => 'IBAN, BIC a splatnost', 'icon' => 'credit-card'],
                    ['route' => 'member_charges_admin.php', 'label' => 'Klubové platby', 'description' => 'Stav členských plateb', 'icon' => 'cash-stack'],
                    ['route' => 'member_charge_reminders_admin.php', 'label' => 'Připomínky plateb', 'description' => 'Auditovaná fronta upomínek', 'icon' => 'bell'],
                ]],
                ['label' => 'Kredity a provoz', 'icon' => 'wallet2', 'items' => [
                    ['route' => 'prehled_kreditu.php', 'label' => 'Kredity', 'description' => 'Kredity sportovců', 'icon' => 'wallet2'],
                    ['route' => 'sprava_sportovec_obdobi.php', 'label' => 'Kreditní období', 'description' => 'Platnost kreditních období', 'icon' => 'calendar2-range'],
                    ['route' => 'hromadne_odmeny.php', 'label' => 'Sazby za trénink', 'description' => 'Hromadné nastavení peněžních sazeb', 'icon' => 'star'],
                    ['route' => 'uctenky/seznam.php', 'label' => 'Účtenky', 'description' => 'Doklady a výdaje', 'icon' => 'receipt-cutoff'],
                    ['route' => 'vozidla/seznam.php', 'label' => 'Vozidla a jízdy', 'description' => 'Vozidla, jízdy a servis', 'icon' => 'car-front'],
                    ['route' => 'udalosti/seznam.php', 'label' => 'Provozní události', 'description' => 'Události a vyúčtování', 'icon' => 'calendar-event'],
                ]],
            ],
        ],
        'system_admin' => [
            'label' => 'Správce systému',
            'short_label' => 'Systém',
            'description' => 'Pracovní účty, pozice, bezpečnost, diagnostika a systémový audit.',
            'icon' => 'shield-lock',
            'legacy_role' => 'admin',
            'sort' => 80,
            'groups' => [
                ['label' => 'Účty a přístupy', 'icon' => 'person-gear', 'items' => [
                    ['route' => 'sprava_pracovnich_pozic.php', 'label' => 'Pracovní pozice', 'description' => 'Přiřazení pozic a superadminů', 'icon' => 'person-workspace'],
                    ['route' => 'sprava_treneru.php', 'label' => 'Pracovní účty', 'description' => 'Účty, hesla a aktivace', 'icon' => 'person-gear'],
                    ['route' => 'nastaveni_opravneni.php', 'label' => 'Legacy oprávnění', 'description' => 'Dočasný kompatibilní práh starých obrazovek', 'icon' => 'sliders'],
                ]],
                ['label' => 'Bezpečnost a diagnostika', 'icon' => 'shield-check', 'items' => [
                    ['route' => 'auditlog/seznam.php', 'label' => 'Systémový audit', 'description' => 'Auditní události aplikace', 'icon' => 'journal-text'],
                    ['route' => 'diagnostika_site_admin.php', 'label' => 'Diagnostika', 'description' => 'Bezpečný technický stav aplikace', 'icon' => 'activity'],
                    ['route' => 'provozni_prehled_admin.php', 'label' => 'Kontrolní přehled', 'description' => 'Read-only souhrn provozních výjimek', 'icon' => 'speedometer2'],
                    ['route' => 'testovaci_scenare.php', 'label' => 'Lokální testovací scénáře', 'description' => 'Pouze localhost', 'icon' => 'check2-square', 'local_only' => true],
                ]],
            ],
        ],
    ];
}

/** @return list<string> */
function staffPositionCodes(): array
{
    $definitions = staffPositionDefinitions();
    uasort($definitions, static fn(array $a, array $b): int => (int)$a['sort'] <=> (int)$b['sort']);
    return array_keys($definitions);
}

/**
 * Běžná navigace ukazuje jen činnosti, které člověk vykonává opakovaně.
 * Ostatní vlastněné trasy zůstávají dostupné jako pokročilé nástroje na
 * pracovním rozcestníku, takže zjednodušení nemění oprávnění ani data.
 *
 * @return array<string,list<string>>
 */
function staffPositionPrimaryRoutes(): array
{
    return [
        'coach'=>['club_calendar.php','formular.php','planovac.php'],
        'sports_lead'=>['sprava_vsech_treninku.php','sprava_zavodu.php','prehled_vsech_vykazu.php'],
        'registrar'=>['sprava_sportovcu.php','kis_rosters_admin.php','eshop_identity_admin.php'],
        'program_coordinator'=>['club_program_offers_admin.php','eshop_events_admin.php','verejny_velodrom_admin.php'],
        'catalog_manager'=>['eshop_produkt_admin.php','eshop_coupons_admin.php'],
        'order_operator'=>['eshop_orders_admin.php'],
        'finance_manager'=>['eshop_payments_admin.php','member_charges_admin.php','prehled_kreditu.php'],
        'system_admin'=>['sprava_pracovnich_pozic.php','sprava_treneru.php'],
    ];
}

/** @return array<string,string> route => position */
function staffRouteOwners(): array
{
    static $owners;
    if (is_array($owners)) return $owners;

    $owners = [];
    foreach (staffPositionDefinitions() as $position => $definition) {
        foreach ($definition['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $route = (string)$item['route'];
                if (isset($owners[$route]) && $owners[$route] !== $position) {
                    throw new LogicException('Administracni trasa ma vice vlastniku: ' . $route);
                }
                $owners[$route] = $position;
            }
        }
    }

    $additional = [
        'coach' => [
            'ajax_denny_rozvrh.php','ajax_dostupnost_sportovist.php','ajax_nova_oznameni.php',
            'ajax_podskupiny.php','ajax_sportovci.php','ajax_treninky.php','ajax_update_plan.php',
            'ajax_update_poznamka.php','duplikovat_trenink.php','edit_trenink.php','generuj_story.php',
            'google_sheets_linky.php','individualni_lekce_form.php','nastaveni_story.php','nacti_podskupiny.php','nacti_skupiny.php',
            'planovany_trenink_form.php','prehled_podskupin.php','prehled_popisu.php','prehled_skupina.php',
            'prehled_skupin.php','prehled_skupiny.php','prehled_stories.php','prehled_trenera.php',
            'prehled_treninku_skupiny_kalendar.php','rezervovat_sportoviste.php','smazat_trenink.php',
            'sportovec_detail.php','ulozit_trenink.php','ulozit_zatezovy_test.php',
            'zatezove_testy/ulozit_zatezovy_test.php','zatezove_testy/zatezovy_test_form.php',
        ],
        'sports_lead' => [
            'ajax_global_search.php','download_import.php','edit_zavod.php','edit_zavod_form.php',
            'export.php','export_csv.php','export_draha.php','export_seznam.php','export_uci.php','export_xls.php',
            'formular_zavod.php','import_vysledku_zavodu.php','oznameni.php','prehled_zavodu.php',
            'search_sportovci.php','sprava_treninku.php','tydenni_report.php','ulozit_zavod.php',
            'update_trenink.php','update_zavod.php','verejny_prehled.php','vypis_vsech_vykazu.php',
            'zavod_detail.php',
        ],
        'registrar' => [
            'athlete_sensitive_admin.php','hromadne_podskupiny.php','kis_training_a07_admin.php',
            'sportovci_hromadne.php','sportovec_karta.php',
        ],
        'program_coordinator' => [
            'club_event_participants_export.php',
        ],
        'catalog_manager' => [],
        'order_operator' => [],
        'finance_manager' => [
            'jizdy/formular.php','jizdy/seznam.php','jizdy/smazat.php','jizdy/uloz.php',
            'servis/formular.php','servis/seznam.php','servis/smazat.php','servis/uloz.php',
            'uctenky/formular.php','uctenky/smazat.php','uctenky/uloz.php',
            'udalosti/formular.php','udalosti/smazat.php','udalosti/uloz.php','udalosti/uzavrit.php','udalosti/vyuctovat.php',
            'vozidla/formular.php','vozidla/smazat.php','vozidla/uloz.php',
        ],
        'system_admin' => [
            'admin_dashboard.php','registrace.php',
        ],
    ];
    foreach ($additional as $position => $routes) {
        foreach ($routes as $route) {
            if (isset($owners[$route]) && $owners[$route] !== $position) {
                throw new LogicException('Administracni trasa ma vice vlastniku: ' . $route);
            }
            $owners[$route] = $position;
        }
    }
    ksort($owners);
    return $owners;
}

/** @return list<string> */
function staffSharedRoutes(): array
{
    return [
        'index.php','login.php','logout.php','pracovni_pozice.php','prepnout_pracovni_pozici.php',
        'private_download.php','push_subscribe.php','program_skupiny.php',
    ];
}

/**
 * Podpurne editory mohou obsluhovat vice pracovnich agend, aniz by se jejich
 * rozcestniky nebo hlavni vlastnictvi trasy sloucily.
 *
 * @return array<string,list<string>> route => dalsi povolene aktivni pozice
 */
function staffRouteDelegates(): array
{
    return [
        'edit_trenink.php' => ['sports_lead'],
        'update_trenink.php' => ['coach'],
        'smazat_trenink.php' => ['sports_lead'],
        'sportovec_detail.php' => ['sports_lead'],
        'kalendar_sportovist.php' => ['program_coordinator'],
        'rezervovat_sportoviste.php' => ['program_coordinator'],
        'club_calendar.php' => ['sports_lead','program_coordinator','finance_manager'],
    ];
}

/** @return list<string> */
function staffRouteAllowedPositions(string $route): array
{
    $route = staffNormalizeRoute($route);
    $owner = staffRouteOwner($route);
    if ($owner === null) return [];
    return array_values(array_unique(array_merge([$owner], staffRouteDelegates()[$route] ?? [])));
}

/** @return list<array<string,mixed>> */
function staffPositionMenuGroups(string $position, bool $isLocal): array
{
    $definition = staffPositionDefinitions()[$position] ?? null;
    if (!is_array($definition)) return [];
    $groups = [];
    foreach ($definition['groups'] as $group) {
        $items = array_values(array_filter(
            $group['items'],
            static fn(array $item): bool => $isLocal || empty($item['local_only'])
        ));
        if ($items === []) continue;
        $group['items'] = $items;
        $groups[] = $group;
    }
    return $groups;
}

/** @return list<array<string,mixed>> */
function staffPositionPrimaryMenuGroups(string $position,bool $isLocal):array
{
    $primary=array_fill_keys(staffPositionPrimaryRoutes()[$position]??[],true);$groups=[];
    foreach(staffPositionMenuGroups($position,$isLocal)as$group){
        $items=array_values(array_filter($group['items'],static fn(array$item):bool=>isset($primary[(string)$item['route']])));
        if($items===[])continue;$group['items']=$items;$groups[]=$group;
    }
    return$groups;
}

/** @return list<array<string,mixed>> */
function staffPositionAdvancedMenuGroups(string $position,bool $isLocal):array
{
    $primary=array_fill_keys(staffPositionPrimaryRoutes()[$position]??[],true);$groups=[];
    foreach(staffPositionMenuGroups($position,$isLocal)as$group){
        $items=array_values(array_filter($group['items'],static fn(array$item):bool=>!isset($primary[(string)$item['route']])));
        if($items===[])continue;$group['items']=$items;$groups[]=$group;
    }
    return$groups;
}

function staffNormalizeRoute(string $route): string
{
    $route = str_replace('\\', '/', trim($route));
    return ltrim((string)preg_replace('~/+~', '/', $route), '/');
}

function staffCurrentRoute(string $appRoot): ?string
{
    $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script === '') return null;
    $root = realpath($appRoot);
    $resolved = realpath($script);
    if ($root === false || $resolved === false) return null;
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $resolved = str_replace('\\', '/', $resolved);
    if ($resolved !== $root && !str_starts_with($resolved, $root . '/')) return null;
    return staffNormalizeRoute(substr($resolved, strlen($root)));
}

function staffRouteOwner(string $route): ?string
{
    return staffRouteOwners()[staffNormalizeRoute($route)] ?? null;
}

/** @return list<string> */
function staffLegacyPositions(string $role): array
{
    return match ($role) {
        'admin' => staffPositionCodes(),
        'hlavni' => ['coach','sports_lead','registrar','program_coordinator'],
        default => ['coach'],
    };
}

function staffIsSuperadmin(): bool
{
    return !empty($_SESSION['staff_is_superadmin']);
}

/** @return list<string> */
function staffAssignedPositions(): array
{
    $valid = array_fill_keys(staffPositionCodes(), true);
    if (!array_key_exists('staff_positions', $_SESSION)) {
        return staffLegacyPositions((string)($_SESSION['role'] ?? 'trener'));
    }
    $positions = array_values(array_unique(array_filter(
        array_map('strval', (array)($_SESSION['staff_positions'] ?? [])),
        static fn(string $code): bool => isset($valid[$code])
    )));
    return $positions;
}

/** @return list<string> */
function staffAvailablePositions(): array
{
    $allowed = staffIsSuperadmin() ? staffPositionCodes() : staffAssignedPositions();
    $order = array_flip(staffPositionCodes());
    usort($allowed, static fn(string $a, string $b): int => ($order[$a] ?? 999) <=> ($order[$b] ?? 999));
    return $allowed;
}

function staffCanUsePosition(string $position): bool
{
    return in_array($position, staffAvailablePositions(), true);
}

function staffActivePosition(): string
{
    $active = (string)($_SESSION['staff_active_position'] ?? '');
    if ($active !== '' && staffCanUsePosition($active)) return $active;
    return staffAvailablePositions()[0] ?? '';
}

function staffEffectiveLegacyRole(string $fallbackRole): string
{
    if (!isset($_SESSION['trener_id'])) return $fallbackRole;
    // Pred prvnim DB refresh requestu jeste pracovni snapshot neexistuje.
    // Stary guard smi docasne pouzit DB roli; centralni route guard se spusti
    // hned po refreshi a pred jakoukoli mutaci stranky.
    if (!isset($_SESSION['staff_active_position'])) return $fallbackRole;
    $definition = staffPositionDefinitions()[staffActivePosition()] ?? null;
    return is_array($definition) ? (string)$definition['legacy_role'] : 'trener';
}

function staffActivePositionIs(string $position): bool
{
    return isset($_SESSION['trener_id']) && hash_equals($position, staffActivePosition());
}

function staffRequireActivePosition(string $position): void
{
    if (!isset($_SESSION['trener_id'])) {
        header('Location: login.php');
        exit;
    }
    if (!staffActivePositionIs($position)) {
        http_response_code(403);
        exit('Tato operace nepatří do aktivní pracovní pozice.');
    }
}

function staffWorkspaceTablesAvailable(PDO $pdo): bool
{
    try {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('staff_positions','staff_user_positions','staff_superadmins')");
            return (int)$statement->fetchColumn() === 3;
        }
        $statement = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('staff_positions','staff_user_positions','staff_superadmins')");
        return (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        return false;
    }
}

function staffWorkspaceRefreshSession(PDO $pdo, int $trainerId): void
{
    $legacyRole = (string)($_SESSION['role'] ?? 'trener');
    $positions = [];
    $default = '';
    $superadmin = false;

    $tablesAvailable = staffWorkspaceTablesAvailable($pdo);
    if ($tablesAvailable) {
        $statement = $pdo->prepare(
            'SELECT position_code,is_default FROM staff_user_positions WHERE trainer_id=? '
            . 'ORDER BY is_default DESC, position_code'
        );
        $statement->execute([$trainerId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = (string)$row['position_code'];
            if (!array_key_exists($code, staffPositionDefinitions())) continue;
            $positions[] = $code;
            if ((int)$row['is_default'] === 1 && $default === '') $default = $code;
        }
        $super = $pdo->prepare('SELECT 1 FROM staff_superadmins WHERE trainer_id=? LIMIT 1');
        $super->execute([$trainerId]);
        $superadmin = (bool)$super->fetchColumn();
    }

    if (!$tablesAvailable) $positions = staffLegacyPositions($legacyRole);
    if ($default === '' || !in_array($default, $positions, true)) $default = $positions[0] ?? '';

    $_SESSION['staff_positions'] = array_values(array_unique($positions));
    $_SESSION['staff_is_superadmin'] = $superadmin;
    $active = (string)($_SESSION['staff_active_position'] ?? '');
    $available = $superadmin ? staffPositionCodes() : $_SESSION['staff_positions'];
    if ($active === '' || !in_array($active, $available, true)) {
        $_SESSION['staff_active_position'] = in_array($default, $available, true) ? $default : ($available[0] ?? '');
    }
}

function staffSwitchPosition(PDO $pdo, int $trainerId, string $target, string $reason = ''): void
{
    if (!array_key_exists($target, staffPositionDefinitions()) || !staffCanUsePosition($target)) {
        throw new InvalidArgumentException('Požadovaná pracovní pozice není dostupná.');
    }
    $from = staffActivePosition();
    if ($from === $target) return;
    if (staffWorkspaceTablesAvailable($pdo)) {
        $statement = $pdo->prepare(
            'INSERT INTO staff_position_switch_events '
            . '(trainer_id,from_position_code,to_position_code,used_superadmin,reason,ip_address,user_agent) '
            . 'VALUES (?,?,?,?,?,?,?)'
        );
        $statement->execute([
            $trainerId,
            $from,
            $target,
            staffIsSuperadmin() && !in_array($target, staffAssignedPositions(), true) ? 1 : 0,
            mb_substr(trim($reason) !== '' ? trim($reason) : 'Přepnutí pracovního kontextu', 0, 1000, 'UTF-8'),
            mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8'),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500, 'UTF-8'),
        ]);
    }
    $_SESSION['staff_active_position'] = $target;
}

function staffEnforceCurrentRoute(string $appRoot): void
{
    if (PHP_SAPI === 'cli' || !isset($_SESSION['trener_id'])) return;
    $route = staffCurrentRoute($appRoot);
    if ($route === null || in_array($route, staffSharedRoutes(), true)) return;
    $owner = staffRouteOwner($route);
    if ($owner === null || in_array(staffActivePosition(), staffRouteAllowedPositions($route), true)) return;

    http_response_code(403);
    header('Cache-Control: no-store, private');
    $definition = staffPositionDefinitions()[$owner];
    if (str_starts_with(basename($route), 'ajax_') || str_ends_with($route, '_admin.php') && str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Tato operace patří do jiné pracovní pozice.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $label = htmlspecialchars((string)$definition['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Přístup odepřen</title><body style="font-family:system-ui;max-width:760px;margin:4rem auto;padding:1rem">'
        . '<h1>Tato stránka patří do jiné pracovní pozice</h1><p>Vlastník agendy: <strong>' . $label . '</strong>.</p>'
        . '<p><a href="pracovni_pozice.php">Zpět na aktivní rozcestník</a></p></body></html>';
    exit;
}
