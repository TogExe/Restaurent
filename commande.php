<?php
require_once __DIR__ . '/inc/common.php';

$platsFile  = 'plats.json';
$orderFile  = 'commandes.json';
$plats      = load_json($platsFile);
$allOrders  = load_json($orderFile);

$usersFile = 'users.json';
$allUsers  = load_json($usersFile);
$uid       = current_user_id();
$secretKey = current_secret_key();

$savedAddress = '';
if ($uid && isset($allUsers[$uid]['address_enc'])) {
    $savedAddress = decryptData($allUsers[$uid]['address_enc'], $secretKey);
}

ensure_ban();

$message = "";
$orderSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['place_order'])) {
    $items   = json_decode($_POST['cart_items'] ?? '[]', true);
    $address = trim($_POST['delivery_address'] ?? '');
    $payRef  = gen_pay_ref();

    if (empty($items)) {
        $message = "<div class='msg-error'>Votre panier est vide.</div>";
    } elseif (empty($address)) {
        $message = "<div class='msg-error'>Veuillez entrer une adresse de livraison.</div>";
    } else {
        $total = 0;
        $names = [];

        foreach ($items as $pid => $qty) {
            if (isset($plats[$pid])) {
                $total += $plats[$pid]['price'] * $qty;
                for ($i = 0; $i < $qty; $i++) {
                    $names[] = $plats[$pid]['name'];
                }
            }
        }

        $orderId = gen_order_id();
        $now     = date("j/m/Y-H:i:s");
        $delTime = date("j/m/Y-H:i", strtotime('+30 minutes'));

        $allOrders[(string)$orderId] = [
            "adress"    => $address,
            "commands"  => $names,
            "price"     => round($total, 2),
            "comm_t"    => $now,
            "des_t"     => $delTime,
            "paid_id"   => $payRef,
            "ready"     => 0,
            "client_id" => $uid,
        ];

        save_json($orderFile, $allOrders);

        $orderSuccess = true;
        $message = "<div class='msg-success'>🎉 Commande #{$orderId} passée avec succès !<br>Référence paiement : <code>{$payRef}</code><br>Livraison estimée : {$delTime}</div>";
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commander — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
</head>
<body>

<?php include '_nav.php'; ?>

<div class="modal-overlay" id="payModal">
    <div class="modal-box">
        <h2 class="modal-title">Paiement</h2>
        <p class="modal-subtitle">Simulation de paiement sécurisé</p>

        <div class="pay-badge">
            🔒 Ceci est un paiement de démonstration — aucune donnée réelle n'est traitée.
        </div>

        <div class="form-group">
            <label>Titulaire de la carte</label>
            <input type="text" id="cardName" placeholder="Jean Dupont">
        </div>

        <div class="form-group">
            <label>Numéro de carte</label>
            <input type="text" id="cardNum" placeholder="4242 4242 4242 4242" maxlength="19">
        </div>

        <div class="card-input">
            <div class="form-group card-field">
                <label>Expiration</label>
                <input type="text" id="cardExp" placeholder="MM/AA" maxlength="5">
            </div>

            <div class="form-group card-field">
                <label>CVV</label>
                <input type="password" id="cardCvv" placeholder="•••" maxlength="3">
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" id="payBtn" class="modal-pay-btn">
                💳 Payer <span id="payAmt"></span>
            </button>

            <button type="button" id="payCancel" class="modal-cancel-btn">
                Annuler
            </button>
        </div>
    </div>
</div>

<main class="main-container">
    <div class="page-header">
        <h1>Commander</h1>
        <p>Choisissez vos plats et passez votre commande</p>
    </div>

    <?= $message ?>

    <?php if (!$orderSuccess): ?>

        <div class="order-layout">
            <div>
                <div class="menu-grid">
                    <?php foreach ($plats as $pid => $p): ?>
                        <div class="dish-card">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php endif; ?>

                            <div class="dish-body">
                                <div class="dish-name">
                                    <?= htmlspecialchars($p['name']) ?>

                                    <?php if ($p['is_vegetarian'] ?? false): ?>
                                        <span class="dish-veg-icon">🌱</span>
                                    <?php endif; ?>
                                </div>

                                <div class="dish-price">
                                    <?= number_format($p['price'], 2, ',', ' ') ?> €
                                </div>

                                <p class="dish-description">
                                    <?= htmlspecialchars(mb_strimwidth($p['text_description'], 0, 70, '…')) ?>
                                </p>

                                <div class="qty-ctrl">
                                    <button type="button" class="qty-btn" data-id="<?= htmlspecialchars($pid) ?>" data-price="<?= htmlspecialchars($p['price']) ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" data-delta="-1">−</button>
                                    <span class="qty-val" id="qty-<?= $pid ?>">0</span>
                                    <button type="button" class="qty-btn" data-id="<?= htmlspecialchars($pid) ?>" data-price="<?= htmlspecialchars($p['price']) ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" data-delta="1">+</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cart-panel">
                <h2 class="cart-title">🛒 Mon Panier</h2>

                <div id="cartItems">
                    <p class="cart-empty">Aucun article pour l'instant.</p>
                </div>

                <div class="cart-total is-hidden" id="cartTotal">
                    <span>Total</span>
                    <span id="totalVal">0,00 €</span>
                </div>

                <div class="form-group cart-address">
                    <label>Adresse de livraison</label>
                    <input type="text" id="deliveryAddr" value="<?= htmlspecialchars($savedAddress) ?>" placeholder="5 rue de la Paix…">
                </div>

                <button type="button" id="orderBtn" disabled class="order-btn-disabled">
                    Procéder au paiement
                </button>

                <form id="orderForm" method="POST" class="hidden-form">
                    <input type="hidden" name="place_order" value="1">
                    <input type="hidden" name="cart_items" id="cartData">
                    <input type="hidden" name="delivery_address" id="addrData">
                </form>
            </div>
        </div>

    <?php else: ?>

        <div class="order-success-actions">
            <a href="menu.php" class="btn return-menu-btn">← Retour au menu</a>
        </div>

    <?php endif; ?>
</main>
</body>
</html>
