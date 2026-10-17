<?php
declare(strict_types=1);

require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/shop_public_navigation.php';
require_once __DIR__.'/includes/public_seo.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');
$paths=publicSeoSitemapPaths(shopPublicSaleableCatalog($pdo));
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n",'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',"\n";
foreach($paths as$path)echo '  <url><loc>',publicSeoH($path===''?appCanonicalBaseUrl().'/':appUrl($path)),'</loc></url>',"\n";
echo '</urlset>',"\n";
