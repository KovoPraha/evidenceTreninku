<?php

declare(strict_types=1);

function barRenderProductEditor(array $editor): void
{
    $product = $editor['product'];
    $input = $editor['input'];
    ?>
    <?php if ($editor['error'] !== ''): ?><div class="alert alert-danger" role="alert"><?= barH($editor['error']) ?></div><?php endif; ?>
    <section class="card card-body mb-4">
        <h2 class="h4"><?= $product === null ? 'Nový produkt' : 'Upravit produkt' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($product['id'] ?? 0) ?>">
            <input type="hidden" name="version" value="<?= (int)($product['lock_version'] ?? 0) ?>">
            <label class="form-label" for="bar-name">Název</label>
            <input class="form-control mb-2" id="bar-name" name="name" value="<?= barH($input['name']) ?>" maxlength="255" required>
            <label class="form-label" for="bar-description">Popis</label>
            <textarea class="form-control mb-2" id="bar-description" name="description"><?= barH($input['description']) ?></textarea>
            <label class="form-label" for="bar-category">Kategorie</label>
            <select class="form-select mb-2" id="bar-category" name="category_id" required>
                <?php foreach ($editor['categories'] as $category): ?>
                    <option value="<?= (int)$category['id'] ?>" <?= (int)$input['category_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= barH($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="row">
                <div class="col-md-6"><label class="form-label" for="bar-price">Prodejní cena v Kč</label>
                    <input class="form-control mb-2" id="bar-price" name="price" value="<?= barH($input['price']) ?>" inputmode="decimal" required></div>
                <div class="col-md-6"><label class="form-label" for="bar-purchase">Nákupní cena v Kč</label>
                    <input class="form-control mb-2" id="bar-purchase" name="purchase_price" value="<?= barH($input['purchase_price']) ?>" inputmode="decimal">
                    <p class="form-text">Prázdná cena znamená neznámý náklad.</p></div>
            </div>
            <label class="form-label" for="bar-mode">Evidence skladu</label>
            <select class="form-select mb-2" id="bar-mode" name="stock_mode">
                <option value="sledovat" <?= $input['stock_mode'] === 'sledovat' ? 'selected' : '' ?>>Sledovat zásobu</option>
                <option value="nesledovat" <?= $input['stock_mode'] === 'nesledovat' ? 'selected' : '' ?>>Nesledovat zásobu</option>
            </select>
            <label class="form-label" for="bar-minimum">Minimální zásoba</label>
            <input class="form-control mb-2" type="number" min="0" max="1000000" id="bar-minimum" name="minimum_stock" value="<?= barH($input['minimum_stock']) ?>">
            <?php foreach (['on_terminal' => 'V nabídce stánku', 'sold_out' => 'Vyprodáno', 'favorite' => 'Oblíbený', 'archived' => 'Archivovaný'] as $key => $label): ?>
                <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" <?= (int)$input[$key] === 1 ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= $label ?></span></label>
            <?php endforeach; ?>
            <label class="form-label" for="bar-edit-reason">Důvod změny</label>
            <input class="form-control mb-2" id="bar-edit-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Uložit produkt</button>
            <a class="btn btn-outline-secondary" href="<?= barH(appUiUrl('bar/stock.php')) ?>">Přejít do skladu</a>
        </form>
    </section>
    <?php if ($product !== null): ?>
    <section class="card card-body mb-4">
        <h2 class="h4">Receptura</h2>
        <p>Vyberte počty surovin na jeden prodaný kus. Nula znamená, že se surovina nepoužije.</p>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="recipe">
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="version" value="<?= (int)$product['lock_version'] ?>">
            <?php foreach ($editor['ingredients'] as $ingredient): ?>
                <label class="form-label" for="ingredient-<?= (int)$ingredient['id'] ?>"><?= barH($ingredient['name']) ?></label>
                <input class="form-control mb-2" type="number" min="0" max="1000000" id="ingredient-<?= (int)$ingredient['id'] ?>"
                       name="ingredients[<?= (int)$ingredient['id'] ?>]" value="<?= (int)($editor['recipe'][(int)$ingredient['id']] ?? 0) ?>">
            <?php endforeach; ?>
            <label class="form-label" for="bar-recipe-reason">Důvod změny receptury</label>
            <input class="form-control mb-2" id="bar-recipe-reason" name="reason" minlength="5" maxlength="1000" required>
            <button class="btn btn-primary">Uložit recepturu</button>
        </form>
    </section>
    <?php endif; ?>
    <?php
}
