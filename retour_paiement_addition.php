<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php';

$vendeur_code = 'MI-1_A'; 
$api_key = getAPIKey($vendeur_code);

// Read and validate CYBank callback parameters for addition order payment
$transaction = $_GET['transaction'] ?? '';
$montant     = $_GET['montant'] ?? '';
$vendeur     = $_GET['vendeur'] ?? '';
$statut      = $_GET['statut'] ?? $_GET['status'] ?? '';
$control_get = $_GET['control'] ?? '';

// Signature verification
$strToHash = $api_key . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $statut . "#";
$control_calc = md5($strToHash);

$message = "";
$success = false;

if ($control_get === $control_calc && $vendeur === $vendeur_code) {
    $orderFile = 'data/commandes.json';
    $allOrders = load_json($orderFile);
    
    if (isset($allOrders[$transaction])) {
        $additionOrder = $allOrders[$transaction];
        
        // Only merge orders that were flagged as a post-order addition
        if (isset($additionOrder['is_addition']) && $additionOrder['is_addition'] === true) {
            $parentOrderId = $additionOrder['parent_order_id'];
            
            if (isset($allOrders[$parentOrderId])) {
                if ($statut === 'accepted') {
                    // 1. Merge commands (additions) into the original parent order
                    $newCommands = array_merge(
                        $allOrders[$parentOrderId]['commands'] ?? [], 
                        $additionOrder['commands'] ?? []
                    );
                    $allOrders[$parentOrderId]['commands'] = $newCommands;
                    
                    // 2. Add the price difference to the parent order total price
                    $allOrders[$parentOrderId]['price'] = round(
                        ($allOrders[$parentOrderId]['price'] ?? 0) + ($additionOrder['price'] ?? 0), 
                        2
                    );
                    
                    // 3. Remove the temporary addition transaction
                    unset($allOrders[$transaction]);
                    save_json($orderFile, $allOrders);
                    
                    $success = true;
                    $message = "🎉 <strong>Paiement de la différence accepté !</strong><br><br>"
                        . "Les plats additionnels ont été ajoutés avec succès à votre commande <strong>#{$parentOrderId}</strong>.<br>"
                        . "Montant de la différence réglé : " . htmlspecialchars($montant) . " €<br>"
                        . "Nouveau montant total de la commande : " . number_format($allOrders[$parentOrderId]['price'], 2, ',', ' ') . " €";
                } else {
                    // Payment declined
                    unset($allOrders[$transaction]);
                    save_json($orderFile, $allOrders);
                    
                    $message = "❌ <strong>Le paiement de la différence a été refusé.</strong><br><br>"
                        . "La transaction a été rejetée. Aucun plat n'a été ajouté à votre commande initiale.";
                }
            } else {
                $message = "⚠️ Erreur critique : La commande d'origine #{$parentOrderId} est introuvable.";
            }
        } else {
            $message = "⚠️ Cette transaction n'est pas répertoriée comme un ajout de plats.";
        }
    } else {
        $message = "⚠️ Référence de transaction d'addition introuvable.";
    }
} else {
    $message = "🛡️ <strong>Erreur critique de sécurité :</strong> Signature numérique invalide.";
}

$currentPage = 'retour_paiement_addition.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi de votre ajout de plats — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header">
        <h1>Résultat de votre ajout de plats</h1>
        <p>Retour sécurisé de la passerelle de règlement CYBank</p>
    </div>

    <div style="max-width: 600px; margin: 40px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center;">
        <div class="<?= $success ? 'msg-success' : 'msg-error' ?>" style="text-align:left;">
            <?= $message ?>
        </div>
        
        <div style="margin-top: 35px; display: flex; justify-content: center; gap: 15px;">
            <a href="mes_commandes.php" class="btn" style="text-decoration: none; display: inline-block;">Voir mes commandes</a>
            <a href="menu.php" class="btn" style="text-decoration: none; display: inline-block; background: var(--overlay); color: var(--text);">Retourner au menu</a>
        </div>
    </div>
</main>

</body>
</html>

