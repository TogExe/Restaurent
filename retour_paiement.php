<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php';

$vendeur = 'MI-2_D'; 
$orderFile = 'commandes.json';
$allOrders = load_json($orderFile);

// Récupération des paramètres envoyés par CYBank par l'URL ($_GET)
$transaction = $_GET['transaction'] ?? '';
$montant     = $_GET['montant'] ?? '';
$status      = $_GET['status'] ?? ''; // ATTENTION: C'est bien 'status' d'après la doc de retour
$vendeur_ret = $_GET['vendeur'] ?? '';
$control_ret = $_GET['control'] ?? '';

$message = "";
$success = false;

if (empty($transaction) || empty($montant) || empty($status) || empty($control_ret)) {
    $message = "Paramètres de paiement manquants ou invalides.";
} else {
    // 1. Récupération de la clé API pour recalculer l'empreinte de contrôle
    $api_key = getAPIKey($vendeur);

    // 2. Calcul du hash local selon la formule : md5(api_key#transaction#montant#vendeur#status#)
    $strToHash = $api_key . "#" . $transaction . "#" . $montant . "#" . $vendeur_ret . "#" . $status . "#";
    $local_control = md5($strToHash);

    // 3. Vérification de l'intégrité des données
    if ($local_control !== $control_ret) {
        $message = "Échec de la vérification de sécurité (Hash invalide). La transaction a pu être altérée.";
    } else {
        // Les données proviennent bien de CYBank. On vérifie si la transaction existe chez nous
        if (isset($allOrders[$transaction])) {
            if ($status === 'accepted') {
                $allOrders[$transaction]['ready'] = 0; 
                $allOrders[$transaction]['paid_id'] = bin2hex(random_bytes(8)); 
                $allOrders[$transaction]['status_text'] = "Payé"; // Ajout explicite du statut texte si besoin
                
                save_json($orderFile, $allOrders);
                
                // Modification du message de succès
                $message = "Merci ! Votre paiement a été accepté. Le statut de votre commande est désormais : Payé.";
                $success = true;

                echo "<script>localStorage.removeItem('panier');</script>";
                
            } else {
                // Le paiement a été refusé par la banque (solde insuffisant, rejet...)
                $allOrders[$transaction]['ready'] = -2; // 0 pour refusé
                save_json($orderFile, $allOrders);
                $message = "Le paiement a été refusé par l'établissement bancaire. Veuillez réessayer.";
            }
        } else {
            $message = "Commande introuvable dans notre système informatique.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statut du paiement — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container" style="margin-top: 50px;">
    <div class="<?= $success ? 'msg-success' : 'msg-error' ?>" style="max-width: 600px; margin: 40px auto; padding: 30px; text-align:left;">
        <?php if ($success): ?>
            <h1>✅ Paiement Validé</h1>
            <p style="margin: 20px 0;"><?= htmlspecialchars($message) ?></p>
            <p style="font-size: 0.9rem; color: #888;">Référence de la transaction : <strong><?= htmlspecialchars($transaction) ?></strong></p>
        <?php else: ?>
            <h1>❌ Échec du paiement</h1>
            <p style="margin: 20px 0;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        
        <a href="index.php" class="btn" style="display: inline-block; margin-top: 30px;">Retour à l'accueil</a>
    </div>
</main>
 <?php if ($success): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
            // Vider le panier (on cible les clés les plus courantes pour être sûr)
            localStorage.removeItem('panier');
            localStorage.removeItem('cart');
            console.log("Panier réinitialisé suite au paiement.");
        });
    </script>
    <?php endif; ?>
</body>
</html>