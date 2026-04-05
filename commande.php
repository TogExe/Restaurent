<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: connect.php"); exit();
}
if (($_SESSION['user_role'] ?? 'client') !== 'client') {
    header("Location: index.php"); exit();
}

$platsFile  = 'plats.json';
$orderFile  = 'commandes.json';
$plats      = file_exists($platsFile) ? json_decode(file_get_contents($platsFile), true) : [];
$allOrders  = file_exists($orderFile) ? json_decode(file_get_contents($orderFile), true) : [];

function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded   = base64_decode($payload);
    $iv        = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

// Récupérer adresse depuis profil
$usersFile = 'users.json';
$allUsers  = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$uid       = $_SESSION['user_id'];
$secretKey = $_SESSION['secret_key'];
$savedAddress = '';
if (isset($allUsers[$uid]['address_enc'])) {
    $savedAddress = decryptData($allUsers[$uid]['address_enc'], $secretKey);
}

$message = "";
$orderSuccess = false;

// --- PASSER LA COMMANDE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['place_order'])) {
    $items   = json_decode($_POST['cart_items'] ?? '[]', true);
    $address = trim($_POST['delivery_address'] ?? '');
    $payRef  = 'PAY_DEMO_' . strtoupper(bin2hex(random_bytes(6)));

    if (empty($items)) {
        $message = "<div class='msg-error'>Votre panier est vide.</div>";
    } elseif (empty($address)) {
        $message = "<div class='msg-error'>Veuillez entrer une adresse de livraison.</div>";
    } else {
        // Calculer prix total
        $total = 0;
        $names = [];
        foreach ($items as $pid => $qty) {
            if (isset($plats[$pid])) {
                $total += $plats[$pid]['price'] * $qty;
                for ($i = 0; $i < $qty; $i++) $names[] = $plats[$pid]['name'];
            }
        }
        $orderId = rand(10000000000, 99999999999);
        $now     = date("j/m/Y-H:i:s");
        $delTime = date("j/m/Y-H:i", strtotime('+30 minutes'));

        $allOrders[(string)$orderId] = [
            "adress"   => $address,
            "commands" => $names,
            "price"    => round($total, 2),
            "comm_t"   => $now,
            "des_t"    => $delTime,
            "paid_id"  => $payRef,
            "ready"    => 0,
            "client_id"=> $uid,
        ];
        file_put_contents($orderFile, json_encode($allOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $orderSuccess = true;
        $message = "<div class='msg-success'>🎉 Commande #{$orderId} passée avec succès !<br>Référence paiement : <code>{$payRef}</code><br>Livraison estimée : {$delTime}</div>";
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commander — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;}
        .dish-card{background:var(--card-bg);border:1px solid var(--glass-border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:var(--transition-smooth);}
        .dish-card:hover{border-color:var(--glass-border-hover);transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,.35);}
        .dish-card img{width:100%;height:140px;object-fit:cover;}
        .dish-body{padding:14px;flex:1;display:flex;flex-direction:column;gap:8px;}
        .dish-name{font-weight:700;color:var(--sapphire);font-size:1rem;}
        .dish-price{color:var(--softlime);font-weight:700;}
        .qty-ctrl{display:flex;align-items:center;gap:10px;margin-top:auto;}
        .qty-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--overlay);background:rgba(255,255,255,.05);color:var(--text);font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--transition-fast);padding:0;margin:0;}
        .qty-btn:hover{background:rgba(138,180,255,.15);border-color:var(--accent-btn);}
        .qty-val{font-weight:700;min-width:20px;text-align:center;color:var(--text);}
        /* Cart sidebar */
        .order-layout{display:grid;grid-template-columns:1fr 320px;gap:30px;align-items:start;max-width:1100px;width:100%;}
        @media(max-width:800px){.order-layout{grid-template-columns:1fr;}}
        .cart-panel{position:sticky;top:88px;background:var(--card-bg);border:1px solid var(--glass-border);border-radius:20px;padding:28px;backdrop-filter:blur(20px);}
        .cart-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.9rem;}
        .cart-item:last-child{border:none;}
        .cart-total{display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;padding-top:14px;border-top:1px solid var(--overlay);color:var(--softlime);}
        /* Payment modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:999;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:var(--surface);border:1px solid var(--glass-border);border-radius:20px;padding:36px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:fadeSlideUp .35s both;}
        .card-input{display:flex;gap:10px;}
        .pay-badge{background:rgba(126,203,163,.1);border:1px solid rgba(126,203,163,.3);border-radius:8px;padding:10px 14px;color:var(--softlime);font-size:.82rem;text-align:center;margin-bottom:16px;}
    </style>
</head>
<body>
<?php include '_nav.php'; ?>

<!-- PAYMENT MODAL (placeholder) -->
<div class="modal-overlay" id="payModal">
    <div class="modal-box">
        <h2 style="color:var(--mauve);font-family:'Playfair Display',serif;margin-bottom:6px;">Paiement</h2>
        <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:20px;">Simulation de paiement sécurisé</p>

        <div class="pay-badge">🔒 Ceci est un paiement de démonstration — aucune donnée réelle n'est traitée.</div>

        <div class="form-group">
            <label>Titulaire de la carte</label>
            <input type="text" id="cardName" placeholder="Jean Dupont">
        </div>
        <div class="form-group">
            <label>Numéro de carte</label>
            <input type="text" id="cardNum" placeholder="4242 4242 4242 4242" maxlength="19" oninput="fmtCard(this)">
        </div>
        <div class="card-input">
            <div class="form-group" style="flex:1"><label>Expiration</label><input type="text" id="cardExp" placeholder="MM/AA" maxlength="5" oninput="fmtExp(this)"></div>
            <div class="form-group" style="flex:1"><label>CVV</label><input type="password" id="cardCvv" placeholder="•••" maxlength="3"></div>
        </div>

        <div style="display:flex;gap:12px;margin-top:10px;">
            <button onclick="submitPayment()" id="payBtn" style="flex:1;">💳 Payer <span id="payAmt"></span></button>
            <button onclick="closeModal()" style="flex:0 0 auto;width:auto;background:rgba(255,255,255,.06);color:var(--text);border:1px solid var(--overlay);box-shadow:none;">Annuler</button>
        </div>
    </div>
</div>

<main class="main-container">
    <div class="page-header"><h1>Commander</h1><p>Choisissez vos plats et passez votre commande</p></div>

    <?= $message ?>

    <?php if (!$orderSuccess): ?>
    <div class="order-layout">
        <!-- Grille des plats -->
        <div>
            <div class="menu-grid">
            <?php foreach ($plats as $pid => $p): ?>
                <div class="dish-card">
                    <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php endif; ?>
                    <div class="dish-body">
                        <div class="dish-name"><?= htmlspecialchars($p['name']) ?><?= ($p['is_vegetarian']??false) ? ' <span style="color:var(--softlime);font-size:.75em;">🌱</span>' : '' ?></div>
                        <div class="dish-price"><?= number_format($p['price'],2,',',' ') ?> €</div>
                        <p style="color:var(--text-muted);font-size:.82rem;line-height:1.4;"><?= htmlspecialchars(mb_strimwidth($p['text_description'],0,70,'…')) ?></p>
                        <div class="qty-ctrl">
                            <button class="qty-btn" onclick="changeQty('<?= $pid ?>',<?= $p['price'] ?>,'<?= addslashes($p['name']) ?>',-1)">−</button>
                            <span class="qty-val" id="qty-<?= $pid ?>">0</span>
                            <button class="qty-btn" onclick="changeQty('<?= $pid ?>',<?= $p['price'] ?>,'<?= addslashes($p['name']) ?>',1)">+</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- Panier -->
        <div class="cart-panel">
            <h2 style="color:var(--sapphire);margin-bottom:18px;">🛒 Mon Panier</h2>
            <div id="cartItems"><p style="color:var(--text-muted);font-style:italic;font-size:.88rem;">Aucun article pour l'instant.</p></div>
            <div class="cart-total" id="cartTotal" style="display:none;">
                <span>Total</span><span id="totalVal">0,00 €</span>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <label>Adresse de livraison</label>
                <input type="text" id="deliveryAddr" value="<?= htmlspecialchars($savedAddress) ?>" placeholder="5 rue de la Paix…">
            </div>

            <button id="orderBtn" onclick="openPayment()" disabled style="opacity:.4;">Procéder au paiement</button>

            <!-- Formulaire caché soumis après "paiement" -->
            <form id="orderForm" method="POST" style="display:none;">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="cart_items" id="cartData">
                <input type="hidden" name="delivery_address" id="addrData">
            </form>
        </div>
    </div>

    <?php else: ?>
        <div style="text-align:center;margin-top:20px;">
            <a href="menu.php" class="btn" style="max-width:260px;display:inline-block;">← Retour au menu</a>
        </div>
    <?php endif; ?>
</main>

<script>
const cart = {};
const prices = {};
const names = {};

function changeQty(id, price, name, delta) {
    cart[id] = (cart[id] || 0) + delta;
    if (cart[id] <= 0) { delete cart[id]; }
    prices[id] = price; names[id] = name;
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const totalDiv  = document.getElementById('cartTotal');
    const totalVal  = document.getElementById('totalVal');
    const orderBtn  = document.getElementById('orderBtn');
    let html = '', total = 0, count = 0;

    for (const id in cart) {
        const q = cart[id]; total += prices[id]*q; count += q;
        html += `<div class="cart-item"><span>${names[id]} ×${q}</span><span style="color:var(--softlime);">${(prices[id]*q).toFixed(2).replace('.',',')} €</span></div>`;
        document.getElementById('qty-'+id).textContent = q;
    }
    // reset non-cart
    document.querySelectorAll('.qty-val').forEach(el => {
        const pid = el.id.replace('qty-','');
        if (!cart[pid]) el.textContent = '0';
    });

    container.innerHTML = count ? html : '<p style="color:var(--text-muted);font-style:italic;font-size:.88rem;">Aucun article pour l\'instant.</p>';
    totalDiv.style.display = count ? 'flex' : 'none';
    totalVal.textContent = total.toFixed(2).replace('.',',') + ' €';
    orderBtn.disabled = count === 0;
    orderBtn.style.opacity = count ? '1' : '.4';
    document.getElementById('payAmt').textContent = total.toFixed(2).replace('.',',') + ' €';
}

function openPayment() {
    const addr = document.getElementById('deliveryAddr').value.trim();
    if (!addr) { alert('Veuillez entrer une adresse de livraison.'); return; }
    document.getElementById('payModal').classList.add('open');
}
function closeModal() { document.getElementById('payModal').classList.remove('open'); }

function submitPayment() {
    const name = document.getElementById('cardName').value.trim();
    const num  = document.getElementById('cardNum').value.replace(/\s/g,'');
    const exp  = document.getElementById('cardExp').value;
    const cvv  = document.getElementById('cardCvv').value;

    if (!name || num.length < 16 || exp.length < 5 || cvv.length < 3) {
        alert('Veuillez remplir tous les champs de paiement.'); return;
    }

    const btn = document.getElementById('payBtn');
    btn.textContent = '⏳ Traitement…'; btn.disabled = true;

    // Simuler délai bancaire
    setTimeout(() => {
        document.getElementById('cartData').value  = JSON.stringify(cart);
        document.getElementById('addrData').value  = document.getElementById('deliveryAddr').value;
        closeModal();
        document.getElementById('orderForm').submit();
    }, 1800);
}

function fmtCard(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
}
function fmtExp(el) {
    let v = el.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.substring(0,2)+'/'+v.substring(2,4);
    el.value = v;
}
</script>
</body>
</html>
