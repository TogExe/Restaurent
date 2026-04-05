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

// --- PREPARER LA COMMANDE ET REDIRIGER VERS CY BANK ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['place_order'])) {
    $items   = json_decode($_POST['cart_items'] ?? '[]', true);
    $address = trim($_POST['delivery_address'] ?? '');

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
                for ($i = 0; $i < $qty; $i++) $names[] = $plats[$pid]['name'];
            }
        }
        
        // Paramètres requis par CY Bank
        $orderId = 'CMD' . rand(100000000, 999999999); // Doit être alphanumérique, 10-24 chars [cite: 148, 149]
        $montant = number_format($total, 2, '.', ''); // Format décimal avec séparateur '.' [cite: 150, 151]
        
        // --- ⚠️ ATTENTION : MODIFIEZ CE CODE VENDEUR AVEC VOTRE GROUPE (ex: MI-1_A) ---
        $vendeur = 'TEST'; // [cite: 167, 206]
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $retour   = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/retour_paiement.php'; // [cite: 168, 169]

        // Générer le hash de contrôle
        require_once 'getapikey.php'; // [cite: 225]
        $api_key = getAPIKey($vendeur); // [cite: 228]
        $control = md5($api_key . "#" . $orderId . "#" . $montant . "#" . $vendeur . "#" . $retour . "#"); // [cite: 154, 155, 156, 157, 158, 159]

        $now     = date("j/m/Y-H:i:s");
        $delTime = date("j/m/Y-H:i", strtotime('+30 minutes'));

        // Sauvegarder la commande en attente de paiement
        $allOrders[(string)$orderId] = [
            "adress"   => $address,
            "commands" => $names,
            "price"    => round($total, 2),
            "comm_t"   => $now,
            "des_t"    => $delTime,
            "ready"    => 0,
            "status"   => "en_attente", // Nouveau statut
            "client_id"=> $uid,
        ];
        file_put_contents($orderFile, json_encode($allOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Redirection automatique vers CY Bank via un formulaire caché
        echo "<!DOCTYPE html><html><body style='background:#111; color:#fff; text-align:center; padding-top:50px; font-family:sans-serif;'>
                <h2>Redirection vers le portail sécurisé CY Bank...</h2>
                <form id='cybank_form' action='https://www.plateforme-smc.fr/cybank/index.php' method='POST'>
                    <input type='hidden' name='transaction' value='$orderId'>
                    <input type='hidden' name='montant' value='$montant'>
                    <input type='hidden' name='vendeur' value='$vendeur'>
                    <input type='hidden' name='retour' value='$retour'>
                    <input type='hidden' name='control' value='$control'>
                </form>
                <script>document.getElementById('cybank_form').submit();</script>
              </body></html>";
        exit();
    }
}
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
        .order-layout{display:grid;grid-template-columns:1fr 320px;gap:30px;align-items:start;max-width:1100px;width:100%;}
        @media(max-width:800px){.order-layout{grid-template-columns:1fr;}}
        .cart-panel{position:sticky;top:88px;background:var(--card-bg);border:1px solid var(--glass-border);border-radius:20px;padding:28px;backdrop-filter:blur(20px);}
        .cart-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.9rem;}
        .cart-item:last-child{border:none;}
        .cart-total{display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;padding-top:14px;border-top:1px solid var(--overlay);color:var(--softlime);}
    </style>
</head>
<body>
<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header"><h1>Commander</h1><p>Choisissez vos plats et passez votre commande</p></div>

    <?= $message ?>

    <div class="order-layout">
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

            <button id="orderBtn" onclick="submitOrder()" disabled style="opacity:.4;">Payer via CY Bank</button>

            <form id="orderForm" method="POST" style="display:none;">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="cart_items" id="cartData">
                <input type="hidden" name="delivery_address" id="addrData">
            </form>
        </div>
    </div>
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

    document.querySelectorAll('.qty-val').forEach(el => {
        const pid = el.id.replace('qty-','');
        if (!cart[pid]) el.textContent = '0';
    });

    container.innerHTML = count ? html : '<p style="color:var(--text-muted);font-style:italic;font-size:.88rem;">Aucun article pour l\'instant.</p>';
    totalDiv.style.display = count ? 'flex' : 'none';
    totalVal.textContent = total.toFixed(2).replace('.',',') + ' €';
    orderBtn.disabled = count === 0;
    orderBtn.style.opacity = count ? '1' : '.4';
}

function submitOrder() {
    const addr = document.getElementById('deliveryAddr').value.trim();
    if (!addr) { alert('Veuillez entrer une adresse de livraison.'); return; }
    
    document.getElementById('cartData').value = JSON.stringify(cart);
    document.getElementById('addrData').value = addr;
    
    const btn = document.getElementById('orderBtn');
    btn.textContent = 'Redirection...'; btn.disabled = true;
    
    document.getElementById('orderForm').submit();
}
</script>
</body>
</html>
