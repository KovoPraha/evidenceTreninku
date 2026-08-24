<?php
declare(strict_types=1);

// Legacy verejny vypis zverejnoval i interni poznamky, trenery a fotografie.
// Zachovame starou adresu, ale vedeme ji na minimalni explicitne verejne DTO.
header('Cache-Control: no-store, max-age=0');
header('Location: booking/treninky.php', true, 302);
exit;
