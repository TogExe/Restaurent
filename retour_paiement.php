<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php';

// CONFIGURATION CYBANK — DOIT ÊTRE IDENTIQUE AU CODE CONFIGURÉ DANS COMMANDE.PHP [cite: 89]
$vendeur_code = 'MI-1_A'; 

$api_key = getAPIKey($vendeur_code);

// Interception des variables ajoutées à l'URL par l'interface CYBank [cite: 60]
$transaction = $_GET['transaction'] ?? '';
$montant     = $_GET['montant'] ?? '';
$vendeur     = $_GET['vendeur'] ?? '';
$statut      = $_GET['statut'] ?? $_GET['status'] ?? ''; // Supporte 'statut' ou l'alternative d'écriture 'status' [cite: 64, 77]
$control_get = $_GET['control'] ?? '';

// Recalcul strict de la signature attendue d'après la formule de validation de retour CYBank [cite: 66, 67, 68, 69, 70, 71]
$strToHash = $api_key . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $statut . "#";
$control_calc = md5($strToHash);

$message = "";

// Étape clé de sécurité : On compare la signature calculée à celle transmise par CYBank [cite: 19]
if ($control_get === $control_calc && $vendeur === $vendeur_code) {
    $orderFile = 'commandes.json';
    $allOrders = load_json($orderFile);
    
    if (isset($allOrders[$transaction])) {
        if ($statut === 'accepted') { // Le paiement est officiellement validé [cite: 17, 64]
            
            $allOrders[$transaction]['ready']   = 0; // Passe en préparation en cuisine
            $allOrders[$transaction]['paid_id'] = 'CYB-' . strtoupper(substr(md5($transaction), 0, 10)); // Référence bancaire générée
            
            save_json($orderFile, $allOrders);
            
            $message = "<div class='msg-success' style='padding: 20px; border-radius: 5px;'>
                            🎉 <strong>Paiement accepté avec succès !</strong><br><br>
                            Votre commande <strong>#{$transaction}</strong> a été transmise au restaurant.<br>
                            Montant réglé : " . htmlspecialchars($montant) . " €<br>
                            Livraison estimée : " . htmlspecialchars($allOrders[$transaction]['des_t']) . "
                        </div>";
        } else { // Cas d'un paiement refusé ('declined' ou 'denied') [cite: 17, 64, 84]
            
            $allOrders[$transaction]['ready'] = -2; // Code interne pour échec de paiement
            save_json($orderFile, $allOrders);
            
            $message = "<div class='msg-error' style='padding: 20px; border-radius: 5px;'>
                            ❌ <strong>Le paiement a été refusé.</strong><br><br>
                            La transaction a été rejetée par l'établissement bancaire. Votre commande est annulée.
                        </div>";
        }
    } else {
        $message = "<div class='msg-error' style='padding: 20px; border-radius: 5px;'>⚠️ Numéro de transaction introuvable dans la base de données de la boutique.</div>";
    }
} else {
    // Les signatures ne correspondent pas : blocage immédiat (fraude ou mauvaise clé API)
    $message = "<div class='msg-error' style='padding: 20px; border-radius: 5px;'>🛡️ <strong>Erreur critique de sécurité :</strong> La signature numérique de validation est invalide ou corrompue.</div>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi de votre transaction — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header">
        <h1>Résultat de votre commande</h1>
        <p>Suivi en direct de la passerelle de paiement CYBank</p>
    </div>

    <div style="max-width: 600px; margin: 40px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center;">
        <?= $message ?>
        
        <div style="margin-top: 35px;">
            <a href="menu.php" class="btn" style="text-decoration: none; display: inline-block;">← Retourner au menu principal</a>
        </div>
    </div>
</main>

</body>
</html>
