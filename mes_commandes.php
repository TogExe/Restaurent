<?php
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/getapikey.php';

ensure_ban();
require_login(['client', 'admin']);

$platsFile  = 'plats.json';
$orderFile  = 'commandes.json';
$plats      = load_json($platsFile);
$allOrders  = load_json($orderFile);
$uid        = current_user_id();

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;

$message = "";

// Check for AJAX request
$isAjax = (isset($_POST['ajax']) && $_POST['ajax']) || (isset($_GET['ajax']) && $_GET['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// --- POST: CANCEL ORDER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel_order'])) {
    $orderId = $_POST['order_id'] ?? '';
    if (isset($allOrders[$orderId])) {
        $order = $allOrders[$orderId];
        // Ensure it belongs to the user and is not yet processed (ready == 0)
        if (($order['client_id'] ?? '') === $uid && ($order['ready'] ?? -1) === 0) {
            unset($allOrders[$orderId]);
            save_json($orderFile, $allOrders);
            $message = "<div class='msg-success'>Votre commande a été annulée et remboursée avec succès.</div>";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Votre commande a été annulée.'
                ]);
                exit();
            }
        } else {
            $message = "<div class='msg-error'>Impossible d'annuler cette commande. Elle a peut-être déjà été prise en charge.</div>";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Impossible d'annuler cette commande."
                ]);
                exit();
            }
        }
    }
}

// --- POST: REMOVE DISH (NOUVEAU) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_dish'])) {
    $orderId = $_POST['order_id'] ?? '';
    $dishIndex = isset($_POST['dish_index']) ? intval($_POST['dish_index']) : -1;
    
    if (isset($allOrders[$orderId])) {
        $order = $allOrders[$orderId];
        
        if (($order['client_id'] ?? '') === $uid && ($order['ready'] ?? -1) === 0 && $dishIndex >= 0 && isset($order['commands'][$dishIndex])) {
            $dishName = $order['commands'][$dishIndex];
            
            // Retrouver le prix du plat pour le déduire
            $priceToSubtract = 0;
            foreach ($plats as $p) {
                if ($p['name'] === $dishName) {
                    $priceToSubtract = floatval($p['price']);
                    break;
                }
            }
            
            // Retirer le plat du tableau
            array_splice($allOrders[$orderId]['commands'], $dishIndex, 1);
            $allOrders[$orderId]['price'] = max(0, round($allOrders[$orderId]['price'] - $priceToSubtract, 2));
            
            $isEmpty = count($allOrders[$orderId]['commands']) === 0;
            
            // Si la commande est vide après suppression, on l'annule complètement
            if ($isEmpty) {
                unset($allOrders[$orderId]);
                $msgTxt = "Dernier article retiré. La commande a été totalement annulée.";
            } else {
                $msgTxt = "Plat retiré avec succès. La différence sera recréditée sur votre compte.";
            }
            
            save_json($orderFile, $allOrders);
            $message = "<div class='msg-success'>{$msgTxt}</div>";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $msgTxt,
                    'is_empty' => $isEmpty
                ]);
                exit();
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Impossible de modifier cette commande."]);
                exit();
            }
        }
    }
}

// --- POST: PREPARE ADDITIONS & REDIRECT TO CYBANK ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['prepare_addition'])) {
    $parentOrderId = $_POST['order_id'] ?? '';
    $additionsData = json_decode($_POST['additions'] ?? '{}', true);
    
    if (isset($allOrders[$parentOrderId])) {
        $parentOrder = $allOrders[$parentOrderId];
        
        if (($parentOrder['client_id'] ?? '') === $uid && ($parentOrder['ready'] ?? -1) === 0) {
            $totalDiff = 0;
            $newDishNames = [];
            
            foreach ($additionsData as $pid => $qty) {
                $qty = intval($qty);
                if ($qty > 0 && isset($plats[$pid])) {
                    $totalDiff += $plats[$pid]['price'] * $qty;
                    for ($i = 0; $i < $qty; $i++) {
                        $newDishNames[] = $plats[$pid]['name'];
                    }
                }
            }
            
            if ($totalDiff > 0 && !empty($newDishNames)) {
                $addId = strtoupper(substr(md5(uniqid(rand(), true)), 0, 16));
                
                $allOrders[(string)$addId] = [
                    "adress"          => $parentOrder['adress'] ?? '',
                    "commands"        => $newDishNames,
                    "price"           => round($totalDiff, 2),
                    "comm_t"          => date("j/m/Y-H:i:s"),
                    "des_t"           => $parentOrder['des_t'] ?? '',
                    "paid_id"         => null,
                    "ready"           => -1,
                    "client_id"       => $uid,
                    "is_addition"     => true,
                    "parent_order_id" => $parentOrderId
                ];
                
                save_json($orderFile, $allOrders);
                
                $vendeur = 'MI-1_A';
                $api_key = getAPIKey($vendeur);
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $retour = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/retour_paiement_addition.php';
                
                $montant_str = number_format($totalDiff, 2, '.', '');
                $strToHash = $api_key . "#" . $addId . "#" . $montant_str . "#" . $vendeur . "#" . $retour . "#";
                $control = md5($strToHash);
                
                ?>
                <!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <title>Redirection de paiement...</title>
                    <script src="scripts.js" defer></script>
                </head>
                <body style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; background-color: #f4f6f8; margin:0;">
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 450px;">
                        <div style="border: 4px solid #f3f3f3; border-top: 4px solid #e74c3c; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                        <h2 style="color: #2c3e50; margin-bottom: 10px;">Paiement de la différence...</h2>
                        <p style="color: #7f8c8d; font-size: 14px;">Redirection vers CYBank pour régler <?= number_format($totalDiff, 2, ',', ' ') ?> €.</p>
                        
                        <form id="cybankForm" action="https://www.plateforme-smc.fr/cybank/index.php" method="POST">
                            <input type="hidden" name="transaction" value="<?= htmlspecialchars($addId) ?>">
                            <input type="hidden" name="montant" value="<?= htmlspecialchars($montant_str) ?>">
                            <input type="hidden" name="vendeur" value="<?= htmlspecialchars($vendeur) ?>">
                            <input type="hidden" name="retour" value="<?= htmlspecialchars($retour) ?>">
                            <input type="hidden" name="control" value="<?= htmlspecialchars($control) ?>">
                        </form>
                    </div>
                    <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
                </body>
                </html>
                <?php
                exit();
            } else {
                $message = "<div class='msg-error'>Sélection d'articles invalide ou nulle.</div>";
            }
        } else {
            $message = "<div class='msg-error'>Impossible d'ajouter des plats à cette commande.</div>";
        }
    }
}

// --- POST: SUBMIT RATING ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_rating'])) {
    $orderId = $_POST['order_id'] ?? '';
    
    if (isset($allOrders[$orderId])) {
        $order = $allOrders[$orderId];
        
        if (($order['client_id'] ?? '') === $uid && ($order['ready'] ?? -1) === 4 && !isset($order['rating'])) {
            $rating = intval($_POST['rating'] ?? 10);
            $comment = trim($_POST['rating_comment'] ?? '');
            
            if ($rating < 0) $rating = 0;
            if ($rating > 10) $rating = 10;
            
            $allOrders[$orderId]['rating'] = $rating;
            $allOrders[$orderId]['rating_comment'] = $comment;
            
            save_json($orderFile, $allOrders);
            $message = "<div class='msg-success'>Merci ! Votre évaluation a été enregistrée avec succès.</div>";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'rating' => $rating,
                    'comment' => $comment,
                    'message' => 'Évaluation enregistrée avec succès.'
                ]);
                exit();
            }
        }
    }
}

// Fetch only user orders
$myOrders = [];
foreach ($allOrders as $oid => $o) {
    if (($o['client_id'] ?? '') === $uid && !isset($o['is_addition'])) {
        $myOrders[$oid] = $o;
    }
}

$statusLabels = [
    -1 => 'En attente de paiement',
     0 => 'Payée (En attente de cuisine)',
     1 => 'En préparation',
     2 => 'Prête',
     3 => 'En livraison',
     4 => 'Livrée ✅'
];

$statusColors = [
    -1 => 'var(--text-muted)',
     0 => 'var(--accent-btn)',
     1 => 'var(--sapphire)',
     2 => 'var(--softlime)',
     3 => 'var(--mauve)',
     4 => 'var(--softlime)'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Commandes — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .order-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.25);
            border-color: rgba(255, 255, 255, 0.15);
        }
        .order-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .order-id { font-size: 1.1rem; font-weight: 700; color: var(--sapphire); }
        .order-status-pill {
            font-size: 0.85rem; padding: 4px 12px; border-radius: 20px;
            border: 1px solid; font-weight: 600; background: rgba(255, 255, 255, 0.03);
        }
        .order-info-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px; margin-bottom: 16px;
        }
        .info-block strong {
            display: block; font-size: 0.8rem; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px;
        }
        .info-block span { font-size: 0.95rem; color: var(--text); }
        .order-actions {
            display: flex; gap: 12px; margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 20px;
        }
        .btn-cancel {
            background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.3);
        }
        .btn-cancel:hover { background: #e74c3c; color: white; box-shadow: 0 0 12px rgba(231, 76, 60, 0.4); }
        .btn-add-dishes {
            background: rgba(52, 152, 219, 0.15); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3);
        }
        .btn-add-dishes:hover { background: #3498db; color: white; box-shadow: 0 0 12px rgba(52, 152, 219, 0.4); }
        
        .additions-panel {
            background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1);
            border-radius: 8px; padding: 20px; margin-top: 16px; display: none;
            animation: fadeIn 0.4s ease forwards;
        }
        .additions-header {
            margin-top: 0; margin-bottom: 16px; color: var(--sapphire); font-size: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px;
        }
        .additions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px; max-height: 250px; overflow-y: auto; padding-right: 8px;
        }
        .addition-item {
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(255, 255, 255, 0.02); padding: 8px 12px;
            border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .add-qty-ctrl { display: flex; align-items: center; gap: 10px; }
        .add-qty-btn {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: var(--text); width: 24px; height: 24px; border-radius: 4px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-weight: bold; transition: all 0.2s;
        }
        .add-qty-btn:hover { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.25); }
        .add-qty-val { font-size: 0.9rem; width: 16px; text-align: center; }
        .additions-checkout {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 12px;
        }
        .diff-text { font-size: 0.95rem; color: var(--text-muted); }
        .diff-val { font-weight: 700; color: var(--softlime); font-size: 1.1rem; }
        
        .dish-list-item {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 6px; font-size: 0.92rem; color: var(--text-muted);
            background: rgba(0,0,0,0.2); padding: 6px 10px; border-radius: 6px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header">
        <h1>📋 Mes Commandes</h1>
        <p>Suivez vos commandes en cours et gérez vos ajouts ou annulations</p>
    </div>

    <div id="messages-container"><?= $message ?></div>

    <div class="orders-list">
        <?php if (empty($myOrders)): ?>
            <div class="glass-panel" style="text-align: center; padding: 40px;">
                <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 1.1rem;">Vous n'avez pas encore passé de commande.</p>
                <a href="commande.php" class="btn" style="text-decoration: none;">Passer ma première commande →</a>
            </div>
        <?php else: ?>
            <?php foreach (array_reverse($myOrders, true) as $oid => $o): 
                $st = $o['ready'] ?? 0;
                $color = $statusColors[$st] ?? 'var(--text-muted)';
                $label = $statusLabels[$st] ?? 'Statut inconnu';
            ?>
                <div class="order-card" id="order-<?= $oid ?>">
                    <div class="order-meta">
                        <span class="order-id">Commande #<?= htmlspecialchars(substr($oid, 0, 10)) ?>...</span>
                        <span class="order-status-pill" style="color: <?= $color ?>; border-color: <?= $color ?>;"><?= htmlspecialchars($label) ?></span>
                    </div>

                    <div class="order-info-grid">
                        <div class="info-block">
                            <strong>Date de Commande</strong>
                            <span><?= htmlspecialchars($o['comm_t'] ?? '') ?></span>
                        </div>
                        <div class="info-block">
                            <strong>Adresse de livraison</strong>
                            <span><?= htmlspecialchars($o['adress'] ?? '') ?></span>
                        </div>
                        <div class="info-block">
                            <strong>Montant Total</strong>
                            <span style="color: var(--softlime); font-weight: 700;" class="order-total-price"><?= number_format($o['price'], 2, ',', ' ') ?> €</span>
                        </div>
                    </div>

                    <div class="info-block" style="margin-bottom: 12px;">
                        <strong>Plats Commandés</strong>
                        <ul style="list-style: none; padding: 0; margin: 6px 0 0 0;">
                            <?php foreach (($o['commands'] ?? []) as $idx => $cName): ?>
                                <li class="dish-list-item">
                                    <span><?= htmlspecialchars($cName) ?></span>
                                    <?php if ($st === 0): ?>
                                        <form method="POST" class="ajax-remove-dish-form" style="margin: 0;">
                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid) ?>">
                                            <input type="hidden" name="dish_index" value="<?= $idx ?>">
                                            <input type="hidden" name="remove_dish" value="1">
                                            <button type="submit" class="btn-danger-sm" style="padding: 2px 8px; font-size: 0.8rem;" title="Retirer ce plat de la commande">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($st === 0): ?>
                        <div class="order-actions">
                            <button type="button" class="btn btn-sm btn-add-dishes" onclick="toggleAdditions('<?= $oid ?>')">➕ Ajouter des plats</button>
                            
                            <form method="POST" class="ajax-cancel-order-form" onsubmit="return confirm('Annuler cette commande et demander le remboursement ?');" style="margin: 0;">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid) ?>">
                                <input type="hidden" name="cancel_order" value="1">
                                <button type="submit" class="btn btn-sm btn-cancel">🗑 Annuler toute la commande</button>
                            </form>
                        </div>
                        
                        <div class="additions-panel" id="additions-panel-<?= $oid ?>">
                            <h4 class="additions-header">Sélectionnez les plats additionnels</h4>
                            <div class="additions-grid">
                                <?php foreach ($plats as $pid => $p): ?>
                                    <div class="addition-item" data-price="<?= $p['price'] ?>" data-pid="<?= $pid ?>">
                                        <div style="font-size: 0.88rem; font-weight: 600;"><?= htmlspecialchars($p['name']) ?> <span style="color: var(--softlime); font-size: 0.78rem; font-weight: normal; margin-left:4px;"><?= number_format($p['price'], 2, ',', ' ') ?> €</span></div>
                                        <div class="add-qty-ctrl">
                                            <button type="button" class="add-qty-btn" onclick="adjustAdditionQty('<?= $oid ?>', '<?= $pid ?>', -1)">−</button>
                                            <span class="add-qty-val" id="qty-add-<?= $oid ?>-<?= $pid ?>">0</span>
                                            <button type="button" class="add-qty-btn" onclick="adjustAdditionQty('<?= $oid ?>', '<?= $pid ?>', 1)">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="additions-checkout">
                                <div class="diff-text">Différence à régler : <span class="diff-val" id="diff-val-<?= $oid ?>">0,00 €</span></div>
                                
                                <form method="POST" id="checkout-additions-form-<?= $oid ?>">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid) ?>">
                                    <input type="hidden" name="prepare_addition" value="1">
                                    <input type="hidden" name="additions" id="additions-data-<?= $oid ?>" value="{}">
                                    <button type="submit" class="btn" style="background: var(--softlime); color: var(--background); border: none; font-weight: bold; padding: 6px 14px; font-size:0.9rem;" id="checkout-add-btn-<?= $oid ?>" disabled>💳 Payer la différence</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="scripts.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-remove-dish-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // On demande confirmation avant de retirer
            if (!confirm('Voulez-vous vraiment retirer ce plat de votre commande ?')) {
                return;
            }

            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;

            const fd = new FormData(form);
            fd.append('ajax', '1');

            try {
                const res = await fetch('mes_commandes.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    // Pour éviter de jongler avec le DOM manuellement (recalcul du prix, décalage des index des arrays),
                    // la méthode la plus robuste et propre est de recharger la page pour refléter le nouveau JSON.
                    window.location.reload();
                } else {
                    alert(data.message || 'Erreur lors du retrait du plat.');
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                alert('Erreur réseau.');
                if (btn) btn.disabled = false;
            }
        });
    });
});
</script>
</body>
</html>