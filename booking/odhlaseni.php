<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
app_session_logout_public_identity();
header('Location: kalendar.php');
exit;
