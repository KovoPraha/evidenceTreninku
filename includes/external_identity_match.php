<?php

declare(strict_types=1);

/** Never converts an identifier to a number, or repairs punctuation. */
function externalIdentityUci(?string $raw): array
{
    $value = str_replace([' ', "\u{00A0}"], '', $raw ?? '');
    if ($value === '') {
        return ['uci' => null, 'state' => 'missing_uci'];
    }
    return preg_match('/^[0-9]{11}$/D', $value)
        ? ['uci' => $value, 'state' => 'valid']
        : ['uci' => null, 'state' => 'invalid_uci'];
}

function externalIdentityDate(?string $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $raw, $m)) {
        [$year, $month, $day] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^([0-9]{1,2})\. *([0-9]{1,2})\. *([0-9]{4})$/D', $raw, $m)) {
        [$year, $month, $day] = [(int)$m[3], (int)$m[2], (int)$m[1]];
    } else {
        return null;
    }
    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
}

/** Token multiset permits surname-first displays, never fuzzy/partial matching. */
function externalIdentityName(?string $value): array
{
    $value = mb_strtolower($value ?? '', 'UTF-8');
    $value = strtr($value, array_combine(
        preg_split('//u', 'áčďéěíňóřšťúůýžäöüĺľŕ', -1, PREG_SPLIT_NO_EMPTY),
        str_split('acdeeinorstuuyzaoullr')
    ));
    $parts = preg_split('/[\s\x{00A0}]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    sort($parts, SORT_STRING);
    return $parts;
}

/** Pure resolver: caller supplies CURRENT canonical candidates, never fallback names. */
function externalIdentityResolve(array $row, array $people, ?array $previous = null): array
{
    $identity = externalIdentityUci($row['uci_id_raw'] ?? null);
    $result = ['status' => $identity['state'], 'sportovec_id' => null, 'uci_id' => $identity['uci'], 'candidate_ids' => []];
    if ($identity['uci'] === null) {
        return $result;
    }
    $matches = [];
    foreach ($people as $person) {
        if (externalIdentityUci($person['uciid'])['uci'] === $identity['uci']) {
            $matches[(int)$person['id']] = $person;
        }
    }
    $result['candidate_ids'] = array_keys($matches);
    if ($previous !== null) {
        $priorPerson = array_column($people, null, 'id')[(int)$previous['sportovec_id']] ?? null;
        if ($previous['uci_id'] !== $identity['uci'] || $priorPerson === null
            || externalIdentityUci($priorPerson['uciid'])['uci'] !== $identity['uci']) {
            $result['status'] = 'identity_conflict';
            return $result;
        }
    }
    if (count($matches) !== 1) {
        $result['status'] = $matches === [] ? 'unmatched' : 'duplicate_kis_uci';
        return $result;
    }
    $person = reset($matches);
    $name = externalIdentityName($row['display_name'] ?? null);
    $canonicalName = externalIdentityName(($person['jmeno'] ?? '') . ' ' . ($person['prijmeni'] ?? ''));
    $birthRaw = $row['birth_date_raw'] ?? null;
    $birth = externalIdentityDate($birthRaw);
    $canonicalBirth = externalIdentityDate($person['narozeni'] ?? null);
    $conflict = ($name !== [] && $canonicalName !== [] && $name !== $canonicalName)
        || ($birthRaw !== null && $birthRaw !== '' && $birth === null)
        || ($birth !== null && $canonicalBirth !== null && $birth !== $canonicalBirth)
        || ($previous !== null && ((int)$previous['sportovec_id'] !== (int)$person['id']
            || $previous['uci_id'] !== $identity['uci']));
    $result['status'] = $conflict ? 'identity_conflict' : 'matched';
    $result['sportovec_id'] = $conflict ? null : (int)$person['id'];
    return $result;
}
