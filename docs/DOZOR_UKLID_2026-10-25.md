# Dozor 5 — úklid a návrh dalších kroků

Základ: `3b164e91`, 25. 10. 2026. Pouze změna aliasu exportu a vyloučení nepoužívaného PDF; introspekce a testy se nemažou.

## Export a statické soubory

- `export.php` a `export_csv.php` byly bajtově shodné (SHA-256 `bd282f08e7a10cb50dcd6b0b871b032a88b63259006085a791be72db95867996`).
- Přesný název export.php je v runtime referencován jen v `staffRouteOwners()` (pozice coach); navigace používá export_csv.php. Staré záložky zachovává přesměrování s query parametry. Guard a obnovení autorizace před redirectem zůstávají.
- `ebook.pdf`: žádná aplikační reference; vyloučeno z obou rsync kroků v předchozím PR #196. Navázaná větev již obsahuje sjednocené výjimky, jednou v každém bloku.
- `arial.ttf`: používá `generuj_story.php` při renderování, zůstává v release. Soubory se nyní nepřesouvají z repozitáře.
- Rsync nepoužívá --delete; historicky nasazený ebook.pdf blokuje nové pravidlo v .htaccess z PR #196. Žádný soubor se na produkci ručně nemaže.

## Runtime introspekce schématu

Inventura podle deklarací *TableExists / *ColumnExists / *HasTable / *HasColumn a jejich volání v request PHP včetně includes/. Řádky jsou v uvedeném základu; migration_runner je migrační infrastruktura, nikoli webová kompatibilita.

| Helper (definice) | Počet volání | Soubory volajících |
| --- | ---: | --- |
| `adminOperationalColumnExists` (includes/admin_operational_overview.php:22) | 2 | `includes/admin_operational_overview.php:85`, `includes/operational_cases.php:73` |
| `adminOperationalTableExists` (includes/admin_operational_overview.php:9) | 1 | `includes/admin_operational_overview.php:39` |
| `childAccessColumnExists` (includes/child_access.php:32) | 1 | `includes/child_access.php:369` |
| `childAccessTableExists` (includes/child_access.php:13) | 1 | `includes/child_access.php:391` |
| `clubEventColumnExists` (includes/club_event.php:159) | 15 | `includes/club_event_registration.php:50`, `includes/club_event_registration.php:68`, `includes/club_event_registration.php:79`, `includes/club_event_registration.php:291`, `includes/club_event_registration.php:292`, `includes/club_event_registration.php:293`, `includes/club_event_registration.php:337`, `includes/club_event_registration.php:337`, `includes/club_event_registration.php:337`, `includes/club_event_registration.php:810`, `includes/club_event_registration.php:810`, `includes/club_event_registration.php:840`, `includes/club_event_shop.php:179`, `includes/club_event_shop.php:179`, `includes/sports_activity.php:69` |
| `clubEventTableExists` (includes/club_event.php:154) | 10 | `booking/krouzky.php:115`, `includes/club_event.php:150`, `includes/club_event_registration.php:17`, `includes/club_event_registration.php:36`, `includes/club_event_registration.php:49`, `includes/club_event_registration.php:67`, `includes/club_event_registration.php:78`, `includes/club_event_registration.php:839`, `includes/shop_catalog_publication.php:101`, `includes/shop_catalog_publication.php:102` |
| `clubFinanceColumnExists` (includes/club_finance.php:18) | 8 | `booking/rezervovat.php:162`, `includes/club_finance.php:82`, `includes/club_finance.php:171`, `includes/club_finance.php:173`, `includes/club_finance.php:290`, `includes/shop_checkout.php:321`, `includes/sportovec_editor.php:36`, `includes/sportovec_editor_details.php:245` |
| `clubFinanceTableExists` (includes/club_finance.php:6) | 61 | `accounting_adjustments_admin.php:15`, `club_finance_admin.php:34`, `includes/accounting_adjustments.php:41`, `includes/accounting_adjustments.php:59`, `includes/accounting_adjustments.php:63`, `includes/accounting_adjustments.php:68`, `includes/accounting_adjustments.php:182`, `includes/accounting_adjustments.php:182`, `includes/accounting_adjustments.php:238`, `includes/accounting_adjustments.php:238`, `includes/accounting_adjustments.php:239`, `includes/accounting_adjustments.php:239`, `includes/accounting_adjustments.php:242`, `includes/club_finance.php:33`, `includes/club_finance.php:50`, `includes/club_finance.php:72`, `includes/club_finance.php:82`, `includes/club_finance.php:127`, `includes/club_finance.php:127`, `includes/club_finance.php:154`, `includes/club_finance.php:155`, `includes/club_finance.php:156`, `includes/club_finance.php:170`, `includes/club_finance.php:170`, `includes/club_finance.php:174`, `includes/club_finance.php:175`, `includes/club_finance.php:180`, `includes/club_finance.php:187`, `includes/club_finance.php:236`, `includes/club_finance.php:263`, `includes/club_finance.php:277`, `includes/club_finance.php:280`, `includes/club_finance.php:286`, `includes/club_finance.php:311`, `includes/member_fee_annual.php:46`, `includes/member_payer_receipt.php:53`, `includes/shop_checkout.php:318`, `includes/shop_checkout.php:903`, `includes/shop_checkout.php:1035`, `includes/shop_fulfillment.php:7`, `includes/shop_fulfillment.php:94`, `includes/shop_fulfillment.php:101`, `includes/shop_fulfillment.php:111`, `includes/shop_fulfillment.php:118`, `includes/shop_fulfillment.php:118`, `includes/shop_fulfillment.php:137`, `includes/shop_fulfillment.php:148`, `includes/shop_fulfillment.php:149`, `includes/sportovec_editor_details.php:190`, `includes/sportovec_editor_details.php:204`, `includes/sportovec_editor_details.php:205`, `includes/sportovec_editor_details.php:215`, `includes/sportovec_editor_details.php:219`, `includes/sportovec_editor_details.php:223`, `includes/sportovec_editor_details.php:240`, `includes/sportovec_editor_details.php:240`, `includes/sportovec_editor_details.php:244`, `includes/sportovec_editor_details.php:244`, `includes/sportovec_editor_details.php:244`, `includes/sportovec_editor_details.php:249`, `includes/sportovec_editor_details.php:252` |
| `clubProgramColumnExists` (includes/club_program.php:146) | 24 | `includes/club_program.php:270`, `includes/club_program.php:274`, `includes/club_program.php:277`, `includes/club_program.php:332`, `includes/club_program.php:542`, `includes/club_program.php:554`, `includes/club_program.php:555`, `includes/club_program.php:558`, `includes/club_program.php:559`, `includes/club_program.php:563`, `includes/club_program.php:574`, `includes/club_program.php:577`, `includes/club_program.php:691`, `includes/club_program.php:697`, `includes/club_program.php:746`, `includes/club_program.php:781`, `includes/club_program.php:838`, `includes/club_program.php:870`, `includes/club_program.php:871`, `includes/club_program.php:901`, `includes/club_program.php:929`, `includes/club_program_wizard.php:60`, `includes/club_program_wizard.php:155`, `includes/club_program_wizard.php:210` |
| `clubProgramRuntimeTableExists` (includes/club_program.php:19) | 16 | `includes/club_program.php:331`, `includes/club_program.php:331`, `includes/club_program.php:508`, `includes/club_program.php:543`, `includes/club_program.php:611`, `includes/club_program.php:698`, `includes/club_program.php:784`, `includes/club_program.php:953`, `includes/club_program.php:987`, `includes/club_program_admission.php:63`, `includes/club_program_wizard.php:159`, `includes/club_program_wizard.php:170`, `includes/club_tocnaci_split.php:76`, `includes/member_fee_agreement.php:4`, `includes/shop_checkout.php:747`, `includes/shop_checkout.php:1101` |
| `clubProgramTermsColumnExists` (includes/club_program_terms.php:19) | 3 | `includes/club_program_terms.php:25`, `includes/club_program_terms.php:26`, `includes/club_program_terms.php:26` |
| `familyAnnualPaidOverviewColumnExists` (includes/family_annual_paid_overview.php:144) | 1 | `includes/family_annual_paid_overview.php:109` |
| `familyPortalTableExists` (includes/family_portal.php:12) | 12 | `booking/sportovni_prehled.php:467`, `includes/club_makeup.php:33`, `includes/club_makeup.php:529`, `includes/club_makeup.php:535`, `includes/family_portal.php:40`, `includes/family_portal.php:41`, `includes/family_portal.php:42`, `includes/family_portal.php:157`, `includes/family_portal.php:176`, `includes/family_tasks.php:13`, `includes/training_rsvp.php:15`, `planovany_trenink_form.php:121` |
| `familyWeeklyDeliveryTableExists` (includes/family_weekly_delivery.php:12) | 5 | `includes/family_weekly_delivery.php:32`, `includes/family_weekly_delivery.php:44`, `includes/family_weekly_delivery.php:74`, `includes/family_weekly_delivery.php:132`, `includes/family_weekly_delivery.php:291` |
| `financialExpenseTableExists` (includes/financial_event_guard.php:75) | 2 | `includes/financial_event_guard.php:59`, `includes/financial_event_guard.php:80` |
| `fioTableExists` (includes/fio_readonly_import.php:36) | 11 | `eshop_fio_admin.php:18`, `includes/fio_readonly_import.php:49`, `includes/fio_readonly_import.php:54`, `includes/fio_readonly_import.php:54`, `includes/fio_readonly_import.php:54`, `includes/fio_readonly_import.php:69`, `includes/fio_readonly_import.php:206`, `includes/fio_readonly_import.php:219`, `includes/fio_readonly_import.php:258`, `includes/fio_readonly_import.php:277`, `includes/fio_readonly_import.php:277` |
| `kisImportColumnExists` (includes/kis_import_run_lib.php:282) | 6 | `includes/kis_import_run_lib.php:35`, `includes/kis_import_run_lib.php:61`, `includes/kis_import_run_lib.php:64`, `includes/kis_import_run_lib.php:67`, `includes/kis_import_run_lib.php:317`, `includes/kis_import_run_lib.php:331` |
| `kisImportTableExists` (includes/kis_import_run_lib.php:300) | 4 | `includes/kis_import_parity_report.php:139`, `includes/kis_import_parity_report.php:140`, `includes/kis_import_run_lib.php:344`, `includes/kis_member_charge_promotion.php:105` |
| `kisRosterHierarchyHasColumn` (includes/roster_hierarchy.php:17) | 18 | `includes/athlete_registration_admin.php:323`, `includes/kis_hobby_transition.php:109`, `includes/kis_hobby_transition.php:256`, `includes/kis_roster.php:132`, `includes/kis_roster.php:149`, `includes/kis_roster.php:201`, `includes/roster_hierarchy.php:93`, `includes/roster_hierarchy.php:95`, `includes/roster_hierarchy.php:135`, `includes/roster_hierarchy.php:141`, `includes/roster_hierarchy.php:142`, `includes/roster_hierarchy.php:147`, `includes/roster_hierarchy.php:168`, `includes/roster_hierarchy.php:230`, `includes/roster_hierarchy.php:325`, `includes/roster_legacy_mapping_preview.php:32`, `includes/roster_legacy_mapping_preview.php:41`, `includes/roster_legacy_mapping_preview.php:53` |
| `kisRosterHierarchyHasTable` (includes/roster_hierarchy.php:6) | 28 | `includes/athlete_training_timeline.php:29`, `includes/athlete_training_timeline.php:29`, `includes/athlete_training_timeline.php:54`, `includes/athlete_training_timeline.php:60`, `includes/athlete_training_timeline.php:72`, `includes/athlete_training_timeline.php:103`, `includes/roster_hierarchy.php:19`, `includes/roster_hierarchy.php:31`, `includes/roster_hierarchy.php:52`, `includes/roster_hierarchy.php:63`, `includes/roster_hierarchy.php:92`, `includes/roster_hierarchy.php:273`, `includes/roster_hierarchy.php:273`, `includes/roster_hierarchy.php:283`, `includes/roster_hierarchy.php:288`, `includes/roster_hierarchy.php:296`, `includes/roster_hierarchy.php:296`, `includes/roster_hierarchy.php:305`, `includes/roster_hierarchy.php:361`, `includes/roster_legacy_mapping_preview.php:15`, `includes/roster_legacy_mapping_preview.php:15`, `includes/roster_legacy_mapping_preview.php:16`, `includes/roster_legacy_mapping_preview.php:31`, `includes/roster_legacy_mapping_preview.php:46`, `includes/roster_legacy_mapping_preview.php:58`, `includes/roster_legacy_mapping_preview.php:66`, `includes/roster_legacy_mapping_preview.php:66`, `includes/roster_legacy_mapping_preview.php:68` |
| `m2FinalizationTableExists` (includes/m2_finalization.php:6) | 2 | `includes/m2_finalization.php:27`, `includes/m2_finalization.php:55` |
| `memberChargeReadTableExists` (includes/member_charge_read.php:4) | 43 | `ajax_update_plan.php:62`, `includes/account_person_role.php:199`, `includes/coach_scope.php:10`, `includes/coach_scope.php:10`, `includes/coach_scope.php:12`, `includes/coach_scope.php:13`, `includes/coach_scope.php:13`, `includes/coach_scope.php:56`, `includes/coach_scope.php:82`, `includes/coach_scope.php:85`, `includes/coach_scope.php:94`, `includes/coach_scope.php:103`, `includes/coach_scope.php:120`, `includes/coach_scope.php:120`, `includes/coach_scope.php:151`, `includes/coach_scope.php:151`, `includes/member_charge_read.php:25`, `includes/member_charge_read.php:26`, `includes/member_charge_read.php:45`, `includes/member_charge_read.php:46`, `includes/member_charge_read.php:47`, `includes/person_identity_review.php:79`, `includes/person_identity_review.php:79`, `includes/person_identity_review.php:79`, `includes/person_state.php:18`, `includes/person_state.php:18`, `includes/person_state.php:27`, `includes/person_state.php:27`, `includes/person_state.php:31`, `includes/person_state.php:37`, `includes/sports_activity.php:68`, `includes/sports_activity.php:78`, `includes/sports_activity.php:83`, `includes/training_closure.php:8`, `includes/training_closure.php:35`, `includes/training_closure.php:35`, `includes/training_closure.php:84`, `includes/training_closure.php:124`, `includes/training_closure.php:128`, `includes/training_closure.php:146`, `includes/training_closure.php:156`, `includes/training_closure.php:188`, `includes/training_closure.php:191` |
| `memberChargeReminderTableExists` (includes/member_charge_reminder.php:12) | 8 | `includes/member_charge_reminder.php:32`, `includes/member_charge_reminder.php:46`, `includes/member_charge_reminder.php:59`, `includes/member_charge_reminder.php:60`, `includes/member_charge_reminder.php:88`, `includes/member_charge_reminder.php:113`, `includes/member_charge_reminder.php:309`, `includes/member_charge_reminder_demo.php:14` |
| `personAuditTableExists` (includes/person_audit_timeline.php:320) | 2 | `includes/person_audit_timeline.php:83`, `includes/person_audit_timeline.php:274` |
| `publicCalendarColumnExists` (includes/public_calendar_feed.php:21) | 4 | `includes/family_calendar_feed.php:193`, `includes/family_calendar_feed.php:194`, `includes/public_calendar_feed.php:64`, `includes/public_calendar_feed.php:65` |
| `publicCalendarTableExists` (includes/public_calendar_feed.php:4) | 1 | `includes/public_calendar_feed.php:226` |
| `shopAttributeTableExists` (includes/shop_attribute.php:4) | 3 | `includes/shop_attribute.php:16`, `includes/shop_attribute.php:20`, `includes/shop_attribute.php:105` |
| `shopBeneficiaryColumnExists` (includes/shop_beneficiary.php:4) | 6 | `booking/sportovni_prehled.php:178`, `includes/shop_checkout.php:108`, `includes/shop_checkout.php:175`, `includes/shop_checkout.php:197`, `includes/shop_checkout.php:296`, `includes/shop_checkout.php:414` |
| `shopCheckoutColumnExists` (includes/shop_checkout.php:45) | 20 | `booking/rychly_nakup.php:53`, `includes/athlete_registration_order.php:26`, `includes/shop_beneficiary.php:67`, `includes/shop_checkout.php:54`, `includes/shop_checkout.php:55`, `includes/shop_checkout.php:56`, `includes/shop_checkout.php:112`, `includes/shop_checkout.php:199`, `includes/shop_checkout.php:300`, `includes/shop_checkout.php:429`, `includes/shop_checkout.php:431`, `includes/shop_checkout.php:615`, `includes/shop_checkout.php:617`, `includes/shop_checkout.php:719`, `includes/shop_checkout.php:984`, `includes/shop_checkout.php:1261`, `includes/shop_packeta.php:18`, `includes/shop_packeta.php:25`, `includes/shop_ppl.php:16`, `includes/shop_program_quick_checkout.php:79` |
| `shopNumberSeriesTableExists` (includes/shop_number_series.php:20) | 7 | `eshop_number_series_admin.php:22`, `includes/shop_catalog_publication.php:18`, `includes/shop_number_series.php:69`, `includes/shop_number_series.php:82`, `includes/shop_number_series.php:100`, `includes/shop_number_series.php:111`, `includes/shop_number_series.php:126` |
| `shopPaymentNotificationColumnExists` (includes/shop_payment_notification.php:31) | 4 | `includes/shop_payment_notification.php:66`, `includes/shop_payment_notification.php:171`, `includes/shop_payment_notification.php:174`, `includes/shop_payment_notification.php:177` |
| `shopPaymentNotificationTableExists` (includes/shop_payment_notification.php:11) | 8 | `includes/shop_guest_checkout.php:191`, `includes/shop_payment_notification.php:58`, `includes/shop_payment_notification.php:65`, `includes/shop_payment_notification.php:85`, `includes/shop_payment_notification.php:98`, `includes/shop_payment_notification.php:288`, `includes/shop_payment_notification.php:291`, `includes/shop_program_quick_checkout.php:91` |
| `shopPaymentPolicyColumnExists` (includes/shop_payment_policy.php:25) | 13 | `eshop_payment_methods_admin.php:22`, `eshop_payment_methods_admin.php:23`, `eshop_payment_methods_admin.php:24`, `includes/shop_checkout.php:460`, `includes/shop_guest_checkout.php:182`, `includes/shop_payment_policy.php:47`, `includes/shop_payment_policy.php:55`, `includes/shop_payment_policy.php:65`, `includes/shop_payment_policy.php:97`, `includes/shop_payment_policy.php:109`, `includes/shop_payment_policy.php:133`, `includes/shop_payment_policy.php:180`, `includes/shop_program_quick_checkout.php:84` |
| `shopPurchaseModeColumnExists` (includes/shop_purchase_mode.php:16) | 0 |  |
| `shopPurchaseModeTableExists` (includes/shop_purchase_mode.php:4) | 2 | `includes/shop_purchase_mode.php:75`, `includes/shop_purchase_mode.php:98` |
| `shopSystemEmailTableExists` (includes/shop_system_email.php:55) | 4 | `includes/club_event_notification.php:230`, `includes/shop_system_email.php:69`, `includes/shop_system_email.php:135`, `includes/shop_system_email_admin_view.php:22` |
| `sportsDataQualityColumnExists` (includes/sports_data_quality.php:26) | 10 | `includes/sports_data_quality.php:152`, `includes/sports_data_quality.php:153`, `includes/sports_data_quality.php:154`, `includes/sports_data_quality.php:155`, `includes/sports_data_quality.php:156`, `includes/sports_data_quality.php:205`, `includes/sports_data_quality.php:206`, `includes/sports_data_quality.php:207`, `includes/sports_import_review.php:206`, `includes/sports_import_review.php:262` |
| `sportsDataQualityTableExists` (includes/sports_data_quality.php:13) | 2 | `includes/sports_data_quality.php:29`, `includes/sports_data_quality.php:44` |
| `uatReadinessTableExists` (includes/uat_readiness.php:7) | 28 | `includes/uat_readiness.php:68`, `includes/uat_readiness.php:77`, `includes/uat_readiness.php:77`, `includes/uat_readiness.php:87`, `includes/uat_readiness.php:87`, `includes/uat_readiness.php:87`, `includes/uat_readiness.php:108`, `includes/uat_readiness.php:108`, `includes/uat_readiness.php:108`, `includes/uat_readiness.php:118`, `includes/uat_readiness.php:118`, `includes/uat_readiness.php:118`, `includes/uat_readiness.php:118`, `includes/uat_readiness.php:136`, `includes/uat_readiness.php:150`, `includes/uat_readiness.php:152`, `includes/uat_readiness.php:159`, `includes/uat_readiness.php:159`, `includes/uat_readiness.php:164`, `includes/uat_readiness.php:164`, `includes/uat_readiness.php:176`, `includes/uat_readiness.php:176`, `includes/uat_readiness.php:181`, `includes/uat_readiness.php:181`, `includes/uat_readiness.php:186`, `includes/uat_readiness.php:186`, `includes/uat_readiness.php:186`, `includes/uat_readiness.php:210` |

Celkem 39 pojmenovaných helperů / 396 volání. Soubory s přímými introspekčními SQL (zahrnuje další specializované funkce):

- `includes/admin_operational_overview.php`
- `includes/auto_migrace.php`
- `includes/child_access.php`
- `includes/club_cutover.php`
- `includes/club_event.php`
- `includes/club_event_shop.php`
- `includes/club_finance.php`
- `includes/club_program.php`
- `includes/club_program_terms.php`
- `includes/club_registration_consents.php`
- `includes/family_annual_paid_overview.php`
- `includes/family_portal.php`
- `includes/family_weekly_delivery.php`
- `includes/fio_readonly_import.php`
- `includes/individual_lesson_context.php`
- `includes/kis_import_run_lib.php`
- `includes/kis_person_cutover.php`
- `includes/kis_roster.php`
- `includes/m2_finalization.php`
- `includes/member_charge_read.php`
- `includes/member_charge_reminder.php`
- `includes/migration_runner.php`
- `includes/payment_variable_symbol_schema.php`
- `includes/person_audit_timeline.php`
- `includes/public_calendar_feed.php`
- `includes/public_profile.php`
- `includes/public_velodrome_shop.php`
- `includes/roster_hierarchy.php`
- `includes/shop_attribute.php`
- `includes/shop_bank_settings.php`
- `includes/shop_beneficiary.php`
- `includes/shop_checkout.php`
- `includes/shop_manual_catalog.php`
- `includes/shop_member_pricing.php`
- `includes/shop_number_series.php`
- `includes/shop_payment_notification.php`
- `includes/shop_payment_policy.php`
- `includes/shop_purchase_mode.php`
- `includes/shop_system_email.php`
- `includes/sports_data_quality.php`
- `includes/staff_workspaces.php`
- `includes/uat_readiness.php`

### Kandidáti po povinných migracích — pouze návrh

- `clubFinanceSettings` / `clubFinanceSaveSettings`: fallback/exception pro chybějící club_finance_settings po povinné migraci finančního základu není běžná produkční větev. Návrh: preflight kontrola schématu a poté přímé čtení; fallback pro prázdný řádek nastavení není totéž a nesmí se bez rozhodnutí odstranit.
- `clubFinanceOrderAthlete`: větev pro chybějící shop_orders.finance_sportovec_id a account_person_roles je po povinných migracích nedosažitelná na zdravém produkčním schématu. Platné návraty pro nulovou/nejednoznačnou rodinnou vazbu zůstávají živé.
- `clubProgramTermsRegistryAvailable` a `clubProgramTermsAcceptanceAvailable`: kompatibilita před term registry/status a snapshot sloupci. Neodstraňovat kontrolu skutečně zveřejněného znění nebo chybějícího souhlasu.
- `shopPurchaseMode*`, `shopPaymentPolicyColumnExists`, `shopNumberSeriesTableExists`: po jejich migracích je větev chybějícího schématu kandidát na přesun do preflightu; nenahrazuje provozní feature flags nebo validaci číselné řady.
- `adminOperational*`, `personAuditTableExists`, `sportsDataQuality*`: řada kontrol je záměrná diagnostika neúplného schématu nebo testovacích fixtures. Neoznačovat všechny za mrtvý kód.
- SQLite/MySQL větvení není mrtvé: SQLite se používá v testech. Proměnné názvy tabulek, historické exporty a řízený migrační runner vyžadují zvláštní posouzení.

Důkaz migračních předpokladů (konkrétní soubory nalezené podle identifikátorů):

- `club_finance_settings`: `migrations/20261015130000_club_financial_balance.php`, `migrations/20261021130000_member_payer_receipts.php`.
- `finance_sportovec_id`: `migrations/20261015130000_club_financial_balance.php`.
- `account_person_roles`: `migrations/20260802230000_account_person_roles.php`, `migrations/20260802233000_account_person_claim_requests.php`, `migrations/20260803130000_club_event_registrations.php`, `migrations/20260804180000_public_velodrome.php`, `migrations/20260804235900_password_reset_tokens.php`.
- `program_terms_snapshot_json`: `migrations/20260817150000_club_program_terms.php`.
- `terms_snapshot_json`: `migrations/20260817150000_club_program_terms.php`.
- `purchase_mode`: .
- `shop_number_series`: `migrations/20261021210000_shop_number_series.php`.

## Wiring testy

61 souborů s Wiring v názvu; 1287 statických míst textových asercí (assertStringContainsString, assertStringNotContainsString, assertMatchesRegularExpression, assertDoesNotMatchRegularExpression). To není počet běhových assertions: smyčky/data providery jej mění. Počty nezahrnují textové testy v jinak pojmenovaných souborech.

| Soubor | Textové aserce |
| --- | ---: |
| `tests/Unit/AccountPersonClaimWiringTest.php` | 21 |
| `tests/Unit/AdminFormAccessibilityWiringTest.php` | 7 |
| `tests/Unit/AthleteRegistrationAdminWiringTest.php` | 32 |
| `tests/Unit/AthleteRegistrationWiringTest.php` | 12 |
| `tests/Unit/AuthWiringTest.php` | 80 |
| `tests/Unit/ChildAccessWiringTest.php` | 30 |
| `tests/Unit/ClubCalendarWiringTest.php` | 30 |
| `tests/Unit/ClubEventParticipantExportWiringTest.php` | 12 |
| `tests/Unit/ClubEventRegistrationWiringTest.php` | 55 |
| `tests/Unit/ClubEventRosterTargetWiringTest.php` | 12 |
| `tests/Unit/ClubEventWiringTest.php` | 19 |
| `tests/Unit/ClubProgramPaymentLifecycleWiringTest.php` | 8 |
| `tests/Unit/ClubProgramWiringTest.php` | 17 |
| `tests/Unit/ClubProgramWizardWiringTest.php` | 18 |
| `tests/Unit/FamilyCalendarWiringTest.php` | 24 |
| `tests/Unit/FamilyPortalWiringTest.php` | 6 |
| `tests/Unit/FamilyWeeklyDeliveryWiringTest.php` | 20 |
| `tests/Unit/FioImportWiringTest.php` | 9 |
| `tests/Unit/IndividualniLekceFormWiringTest.php` | 5 |
| `tests/Unit/KisA06WiringTest.php` | 5 |
| `tests/Unit/KisHobbyTransitionWiringTest.php` | 3 |
| `tests/Unit/KisImportPreviewWiringTest.php` | 3 |
| `tests/Unit/KisImportSandboxPromotionWiringTest.php` | 11 |
| `tests/Unit/KisRosterRolloverWiringTest.php` | 8 |
| `tests/Unit/KisRosterWiringTest.php` | 8 |
| `tests/Unit/KisSourceArchiveWiringTest.php` | 7 |
| `tests/Unit/LightweightEshopWorkflowWiringTest.php` | 29 |
| `tests/Unit/LocalhostAcceptanceHubWiringTest.php` | 52 |
| `tests/Unit/LocalhostBootstrapWiringTest.php` | 20 |
| `tests/Unit/LoggedInUxWiringTest.php` | 11 |
| `tests/Unit/MemberChargeReminderAdminWiringTest.php` | 25 |
| `tests/Unit/MemberChargeReminderWiringTest.php` | 10 |
| `tests/Unit/PasswordResetWiringTest.php` | 13 |
| `tests/Unit/PersonAuditTimelineWiringTest.php` | 11 |
| `tests/Unit/PublicCalendarWiringTest.php` | 4 |
| `tests/Unit/PublicPortalWiringTest.php` | 18 |
| `tests/Unit/PublicVelodromeShopWiringTest.php` | 9 |
| `tests/Unit/PublicVelodromeWiringTest.php` | 25 |
| `tests/Unit/RosterMessageWiringTest.php` | 16 |
| `tests/Unit/RuntimeSchemaWriteWiringTest.php` | 2 |
| `tests/Unit/SecurityInfrastructureWiringTest.php` | 58 |
| `tests/Unit/ShopAdminWiringTest.php` | 57 |
| `tests/Unit/ShopBankSettingsWiringTest.php` | 21 |
| `tests/Unit/ShopCatalogPublicationWiringTest.php` | 10 |
| `tests/Unit/ShopCheckoutWiringTest.php` | 38 |
| `tests/Unit/ShopIdentityAdminWiringTest.php` | 10 |
| `tests/Unit/ShopOrderExpirationWiringTest.php` | 10 |
| `tests/Unit/ShopPaymentNotificationWiringTest.php` | 7 |
| `tests/Unit/ShopPaymentPolicyWiringTest.php` | 6 |
| `tests/Unit/ShopPublicNavigationWiringTest.php` | 19 |
| `tests/Unit/ShopStorefrontWiringTest.php` | 49 |
| `tests/Unit/SiteDiagnosticsWiringTest.php` | 7 |
| `tests/Unit/SportovecEditorWiringTest.php` | 18 |
| `tests/Unit/StripeWiringTest.php` | 6 |
| `tests/Unit/SumUpWiringTest.php` | 7 |
| `tests/Unit/TesterFeedbackWiringTest.php` | 52 |
| `tests/Unit/TrainingCalendarWiringTest.php` | 21 |
| `tests/Unit/TrainingRosterBridgeWiringTest.php` | 13 |
| `tests/Unit/UnifiedHomepageWiringTest.php` | 28 |
| `tests/Unit/UxAccessibilityWiringTest.php` | 126 |
| `tests/Unit/UxJourneyWiringTest.php` | 17 |

### Co postupně nahradit chováním

- Oprávnění a stránky: Wiring testy existence guardu → anonymous sweep + přihlášený HTTP scénář pro každou pozici, revokaci a cizí objekt.
- Platby, vratky a sklady: hledání názvu služby ve zdrojáku → transakční scénáře nad MariaDB, opakované požadavky, souběh a kontrola ledgeru/částek. Využít již existující Support HTTP a race sady.
- Registrační texty, souhlasy a rodinné vazby: hledání názvu inputu → render formuláře, odeslání platného/neplatného CSRF, změna verze a ověření uloženého snapshotu.
- Navigace: hledání HTML řetězce → render v konkrétní pracovní pozici a kontrola DOM/URL, bez přebírání oprávnění z pouhé viditelnosti odkazu.
- Ponechat malé strukturální kontrakty tam, kde skutečně hlídají deploy pořadí, deny pravidla, vlastnictví tabulek a explicitní seznam tras. Jejich účelem není nahrazovat runtime test.

V tomto PR se žádný Wiring test ani runtime introspekce neodstraňuje.
