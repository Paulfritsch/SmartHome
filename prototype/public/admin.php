<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use SmartSave\Catalog;
use SmartSave\OrderRepository;

$admin = requireAdmin($users);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $orderId = (int) filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    if (($_POST['action'] ?? '') === 'mark_delivered') {
        if ($orders->markDelivered($orderId)) {
            flash('ok', ["Bestellung #$orderId als geliefert markiert."]);
        } else {
            flash('fehler', ["Bestellung #$orderId ist nicht (mehr) offen."]);
        }
    }

    redirect('admin.php');
}

$counts = $orders->countByStatus();
$open = $orders->listByStatus(OrderRepository::STATUS_OPEN);
$delivered = $orders->listByStatus(OrderRepository::STATUS_DELIVERED, 10);
$packing = $orders->packingList();

pageStart('Bestellungen', $admin);
?>
<h1>Bestellungen</h1>

<div class="zaehler">
    <div class="offen"><strong><?= e($counts[OrderRepository::STATUS_OPEN]) ?></strong> offen</div>
    <div><strong><?= e($counts[OrderRepository::STATUS_DELIVERED]) ?></strong> geliefert</div>
    <div><strong><?= e($counts[OrderRepository::STATUS_CANCELLED]) ?></strong> storniert</div>
</div>

<?php if ($packing !== []): ?>
    <section class="karte" aria-labelledby="packen">
        <h2 id="packen">Zum Packen</h2>
        <ul class="packliste">
            <?php foreach ($packing as $productId => $count): ?>
                <li><strong><?= e($count) ?>×</strong> <?= e(Catalog::productName($productId)) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (isset($packing[Catalog::BASE_PRODUCT_ID])): ?>
            <p class="klein">Ein Basis-Set enthält: <?= e(Catalog::baseSetContents()) ?>.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="karte" aria-labelledby="offene">
    <h2 id="offene">Offene Lieferungen</h2>
    <?php if ($open === []): ?>
        <p>Keine offenen Bestellungen.</p>
    <?php else: ?>
        <ul class="lieferungen">
            <?php foreach ($open as $order): ?>
                <li>
                    <div>
                        <strong>#<?= e($order['id']) ?> · <?= e($order['quantity']) ?>× <?= e(Catalog::productName($order['product'])) ?></strong>
                        <span class="klein">bestellt am <?= e(berlinTime($order['createdAt'])) ?> von <?= e($order['customerName']) ?> (<?= e($order['customerEmail']) ?>)</span>
                        <address>
                            <?= e($order['name']) ?><br>
                            <?= e($order['street']) ?><br>
                            <?= e($order['zip']) ?> <?= e($order['city']) ?>
                        </address>
                        <span class="klein"><?= e(OrderRepository::PAYMENT_METHODS[$order['paymentMethod']] ?? $order['paymentMethod']) ?>
                            · <?= e(euro($order['monthlyPrice'] * $order['quantity'])) ?> pro Monat</span>
                    </div>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_delivered">
                        <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                        <button type="submit">Als geliefert markieren</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($delivered !== []): ?>
    <section class="karte" aria-labelledby="geliefert">
        <h2 id="geliefert">Zuletzt geliefert</h2>
        <ul class="gebucht">
            <?php foreach ($delivered as $order): ?>
                <li>
                    <span>#<?= e($order['id']) ?> · <?= e($order['quantity']) ?>× <?= e(Catalog::productName($order['product'])) ?>
                        <span class="klein">an <?= e($order['name']) ?>, <?= e($order['zip']) ?> <?= e($order['city']) ?>
                            · geliefert am <?= e(berlinTime((string) $order['deliveredAt'])) ?></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php pageEnd();
