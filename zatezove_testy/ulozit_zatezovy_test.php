<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
// Kanonický handler je v kořenovém adresáři: ../ulozit_zatezovy_test.php
// POST sem ze starého formuláře — přesměrujeme na formulář s upozorněním.
app_session_start();
$_SESSION['flash_warning'] = 'Formulář byl přesunut — prosím zadejte test znovu.';
header('Location: ../zatezovy_test_form.php', true, 303);
exit;
