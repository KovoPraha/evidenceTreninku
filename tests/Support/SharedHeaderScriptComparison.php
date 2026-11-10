<?php

declare(strict_types=1);

/** Test-only canonical context; reject every unrecognised script or extra field. */
function sharedHeaderScriptContext(array $context): ?array
{
    $keys = ['pushPublicKey', 'serviceWorkerUrl', 'appBaseUrl', 'pushEndpoint', 'csrfToken', 'searchUrl'];
    if (array_keys($context) !== $keys || !is_string($context['csrfToken'])
        || !preg_match('/^[a-f0-9]{64}$/D', $context['csrfToken'])
    ) {
        return null;
    }
    $context['csrfToken'] = 'SYNTHETIC_CSRF';
    return $context;
}

/** Allow only the frozen legacy script versus its verified JSON + local bundle. */
function sharedHeaderScriptComparisonHtml(string $html): string
{
    $reference = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/SharedHeaderScriptReference.php.txt'));
    $parts = preg_split('/<\?=.*?\?>/s', $reference);
    if ($parts === false || count($parts) !== 11) {
        throw new RuntimeException('Invalid frozen staff script.');
    }
    $json = '("(?:\\\\.|[^"\\\\])*"|null|true|false|-?[0-9]+(?:\.[0-9]+)?)';
    $pattern = '~' . preg_quote($parts[0], '~') . '([^"]+)' . preg_quote($parts[1], '~');
    for ($i = 2; $i < count($parts); $i++) {
        $pattern .= $json . preg_quote($parts[$i], '~');
    }
    $pattern .= '~s';
    if (preg_match_all($pattern, $html, $old, PREG_SET_ORDER) === 1) {
        if (str_contains($html, 'id="appStaffHeaderConfig"') || str_contains($html, 'assets/staff-header.js')) {
            return $html;
        }
        $context = [];
        $fields = ['pushPublicKey', 'serviceWorkerUrl', 'appBaseUrl', 'pushEndpoint', 'csrfToken',
            'pushEndpoint', 'csrfToken', 'searchUrl', 'appBaseUrl'];
        foreach ($fields as $i => $key) {
            $value = json_decode($old[0][$i + 2], true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists($key, $context) && $context[$key] !== $value) {
                return $html;
            }
            $context[$key] = $value;
        }
        $context = sharedHeaderScriptContext($context);
        if ($context === null) {
            return $html;
        }
        return str_replace($old[0][0], sharedHeaderScriptMarker($context), $html);
    }
    $new = '~<script type="application/json" id="appStaffHeaderConfig" nonce="([^"]+)">\s*(\{.*?\})\s*</script>\s*'
        . '<script nonce="([^"]+)"\s+src="(/(?!/)[^"]*assets/staff-header\.js\?v=[0-9]+)"></script>~s';
    if (preg_match_all($new, $html, $matches, PREG_SET_ORDER) !== 1 || $matches[0][1] !== $matches[0][3]) {
        return $html;
    }
    if (substr_count($html, 'id="appStaffHeaderConfig"') !== 1 || substr_count($html, 'assets/staff-header.js') !== 1) {
        return $html;
    }
    $context = json_decode($matches[0][2], true, 512, JSON_THROW_ON_ERROR);
    $context = is_array($context) ? sharedHeaderScriptContext($context) : null;
    if ($context === null) {
        return $html;
    }
    return str_replace($matches[0][0], sharedHeaderScriptMarker($context), $html);
}

function sharedHeaderScriptMarker(array $context): string
{
    return '<!-- verified-staff-header-context:' . hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)) . ' -->';
}
