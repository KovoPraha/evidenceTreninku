<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';
require_once dirname(__DIR__) . '/private_storage.php';

function barDocumentMinor(mixed $value): int
{
    if (!is_string($value) && !is_int($value)) {
        throw new InvalidArgumentException('Neplatná částka dokladu.');
    }
    $value = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], trim((string)$value));
    if (!preg_match('/^(-?)(\d{1,7})(?:\.(\d{1,2}))?$/D', $value, $m)) {
        throw new InvalidArgumentException('Částka musí mít nejvýše dvě desetinná místa.');
    }
    $minor = (int)$m[2] * 100 + (int)str_pad($m[3] ?? '', 2, '0');
    return ($m[1] === '-' ? -1 : 1) * barCheckMinor($minor);
}

function barDocumentDate(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Neplatné datum dokladu.');
    }
    return $date;
}

/** One bounded form payload avoids PHP max_input_vars truncating a 200-line invoice. */
function barInvoiceFormData(array $post): array
{
    $raw = $post;
    if (isset($post['document_json'])) {
        if (!is_string($post['document_json']) || strlen($post['document_json']) > 500000) {
            throw new InvalidArgumentException('Formulář faktury je příliš velký.');
        }
        try {
            $raw = json_decode($post['document_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Formulář faktury není úplný. Obnovte stránku.');
        }
        if (!is_array($raw) || array_is_list($raw)) {
            throw new InvalidArgumentException('Neplatný formulář faktury.');
        }
    }
    $input = $raw;
    foreach (['net', 'vat', 'gross', 'rounding', 'payable'] as $key) {
        $input[$key . '_minor'] = barDocumentMinor($raw[$key] ?? null);
    }
    if (!is_array($raw['rows'] ?? null) || count($raw['rows']) > 201) {
        throw new InvalidArgumentException('Neplatný seznam položek.');
    }
    $input['rows'] = [];
    foreach ($raw['rows'] as $row) {
        if (!is_array($row) || !is_string($row['description'] ?? null)) {
            throw new InvalidArgumentException('Neplatná položka.');
        }
        if (trim($row['description']) === '') {
            if (trim((string)($row['code'] ?? '')) !== '' || barDocumentMinor($row['net'] ?? '0') !== 0
                || barDocumentMinor($row['gross'] ?? '0') !== 0 || (int)($row['product_id'] ?? 0) > 0) {
                throw new InvalidArgumentException('Doplňte popis položky, nebo ji odeberte.');
            }
            continue;
        }
        $row['net_minor'] = barDocumentMinor($row['net'] ?? null);
        $row['gross_minor'] = barDocumentMinor($row['gross'] ?? null);
        $input['rows'][] = $row;
    }
    return $input;
}

function barInvoiceDirectory(): string
{
    $directory = privateStorageEnsureDirectory(PRIVATE_STORAGE_BAR_INVOICES);
    $resolved = realpath($directory);
    if ($resolved === false || is_link($directory) || is_link(privateStorageRoot())) {
        throw new RuntimeException('Soukromé úložiště faktur není dostupné.');
    }
    foreach ([dirname(__DIR__, 2), (string)($_SERVER['DOCUMENT_ROOT'] ?? '')] as $webRoot) {
        $web = $webRoot === '' ? false : realpath($webRoot);
        if ($web !== false && ($resolved === $web || str_starts_with($resolved . DIRECTORY_SEPARATOR, $web . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Faktury musí být mimo veřejný web.');
        }
    }
    return $resolved;
}

function barInvoicePdfPath(array $invoice): string
{
    $sha = $invoice['pdf_sha256'];
    if (!preg_match('/^[a-f0-9]{64}$/D', $sha) || $invoice['storage_key'] !== 'private://bar-invoices/' . $sha . '.pdf') {
        throw new RuntimeException('Neplatná příloha faktury.');
    }
    $path = barInvoiceDirectory() . DIRECTORY_SEPARATOR . $sha . '.pdf';
    if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $sha) {
        throw new RuntimeException('Příloha faktury chybí nebo byla změněna.');
    }
    return $path;
}

function barExtractInvoiceText(string $bytes): string
{
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    $config = new Smalot\PdfParser\Config();
    $config->setRetainImageContent(false);
    $config->setDecodeMemoryLimit(8 * 1024 * 1024);
    $pdf = (new Smalot\PdfParser\Parser([], $config))->parseContent($bytes);
    if (count($pdf->getPages()) > 20) {
        throw new DomainException('Doklad může mít nejvýše 20 stran.');
    }
    $text = $pdf->getText();
    if (strlen($text) > 1000000) {
        throw new DomainException('Doklad obsahuje příliš mnoho textu.');
    }
    return $text;
}

/** Deliberately narrow profile inherited from BAR. No guesswork for scans/unknown suppliers. */

function barRecognizeInvoice(string $text): array
{
    $text = str_replace(["\r", "\xc2\xa0"], ['', ' '], $text);
    if (!str_contains($text, 'LE PATIO GROUP') || !str_contains($text, 'CZ07821921')
        || preg_match('/dobropis|zálohov[áý]|opravný daňový|\b(?:EUR|USD|GBP|CHF|PLN)\b/iu', $text)) {
        throw new DomainException('Formát nebyl rozpoznán. Přepište běžnou fakturu v CZK ručně a ověřte ji s originálem. Dobropisy ani zálohy zde nepřijímejte.');
    }
    if (!preg_match('/Variabilní symbol[^\n]*\n(\d+)\s+\1\b/u', $text, $number)
        || !preg_match(
            '/Datum vystavení dokladu:\s*(?:Datum zdanitelného plnění:\s*\d{2}\.\d{2}\.\d{4}\s*)?(\d{2}\.\d{2}\.\d{4})/u',
            $text,
            $date
        )) {
        throw new DomainException('Číslo nebo datum není jednoznačné. Doplňte doklad ručně.');
    }
    $money = '-?\d[\d ]*,\d{2}';
    if (!preg_match('/^celkem\s+(' . $money . ')\s+(' . $money . ')\s+(' . $money . ')/mu', $text, $totals)
        || !preg_match('/(' . $money . ')\s*zaokrouhlení/u', $text, $round)
        || !preg_match('/částka k úhradě\s+(' . $money . ')/u', $text, $pay)) {
        throw new DomainException('Chybí úplný souhrn faktury. Doplňte jej ručně.');
    }
    preg_match_all(
        '/^([A-Z]{1,5}\d{3,8})[ \t]+(.+?)[ \t]+(\d+)[ \t]*(ks|bal)[ \t]+(\d[\d ]*,\d{2,3})[ \t]+(' . $money . ')\s+(\d{1,2})%\s+(' . $money . ')\s+(\d+(?:,\d+)?)\s*$/mu',
        $text,
        $matches,
        PREG_SET_ORDER
    );
    if (count($matches) > 200) {
        throw new DomainException('Doklad smí mít nejvýše 200 položek.');
    }
    $rows = [];
    foreach ($matches as $m) {
        $rows[] = ['code' => $m[1], 'description' => trim($m[2]), 'quantity' => (int)$m[3], 'unit' => $m[4],
            'net_minor' => barDocumentMinor($m[6]), 'gross_minor' => barDocumentMinor($m[8]),
            'kind' => 'stock', 'product_id' => 0, 'multiplier' => 1, 'confirmed' => 0, 'remember' => 0, 'cost_choice' => '',
     'purchase_version' => 0];
    }
    $parsed = DateTimeImmutable::createFromFormat('!d.m.Y', $date[1]);
    if (!$parsed || $parsed->format('d.m.Y') !== $date[1]) {
        throw new DomainException('Datum faktury není platné.');
    }
    return ['supplier' => 'LE PATIO GROUP s.r.o.', 'supplier_id' => '07821921', 'number' => $number[1], 'date' => $parsed->format('Y-m-d'),
        'currency' => 'CZK', 'net_minor' => barDocumentMinor($totals[1]), 'vat_minor' => barDocumentMinor($totals[2]),
        'gross_minor' => barDocumentMinor($totals[3]), 'rounding_minor' => barDocumentMinor($round[1]), 'payable_minor' => barDocumentMinor($pay[1]),
     'rows' => $rows];
}

function barUploadInvoice(PDO $pdo, string $source, string $token, bool $uploaded = true): array
{
    barActor($pdo, 'invoice_manage');
    if (($uploaded && !is_uploaded_file($source)) || !is_file($source) || is_link($source)) {
        throw new InvalidArgumentException('Fakturu se nepodařilo nahrát.');
    }
    $size = filesize($source);
    if (!is_int($size) || $size < 20 || $size > 10 * 1024 * 1024) {
        throw new InvalidArgumentException('Nahrajte PDF do 10 MB.');
    }
    $bytes = file_get_contents($source);
    if (!is_string($bytes) || strlen($bytes) !== $size || !str_starts_with($bytes, '%PDF-')
        || !str_contains(substr($bytes, -2048), '%%EOF') || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf') {
        throw new InvalidArgumentException('Soubor není úplné PDF.');
    }
    $sha = hash('sha256', $bytes);
    $text = '';
    $draft = [];
    $warning = '';
    try {
        $text = barExtractInvoiceText($bytes);
        $draft = barRecognizeInvoice($text);
    } catch (Throwable $error) {
        $warning = $error instanceof DomainException ? $error->getMessage() : 'Text PDF nelze bezpečně načíst. Použijte ruční přepis a originál.';
    }
    return barRunRequest($pdo, 'invoice_upload', $token, [$sha], function (array $actor) use (
        $pdo,
        $sha,
        $bytes,
        $size,
        $text,
        $draft,
        $warning
    ): array {
        $query = $pdo->prepare('SELECT id FROM kis_bar_invoices WHERE pdf_sha256=?');
        $query->execute([$sha]);
        if ($id = $query->fetchColumn()) {
            return ['invoice_id' => (int)$id];
        }
        $path = barInvoiceDirectory() . DIRECTORY_SEPARATOR . $sha . '.pdf';
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $sha) {
                throw new RuntimeException('PDF nelze bezpečně uložit.');
            }
        } else {
            try {
                if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) {
                    throw new RuntimeException('PDF se neuložilo úplně.');
                }
            } finally {
                fclose($handle);
            }
            @chmod($path, 0600);
        }
        $pdo->prepare('INSERT INTO kis_bar_invoices(pdf_sha256,storage_key,pdf_bytes,raw_text,parse_warning,draft_json,uploaded_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([$sha, 'private://bar-invoices/' . $sha . '.pdf', $size, $text, $warning, json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
     $actor['id']]);
        $id = (int)$pdo->lastInsertId();
        barEvent($pdo, $actor['id'], 'invoice', $id, 'uploaded', ['sha256' => $sha, 'recognized' => $draft !== []]);
        return ['invoice_id' => $id];
    }, 'invoice_manage');
}
