<?php
// Přesměrování na kanonický formulář v kořenovém adresáři.
// Tento soubor je zachován pro zpětnou kompatibilitu starých záložek.
$id = isset($_GET['sportovec_id']) ? (int)$_GET['sportovec_id'] : 0;
header('Location: ../zatezovy_test_form.php' . ($id > 0 ? '?sportovec_id=' . $id : ''), true, 301);
exit;
