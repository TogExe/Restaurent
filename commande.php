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
            <input type="text" id="cardNum" placeholder="4242 4242 4242 4242" maxlength="19" oninput="fmtCard(this)">
        </div>

        <div class="card-input">
            <div class="form-group card-field">
                <label>Expiration</label>
                <input type="text" id="cardExp" placeholder="MM/AA" maxlength="5" oninput="fmtExp(this)">
            </div>

            <div class="form-group card-field">
                <label>CVV</label>
                <input type="password" id="cardCvv" placeholder="•••" maxlength="3">
            </div>
        </div>

        <div class="modal-actions">
            <button onclick="submitPayment()" id="payBtn" class="modal-pay-btn">
                💳 Payer <span id="payAmt"></span>
            </button>

            <button onclick="closeModal()" class="modal-cancel-btn">
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
                                    <button class="qty-btn" onclick="changeQty('<?= $pid ?>', <?= $p['price'] ?>, '<?= addslashes($p['name']) ?>', -1)">−</button>
                                    <span class="qty-val" id="qty-<?= $pid ?>">0</span>
                                    <button class="qty-btn" onclick="changeQty('<?= $pid ?>', <?= $p['price'] ?>, '<?= addslashes($p['name']) ?>', 1)">+</button>
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

                <button id="orderBtn" onclick="openPayment()" disabled class="order-btn-disabled">
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

<script>
const cart = {};
const prices = {};
const names = {};

function changeQty(id, price, name, delta) {
    cart[id] = (cart[id] || 0) + delta;

    if (cart[id] <= 0) {
        delete cart[id];
    }

    prices[id] = price;
    names[id] = name;

    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const totalDiv  = document.getElementById('cartTotal');
    const totalVal  = document.getElementById('totalVal');
    const orderBtn  = document.getElementById('orderBtn');

    let html = '';
    let total = 0;
    let count = 0;

    for (const id in cart) {
        const q = cart[id];
        total += prices[id] * q;
        count += q;

        html += `
            <div class="cart-item">
                <span>${names[id]} ×${q}</span>
                <span class="cart-item-price">${(prices[id] * q).toFixed(2).replace('.', ',')} €</span>
            </div>
        `;

        document.getElementById('qty-' + id).textContent = q;
    }

    document.querySelectorAll('.qty-val').forEach(el => {
        const pid = el.id.replace('qty-', '');
        if (!cart[pid]) {
            el.textContent = '0';
        }
    });

    container.innerHTML = count ? html : '<p class="cart-empty">Aucun article pour l\\'instant.</p>';

    totalDiv.classList.toggle('is-hidden', count === 0);

    totalVal.textContent = total.toFixed(2).replace('.', ',') + ' €';

    orderBtn.disabled = count === 0;
    orderBtn.classList.toggle('order-btn-disabled', count === 0);

    document.getElementById('payAmt').textContent = total.toFixed(2).replace('.', ',') + ' €';
}

function openPayment() {
    const addr = document.getElementById('deliveryAddr').value.trim();

    if (!addr) {
        alert('Veuillez entrer une adresse de livraison.');
        return;
    }

    document.getElementById('payModal').classList.add('open');
}

function closeModal() {
    document.getElementById('payModal').classList.remove('open');
}

function submitPayment() {
    const name = document.getElementById('cardName').value.trim();
    const num  = document.getElementById('cardNum').value.replace(/\s/g, '');
    const exp  = document.getElementById('cardExp').value;
    const cvv  = document.getElementById('cardCvv').value;

    if (!name || num.length < 16 || exp.length < 5 || cvv.length < 3) {
        alert('Veuillez remplir tous les champs de paiement.');
        return;
    }

    const btn = document.getElementById('payBtn');
    btn.textContent = '⏳ Traitement…';
    btn.disabled = true;

    setTimeout(() => {
        document.getElementById('cartData').value = JSON.stringify(cart);
        document.getElementById('addrData').value = document.getElementById('deliveryAddr').value;

        closeModal();

        document.getElementById('orderForm').submit();
    }, 1800);
}

function fmtCard(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
}

function fmtExp(el) {
    let v = el.value.replace(/\D/g, '');

    if (v.length >= 2) {
        v = v.substring(0, 2) + '/' + v.substring(2, 4);
    }

    el.value = v;
}
</script>

</body>
</html>
