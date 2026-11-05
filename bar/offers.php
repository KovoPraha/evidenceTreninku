<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bar/bootstrap.php';
$pdo = barBootstrap();
require_once dirname(__DIR__) . '/includes/bar/checkout_web.php';
require_once dirname(__DIR__) . '/includes/bar/offers.php';
barRequireWebActor($pdo, 'offers_manage');
$error = '';
$id = max(0, (int)($_GET['id'] ?? 0));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        barPostCsrf();
        $id = barSourceInteger($_POST['id'] ?? 0, 0, 2147483647);
        $version = barSourceInteger($_POST['version'] ?? 0, 0, 2147483647);
        $token = barPostText('token', 64);
        $reason = barPostText('reason', 1000);
        if (($_POST['action'] ?? '') === 'save') {
            $input = $_POST;
            $input['products'] = $_POST['products'] ?? [];
            $result = barSaveOffer($pdo, $id, $version, $input, $reason, $token);
        } elseif (($_POST['action'] ?? '') === 'publish') {
            $result = barPublishOffer($pdo, $id, $version, $reason, $token);
        } else {
            throw new InvalidArgumentException('Neplatná operace nabídky.');
        }
        header('Location: ' . appUiUrl('bar/offers.php?id=' . $result['offer_id']), true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $exception) {
        http_response_code($exception instanceof BarConflictException ? 409 : 422);
        $error = $exception->getMessage();
    }
}
$query = $pdo->prepare('SELECT * FROM kis_bar_offers WHERE id=?');
$query->execute([$id]);
$offer = $query->fetch(PDO::FETCH_ASSOC) ?: ['id' => 0, 'lock_version' => 0, 'title' => '', 'subtitle' => '', 'layout' =>
    'text',
     'selection_json' => '[]'];
$selected = json_decode($offer['selection_json'], true, 8, JSON_THROW_ON_ERROR);
if ($error !== '' && ($_POST['action'] ?? '') === 'save') {
    $offer = $_POST + $offer;
    $offer['lock_version'] = (int)($_POST['version'] ?? 0);
    $selected = array_map('intval', is_array($_POST['products'] ?? null) ? $_POST['products'] : []);
}
$active = barActiveOffer($pdo);
barCheckoutPageStart($pdo, 'Nabídky a obrazovka baru');
?>
<p>Zveřejněná nabídka určuje výběr produktů na obrazovce i v pokladně. Ceny a popisy přebírá z aktuálního katalogu. Vlastní spotřeba
má samostatná pravidla.</p>
<p><a href="<?= barH(appUiUrl('bar/menu.php')) ?>" target="_blank" rel="noopener">Veřejná obrazovka</a> · <a href="<?= barH(appUiUrl('bar/menu-pdf.php'))
?>">Tiskový lístek PDF</a> · <a href="?">Nová nabídka</a></p>
<p><?= $active ? 'Zveřejněno: ' . barH($active['title']) . ' (vydání ' . (int)$active['publication_id'] . ')' : 'Zatím není zveřejněná nabídka. Pokladna používá celý prodejní katalog.'
?></p>
<?php if ($error !== ''): ?><p class="alert alert-danger"><?= barH($error) ?></p><?php endif; ?>
<form method="post" class="card card-body mb-4"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$offer['id'] ?>"><input type="hidden"
name="version" value="<?= (int)$offer['lock_version'] ?>"><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32))
?>">
    <label>Název<input class="form-control" name="title" maxlength="150" value="<?= barH($offer['title']) ?>" required></label>
    <label>Podtitulek<input class="form-control" name="subtitle" maxlength="500" value="<?= barH($offer['subtitle']) ?>"></label>
    <label>Zobrazení<select class="form-select" name="layout"><option value="text">Textový lístek</option><option value="photos"
<?= $offer['layout'] === 'photos' ? 'selected' : '' ?>>Fotografie produktů</option></select></label>
    <fieldset class="my-3"><legend class="h5">Produkty</legend>
    <?php foreach ($pdo->query('SELECT id,name,price_minor FROM kis_bar_products WHERE archived=0 AND on_terminal=1 ORDER BY category_id,sort_order,id')->fetchAll(PDO::FETCH_ASSOC) as $product): ?>
        <label class="d-block"><input type="checkbox" name="products[]" value="<?= (int)$product['id'] ?>" <?= in_array((int)$product['id'],
            $selected, true) ? 'checked' : '' ?>> <?= barH($product['name']) ?> — <?= barH(barFormatMoney((int)$product['price_minor']))
        ?></label>
    <?php endforeach; ?></fieldset>
    <label>Důvod úpravy<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label><button class="btn btn-primary mt-3">Uložit
pracovní návrh</button>
</form>
<?php if ((int)$offer['id'] > 0 && $error === ''): ?>
<form method="post" class="card card-body mb-4"><?= csrf_field() ?>
    <input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int)$offer['id'] ?>"><input type="hidden"
name="version" value="<?= (int)$offer['lock_version'] ?>"><input type="hidden" name="token" value="<?= bin2hex(random_bytes(32))
    ?>">
    <p>Zveřejníte uložený návrh včetně případné prázdné nabídky. Nahrazuje dosavadní výběr v pokladně.</p>
    <label>Důvod zveřejnění<input class="form-control" name="reason" minlength="5" maxlength="1000" required></label><button class="btn btn-success mt-3">Zveřejnit
uloženou nabídku</button>
</form><?php endif; ?>
<h2 class="h4">Uložené návrhy</h2><ul>
<?php foreach ($pdo->query('SELECT id,title FROM kis_bar_offers ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $row): ?><li><a
href="?id=<?= (int)$row['id'] ?>"><?= barH($row['title']) ?></a></li><?php endforeach; ?>
</ul>
<?php barCheckoutPageEnd();
