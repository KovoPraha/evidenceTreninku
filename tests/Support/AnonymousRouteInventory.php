<?php

declare(strict_types=1);

// Explicit exceptions, reviewed as routes rather than inferred from source strings.
function anonymousPublicRoutes(): array
{
    return [
        'index.php' => 'Public landing page.',
        'login.php' => 'Staff sign-in.',
        'sitemap.php' => 'Public search-engine sitemap.',
        'pub.php' => 'Legacy redirect to the published training schedule.',
        'sportovec_treninky.php' => 'Published athlete view gated by a token.',
        'public_profile_access.php' => 'Token exchange for a published profile.',
        'ajax_sportovec_poznamka.php' => 'Published profile token plus CSRF and athlete/family authorization.',
        'program_skupiny.php' => 'Published group programme, token-scoped.',
        'booking/cyklisticke_krouzky.php' => 'Public course catalogue.',
        'booking/eshop.php' => 'Public shop catalogue.',
        'booking/kalendar.php' => 'Public lesson calendar.',
        'booking/klubovy_kalendar.php' => 'Public club calendar; private participation requires sign-in.',
        'booking/krouzky.php' => 'Public event offers; registration requires account.',
        'booking/nove_heslo.php' => 'Password reset with one-time token.',
        'booking/objednavka.php' => 'Guest order access with secret access token or account.',
        'booking/overeni.php' => 'One-time email verification.',
        'booking/potvrdit.php' => 'One-time booking approval token.',
        'booking/prihlaseni.php' => 'Customer sign-in.',
        'booking/produkt.php' => 'Public product detail.',
        'booking/prvni_heslo.php' => 'First password with one-time token.',
        'booking/registrace.php' => 'Customer registration.',
        'booking/rodinny_kalendar.php' => 'Private calendar subscription using bearer token.',
        'booking/rychla_prihlaska.php' => 'Public course checkout entry.',
        'booking/rychly_nakup.php' => 'Public guest checkout entry.',
        'booking/sportovec_prihlaseni.php' => 'Athlete sign-in.',
        'booking/stripe_webhook.php' => 'Provider callback; signature verified separately.',
        'booking/sumup_webhook.php' => 'Provider callback; server-side payment retrieval.',
        'booking/treninky.php' => 'Published training schedule.',
        'booking/velodrom.php' => 'Public velodrome offers; reservations require account.',
        'booking/verejny_kalendar.php' => 'Published calendar feed.',
        'booking/zapomenute_heslo.php' => 'Password reset request.',
        'booking/znovu_overit_email.php' => 'Email verification request.',
    ];
}

function anonymousLibraryFiles(): array
{
    return [
        'auth/sso_bridge.php' => 'Function-only optional SSO adapter.',
        'booking/waiting_list.php' => 'Function-only queue helper.',
        'booking/free_event_registration_form.php' => 'Included form fragment; requires caller-provided variables, not request parameters.',
        'config.example.php' => 'Example constants, never an application route.',
        'csrf_helper.php' => 'CSRF library; Apache FilesMatch denies direct access.',
        'db.php' => 'Database bootstrap; Apache FilesMatch denies direct access.',
        'hlavicka.php' => 'Shared HTML include, no standalone page contract.',
        'report_tyden_lib.php' => 'Function-only weekly report library.',
    ];
}

function anonymousEntryPoints(string $root): array
{
    $excluded = ['includes', 'vendor', 'tests', 'migrations', 'bin', 'tools', 'database', 'docs', 'output', 'outputs', 'var'];
    $routes = [];
    foreach (anonymousTrackedPhpFiles($root) as $relative) {
        if (in_array(explode('/', $relative)[0], $excluded, true)) {
            continue;
        }
        if ($relative === 'config.php' || isset(anonymousLibraryFiles()[$relative])) {
            continue;
        }
        $routes[] = $relative;
    }
    sort($routes);
    return $routes;
}

function anonymousTrackedPhpFiles(string $root): array
{
    $process = proc_open(['git', '-C', $root, 'ls-files', '-z', '--', '*.php'], [
        1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start tracked PHP inventory.');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('git ls-files failed: ' . $error);
    }
    return array_values(array_filter(explode("\0", (string) $output), static function (string $path): bool {
        // Matches the Apache rule for every dot-prefixed path component.
        return $path !== '' && !preg_match('~(^|/)\.~', $path) && $path !== 'config.php';
    }));
}

function anonymousCliRoutes(): array
{
    return [
        'cron_report_tyden.php' => 403,
        'cron_upominky.php' => 404,
    ];
}

function anonymousHiddenRoutes(): array
{
    return [
        'diagnostika_site_admin.php' => 'Admin identity guard intentionally hides this diagnostic with 404.',
    ];
}

function anonymousRouteQueries(string $route): array
{
    if ($route === 'private_download.php') {
        // Valid IDs reach each authorization branch; an absent ID only proves validation.
        return array_map(static fn (string $kind): string => '?id=1&kind=' . $kind, [
            'receipt', 'stress', 'athlete-photo', 'service',
        ]);
    }
    return ['', '?id=1'];
}
