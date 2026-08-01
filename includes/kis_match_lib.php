<?php
declare(strict_types=1);

function kisMatchNormalizeText(?string $value): string
{
    $s = trim((string)$value);
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = (string)preg_replace('/\s+/u', ' ', $s);
    if (function_exists('kis_deaccent')) {
        $s = kis_deaccent($s);
    } else {
        $map = [
            'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
            'Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z',
        ];
        $s = strtr($s, $map);
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = (string)preg_replace('/[^a-z0-9@. _|+-]/', '', $s);
    return trim($s);
}

function kisMatchPersonKey(array $person): string
{
    $first = kisMatchNormalizeText((string)($person['jmeno'] ?? ''));
    $last = kisMatchNormalizeText((string)($person['prijmeni'] ?? ''));
    $birth = trim((string)($person['narozeni'] ?? ''));
    return $first . '|' . $last . '|' . $birth;
}

function kisMatchFindCandidates(PDO $pdo, array $person): array
{
    $jmeno = kisMatchNormalizeText((string)($person['jmeno'] ?? ''));
    $prijmeni = kisMatchNormalizeText((string)($person['prijmeni'] ?? ''));
    $narozeni = trim((string)($person['narozeni'] ?? ''));
    $email = kisMatchNormalizeText((string)($person['email'] ?? ''));
    $uciid = kisMatchNormalizeText((string)($person['uciid'] ?? ''));

    if ($uciid === '' && $email === '' && ($jmeno === '' || $prijmeni === '')) {
        return [];
    }

    // Snímek tabulky se načte jen jednou za request (O(N) místo O(N²) při importu 1500 osob).
    // Bonus: čerstvě vložené osoby ve stejném běhu nejsou v cache → dva různí lidé se stejným
    // jménem (otec/syn) se nesloučí do jednoho záznamu.
    /** @var WeakMap<PDO, array<int, array<string, mixed>>>|null $rowsByPdo */
    static $rowsByPdo = null;
    if (!$rowsByPdo instanceof WeakMap) {
        $rowsByPdo = new WeakMap();
    }
    if (!$rowsByPdo->offsetExists($pdo)) {
        $rows = [];
        foreach ($pdo->query("SELECT * FROM sportovci")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['id']] = $row;
        }
        $rowsByPdo[$pdo] = $rows;
    }

    $candidates = [];
    foreach ($rowsByPdo[$pdo] as $row) {
        $score = 0;
        $reasons = [];
        if ($uciid !== '' && kisMatchNormalizeText((string)($row['uciid'] ?? '')) === $uciid) {
            $score += 95;
            $reasons[] = 'UCI ID';
        }
        if ($email !== '' && kisMatchNormalizeText((string)($row['email'] ?? '')) === $email) {
            $score += 85;
            $reasons[] = 'email';
        }
        $rowFirst = kisMatchNormalizeText((string)($row['first_name_norm'] ?? $row['jmeno'] ?? ''));
        $rowLast = kisMatchNormalizeText((string)($row['last_name_norm'] ?? $row['prijmeni'] ?? ''));
        if ($jmeno !== '' && $prijmeni !== '' && $rowFirst === $jmeno && $rowLast === $prijmeni) {
            $score += 60;
            $reasons[] = 'jmeno+prijmeni';
            if ($narozeni !== '' && (string)($row['narozeni'] ?? '') === $narozeni) {
                $score += 35;
                $reasons[] = 'datum narozeni';
            }
        }
        if (
            $jmeno !== ''
            && $prijmeni !== ''
            && $rowFirst === $jmeno
            && $rowLast === $prijmeni
            && $narozeni !== ''
            && trim((string)($row['narozeni'] ?? '')) !== ''
            && (string)$row['narozeni'] !== $narozeni
        ) {
            $reasons[] = 'datum narozeni se lisi';
        }
        $matchScore = min(100, $score);
        if ($matchScore > 0) {
            // Kandidátní payload se ukládá do kis_import_matches. Nikdy do něj
            // neposílej celý řádek sportovce s kontakty, adresou nebo rodným číslem.
            $candidates[] = [
                'id' => (int)$row['id'],
                'jmeno' => (string)($row['jmeno'] ?? ''),
                'prijmeni' => (string)($row['prijmeni'] ?? ''),
                'narozeni' => $row['narozeni'] ?? null,
                'uciid' => (string)($row['uciid'] ?? ''),
                '_match_score' => $matchScore,
                '_match_reason' => implode(', ', $reasons),
            ];
        }
    }

    usort($candidates, static fn(array $a, array $b): int => ($b['_match_score'] ?? 0) <=> ($a['_match_score'] ?? 0));
    return $candidates;
}

function kisMatchResolve(PDO $pdo, array $person): array
{
    $candidates = kisMatchFindCandidates($pdo, $person);
    if (!$candidates) {
        return [
            'status' => 'new',
            'sportovec_id' => null,
            'confidence' => 0,
            'reason' => 'Nenalezena shoda',
            'candidates' => [],
        ];
    }

    $best = $candidates[0];
    $bestScore = (int)($best['_match_score'] ?? 0);
    $secondScore = isset($candidates[1]) ? (int)($candidates[1]['_match_score'] ?? 0) : 0;
    $bestReason = (string)($best['_match_reason'] ?? '');
    if (str_contains($bestReason, 'datum narozeni se lisi')) {
        return [
            'status' => 'conflict',
            'sportovec_id' => null,
            'confidence' => $bestScore,
            'reason' => 'Datum narozeni se lisi, vyzaduje rucni potvrzeni',
            'candidates' => array_slice($candidates, 0, 5),
        ];
    }
    if ($bestScore >= 90 && ($bestScore - $secondScore) >= 10) {
        return [
            'status' => 'matched',
            'sportovec_id' => (int)$best['id'],
            'confidence' => $bestScore,
            'reason' => $bestReason ?: 'silna shoda',
            'candidates' => array_slice($candidates, 0, 5),
        ];
    }
    return [
        'status' => count($candidates) > 1 ? 'ambiguous' : 'conflict',
        'sportovec_id' => null,
        'confidence' => $bestScore,
        'reason' => 'Vyžaduje ruční potvrzení',
        'candidates' => array_slice($candidates, 0, 5),
    ];
}

function kisMatchJson(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}
