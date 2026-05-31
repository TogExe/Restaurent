<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
require_login(['livreur','admin']);

// Récupération de l'identité et du rôle
$uid  = current_user_id() ?? $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'livreur';

$allorders      = load_json('commandes.json');
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$statusPrepared = 2;
$statusDelivery = 3;
$statusDone     = 4;

$isAjax = (isset($_POST['ajax']) && $_POST['ajax']) || (isset($_GET['ajax']) && $_GET['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// --- 1. ACTION : PRENDRE EN CHARGE UNE COMMANDE ORPHELINE ---
if (isset($_POST['take_charge']) && isset($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    $data = load_json('commandes.json');

    // On s'assure que la commande existe et qu'elle n'a toujours pas de livreur (sécurité anti-double clic)
    if (isset($data[$orderId]) && empty($data[$orderId]['livreur_id'])) {
        $data[$orderId]['livreur_id'] = $uid;
        save_json('commandes.json', $data);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Commande assignée avec succès.']);
            exit;
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Trop tard, un autre livreur a déjà pris cette commande.']);
            exit;
        }
    }
    header("Location: livreur.php?filter=" . $selectedFilter);
    exit;
}

// --- 2. ACTION : METTRE À JOUR LE STATUT (Existant) ---
function updateOrderStatus($orderId, $userId, $userRole) {
    $data = load_json('commandes.json');
    if (isset($data[$orderId])) {
        $order = $data[$orderId];
        // Le livreur doit être le propriétaire pour changer le statut
        $isAssigned = ($userRole === 'admin' || ($order['livreur_id'] ?? '') === $userId);
        
        if ($isAssigned && $order['ready'] >= 2 && $order['ready'] < 4) {
            $data[$orderId]['ready'] += 1;
            save_json('commandes.json', $data);
            return $data[$orderId]['ready'];
        }
    }
    return null;
}

if (isset($_POST['change_status']) && isset($_POST['order_id'])) {
    $newStatus = updateOrderStatus($_POST['order_id'], $uid, $role);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $newStatus !== null,
            'order_id' => $_POST['order_id'],
            'ready' => $newStatus
        ]);
        exit;
    }
    header("Location: livreur.php?filter=" . $selectedFilter);
    exit;
}

// --- 3. LOGIQUE DE FILTRAGE PAR LIVREUR ---
$myOrders = [];
foreach ($allorders as $oid => $o) {
    $status = $o['ready'] ?? 0;
    
    // On isole uniquement les commandes prêtes ou au-delà
    if ($status >= $statusPrepared) {
        $assignedTo = $o['livreur_id'] ?? '';
        
        if ($role === 'admin') {
            $myOrders[$oid] = $o;
        } else {
            // Le livreur voit ses commandes OU les commandes orphelines qui attendent un ramassage (statut 2)
            if ($assignedTo === $uid || (empty($assignedTo) && $status == $statusPrepared)) {
                $myOrders[$oid] = $o;
            }
        }
    }
}

$ordersToPickUp  = array_filter($myOrders, fn($o) => $o['ready'] == $statusPrepared);
$ordersInTransit = array_filter($myOrders, fn($o) => $o['ready'] == $statusDelivery);
$ordersFinished  = array_filter($myOrders, fn($o) => $o['ready'] == $statusDone);

// --- 4. GÉNÉRATION DES CARTES HTML ---
function orderToCard($order, $id) {
    global $uid, $role; // Rend ces variables accessibles dans la fonction
    
    $currentFilter = $_GET['filter'] ?? 'all';

    $statusText = match($order['ready']) {
        2 => 'Prête (Attente ramassage)',
        3 => 'En livraison',
        4 => 'Livrée ✅',
        default => 'En préparation…'
    };

    $nextAction = match($order['ready']) {
        2 => 'Prendre en livraison',
        3 => 'Marquer comme Livrée',
        default => null
    };

    $assignedTo = $order['livreur_id'] ?? '';
    $isOrphan = empty($assignedTo);

    $address  = htmlspecialchars($order['adress'] ?? '');
    $destHour = isset($order['des_t']) ? substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5) : '--:--';
    $items = htmlspecialchars(implode(', ', $order['commands'] ?? []));

    $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($order['adress'] ?? '');

    $statusClass = match($order['ready']) {
        2 => 'delivery-status-ready',
        3 => 'delivery-status-transit',
        4 => 'delivery-status-done',
        default => 'delivery-status-default'
    };

    // Logique d'affichage des boutons
    $button = "";
    
    if ($isOrphan && $role !== 'admin') {
        // Bouton spécial si la commande n'a pas de livreur
        $button = "
            <form method='POST' action='livreur.php?filter=$currentFilter' style='margin:0;'>
                <input type='hidden' name='order_id' value='$id'>
                <input type='hidden' name='take_charge' value='1'>
                <button type='submit' class='btn' style='background: var(--sapphire); border-color: var(--sapphire); color: white; width: 100%;'>
                    🙋‍♂️ Prendre en charge
                </button>
            </form>
        ";
    } elseif ($nextAction && ($assignedTo === $uid || $role === 'admin')) {
        // Bouton classique de changement de statut si on est le propriétaire
        $buttonClass = $order['ready'] == 2 ? 'delivery-btn-pickup' : 'delivery-btn-finished';
        $button = "
            <form method='POST' action='livreur.php?filter=$currentFilter' style='margin:0;'>
                <input type='hidden' name='order_id' value='$id'>
                <input type='hidden' name='change_status' value='1'>
                <button type='submit' class='btn $buttonClass' style='width: 100%;'>
                    $nextAction
                </button>
            </form>
        ";
    } elseif ($order['ready'] == 4) {
        $button = "
            <div class='btn delivery-complete-badge' style='background: var(--softlime); color: var(--background); pointer-events: none; width: 100%;'>
                Livraison Terminée ✅
            </div>
        ";
    }

    $adminBadge = "";
    if ($role === 'admin') {
        $assignTxt = $isOrphan ? "<span style='color:var(--danger);'>Orpheline (Aucun)</span>" : "Assigné à: " . htmlspecialchars(substr($assignedTo, 0, 8));
        $adminBadge = "<p style='font-size: 0.8rem; color: var(--mauve); margin-top: -10px; margin-bottom: 10px;'>🛵 $assignTxt</p>";
    }

    return "
        <div class='order-card'>
            <h3 class='delivery-order-title'>Commande #$id</h3>
            $adminBadge
            <p><strong>📍 Adresse :</strong> $address</p>
            <a href='$mapUrl' target='_blank' class='maps-link'>📍 Ouvrir dans Maps →</a>
            <p><strong>Statut :</strong> <span class='$statusClass'>$statusText</span></p>
            <p><strong>Livraison prévue :</strong> $destHour</p>
            <p class='delivery-order-items'><strong>Plats :</strong> $items</p>
            <br>
            $button
        </div>
    ";
}

function getOrders($orders) {
    $html = "";
    foreach ($orders as $id => $order) {
        $html .= orderToCard($order, $id);
    }
    return $html;
}

$isLoggedIn = true;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Livreur</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header">
        <h1>🛵 Espace Livreur</h1>
        <p>Gérez vos livraisons en temps réel</p>
    </div>

    <section class="search search-panel">
        <form method="GET" action="livreur.php" class="lined">
            <div class="form-group">
                <label>Filtrer par statut</label>
                <select name="filter" class="filter-select">
                    <option value="all" <?= $selectedFilter == 'all' ? 'selected' : '' ?>>Tout voir</option>
                    <option value="to-pickup" <?= $selectedFilter == 'to-pickup' ? 'selected' : '' ?>>À récupérer (Prêtes)</option>
                    <option value="in-transit" <?= $selectedFilter == 'in-transit' ? 'selected' : '' ?>>En livraison</option>
                    <option value="delivered" <?= $selectedFilter == 'delivered' ? 'selected' : '' ?>>Livrées</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Filtrer</button>
            </div>
        </form>
    </section>

    <div class="orders">
        <?php
        $rendered = match($selectedFilter) {
            'to-pickup'  => getOrders($ordersToPickUp),
            'in-transit' => getOrders($ordersInTransit),
            'delivered'  => getOrders($ordersFinished),
            default      => getOrders($myOrders),
        };

        echo $rendered ?: "
            <p class='empty-orders'>
                Aucune commande disponible ou assignée actuellement.
            </p>
        ";
        ?>
    </div>
</main>

<script src="scripts.js" defer></script>
</body>
</html>