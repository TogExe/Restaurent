<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php'; 

// CYBank payment gateway configuration for new order checkout
$vendeur = 'MI-2_D'; 

$platsFile  = 'data/plats.json';
$orderFile  = 'data/commandes.json';
$plats      = load_json($platsFile);
$allOrders  = load_json($orderFile);

$usersFile = 'data/users.json';
$allUsers  = load_json($usersFile);
$uid       = current_user_id();
$secretKey = current_secret_key();

// Build the saved delivery address from the user's profile data
$savedAddress = '';

// Vérifie si l'utilisateur est connecté et possède le bloc "address" dans users.json
if ($uid && isset($allUsers[$uid]['address']) && is_array($allUsers[$uid]['address'])) {
    $addr = $allUsers[$uid]['address'];
    
    // On rassemble les éléments de la rue (Numéro + Rue + Complément)
    $streetParts = [];
    if (!empty($addr['number']))     $streetParts[] = $addr['number'];
    if (!empty($addr['street']))     $streetParts[] = $addr['street'];
    if (!empty($addr['complement'])) $streetParts[] = $addr['complement'];
    $streetLine = implode(' ', $streetParts);
    
    // On rassemble le code postal et la ville
    $cityLine = trim(($addr['postal'] ?? '') . ' ' . ($addr['city'] ?? ''));
    
    // On combine le tout proprement avec une virgule
    $fullAddressParts = [];
    if (!empty($streetLine)) $fullAddressParts[] = $streetLine;
    if (!empty($cityLine))   $fullAddressParts[] = $cityLine;
    
    $savedAddress = implode(', ', $fullAddressParts);
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

        // Création d'un identifiant alphanumérique unique respectant le format [0-9a-zA-Z]{10,24}
        $orderId = strtoupper(substr(md5(uniqid(rand(), true)), 0, 16));
        $now     = date("j/m/Y-H:i:s");
        $delTime = date("j/m/Y-H:i", strtotime('+30 minutes'));
        
        // Formatage obligatoire du montant : 2 chiffres après la virgule avec un point
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

        // Récupération dynamique de la clé d'API secrète via le composant fourni
        $api_key = getAPIKey($vendeur);

        // Détection automatisée de l'URL absolue pour créer le lien de retour vers votre projet
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $retour = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/retour_paiement.php';

        // Calculate the gateway control hash to ensure payment data integrity
        $strToHash = $api_key . "#" . $orderId . "#" . $montant_str . "#" . $vendeur . "#" . $retour . "#";
        $control = md5($strToHash);

        // Rendu immédiat d'une page de transition avec soumission transparente du formulaire vers CYBank
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>Redirection vers la passerelle de paiement...</title>
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
            <script>
                // Soumission automatique dès le chargement de la page de transition
                document.getElementById('cybankForm').submit();
            </script>
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

            <button id="orderBtn" onclick="openPayment()" class="order-btn">
                Procéder au paiement
            </button>

            <form id="orderForm" method="POST" style="display:none;">
                <input type="hidden" name="place_order" value="1">
                <input type="hidden" name="cart_items" id="cartData">
                <input type="hidden" name="delivery_address" id="addrData">
            </form>
        </div>
</main>

</body>
</html>
