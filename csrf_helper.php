<?php
/**
 * csrf_helper.php
 * Jednoduchá CSRF ochrana pro formuláře aplikace.
 *
 * Použití:
 *   V HTML formuláři: <?= csrf_field() ?>
 *   Při zpracování POST: csrf_verify($_POST['csrf_token'] ?? '')
 */

/**
 * Vrátí aktuální CSRF token (vygeneruje nový, pokud neexistuje).
 * Token je platný po celou session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vrátí skrytý input s CSRF tokenem.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Ověří CSRF token z POST dat.
 * Vrací true pokud je token platný, false jinak.
 */
function csrf_verify(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
