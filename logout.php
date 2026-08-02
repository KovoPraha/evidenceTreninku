<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
app_session_destroy();
header("Location: index.php");
exit;
