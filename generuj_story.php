<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/csrf_helper.php';
if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(405);
    header('Allow: POST');
    exit('Neplatný požadavek.');
}

// ── Pomocné funkce ──────────────────────────────────────────────

/**
 * Zalomení textu na max. šířku (word-wrap pro GD).
 */
function wrapText($fontSize, $angle, $fontFile, $text, $maxWidth) {
    $words = explode(' ', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($fontSize, $angle, $fontFile, $test);
        $width = abs($box[2] - $box[0]);
        if ($width > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

/**
 * HEX na RGB pole.
 */
function hex2rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $int = hexdec($hex);
    return [($int >> 16) & 255, ($int >> 8) & 255, $int & 255];
}

/**
 * Vykreslení obrázku jako "cover" (center-crop + vyplnění).
 */
function drawCover($dst, $src, int $dx, int $dy, int $dw, int $dh) {
    $sw = imagesx($src);
    $sh = imagesy($src);
    $rDst = $dw / $dh;
    $rSrc = $sw / $sh;
    if ($rSrc > $rDst) {
        $nh = $sh;
        $nw = intval($sh * $rDst);
        $sx = intval(($sw - $nw) / 2);
        $sy = 0;
    } else {
        $nw = $sw;
        $nh = intval($sw / $rDst);
        $sx = 0;
        $sy = intval(($sh - $nh) / 2);
    }
    imagecopyresampled($dst, $src, $dx, $dy, $sx, $sy, $dw, $dh, $nw, $nh);
}

/**
 * Načtení obrázku (JPEG, PNG, WebP, HEIC).
 */
function loadImage(string $path) {
    $info = @getimagesize($path);
    if ($info !== false) {
        switch ($info[2]) {
            case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:  return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP: return @imagecreatefromwebp($path);
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (($ext === 'heic' || $ext === 'heif') && class_exists('Imagick')) {
        try {
            $im = new Imagick($path);
            $im->setImageFormat('jpeg');
            $gd = imagecreatefromstring($im->getImageBlob());
            $im->clear();
            $im->destroy();
            return $gd;
        } catch (Exception $e) {
            return null;
        }
    }
    return null;
}

/**
 * Vykreslení textu s drop-shadow.
 */
function drawTextShadow($img, $size, $angle, $x, $y, $color, $font, $text, $shadowOffset = 2) {
    $shadow = imagecolorallocatealpha($img, 0, 0, 0, 40);
    imagettftext($img, $size, $angle, $x + $shadowOffset, $y + $shadowOffset, $shadow, $font, $text);
    imagettftext($img, $size, $angle, $x, $y, $color, $font, $text);
}

/**
 * Vykreslení vertikálního gradientu (barva → průhledná, nebo obráceně).
 */
function drawGradient($img, int $x, int $y, int $w, int $h, int $r, int $g, int $b, bool $topToBottom = true) {
    imagealphablending($img, true);
    for ($i = 0; $i < $h; $i++) {
        // alpha: 0 = neprůhledné, 127 = plně průhledné
        if ($topToBottom) {
            $alpha = intval(127 * $i / $h);        // shora dolů: neprůhledné → průhledné
        } else {
            $alpha = intval(127 * (1 - $i / $h));   // zdola nahoru: průhledné → neprůhledné
        }
        $col = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
        imagefilledrectangle($img, $x, $y + $i, $x + $w - 1, $y + $i, $col);
    }
}

/**
 * České formátování data.
 */
function datumCesky(string $datum): string {
    $mesice = [
        1 => 'ledna', 2 => 'února', 3 => 'března', 4 => 'dubna',
        5 => 'května', 6 => 'června', 7 => 'července', 8 => 'srpna',
        9 => 'září', 10 => 'října', 11 => 'listopadu', 12 => 'prosince'
    ];
    $ts = strtotime($datum);
    if (!$ts) return $datum;
    $den = (int)date('j', $ts);
    $mesic = (int)date('n', $ts);
    $rok = date('Y', $ts);
    return $den . '. ' . ($mesice[$mesic] ?? '') . ' ' . $rok;
}

/**
 * Centrování textu horizontálně.
 */
function getTextCenterX($fontSize, $angle, $font, $text, $canvasWidth) {
    $box = imagettfbbox($fontSize, $angle, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    return intval(($canvasWidth - $textWidth) / 2);
}

// ── Hlavní logika ───────────────────────────────────────────────

require_once __DIR__ . '/db.php';

$trenink_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($trenink_id <= 0) {
    die('Neplatné ID tréninku.');
}

// Autorizace
$stmtAuth = $pdo->prepare(
    'SELECT 1 FROM trenink_trener WHERE trenink_id = :tid AND trener_id = :uid'
);
$stmtAuth->execute([':tid' => $trenink_id, ':uid' => $_SESSION['trener_id']]);
if (!roleAtLeast('hlavni') && !$stmtAuth->fetch()) {
    die('Nemáte oprávnění generovat tuto story.');
}

// Data tréninku
$stmt = $pdo->prepare('SELECT * FROM treninky WHERE id = :id');
$stmt->execute([':id' => $trenink_id]);
$trenink = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$trenink) {
    die('Trénink nenalezen.');
}

// Fotky
$rawImages = explode(',', $trenink['obrazky'] ?? '');
$images = [];
foreach ($rawImages as $f) {
    $f = trim($f);
    if ($f !== '') $images[] = $f;
}

// Sportovci tréninku
$stmtSp = $pdo->prepare("
    SELECT sp.jmeno, sp.prijmeni
    FROM trenink_sportovec ts
    JOIN sportovci sp ON sp.id = ts.sportovec_id
    WHERE ts.trenink_id = ?
    ORDER BY sp.prijmeni, sp.jmeno
");
$stmtSp->execute([$trenink_id]);
$sportovci = $stmtSp->fetchAll(PDO::FETCH_ASSOC);

// Styl: podskupina → skupina → výchozí
$style = null;
$stmtPS = $pdo->prepare("SELECT podskupina_id FROM trenink_podskupina WHERE trenink_id = ?");
$stmtPS->execute([$trenink_id]);
foreach ($stmtPS->fetchAll(PDO::FETCH_COLUMN) as $psId) {
    $stmtStyle = $pdo->prepare("SELECT * FROM story_nastaveni WHERE typ='podskupina' AND entita_id = ?");
    $stmtStyle->execute([$psId]);
    if ($row = $stmtStyle->fetch(PDO::FETCH_ASSOC)) {
        $style = $row;
        break;
    }
}
if (!$style) {
    $stmtGS = $pdo->prepare("SELECT skupina_id FROM trenink_skupina WHERE trenink_id = ?");
    $stmtGS->execute([$trenink_id]);
    foreach ($stmtGS->fetchAll(PDO::FETCH_COLUMN) as $gId) {
        $stmtStyle = $pdo->prepare("SELECT * FROM story_nastaveni WHERE typ='skupina' AND entita_id = ?");
        $stmtStyle->execute([$gId]);
        if ($row = $stmtStyle->fetch(PDO::FETCH_ASSOC)) {
            $style = $row;
            break;
        }
    }
}
if (!$style) {
    $style = ['barva' => '#1a1a2e', 'barva_textu' => '#FFFFFF', 'hlavicka' => '', 'paticka' => '', 'logo' => ''];
}

// ── Rozměry a barvy ─────────────────────────────────────────────

$W = 1080;
$H = 1920;

list($rB, $gB, $bB) = hex2rgb($style['barva']);
list($rT, $gT, $bT) = hex2rgb($style['barva_textu']);

// ── Vytvoření plátna ────────────────────────────────────────────

$img = imagecreatetruecolor($W, $H);
imagesavealpha($img, true);
imagealphablending($img, true);

$bg = imagecolorallocate($img, $rB, $gB, $bB);
$tc = imagecolorallocate($img, $rT, $gT, $bT);
imagefill($img, 0, 0, $bg);

// ── Vložení fotografií ──────────────────────────────────────────

$hasPhotos = false;
if (count($images) >= 2) {
    for ($i = 0; $i < 2; $i++) {
        $file = __DIR__ . '/nahrane_obrazky/' . $images[$i];
        if (file_exists($file)) {
            $src = loadImage($file);
            if ($src) {
                $halfH = intval($H / 2);
                drawCover($img, $src, 0, $i * $halfH, $W, $halfH);
                imagedestroy($src);
                $hasPhotos = true;
            }
        }
    }
} elseif (count($images) === 1) {
    $file = __DIR__ . '/nahrane_obrazky/' . $images[0];
    if (file_exists($file)) {
        $src = loadImage($file);
        if ($src) {
            drawCover($img, $src, 0, 0, $W, $H);
            imagedestroy($src);
            $hasPhotos = true;
        }
    }
}

// ── Gradient overlay ────────────────────────────────────────────

if ($hasPhotos) {
    // Horní gradient: barva → průhledná (pro logo/hlavičku)
    drawGradient($img, 0, 0, $W, 250, $rB, $gB, $bB, true);
    // Spodní gradient: průhledná → barva (pro obsah/patičku)
    drawGradient($img, 0, $H - 700, $W, 700, $rB, $gB, $bB, false);
} else {
    // Bez fotek — plné pozadí (gradient efekt světlejší verze barvy)
    $lighterR = min(255, $rB + 30);
    $lighterG = min(255, $gB + 30);
    $lighterB = min(255, $bB + 30);
    for ($i = 0; $i < $H; $i++) {
        $ratio = $i / $H;
        $r = intval($lighterR + ($rB - $lighterR) * $ratio);
        $g = intval($lighterG + ($gB - $lighterG) * $ratio);
        $b = intval($lighterB + ($bB - $lighterB) * $ratio);
        $col = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $i, $W - 1, $i, $col);
    }
}

// ── Font ────────────────────────────────────────────────────────

$font = __DIR__ . '/arial.ttf';
if (!file_exists($font)) {
    die('Font soubor arial.ttf nenalezen.');
}

// ── Horní část: Logo + Hlavička ─────────────────────────────────

$topY = 50;

// Logo
$logoEndX = 50; // kde začne text, pokud není logo
if (!empty($style['logo'])) {
    $logoPath = __DIR__ . '/loga_story/' . $style['logo'];
    if (file_exists($logoPath)) {
        $logoImg = loadImage($logoPath);
        if ($logoImg) {
            $lw = imagesx($logoImg);
            $lh = imagesy($logoImg);
            $maxLogoH = 80;
            $ratio = $lw / $lh;
            $logoW = intval($maxLogoH * $ratio);
            $logoH = $maxLogoH;
            imagecopyresampled($img, $logoImg, 50, $topY, 0, 0, $logoW, $logoH, $lw, $lh);
            imagedestroy($logoImg);
            $logoEndX = 50 + $logoW + 30;
        }
    }
}

// Hlavička vedle loga
if (!empty($style['hlavicka'])) {
    $fontSizeH = 36;
    $headerY = $topY + 55; // vertikální centrování k logu
    drawTextShadow($img, $fontSizeH, 0, $logoEndX, $headerY, $tc, $font, $style['hlavicka'], 3);
}

// ── Spodní obsahová oblast ──────────────────────────────────────

$contentAreaBottom = $H - 60;
$fontSizePaticka = 24;
$fontSizeContent = 28;
$fontSizeSportovci = 22;
$fontSizeDatum = 22;
$lineSpacing = 10;
$margin = 50;
$maxTextW = $W - ($margin * 2);

// Nejprve spočítáme výšku veškerého spodního obsahu zdola nahoru
$cursorY = $contentAreaBottom;

// Patička (úplně dole)
$patickaText = trim($style['paticka'] ?? '');
if ($patickaText !== '') {
    $cursorY -= $fontSizePaticka;
    $patickaY = $cursorY;
    $cursorY -= 30; // mezera nad patičkou
}

// Sportovci
$sportovciText = '';
if (!empty($sportovci)) {
    $jmena = [];
    $maxZobrazit = 8;
    foreach (array_slice($sportovci, 0, $maxZobrazit) as $sp) {
        $jmena[] = trim(($sp['jmeno'] ?? '') . ' ' . ($sp['prijmeni'] ?? ''));
    }
    $sportovciText = implode('  •  ', $jmena);
    if (count($sportovci) > $maxZobrazit) {
        $sportovciText .= '  a další ' . (count($sportovci) - $maxZobrazit) . '…';
    }

    $sportovciLines = wrapText($fontSizeSportovci, 0, $font, $sportovciText, $maxTextW);
    foreach (array_reverse($sportovciLines) as $line) {
        $cursorY -= $fontSizeSportovci + 6;
    }
    $sportovciStartY = $cursorY + $fontSizeSportovci + 6;
    $cursorY -= 15; // mezera
}

// Náplň tréninku
$content = trim($trenink['napln'] ?? '');
$contentLines = [];
if ($content !== '') {
    $contentLines = wrapText($fontSizeContent, 0, $font, $content, $maxTextW);
    // Omezit na max 12 řádků
    if (count($contentLines) > 12) {
        $contentLines = array_slice($contentLines, 0, 12);
        $contentLines[11] = rtrim($contentLines[11]) . '…';
    }
    foreach (array_reverse($contentLines) as $line) {
        $cursorY -= $fontSizeContent + $lineSpacing;
    }
    $contentStartY = $cursorY + $fontSizeContent + $lineSpacing;
    $cursorY -= 15; // mezera
}

// Oddělovací čára
$separatorY = $cursorY;
$cursorY -= 25;

// Datum
$datumText = datumCesky($trenink['datum'] ?? '');
if ($datumText !== '') {
    $cursorY -= $fontSizeDatum;
    $datumY = $cursorY + $fontSizeDatum;
    $cursorY -= 20;
}

// ── Vykreslení spodní oblasti (odshora dolů) ────────────────────

// Poloprůhledný obdélník pod textem (pokud jsou fotky)
if ($hasPhotos) {
    $boxTop = max(0, $cursorY - 20);
    $boxColor = imagecolorallocatealpha($img, $rB, $gB, $bB, 50);
    imagefilledrectangle($img, 0, $boxTop, $W, $H, $boxColor);
}

// Datum
if ($datumText !== '') {
    drawTextShadow($img, $fontSizeDatum, 0, $margin, $datumY, $tc, $font, $datumText, 2);
}

// Oddělovací čára
$lineColor = imagecolorallocatealpha($img, $rT, $gT, $bT, 60);
imagesetthickness($img, 2);
imageline($img, $margin, $separatorY, $W - $margin, $separatorY, $lineColor);
imagesetthickness($img, 1);

// Náplň tréninku
if (!empty($contentLines)) {
    $y = $contentStartY;
    foreach ($contentLines as $line) {
        drawTextShadow($img, $fontSizeContent, 0, $margin, $y, $tc, $font, $line, 2);
        $y += $fontSizeContent + $lineSpacing;
    }
}

// Sportovci
if ($sportovciText !== '') {
    $sportovciColor = imagecolorallocatealpha($img, $rT, $gT, $bT, 30);
    $y = $sportovciStartY;
    $sportovciLines = wrapText($fontSizeSportovci, 0, $font, $sportovciText, $maxTextW);
    foreach ($sportovciLines as $line) {
        drawTextShadow($img, $fontSizeSportovci, 0, $margin, $y, $sportovciColor, $font, $line, 1);
        $y += $fontSizeSportovci + 6;
    }
}

// Patička
if (!empty($patickaText)) {
    $patickaX = getTextCenterX($fontSizePaticka, 0, $font, $patickaText, $W);
    drawTextShadow($img, $fontSizePaticka, 0, $patickaX, $patickaY, $tc, $font, $patickaText, 2);
}

// ── Uložení výsledku ────────────────────────────────────────────

if (!is_dir(__DIR__ . '/stories')) {
    mkdir(__DIR__ . '/stories', 0777, true);
}
$filename = "story_{$trenink_id}_" . time() . ".jpg";
$fullPath = __DIR__ . '/stories/' . $filename;
imagejpeg($img, $fullPath, 90);

// Záznam do DB
$stmtLog = $pdo->prepare(
    "INSERT INTO story_vygenerovane (trenink_id, soubor) VALUES (:tid, :soubor)"
);
$stmtLog->execute([
    ':tid'    => $trenink_id,
    ':soubor' => $filename
]);

// Výstup
header('Content-Type: image/jpeg');
header('Content-Disposition: inline; filename="' . $filename . '"');
readfile($fullPath);

imagedestroy($img);
exit;
