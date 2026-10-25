# Branch inventory — 2026-10-25

Read-only audit of 249 local and origin refs. Bases: origin/main `6bf57d6f` and completed club/acceptance `420e78a0`. No production actions, no checkout/merge/WIP edits.

## Result

Integrate completed club acceptance branch with current main. No other accepted completed implementation is missing. Preserve and exclude existing dirty roster-help WIP. Explicitly exclude closed-unmerged PR109 and superseded Fio alternate migration.

This inventory compares ancestry, stable individual patch IDs, stable aggregate branch patch IDs, and exact trees; unmerged Git ancestry alone is not interpreted as missing code.

## Classification totals

- ancestry-contained: 13 refs
- closed-unmerged-excluded: 2 refs
- earlier-stage-of-squash: 9 refs
- exact-aggregate-squash: 222 refs
- exact-tree-main-history: 2 refs
- superseded-alternate-fio: 1 refs

## Evidence per ref

| Ref | Tip | Classification | Evidence |
|---|---|---|---|
| codex/accounting-deploy-proof-20261023 | de613f30 | exact-aggregate-squash | Stable aggregate patch equals 0f8eddff8bc1ceba00624118304408b82907d7aa |
| codex/accounting-followup-20261023 | 4208d65e | exact-aggregate-squash | Stable aggregate patch equals ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9 |
| codex/accounting-production-audit-20261023 | b63fea70 | exact-aggregate-squash | Stable aggregate patch equals 59725050cf78927bfa6fb5cf71bef995038715d6 |
| codex/admin-hero-contrast-20261020 | 64bde60b | exact-aggregate-squash | Stable aggregate patch equals 175f92c7a59294acff7e15fe09cbed258707fa2e |
| codex/admin-shop-production-fixes-20261020 | 5bf4fb3f | exact-aggregate-squash | Stable aggregate patch equals f47f78bfacacd8aa6dfcfd9a36eaf93be3d07d4e |
| codex/athlete-editor-20261020 | da6f1186 | exact-aggregate-squash | Stable aggregate patch equals 7c0d5ce7e931b43018a5b1cffeda8b89fde404c5 |
| codex/athlete-editor-relations-20261020 | 80ad21b3 | exact-aggregate-squash | Stable aggregate patch equals 6a1c878922b45782490066478f1008f8ead1ccf9 |
| codex/athlete-financial-balance | bd907cff | exact-tree-main-history | git diff codex/athlete-financial-balance90067fa6 empty; 5805de67 tree equals fd325172. |
| codex/browser-acceptance-fixes-20261023 | 653758dc | exact-aggregate-squash | Stable aggregate patch equals e78af565d6d89492e54f0b9e2ecb2b63a4b534c7 |
| codex/browser-acceptance-release-proof-20261023 | 0f546c6a | exact-aggregate-squash | Stable aggregate patch equals dc75dcc55f115a883a1c3cf621f4bc3162253cab |
| codex/cash-refund-production-test-20261021 | d113a09a | exact-aggregate-squash | Stable aggregate patch equals a0e2bac12cf6818120f2dd5515499bfe5d7dc601 |
| codex/club-acceptance-package-20261023 | 2158b52b | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/club-administration-r31-r37-20261023 | 67121f0b | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/club-catalog-unification-20261020 | cddf6cfa | exact-aggregate-squash | Stable aggregate patch equals 2439a1b4afea842546f042b6b11407a5b4bb56fa |
| codex/club-followup-review-20261023 | abbb4be9 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/club-foundation-r07-r12-20261022 | 0e23e9e7 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/club-goods-alias-20261020 | 687acb83 | exact-aggregate-squash | Stable aggregate patch equals fbeccaf32fe6a9cd4a4fb6d77bed3287fb085283 |
| codex/club-live-verification-20261023 | 5c072241 | exact-aggregate-squash | Stable aggregate patch equals 0b03b564dfa6b20817478848fa8721fea09ca082 |
| codex/club-production-proof-20261023 | 43f2ab1c | exact-aggregate-squash | Stable aggregate patch equals ab7d67d924451bc99876b76da7f3ec334579188b |
| codex/club-production-release-20261023 | 1e3c81f7 | exact-aggregate-squash | Stable aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/club-production-report-20261023 | 16d78c5d | exact-aggregate-squash | Stable aggregate patch equals a159df09869d8c28755fb9ee9e635b057863e132 |
| codex/club-targets-diagnostic-20261020 | a3a9af1f | exact-aggregate-squash | Stable aggregate patch equals cb202b7509a5ed3451caf15d8afce65281f9aed1 |
| codex/custom-consents-production-proof-20261021 | 61ed7385 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/custom-registration-consents-20261021 | 557c260f | exact-aggregate-squash | Stable aggregate patch equals 61ed73854b57ff44a6b3bf56fb3b5401c8ae7941 |
| codex/daily-coach-registrar-20261024 | 1925f88c | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/event-administration-20261024 | 964d73da | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/family-course-production-proof-20261023 | 4c27e6e0 | exact-aggregate-squash | Stable aggregate patch equals b3f179c185d1cdf1e1b95101adf1392a8523c4d4 |
| codex/family-course-uat-20261023 | 43168b11 | exact-aggregate-squash | Stable aggregate patch equals c6e3328be0567a1ea4b242f18eb6d66c4e1e9202 |
| codex/family-followup-20261023 | eb18acd0 | exact-aggregate-squash | Stable aggregate patch equals c9724de9c100766f4a6f4c90fb69abfacbb8c8c8 |
| codex/final-system-acceptance-20261024 | 80d583ad | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/finance-b1-20261022 | 2ccd30e2 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/finance-migration-checksum | 10de26e6 | exact-aggregate-squash | Stable aggregate patch equals 90067fa6434afddc9483bf9d9ace589df2cf0bbf |
| codex/finance-r13-r17-20261022 | fd402b6d | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/fio-account-switch-20261020 | 2a4630f3 | closed-unmerged-excluded | GitHub PR109 CLOSED 2026-10-20, mergedAt null; unique manual one-off account identification diagnostic, excluded from completed accepted rollout. |
| codex/fio-auto-account-snapshot | 513eea85 | exact-aggregate-squash | Stable aggregate patch equals d06f6ac132c935e46d8deff6f081781a9f633fba |
| codex/fio-auto-reconciliation-recovery | d1221f35 | superseded-alternate-fio | d1221f35 range-diff equivalent to84233b99 except migration ID120000→170000. Canonical branch aggregate matches main c4f5d92f; older delivery changes match0f7098cb. Do not duplicate migration. |
| codex/fio-booking-date-offset | 6b0eda2e | exact-aggregate-squash | Stable aggregate patch equals acdbbd38e9910212d838d0c05d4fd3535041ebd7 |
| codex/fio-config-legacy-guard | 70b9b8c7 | exact-aggregate-squash | Stable aggregate patch equals ca653bb5b04d993bbe59aba6b9ca069b790a851f |
| codex/fio-date-diagnostic-args | 1147c4b9 | exact-aggregate-squash | Stable aggregate patch equals d0e01a539f4ffd077eb26cb7dcf4d791da1f68e0 |
| codex/fio-date-diagnostic-token | 72205910 | exact-aggregate-squash | Stable aggregate patch equals e23aa3e81a9dfeab219101aac1b90a5e21824fef |
| codex/fio-live-date-diagnostic | a9d33e80 | exact-aggregate-squash | Stable aggregate patch equals 4ea278a305317552bde79211e6fb287caac7638f |
| codex/fio-reconciliation-recovery-main | 218ea64f | exact-aggregate-squash | Stable aggregate patch equals c4f5d92fe6aa56df51ec886af01ee0b4385a18b9 |
| codex/fio-scheduled-import-gated | c2f84a9a | exact-aggregate-squash | Stable aggregate patch equals 7b7422f0a0e4b2a0ea7b33a16805a96c5f832599 |
| codex/fio-token-account-diagnostic | 2f8e4478 | exact-aggregate-squash | Stable aggregate patch equals 5ca02abbd887152eb22be2c08607e1c7da21f003 |
| codex/identity-flow-ux-20261019 | bf954d96 | exact-aggregate-squash | Stable aggregate patch equals 7e29bdcd304d53db99fbea0fbd741019020d6586 |
| codex/integration-20261025 | 6bf57d6f | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/inventory-r18-r24-20261022 | 12f28b61 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/kis-current-rosters-20261020 | 03609727 | exact-aggregate-squash | Stable aggregate patch equals c29b91c7eb307d5fe33f6fe12f67c3b8bfc0bff1 |
| codex/kis-full-person-import-20261020 | 6d5490d9 | exact-aggregate-squash | Stable aggregate patch equals 313cb9aa5aea37267f796dd63e070dab8a3d92ca |
| codex/kis-import-payment-history-20261020 | dd9433b0 | exact-aggregate-squash | Stable aggregate patch equals daccea3575e2f11195b1bda916832c7d81ec635c |
| codex/kis-import-private-key-20261020 | 045f435f | exact-aggregate-squash | Stable aggregate patch equals 899f1a1f8be1580106bd03f02fd70608c3054a2e |
| codex/kis-import-text-transfer-20261020 | 30ec6eb6 | exact-aggregate-squash | Stable aggregate patch equals a4232fd78fcea1135bcbfabbf5bd7cca3cfb2e67 |
| codex/kis-jersey-branding | da8ef9c7 | exact-aggregate-squash | Stable aggregate patch equals 276a5969314c7ae5834433b9ebe65f31dd80a267 |
| codex/kis-key-access-diagnostics-20261020 | 48053636 | exact-aggregate-squash | Stable aggregate patch equals 4711b822240db446589fd10a8b83ecc7b5ae2aa1 |
| codex/kis-rosters-from-old-20261020 | 3baa0690 | exact-aggregate-squash | Stable aggregate patch equals f4e355bee3e6cfe53efea82d36da3f842c1501c6 |
| codex/kis-uat-hotfix-20261017 | 5c30beb8 | exact-aggregate-squash | Stable aggregate patch equals 99d6d633f049cc8d0c23289d09c20794e83cb74b |
| codex/kis-ux-20261017 | 010877b6 | exact-aggregate-squash | Stable aggregate patch equals 26b62e1654bba901b3647ba9f666f4e86ee97190 |
| codex/kis-web-key-storage-20261020 | 64db0b7b | exact-aggregate-squash | Stable aggregate patch equals 1d62b42326f0ce93fee92dd0c208f1584d99466f |
| codex/kunratice-fallback-hide-20261020 | 73b8acd5 | exact-aggregate-squash | Stable aggregate patch equals bfe633d21aa035ff4441f8916734e92965cb3ebf |
| codex/kunratice-prod-fix-20261020 | 2b5e3ece | exact-aggregate-squash | Stable aggregate patch equals 659aa7fafcb373b496d20831099bea7b4c785600 |
| codex/kunratice-source-fix-20261020 | e1eac078 | exact-aggregate-squash | Stable aggregate patch equals b62b2b9ff484f35b320d5d8a093d56956c7e0954 |
| codex/linux-browser-acceptance-20261024 | 420e78a0 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/membership-sports-r25-r30-20261023 | 4033bf18 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/migration-cutover-r38-r44-20261023 | 7aff7884 | earlier-stage-of-squash | Ancestor of codex/club-production-release-20261023 whose aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| codex/multi-child-course-20261023 | 7d6575ed | exact-aggregate-squash | Stable aggregate patch equals 43a825e01235e66baa1601c3bc957365df011009 |
| codex/order-cash-payment | b2220153 | exact-aggregate-squash | Stable aggregate patch equals f813264b16ffd49224bd41a908adc33d9687af88 |
| codex/order-cash-payment-browser-verification | 533a53b1 | exact-aggregate-squash | Stable aggregate patch equals e7e0d44d95f607e2dbd4d13ac7b2bcdf53b5046a |
| codex/order-cash-payment-deploy-handoff | 70f86cfd | exact-aggregate-squash | Stable aggregate patch equals 3395a07688114056700a83b868be8930b60ec47d |
| codex/order-payment-docs-closeout-20261021 | 3c935b29 | exact-aggregate-squash | Stable aggregate patch equals 45ada1356d950abce1313c646330f0be56bf239e |
| codex/order-status-release-proof-20261021 | 3a1f2b99 | exact-aggregate-squash | Stable aggregate patch equals 06c3b5a611b91f01001489671b7c6f27bade595d |
| codex/order-status-system-mail-20261021 | f68dc8a8 | exact-aggregate-squash | Stable aggregate patch equals 574328421fff748b48f7d744a3ce454b9b50dd1e |
| codex/packeta-module-audit-20261021 | acb191ff | exact-aggregate-squash | Stable aggregate patch equals b6b4251548fc6a99a4c654dc00a70589cc8d2386 |
| codex/packeta-readonly-checks-20261022 | 7a0dd592 | exact-aggregate-squash | Stable aggregate patch equals 68b22bd2b3b666dbb4fb571a2731eae5b7f056d9 |
| codex/packeta-release-proof-20261022 | 735d35c2 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/parent-browser-uat-20261020 | 474c1ffe | exact-aggregate-squash | Stable aggregate patch equals 6460d1087f71145a9b9eb80adb7282d83e78ea4f |
| codex/parent-journey | aacb2b78 | exact-aggregate-squash | Stable aggregate patch equals d6b10e70230f1e5497e0a59bcfaf217594523f4c |
| codex/parent-journey-accessories | 326cb710 | exact-aggregate-squash | Stable aggregate patch equals fd6ccc1584beb3a3266e8147497263cb8a38044f |
| codex/parent-journey-production-report | 3d2c971c | exact-aggregate-squash | Stable aggregate patch equals fe4b71e04248a28ac201bb53f612992a48f1bd09 |
| codex/payment-gates-php-cli-fix-20261020 | 64444a0a | exact-aggregate-squash | Stable aggregate patch equals 9ab78d9b5e17bb7a820f894d509d988eb1130c7c |
| codex/people-training-acceptance-20261023 | d794455f | exact-aggregate-squash | Stable aggregate patch equals ee6cff62f9b7628ec1dda7bb4c3571b7272a9b30 |
| codex/people-training-production-20261023 | 7f2d5e0a | exact-aggregate-squash | Stable aggregate patch equals 313cd61ba5111f0d88e158b5107bb1160cb0d374 |
| codex/people-training-production-proof-20261023 | d7d6b62a | exact-aggregate-squash | Stable aggregate patch equals 20af547c03462a185b7f1e4141d8cb6074d0495a |
| codex/people-training-release-proof-20261023 | 3d776e77 | exact-aggregate-squash | Stable aggregate patch equals dfb2b0e5180775750f5810ed4d5c88494c0974d4 |
| codex/person-life-documents-20261024 | f521a10d | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/ppl-box-checkout | d591d87c | exact-aggregate-squash | Stable aggregate patch equals 0f7098cb33c31091384b58f71860368d86fd099d |
| codex/private-storage-production-proof-20261023 | af466c82 | exact-aggregate-squash | Stable aggregate patch equals 6f574033a3b06ff3280542a6d268e087c54268be |
| codex/private-storage-web-probe-20261023 | a2f6d670 | exact-aggregate-squash | Stable aggregate patch equals fb2e00c72eaeb576b79fc413000278df8174b153 |
| codex/publish-shoptet-catalog | 1767e821 | exact-aggregate-squash | Stable aggregate patch equals 9de3ba9d1075be8f070950dab157771ba3ab359a |
| codex/pumptrack-archived-pricing-20261020 | 976ce485 | exact-aggregate-squash | Stable aggregate patch equals 5c6a8b3158e685b8ef5dd58dfd667f4e6b292b93 |
| codex/pumptrack-catalog-diagnostic-20261020 | 4c4e2a1e | exact-aggregate-squash | Stable aggregate patch equals d8b69b31440797b8f7e2a5e030eea0edcad5fa72 |
| codex/pumptrack-diagnostic-bootstrap-20261020 | 8e98204e | exact-aggregate-squash | Stable aggregate patch equals 0db907a7dea5baef5a0531ea66a6360b313aaea9 |
| codex/pumptrack-diagnostic-errors-20261020 | 4c4e2a1e | exact-aggregate-squash | Stable aggregate patch equals d8b69b31440797b8f7e2a5e030eea0edcad5fa72 |
| codex/pumptrack-goods-pricing-20261020 | 895f0186 | exact-aggregate-squash | Stable aggregate patch equals 792058af53336897190a6a55ecc629dcc1e56dcd |
| codex/quick-program-registration-fix-20261021 | 3f19f648 | exact-aggregate-squash | Stable aggregate patch equals e935950ed5be7181687dadba4b1fc2daf8dd5095 |
| codex/quick-registration-handoff-20261021 | 996aa426 | exact-aggregate-squash | Stable aggregate patch equals 8bf4056807a8ad1eb687a13943d2f680b2f21ec6 |
| codex/registration-text-titles-20261021 | 8c1560e1 | exact-aggregate-squash | Stable aggregate patch equals f5d6276838239ace6426aa8c326c01a213730a36 |
| codex/registration-texts-admin-20261021 | ac52b777 | exact-aggregate-squash | Stable aggregate patch equals 3c64dc530fa8025854f2cc8be07e74bdc1a51aab |
| codex/registration-texts-production-proof-20261021 | 46daf6d4 | exact-aggregate-squash | Stable aggregate patch equals 2d86a5c3cc3ca10a8554beb12992c431cd5a766e |
| codex/registration-titles-proof-20261021 | c1a72392 | exact-aggregate-squash | Stable aggregate patch equals 06e0a9b17217bf55c23e9507428ed12f48685b16 |
| codex/remaining-admin-forms-20261020 | 6054711b | exact-aggregate-squash | Stable aggregate patch equals 246dee2a4df5dc3152c1a769a9af128b87a91092 |
| codex/remaining-admin-forms-evidence-20261020 | b294166f | exact-aggregate-squash | Stable aggregate patch equals 1482cdbd888c1d1c4535769137f38a5728e17287 |
| codex/roster-form-help-20261020 | dce43d2b | exact-aggregate-squash | Stable aggregate patch equals 190fb0763119dfb676e16611c828331eecf01cc2 |
| codex/roster-hierarchy-20261020 | c3ae67f3 | exact-aggregate-squash | Stable aggregate patch equals 4991c91f966eb65852876fd0084dd6ad041e3b69 |
| codex/roster-release-proof-20261020 | 20f48d20 | exact-aggregate-squash | Stable aggregate patch equals 56dcd6ba7c9d4dc85d9fd5c24393be37dfdbd4ea |
| codex/season-operations-transition-20261024 | d14ee589 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| codex/security-audit-fixes-20261024 | 81f5adeb | exact-aggregate-squash | Stable aggregate patch equals 6bf57d6fe4c20fd9d9e182d3bde4253b1abc4a51 |
| codex/security-hardening-20261018 | 5d1a58d1 | exact-aggregate-squash | Stable aggregate patch equals 219995ed99ad0b693ac8443578240d5b464fe217 |
| codex/security-private-route-20261018 | 763c6357 | exact-aggregate-squash | Stable aggregate patch equals 452b060cae7a025b39e86648d3e7f63fd1cf7881 |
| codex/shop-number-series-20261021 | f6d008c1 | exact-aggregate-squash | Stable aggregate patch equals 1441683ae1ae2bb9da16d8be3af62ce40f35385c |
| codex/shop-number-series-release-proof-20261021 | 4913e322 | exact-aggregate-squash | Stable aggregate patch equals 886c07b2ce784ddc0beceaca1214ba640783aa8c |
| codex/shop-order-statuses-20261015 | 9faad6ef | exact-aggregate-squash | Stable aggregate patch equals d64141fc7a72a553507846a09ea81fa87125a5b0 |
| codex/shop-publication-accounting-guard-20261022 | 95faa736 | exact-aggregate-squash | Stable aggregate patch equals cc9c9280f137465eb59f20523d827b5502416e20 |
| codex/shop-publication-release-proof-20261022 | d2552858 | exact-aggregate-squash | Stable aggregate patch equals 3fdd1e16a6de7ec9323b27123ae9dc5b56dae16d |
| codex/shoptet-public-catalog-hotfix | c2c8b32e | exact-aggregate-squash | Stable aggregate patch equals 7fbaf9acd14d055aa81b318bdc5593a570fbbde7 |
| codex/shoptet-storefront-performance | f7baa69b | exact-aggregate-squash | Stable aggregate patch equals 2b6bc7998d2fb621dc3a694a3c3d5102a3def54b |
| codex/sibling-production-proof-20261023 | f16e757e | exact-aggregate-squash | Stable aggregate patch equals 4d7aacf84d75639ccf0c202c2b05b4d824d44347 |
| codex/stock-followup-20261024 | 802ba543 | exact-aggregate-squash | Stable aggregate patch equals 065ed2a6fb797e21928d0eb5d1d5b84af0c04d93 |
| codex/stock-production-audit-20261024 | fc366db1 | exact-aggregate-squash | Stable aggregate patch equals 283c46db7b5d31dda26ae2faef63da944715aedc |
| codex/stock-production-proof-20261024 | f4a25f52 | exact-aggregate-squash | Stable aggregate patch equals f2626b86b714ffd1ec90f572170dac839857dfcc |
| codex/stripe-disable-20261020 | ebe48894 | exact-aggregate-squash | Stable aggregate patch equals dffd4a063ef2f140bc5a1d3d1912f40b784c3bc4 |
| codex/tocnaci-prod-fix-20261020 | 09f93361 | exact-aggregate-squash | Stable aggregate patch equals 5bff9e05da239e324fd9c7da23b770c2b7f73b83 |
| codex/training-rsvp-notes-calendar-20261014 | c69c6c52 | exact-aggregate-squash | Stable aggregate patch equals c745afa5b502fa46884e09ce6308aac78d2cd9bb |
| codex/uat-capacity-search-ux | b95f6e43 | exact-aggregate-squash | Stable aggregate patch equals 42cbe6bba4b7bffd3d44410d5808fb99e5aa6d7f |
| codex/uat-fixes-20261016 | 2eee37f0 | exact-tree-main-history | git diff codex/uat-fixes-20261016 99d6d633 empty;12585e98 tree equals44a651fa. |
| codex/uat-fixture-restore-20261020 | e632ea52 | exact-aggregate-squash | Stable aggregate patch equals 1138f7a818a93a29476475639e76a70d1c173719 |
| codex/uat-makeup-readiness-20261016 | c1e096e1 | exact-aggregate-squash | Stable aggregate patch equals 3204d66b1ee6e025bd513ed3aa12fa4ec220e4c2 |
| codex/uat-order-cancel-20261020 | 264a34f7 | exact-aggregate-squash | Stable aggregate patch equals 7575a3e947a5e27fafc3a101fe39b92466db51c5 |
| codex/uat-order-guard-diagnostics-20261020 | 86d4aa7d | exact-aggregate-squash | Stable aggregate patch equals e04bf9b62636976e03b4dd72b97855e3f1f51d50 |
| codex/uat-paid-event-reopen | 659d43b3 | exact-aggregate-squash | Stable aggregate patch equals d041eec17b672843ef3e3f74d839da3a9a0d98fb |
| codex/uat-readiness-diagnostics | e6ad23b6 | exact-aggregate-squash | Stable aggregate patch equals bbc3dbf33c5cec175555c389202b50331d1fc1fd |
| codex/unified-admin-production-evidence-20261020 | 3b265059 | exact-aggregate-squash | Stable aggregate patch equals 42a66caba8c842369ef664714cc65d5b2a07b0fe |
| codex/unified-admin-shop-20261020 | 8cb1707a | exact-aggregate-squash | Stable aggregate patch equals 872ac1a471e4aece6963345eef3aa9ed4d8c4994 |
| codex/youngest-draft-groups-20261020 | e2dd6093 | exact-aggregate-squash | Stable aggregate patch equals e38af3b27f52a7cd2590f5835d4fa4c4f867e1c8 |
| codex/youngest-dryrun-team-20261020 | be4da468 | exact-aggregate-squash | Stable aggregate patch equals 2dc0f6b3d80ae4fe66407feb5b0c5be5b9117480 |
| codex/youngest-public-titles-20261020 | 7d297354 | exact-aggregate-squash | Stable aggregate patch equals 0db2a09229800bd037f3a30c937f8b087d980d01 |
| codex/youngest-wrapper-no-args-20261020 | 997d8b14 | exact-aggregate-squash | Stable aggregate patch equals 1545306542e0b9698dcc1fd923031eb559fa5e4e |
| main | 1b2d92e8 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| origin | 6bf57d6f | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| origin/codex/accounting-deploy-proof-20261023 | de613f30 | exact-aggregate-squash | Stable aggregate patch equals 0f8eddff8bc1ceba00624118304408b82907d7aa |
| origin/codex/accounting-followup-20261023 | 4208d65e | exact-aggregate-squash | Stable aggregate patch equals ebc3081cbee3a1b38f75bcc2989e4bf6800b1cb9 |
| origin/codex/accounting-production-audit-20261023 | b63fea70 | exact-aggregate-squash | Stable aggregate patch equals 59725050cf78927bfa6fb5cf71bef995038715d6 |
| origin/codex/admin-hero-contrast-20261020 | 64bde60b | exact-aggregate-squash | Stable aggregate patch equals 175f92c7a59294acff7e15fe09cbed258707fa2e |
| origin/codex/admin-shop-production-fixes-20261020 | 5bf4fb3f | exact-aggregate-squash | Stable aggregate patch equals f47f78bfacacd8aa6dfcfd9a36eaf93be3d07d4e |
| origin/codex/athlete-editor-20261020 | da6f1186 | exact-aggregate-squash | Stable aggregate patch equals 7c0d5ce7e931b43018a5b1cffeda8b89fde404c5 |
| origin/codex/athlete-editor-relations-20261020 | 80ad21b3 | exact-aggregate-squash | Stable aggregate patch equals 6a1c878922b45782490066478f1008f8ead1ccf9 |
| origin/codex/athlete-financial-balance | 5805de67 | exact-aggregate-squash | Stable aggregate patch equals fd3251720c8d8ee337d79b9e830e11315724413f |
| origin/codex/browser-acceptance-fixes-20261023 | 653758dc | exact-aggregate-squash | Stable aggregate patch equals e78af565d6d89492e54f0b9e2ecb2b63a4b534c7 |
| origin/codex/browser-acceptance-release-proof-20261023 | 0f546c6a | exact-aggregate-squash | Stable aggregate patch equals dc75dcc55f115a883a1c3cf621f4bc3162253cab |
| origin/codex/cash-refund-production-test-20261021 | d113a09a | exact-aggregate-squash | Stable aggregate patch equals a0e2bac12cf6818120f2dd5515499bfe5d7dc601 |
| origin/codex/club-catalog-unification-20261020 | cddf6cfa | exact-aggregate-squash | Stable aggregate patch equals 2439a1b4afea842546f042b6b11407a5b4bb56fa |
| origin/codex/club-goods-alias-20261020 | 687acb83 | exact-aggregate-squash | Stable aggregate patch equals fbeccaf32fe6a9cd4a4fb6d77bed3287fb085283 |
| origin/codex/club-live-verification-20261023 | 5c072241 | exact-aggregate-squash | Stable aggregate patch equals 0b03b564dfa6b20817478848fa8721fea09ca082 |
| origin/codex/club-production-proof-20261023 | 43f2ab1c | exact-aggregate-squash | Stable aggregate patch equals ab7d67d924451bc99876b76da7f3ec334579188b |
| origin/codex/club-production-release-20261023 | 1e3c81f7 | exact-aggregate-squash | Stable aggregate patch equals 57c9e65ad1c00feb06916d7bdbb8fece058cd316 |
| origin/codex/club-production-report-20261023 | 16d78c5d | exact-aggregate-squash | Stable aggregate patch equals a159df09869d8c28755fb9ee9e635b057863e132 |
| origin/codex/club-targets-diagnostic-20261020 | a3a9af1f | exact-aggregate-squash | Stable aggregate patch equals cb202b7509a5ed3451caf15d8afce65281f9aed1 |
| origin/codex/custom-registration-consents-20261021 | 557c260f | exact-aggregate-squash | Stable aggregate patch equals 61ed73854b57ff44a6b3bf56fb3b5401c8ae7941 |
| origin/codex/family-course-production-proof-20261023 | 4c27e6e0 | exact-aggregate-squash | Stable aggregate patch equals b3f179c185d1cdf1e1b95101adf1392a8523c4d4 |
| origin/codex/family-course-uat-20261023 | 43168b11 | exact-aggregate-squash | Stable aggregate patch equals c6e3328be0567a1ea4b242f18eb6d66c4e1e9202 |
| origin/codex/family-followup-20261023 | eb18acd0 | exact-aggregate-squash | Stable aggregate patch equals c9724de9c100766f4a6f4c90fb69abfacbb8c8c8 |
| origin/codex/finance-migration-checksum | 10de26e6 | exact-aggregate-squash | Stable aggregate patch equals 90067fa6434afddc9483bf9d9ace589df2cf0bbf |
| origin/codex/fio-account-switch-20261020 | 2a4630f3 | closed-unmerged-excluded | GitHub PR109 CLOSED 2026-10-20, mergedAt null; unique manual one-off account identification diagnostic, excluded from completed accepted rollout. |
| origin/codex/fio-auto-account-snapshot | 513eea85 | exact-aggregate-squash | Stable aggregate patch equals d06f6ac132c935e46d8deff6f081781a9f633fba |
| origin/codex/fio-booking-date-offset | 6b0eda2e | exact-aggregate-squash | Stable aggregate patch equals acdbbd38e9910212d838d0c05d4fd3535041ebd7 |
| origin/codex/fio-config-legacy-guard | 70b9b8c7 | exact-aggregate-squash | Stable aggregate patch equals ca653bb5b04d993bbe59aba6b9ca069b790a851f |
| origin/codex/fio-date-diagnostic-args | 1147c4b9 | exact-aggregate-squash | Stable aggregate patch equals d0e01a539f4ffd077eb26cb7dcf4d791da1f68e0 |
| origin/codex/fio-date-diagnostic-token | 72205910 | exact-aggregate-squash | Stable aggregate patch equals e23aa3e81a9dfeab219101aac1b90a5e21824fef |
| origin/codex/fio-live-date-diagnostic | a9d33e80 | exact-aggregate-squash | Stable aggregate patch equals 4ea278a305317552bde79211e6fb287caac7638f |
| origin/codex/fio-reconciliation-recovery-main | 218ea64f | exact-aggregate-squash | Stable aggregate patch equals c4f5d92fe6aa56df51ec886af01ee0b4385a18b9 |
| origin/codex/fio-scheduled-import-gated | c2f84a9a | exact-aggregate-squash | Stable aggregate patch equals 7b7422f0a0e4b2a0ea7b33a16805a96c5f832599 |
| origin/codex/fio-token-account-diagnostic | 2f8e4478 | exact-aggregate-squash | Stable aggregate patch equals 5ca02abbd887152eb22be2c08607e1c7da21f003 |
| origin/codex/identity-flow-ux-20261019 | bf954d96 | exact-aggregate-squash | Stable aggregate patch equals 7e29bdcd304d53db99fbea0fbd741019020d6586 |
| origin/codex/kis-current-rosters-20261020 | 03609727 | exact-aggregate-squash | Stable aggregate patch equals c29b91c7eb307d5fe33f6fe12f67c3b8bfc0bff1 |
| origin/codex/kis-full-person-import-20261020 | 6d5490d9 | exact-aggregate-squash | Stable aggregate patch equals 313cb9aa5aea37267f796dd63e070dab8a3d92ca |
| origin/codex/kis-import-payment-history-20261020 | dd9433b0 | exact-aggregate-squash | Stable aggregate patch equals daccea3575e2f11195b1bda916832c7d81ec635c |
| origin/codex/kis-import-private-key-20261020 | 045f435f | exact-aggregate-squash | Stable aggregate patch equals 899f1a1f8be1580106bd03f02fd70608c3054a2e |
| origin/codex/kis-import-text-transfer-20261020 | 30ec6eb6 | exact-aggregate-squash | Stable aggregate patch equals a4232fd78fcea1135bcbfabbf5bd7cca3cfb2e67 |
| origin/codex/kis-jersey-branding | da8ef9c7 | exact-aggregate-squash | Stable aggregate patch equals 276a5969314c7ae5834433b9ebe65f31dd80a267 |
| origin/codex/kis-key-access-diagnostics-20261020 | 48053636 | exact-aggregate-squash | Stable aggregate patch equals 4711b822240db446589fd10a8b83ecc7b5ae2aa1 |
| origin/codex/kis-uat-hotfix-20261017 | 5c30beb8 | exact-aggregate-squash | Stable aggregate patch equals 99d6d633f049cc8d0c23289d09c20794e83cb74b |
| origin/codex/kis-ux-20261017 | 010877b6 | exact-aggregate-squash | Stable aggregate patch equals 26b62e1654bba901b3647ba9f666f4e86ee97190 |
| origin/codex/kis-web-key-storage-20261020 | 64db0b7b | exact-aggregate-squash | Stable aggregate patch equals 1d62b42326f0ce93fee92dd0c208f1584d99466f |
| origin/codex/kunratice-prod-fix-20261020 | 2b5e3ece | exact-aggregate-squash | Stable aggregate patch equals 659aa7fafcb373b496d20831099bea7b4c785600 |
| origin/codex/kunratice-source-fix-20261020 | e1eac078 | exact-aggregate-squash | Stable aggregate patch equals b62b2b9ff484f35b320d5d8a093d56956c7e0954 |
| origin/codex/linux-browser-acceptance-20261024 | a0fcfbc8 | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |
| origin/codex/multi-child-course-20261023 | 7d6575ed | exact-aggregate-squash | Stable aggregate patch equals 43a825e01235e66baa1601c3bc957365df011009 |
| origin/codex/order-cash-payment | b2220153 | exact-aggregate-squash | Stable aggregate patch equals f813264b16ffd49224bd41a908adc33d9687af88 |
| origin/codex/order-cash-payment-browser-verification | 533a53b1 | exact-aggregate-squash | Stable aggregate patch equals e7e0d44d95f607e2dbd4d13ac7b2bcdf53b5046a |
| origin/codex/order-cash-payment-deploy-handoff | 70f86cfd | exact-aggregate-squash | Stable aggregate patch equals 3395a07688114056700a83b868be8930b60ec47d |
| origin/codex/order-payment-docs-closeout-20261021 | 3c935b29 | exact-aggregate-squash | Stable aggregate patch equals 45ada1356d950abce1313c646330f0be56bf239e |
| origin/codex/order-status-release-proof-20261021 | 3a1f2b99 | exact-aggregate-squash | Stable aggregate patch equals 06c3b5a611b91f01001489671b7c6f27bade595d |
| origin/codex/order-status-system-mail-20261021 | f68dc8a8 | exact-aggregate-squash | Stable aggregate patch equals 574328421fff748b48f7d744a3ce454b9b50dd1e |
| origin/codex/packeta-module-audit-20261021 | acb191ff | exact-aggregate-squash | Stable aggregate patch equals b6b4251548fc6a99a4c654dc00a70589cc8d2386 |
| origin/codex/packeta-readonly-checks-20261022 | 7a0dd592 | exact-aggregate-squash | Stable aggregate patch equals 68b22bd2b3b666dbb4fb571a2731eae5b7f056d9 |
| origin/codex/packeta-release-proof-20261022 | c0d7bc04 | exact-aggregate-squash | Stable aggregate patch equals 735d35c282df602337d97c535fc0d896880ae747 |
| origin/codex/parent-browser-uat-20261020 | 474c1ffe | exact-aggregate-squash | Stable aggregate patch equals 6460d1087f71145a9b9eb80adb7282d83e78ea4f |
| origin/codex/parent-journey | aacb2b78 | exact-aggregate-squash | Stable aggregate patch equals d6b10e70230f1e5497e0a59bcfaf217594523f4c |
| origin/codex/parent-journey-accessories | 326cb710 | exact-aggregate-squash | Stable aggregate patch equals fd6ccc1584beb3a3266e8147497263cb8a38044f |
| origin/codex/parent-journey-production-report | 3d2c971c | exact-aggregate-squash | Stable aggregate patch equals fe4b71e04248a28ac201bb53f612992a48f1bd09 |
| origin/codex/people-training-acceptance-20261023 | d794455f | exact-aggregate-squash | Stable aggregate patch equals ee6cff62f9b7628ec1dda7bb4c3571b7272a9b30 |
| origin/codex/people-training-production-20261023 | 7f2d5e0a | exact-aggregate-squash | Stable aggregate patch equals 313cd61ba5111f0d88e158b5107bb1160cb0d374 |
| origin/codex/people-training-production-proof-20261023 | d7d6b62a | exact-aggregate-squash | Stable aggregate patch equals 20af547c03462a185b7f1e4141d8cb6074d0495a |
| origin/codex/people-training-release-proof-20261023 | 3d776e77 | exact-aggregate-squash | Stable aggregate patch equals dfb2b0e5180775750f5810ed4d5c88494c0974d4 |
| origin/codex/ppl-box-checkout | d591d87c | exact-aggregate-squash | Stable aggregate patch equals 0f7098cb33c31091384b58f71860368d86fd099d |
| origin/codex/private-storage-production-proof-20261023 | af466c82 | exact-aggregate-squash | Stable aggregate patch equals 6f574033a3b06ff3280542a6d268e087c54268be |
| origin/codex/private-storage-web-probe-20261023 | a2f6d670 | exact-aggregate-squash | Stable aggregate patch equals fb2e00c72eaeb576b79fc413000278df8174b153 |
| origin/codex/publish-shoptet-catalog | 1767e821 | exact-aggregate-squash | Stable aggregate patch equals 9de3ba9d1075be8f070950dab157771ba3ab359a |
| origin/codex/pumptrack-archived-pricing-20261020 | 976ce485 | exact-aggregate-squash | Stable aggregate patch equals 5c6a8b3158e685b8ef5dd58dfd667f4e6b292b93 |
| origin/codex/pumptrack-catalog-diagnostic-20261020 | 4bdf0c1c | exact-aggregate-squash | Stable aggregate patch equals fefdd8114b6fa37db5191e66cb0cf99e82b7cebc |
| origin/codex/pumptrack-diagnostic-bootstrap-20261020 | 8e98204e | exact-aggregate-squash | Stable aggregate patch equals 0db907a7dea5baef5a0531ea66a6360b313aaea9 |
| origin/codex/pumptrack-diagnostic-errors-20261020 | 4c4e2a1e | exact-aggregate-squash | Stable aggregate patch equals d8b69b31440797b8f7e2a5e030eea0edcad5fa72 |
| origin/codex/pumptrack-goods-pricing-20261020 | 895f0186 | exact-aggregate-squash | Stable aggregate patch equals 792058af53336897190a6a55ecc629dcc1e56dcd |
| origin/codex/quick-program-registration-fix-20261021 | 3f19f648 | exact-aggregate-squash | Stable aggregate patch equals e935950ed5be7181687dadba4b1fc2daf8dd5095 |
| origin/codex/quick-registration-handoff-20261021 | 996aa426 | exact-aggregate-squash | Stable aggregate patch equals 8bf4056807a8ad1eb687a13943d2f680b2f21ec6 |
| origin/codex/registration-text-titles-20261021 | 8c1560e1 | exact-aggregate-squash | Stable aggregate patch equals f5d6276838239ace6426aa8c326c01a213730a36 |
| origin/codex/registration-texts-admin-20261021 | ac52b777 | exact-aggregate-squash | Stable aggregate patch equals 3c64dc530fa8025854f2cc8be07e74bdc1a51aab |
| origin/codex/registration-texts-production-proof-20261021 | 46daf6d4 | exact-aggregate-squash | Stable aggregate patch equals 2d86a5c3cc3ca10a8554beb12992c431cd5a766e |
| origin/codex/registration-titles-proof-20261021 | c1a72392 | exact-aggregate-squash | Stable aggregate patch equals 06e0a9b17217bf55c23e9507428ed12f48685b16 |
| origin/codex/remaining-admin-forms-20261020 | 6054711b | exact-aggregate-squash | Stable aggregate patch equals 246dee2a4df5dc3152c1a769a9af128b87a91092 |
| origin/codex/remaining-admin-forms-evidence-20261020 | b294166f | exact-aggregate-squash | Stable aggregate patch equals 1482cdbd888c1d1c4535769137f38a5728e17287 |
| origin/codex/roster-form-help-20261020 | dce43d2b | exact-aggregate-squash | Stable aggregate patch equals 190fb0763119dfb676e16611c828331eecf01cc2 |
| origin/codex/roster-hierarchy-20261020 | c3ae67f3 | exact-aggregate-squash | Stable aggregate patch equals 4991c91f966eb65852876fd0084dd6ad041e3b69 |
| origin/codex/roster-release-proof-20261020 | 20f48d20 | exact-aggregate-squash | Stable aggregate patch equals 56dcd6ba7c9d4dc85d9fd5c24393be37dfdbd4ea |
| origin/codex/security-audit-fixes-20261024 | 81f5adeb | exact-aggregate-squash | Stable aggregate patch equals 6bf57d6fe4c20fd9d9e182d3bde4253b1abc4a51 |
| origin/codex/security-hardening-20261018 | 5d1a58d1 | exact-aggregate-squash | Stable aggregate patch equals 219995ed99ad0b693ac8443578240d5b464fe217 |
| origin/codex/security-private-route-20261018 | 763c6357 | exact-aggregate-squash | Stable aggregate patch equals 452b060cae7a025b39e86648d3e7f63fd1cf7881 |
| origin/codex/shop-number-series-20261021 | f6d008c1 | exact-aggregate-squash | Stable aggregate patch equals 1441683ae1ae2bb9da16d8be3af62ce40f35385c |
| origin/codex/shop-number-series-release-proof-20261021 | 4913e322 | exact-aggregate-squash | Stable aggregate patch equals 886c07b2ce784ddc0beceaca1214ba640783aa8c |
| origin/codex/shop-order-statuses-20261015 | 9faad6ef | exact-aggregate-squash | Stable aggregate patch equals d64141fc7a72a553507846a09ea81fa87125a5b0 |
| origin/codex/shop-publication-accounting-guard-20261022 | 95faa736 | exact-aggregate-squash | Stable aggregate patch equals cc9c9280f137465eb59f20523d827b5502416e20 |
| origin/codex/shop-publication-release-proof-20261022 | d2552858 | exact-aggregate-squash | Stable aggregate patch equals 3fdd1e16a6de7ec9323b27123ae9dc5b56dae16d |
| origin/codex/shoptet-public-catalog-hotfix | c2c8b32e | exact-aggregate-squash | Stable aggregate patch equals 7fbaf9acd14d055aa81b318bdc5593a570fbbde7 |
| origin/codex/shoptet-storefront-performance | f7baa69b | exact-aggregate-squash | Stable aggregate patch equals 2b6bc7998d2fb621dc3a694a3c3d5102a3def54b |
| origin/codex/sibling-production-proof-20261023 | f16e757e | exact-aggregate-squash | Stable aggregate patch equals 4d7aacf84d75639ccf0c202c2b05b4d824d44347 |
| origin/codex/stock-followup-20261024 | 802ba543 | exact-aggregate-squash | Stable aggregate patch equals 065ed2a6fb797e21928d0eb5d1d5b84af0c04d93 |
| origin/codex/stock-production-audit-20261024 | fc366db1 | exact-aggregate-squash | Stable aggregate patch equals 283c46db7b5d31dda26ae2faef63da944715aedc |
| origin/codex/stock-production-proof-20261024 | f4a25f52 | exact-aggregate-squash | Stable aggregate patch equals f2626b86b714ffd1ec90f572170dac839857dfcc |
| origin/codex/tocnaci-prod-fix-20261020 | 09f93361 | exact-aggregate-squash | Stable aggregate patch equals 5bff9e05da239e324fd9c7da23b770c2b7f73b83 |
| origin/codex/training-rsvp-notes-calendar-20261014 | c69c6c52 | exact-aggregate-squash | Stable aggregate patch equals c745afa5b502fa46884e09ce6308aac78d2cd9bb |
| origin/codex/uat-capacity-search-ux | b95f6e43 | exact-aggregate-squash | Stable aggregate patch equals 42cbe6bba4b7bffd3d44410d5808fb99e5aa6d7f |
| origin/codex/uat-fixes-20261016 | 12585e98 | exact-aggregate-squash | Stable aggregate patch equals 44a651fa5a49f6f5e8973fa938f0e66e1b1eb897 |
| origin/codex/uat-makeup-readiness-20261016 | c1e096e1 | exact-aggregate-squash | Stable aggregate patch equals 3204d66b1ee6e025bd513ed3aa12fa4ec220e4c2 |
| origin/codex/uat-order-cancel-20261020 | 264a34f7 | exact-aggregate-squash | Stable aggregate patch equals 7575a3e947a5e27fafc3a101fe39b92466db51c5 |
| origin/codex/uat-order-guard-diagnostics-20261020 | 86d4aa7d | exact-aggregate-squash | Stable aggregate patch equals e04bf9b62636976e03b4dd72b97855e3f1f51d50 |
| origin/codex/uat-paid-event-reopen | 659d43b3 | exact-aggregate-squash | Stable aggregate patch equals d041eec17b672843ef3e3f74d839da3a9a0d98fb |
| origin/codex/uat-readiness-diagnostics | e6ad23b6 | exact-aggregate-squash | Stable aggregate patch equals bbc3dbf33c5cec175555c389202b50331d1fc1fd |
| origin/codex/unified-admin-production-evidence-20261020 | 3b265059 | exact-aggregate-squash | Stable aggregate patch equals 42a66caba8c842369ef664714cc65d5b2a07b0fe |
| origin/codex/unified-admin-shop-20261020 | 8cb1707a | exact-aggregate-squash | Stable aggregate patch equals 872ac1a471e4aece6963345eef3aa9ed4d8c4994 |
| origin/main | 6bf57d6f | ancestry-contained | Ancestor of origin/main6bf57d6f or accepted club420e78a0 |

## Specific exclusions

- Fio d1221f35 is a superseded version of84233b99; range-diff shows migration name/ID and corresponding references changed from20261020120000 to20261020170000. The canonical reconciliation branch aggregate equals c4f5d92f (#102), already on main. Reintroducing the old commit would add a duplicate historical migration.
- The unique2a4630f3 manual account identification patch remains on `codex/fio-account-switch-20261020`. GitHub PR109 is CLOSED and unmerged (2026-10-20T12:07:16Z); no review/comment declares reinstatement. Preserve branch, exclude from accepted common release. https://github.com/KovoPraha/evidenceTreninku/pull/109
- Security branch81f5adeb has an exactly identical full tree to origin/main6bf57d6f; it requires no extra cherry-pick.
- Existing root worktree has modified `kis_rosters_admin.php`. This audit does not alter it and does not declare it completed.
