<?php
declare(strict_types=1);

require_once __DIR__ . '/kis_import_field_contract.php';

const MEMBER_CHARGE_CONTRACT = 'member-charge-v1';
const MEMBER_CHARGE_PAYABLE_TYPE = 'member_charge';

/** @return array<string,mixed> */
function memberChargeTargetContract(): array
{
    return [
        'contract' => MEMBER_CHARGE_CONTRACT,
        'payable_type' => MEMBER_CHARGE_PAYABLE_TYPE,
        'statuses' => ['draft', 'pending', 'paid', 'cancelled', 'waived'],
        'source_systems' => ['manual', 'kis_import', 'program', 'event', 'membership'],
        'rules' => [
            'beneficiary_required' => true,
            'payer_account_optional' => true,
            'amount_minor_integer' => true,
            'currency_iso_4217' => true,
            'payment_separate' => true,
            'source_idempotent' => true,
        ],
    ];
}

function memberChargeNormalizeCurrency(mixed $currency): string
{
    $currency = strtoupper(trim((string)$currency));
    if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) throw new InvalidArgumentException('Mena predpisu musi byt ISO kod.');
    return $currency;
}

function memberChargeValidateStatus(mixed $status): string
{
    $status = strtolower(trim((string)$status));
    if (!in_array($status, memberChargeTargetContract()['statuses'], true)) throw new InvalidArgumentException('Neplatny stav clenskeho predpisu.');
    return $status;
}

/** @return array<string,mixed> */
function memberChargeProjection(array $row): array
{
    $externalId = kisFieldNormalizeExternalId($row['payment_external_id'] ?? '');
    if ($externalId === '') throw new InvalidArgumentException('Clensky predpis nema stabilni zdrojove ID.');
    $amountMinor = filter_var($row['amount_minor'] ?? null, FILTER_VALIDATE_INT);
    $outstandingMinor = filter_var($row['outstanding_minor'] ?? null, FILTER_VALIDATE_INT);
    if ($amountMinor === false || $amountMinor < 0 || $outstandingMinor === false || $outstandingMinor < 0 || $outstandingMinor > $amountMinor) {
        throw new InvalidArgumentException('Castky clenskeho predpisu nejsou konzistentni.');
    }
    $status = memberChargeValidateStatus($row['status'] ?? '');
    if ($status === 'paid' && $outstandingMinor !== 0) throw new InvalidArgumentException('Uhrazeny predpis nesmi mit zustatek.');
    return [
        'source_system' => 'kis_import',
        'source_external_id' => $externalId,
        'status' => $status,
        'amount_minor' => $amountMinor,
        'outstanding_minor' => $outstandingMinor,
        'currency' => memberChargeNormalizeCurrency($row['currency'] ?? ''),
        'due_on' => ($row['due_on'] ?? null) ?: null,
        'paid_on' => ($row['paid_on'] ?? null) ?: null,
    ];
}
