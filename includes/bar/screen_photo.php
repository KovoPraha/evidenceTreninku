<?php

declare(strict_types=1);

/** Produce a small JPEG for the rotating TV cards without changing the private original. */
function barScreenPhotoBytes(string $path): string
{
    $bytes = @file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException('Fotografii nelze načíst.');
    }
    $size = @getimagesizefromstring($bytes);
    if (!is_array($size) || $size[0] < 1 || $size[1] < 1) {
        throw new RuntimeException('Fotografie nemá platné rozměry.');
    }
    $source = @imagecreatefromstring($bytes);
    if (!$source instanceof GdImage) {
        throw new RuntimeException('Fotografii nelze dekódovat.');
    }
    $scale = min(1, 960 / max($size[0], $size[1]));
    $width = max(1, (int)round($size[0] * $scale));
    $height = max(1, (int)round($size[1] * $scale));
    $target = imagecreatetruecolor($width, $height);
    if (!$target instanceof GdImage) {
        imagedestroy($source);
        throw new RuntimeException('Náhled fotografie nelze vytvořit.');
    }
    try {
        imagefill($target, 0, 0, imagecolorallocate($target, 21, 21, 21));
        if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1])) {
            throw new RuntimeException('Náhled fotografie nelze zmenšit.');
        }
        imageinterlace($target, true);
        ob_start();
        try {
            if (!imagejpeg($target, null, 72)) {
                throw new RuntimeException('Náhled fotografie nelze zapsat.');
            }
            $result = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!is_string($result) || $result === '') {
            throw new RuntimeException('Náhled fotografie je prázdný.');
        }
        return $result;
    } finally {
        imagedestroy($target);
        imagedestroy($source);
    }
}
