<?php
session_start();

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userRole   = $_SESSION['user_role'] ?? 'client';
if (!$isLoggedIn || !in_array($userRole, ['livreur', 'admin'])) {
    header("Location: connect.php"); exit();
}

$allorders      = json_decode(file_get_contents('commandes.json'), true);
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$statusPrepared = 2; $statusDelivery = 3; $statusDone = 4;

if (isset($_POST['change_status'])) {
    updateOrderStatus($_POST['order_id']);
    header("Location: livreur.php?filter=" . $selectedFilter); exit;
}

if (isset($_POST['cancel_delivery'])) {
    updateOrderStatus($_POST['order_id'], -1);
    header("Location: livreur.php?filter=" . $selectedFilter); exit;
}

$ordersToPickUp  = array_filter($allorders, fn($o) => $o['ready'] == $statusPrepared);
$ordersInTransit = array_filter($allorders, fn($o) => $o['ready'] == $statusDelivery);
$ordersFinished  = array_filter($allorders, fn($o) => $o['ready'] == $statusDone);

function updateOrderStatus($orderId, $status = 1) {
    $data = json_decode(file_get_contents('commandes.json'), true);
    if (isset($data[$orderId]) && $data[$orderId]['ready'] < 4) {
        $data[$orderId]['ready'] += $status;
        file_put_contents('commandes.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function orderToCard($order, $id) {
    $currentFilter = $_GET['filter'] ?? 'all';
    $statusText = match($order['ready']) {
        2 => 'Prête (Attente ramassage)', 3 => 'En livraison', 4 => 'Livrée ✅', default => 'En préparation…'
    };
    $nextAction = match($order['ready']) {
        2 => 'Prendre en livraison', 3 => 'Marquer comme Livrée', default => null
    };
    $address  = htmlspecialchars($order['adress'] ?? '');
    $destHour = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);
    $items    = htmlspecialchars(implode(', ', $order['commands']));
    $mapUrl   = "https://www.google.com/maps/search/?api=1&query=" . urlencode($order['adress'] ?? '');

    $button = "";
    $secondButton = "";
    if ($nextAction) {
        $btnColor = $order['ready'] == 2 ? '#f59e0b' : 'var(--softlime)';
        $button = "<form method='POST' action='livreur.php?filter=$currentFilter'>
                       <input type='hidden' name='order_id' value='$id'>
                       <button type='submit' name='change_status' class='btn' style='background:$btnColor;color:#0a0a1a;'>$nextAction</button>
                   </form>";
        if ($nextAction == 'Marquer comme Livrée') {
            $secondButton = "<br><form method='POST' action='livreur.php?filter=$currentFilter'>
                            <input type='hidden' name='order_id' value='$id'>
                            <button type='submit' name='cancel_delivery' class='btn' style='background:#e50000;color:#0a0a1a;'>Abandonner la livraison</button>
                        </form>";
        }
    } elseif ($order['ready'] == 4) {
        $button = "<div class='btn' style='background:linear-gradient(135deg,var(--softlime),#5aab85);color:#0a0a1a;border:none;'>Livraison Terminée ✅</div>";
    }

    $statusColor = match($order['ready']) { 2=>'var(--softlime)', 3=>'var(--sapphire)', 4=>'var(--mauve)', default=>'var(--text-muted)' };

    return "<div class='order-card'>
        <h3 style='color:var(--sapphire);margin-bottom:10px;'>Commande #$id</h3>
        <p><strong>📍 Adresse :</strong> $address</p>
        <a href='$mapUrl' target='_blank' style='display:inline-block;margin:8px 0;color:var(--sapphire);font-size:.85rem;text-decoration:none;'>📍 Ouvrir dans Maps →</a>
        <p><strong>Statut :</strong> <span style='color:$statusColor;'>$statusText</span></p>
        <p><strong>Livraison prévue :</strong> $destHour</p>
        <p style='margin-top:8px;'><strong>Plats :</strong> $items</p>
        <br>$button $secondButton
    </div>";
}

function getOrders($orders) {
    $html = "";
    foreach ($orders as $id => $order) {
        if ($order['ready'] >= 2) $html .= orderToCard($order, $id);
    }
    return $html;
}
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

    <section class="search" style="max-width:850px;margin-bottom:40px;width:100%;">
        <form method="GET" action="livreur.php" class="lined">
            <div class="form-group">
                <label>Filtrer par statut</label>
                <select name="filter" style="width:100%;padding:12px;border-radius:8px;background-color:var(--base);color:white;border:1px solid var(--overlay);font-family:inherit;cursor:pointer;">
                    <option value="all"        <?= $selectedFilter=='all'        ?'selected':'' ?>>Tout voir</option>
                    <option value="to-pickup"  <?= $selectedFilter=='to-pickup'  ?'selected':'' ?>>À récupérer (Prêtes)</option>
                    <option value="in-transit" <?= $selectedFilter=='in-transit' ?'selected':'' ?>>En livraison</option>
                    <option value="delivered"  <?= $selectedFilter=='delivered'  ?'selected':'' ?>>Livrées</option>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn">Filtrer</button></div>
        </form>
    </section>

    <div class="orders">
        <?php
        $rendered = match($selectedFilter) {
            'to-pickup'  => getOrders($ordersToPickUp),
            'in-transit' => getOrders($ordersInTransit),
            'delivered'  => getOrders($ordersFinished),
            default      => getOrders($allorders),
        };
        echo $rendered ?: "<p style='color:var(--text-muted);'>Aucune commande disponible.</p>";
        ?>
    </div>
</main>
</body>
</html>
