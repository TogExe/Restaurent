<?php
require_once __DIR__ . '/inc/common.php';

require_login(['livreur','admin']);

$allorders      = load_json('commandes.json');
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$statusPrepared = 2; $statusDelivery = 3; $statusDone = 4;

if (isset($_POST['change_status'])) {
    updateOrderStatus($_POST['order_id']);
    header("Location: livreur.php?filter=" . $selectedFilter); exit;
}

$ordersToPickUp  = array_filter($allorders, fn($o) => $o['ready'] == $statusPrepared);
$ordersInTransit = array_filter($allorders, fn($o) => $o['ready'] == $statusDelivery);
$ordersFinished  = array_filter($allorders, fn($o) => $o['ready'] == $statusDone);

function updateOrderStatus($orderId) {
    $data = load_json('commandes.json');
    if (isset($data[$orderId]) && $data[$orderId]['ready'] < 4) {
        $data[$orderId]['ready'] += 1;
        save_json('commandes.json', $data);
    }
}

function orderToCard($order, $id) {
    $currentFilter = $_GET['filter'] ?? 'all';
    $statusText = NULL;
    switch ($order['ready']) {
        case 2:
            $statusText = 'Prête (Attente ramassage)';
            break;
        case 3:
            $statusText = 'En livraison';
            break;
        case 4:
             $statusText = 'Livrée ✅';
            break;
        default:
         $statusText = 'En préparation…';
    }
    $nextAction = NULL;

    switch ($order['ready']){
        case 2:
            $nextAction = 'Prendre en livraison';
            break;
        case 3:
            $nextAction =  'Marquer comme Livrée';
            break;
        default:
            $nextAction = null;

    }
    $address  = htmlspecialchars($order['adress'] ?? '');
    $destHour = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);
    $items    = htmlspecialchars(implode(', ', $order['commands']));
    $mapUrl   = "https://www.google.com/maps/search/?api=1&query=" . urlencode($order['adress'] ?? '');

    $button = "";
    if ($nextAction) {
        $btnColor = $order['ready'] == 2 ? '#f59e0b' : 'var(--softlime)';
        $button = "<form method='POST' action='livreur.php?filter=$currentFilter'>
                       <input type='hidden' name='order_id' value='$id'>
                       <button type='submit' name='change_status' class='btn' style='background:$btnColor;color:#0a0a1a;'>$nextAction</button>
                   </form>";
    } elseif ($order['ready'] == 4) {
        $button = "<div class='btn' style='background:linear-gradient(135deg,var(--softlime),#5aab85);color:#0a0a1a;border:none;'>Livraison Terminée ✅</div>";
    }
    $statusColor = NULL;
    switch ($order['ready']){
        case 2:
            $statusColor = 'var(--softlime)';
            break;
        case 3:
            $statusColor = 'var(--sapphire)';
            break;
        case 4:
            $statusColor = 'var(--mauve)';
            break;
        default:
            $statusColor = 'var(--text-muted)';
    }

    return "<div class='order-card'>
        <h3 style='color:var(--sapphire);margin-bottom:10px;'>Commande #$id</h3>
        <p><strong>📍 Adresse :</strong> $address</p>
        <a href='$mapUrl' target='_blank' style='display:inline-block;margin:8px 0;color:var(--sapphire);font-size:.85rem;text-decoration:none;'>📍 Ouvrir dans Maps →</a>
        <p><strong>Statut :</strong> <span style='color:$statusColor;'>$statusText</span></p>
        <p><strong>Livraison prévue :</strong> $destHour</p>
        <p style='margin-top:8px;'><strong>Plats :</strong> $items</p>
        <br>$button
    </div>";
}

function getOrders($orders) {
    $html = "";
    foreach ($orders as $id => $order) {
        if ($order['ready'] >= 2) $html .= orderToCard($order, $id);
    }
    return $html;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
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
        $rendered = NULL;
        switch ($selectedFilter){
            case 'to-pickup':
                $rendered = getOrders($ordersToPickUp);
                break;
            case 'in-transit':
                $rendered = getOrders($ordersInTransit);
                break;
            case 'delivered':
                $rendered = getOrders($ordersFinished);
                break;
            default:
                $rendered = getOrders($allorders);
        }
        echo $rendered ?: "<p style='color:var(--text-muted);'>Aucune commande disponible.</p>";
        ?>
    </div>
</main>
</body>
</html>
