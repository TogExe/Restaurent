<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php'; 

// CONFIGURATION CYBANK
$vendeur = 'MI-2_D'; 

$platsFile  = 'plats.json';
$orderFile  = 'commandes.json';
$plats      = load_json($platsFile);
$allOrders  = load_json($orderFile);

$usersFile = 'users.json';
$allUsers  = load_json($usersFile);
$uid       = current_user_id();
$secretKey = current_secret_key();

// --- CORRECTION : Récupération et formatage propre de l'adresse de l'utilisateur ---
$savedAddress = '';
if ($uid && isset($allUsers[$uid]['address_enc'])) {
    $address_raw = decryptData($allUsers[$uid]['address_enc'], $secretKey);
    if ($address_raw !== '') {
        $decoded = json_decode($address_raw, true);
        if (is_array($decoded)) {
            // C'est un JSON, on concatène les parties pour l'input text
            $parts = [];
            if (!empty($decoded['number'])) $parts[] = $decoded['number'];
            if (!empty($decoded['street'])) $parts[] = $decoded['street'];
            if (!empty($decoded['complement'])) $parts[] = $decoded['complement'];
            $addressL1 = implode(' ', $parts);
            
            $cityParts = [];
            if (!empty($decoded['postal'])) $cityParts[] = $decoded['postal'];
            if (!empty($decoded['city'])) $cityParts[] = $decoded['city'];
            $addressL2 = implode(' ', $cityParts);
            
            // Combine rue et ville
            $savedAddress = trim($addressL1 . (!empty($addressL1) && !empty($addressL2) ? ', ' : '') . $addressL2);
        } else {
            // C'est une simple chaîne
            $savedAddress = $address_raw;
        }
    }
}
// -------------------------------------------------------------------------------------

ensure_ban();

$message = "";

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
                for ($i = 0; $i < $qty; $i++) {
                    $names[] = $plats[$pid]['name'];
                }
            }
        }

        $orderId = strtoupper(substr(md5(uniqid(rand(), true)), 0, 16));
        $now     = date("j/m/Y-H:i:s");
        $delTime = date("j/m/Y-H:i", strtotime('+30 minutes'));
        
        $montant_str = number_format($total, 2, '.', ''); 

        $allOrders[(string)$orderId] = [
            "adress"    => $address,
            "commands"  => $names,
            "price"     => round($total, 2),
            "comm_t"    => $now,
            "des_t"     => $delTime,
            "paid_id"   => null,
            "ready"     => -1,
            "client_id" => $uid,
        ];

        save_json($orderFile, $allOrders);

        $api_key = getAPIKey($vendeur);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $retour = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/retour_paiement.php';

        $strToHash = $api_key . "#" . $orderId . "#" . $montant_str . "#" . $vendeur . "#" . $retour . "#";
        $control = md5($strToHash);

        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Redirection vers la passerelle de paiement...</title>
            <script>
                // Efface le panier JS après une validation de commande réussie
                sessionStorage.removeItem('restaurantCart');
            </script>
        </head>
        <body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; background-color: #f4f6f8; margin:0;">
            <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 450px;">
                <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                <h2 style="color: #2c3e50; margin-bottom: 10px;">Connexion sécurisée à CYBank...</h2>
                <p style="color: #7f8c8d; font-size: 14px;">Veuillez patienter, nous vous redirigeons vers l'interface externe de règlement règlementaire.</p>
                
                <form id="cybankForm" action="https://www.plateforme-smc.fr/cybank/index.php" method="POST">
                    <input type="hidden" name="transaction" value="<?= htmlspecialchars($orderId) ?>">
                    <input type="hidden" name="montant" value="<?= htmlspecialchars($montant_str) ?>">
                    <input type="hidden" name="vendeur" value="<?= htmlspecialchars($vendeur) ?>">
                    <input type="hidden" name="retour" value="<?= htmlspecialchars($retour) ?>">
                    <input type="hidden" name="control" value="<?= htmlspecialchars($control) ?>">
                </form>
                <script>
                    setTimeout(() => document.getElementById('cybankForm').submit(), 1000);
                </script>
            </div>
            <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
        </body>
        </html>
        <?php
        exit;
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

<main class="main-container">
    <div class="page-header">
        <h1>Commander</h1>
        <p>Choisissez vos plats et passez votre commande</p>
    </div>

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
                <input type="text" id="deliveryAddr" value="<?= htmlspecialchars($savedAddress, ENT_QUOTES) ?>" placeholder="5 rue de la Paix…">
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
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof changeQty === 'function') {
        let cart = JSON.parse(sessionStorage.getItem('restaurantCart')) || {};
        
        for (const [pid, item] of Object.entries(cart)) {
            if (item.qty > 0) {
                for (let i = 0; i < item.qty; i++) {
                    let safeName = item.name.replace(/'/g, "\\'");
                    changeQty(pid, parseFloat(item.price), safeName, 1);
                }
            }
        }
    }
});
</script>

</body>
</html>