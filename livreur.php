<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
require_login(['livreur','admin']);

$allorders      = load_json('commandes.json');
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$statusPrepared = 2;
$statusDelivery = 3;
$statusDone     = 4;

if (isset($_POST['change_status'])) {
    updateOrderStatus($_POST['order_id']);
    header("Location: livreur.php?filter=" . $selectedFilter);
    exit;
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

    $address  = htmlspecialchars($order['adress'] ?? '');
    $destHour = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);

    $items = htmlspecialchars(
        implode(', ', $order['commands'])
    );

    $mapUrl = "https://www.google.com/maps/search/?api=1&query="
        . urlencode($order['adress'] ?? '');

    $statusClass = match($order['ready']) {
        2 => 'delivery-status-ready',
        3 => 'delivery-status-transit',
        4 => 'delivery-status-done',
        default => 'delivery-status-default'
    };

    $button = "";

    if ($nextAction) {

        $buttonClass = $order['ready'] == 2
            ? 'delivery-btn-pickup'
            : 'delivery-btn-finished';

        $button = "
            <form method='POST'
                  action='livreur.php?filter=$currentFilter'>

                <input type='hidden'
                       name='order_id'
                       value='$id'>

                <button type='submit'
                        name='change_status'
                        class='btn $buttonClass'>
                    $nextAction
                </button>

            </form>
        ";

    } elseif ($order['ready'] == 4) {

        $button = "
            <div class='btn delivery-complete-badge'>
                Livraison Terminée ✅
            </div>
        ";
    }

    return "
        <div class='order-card'>

            <h3 class='delivery-order-title'>
                Commande #$id
            </h3>

            <p>
                <strong>📍 Adresse :</strong>
                $address
            </p>

            <a href='$mapUrl'
               target='_blank'
               class='maps-link'>
                📍 Ouvrir dans Maps →
            </a>

            <p>
                <strong>Statut :</strong>
                <span class='$statusClass'>
                    $statusText
                </span>
            </p>

            <p>
                <strong>Livraison prévue :</strong>
                $destHour
            </p>

            <p class='delivery-order-items'>
                <strong>Plats :</strong>
                $items
            </p>

            <br>

            $button

        </div>
    ";
}

function getOrders($orders) {

    $html = "";

    foreach ($orders as $id => $order) {

        if ($order['ready'] >= 2) {
            $html .= orderToCard($order, $id);
        }
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

    <section class="search search-panel">

        <form method="GET"
              action="livreur.php"
              class="lined">

            <div class="form-group">

                <label>Filtrer par statut</label>

                <select name="filter"
                        class="filter-select">

                    <option value="all"
                        <?= $selectedFilter == 'all' ? 'selected' : '' ?>>
                        Tout voir
                    </option>

                    <option value="to-pickup"
                        <?= $selectedFilter == 'to-pickup' ? 'selected' : '' ?>>
                        À récupérer (Prêtes)
                    </option>

                    <option value="in-transit"
                        <?= $selectedFilter == 'in-transit' ? 'selected' : '' ?>>
                        En livraison
                    </option>

                    <option value="delivered"
                        <?= $selectedFilter == 'delivered' ? 'selected' : '' ?>>
                        Livrées
                    </option>

                </select>

            </div>

            <div class="form-group">
                <button type="submit" class="btn">
                    Filtrer
                </button>
            </div>

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

        echo $rendered ?: "
            <p class='empty-orders'>
                Aucune commande disponible.
            </p>
        ";

        ?>

    </div>

</main>

</body>
</html>
