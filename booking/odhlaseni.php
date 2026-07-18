<?php
session_start();
unset($_SESSION['verejny_uzivatel_id'], $_SESSION['verejny_uzivatel_jmeno']);
header('Location: kalendar.php');
exit;
