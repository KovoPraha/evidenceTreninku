<?php

declare(strict_types=1);

// Keep the established administration URL while the complete feed lives in one page.
header('Location: mail_admin.php', true, 302);
exit;
