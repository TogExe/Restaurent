<?php
require_once __DIR__ . '/inc/common.php';

require_login(['cuisinier','admin']);

$allorders      = load_json('commandes.json');
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$paid = 0;
$inProgress = 1;
$prepared = 2;

if (isset($_POST['change_status'])) {
    updateOrderStatus($_POST['order_id']);
    header("Location: cuisinier.php?filter=" . $selectedFilter);
    exit;
}

$preparedOrders   = array_filter($allorders, fn($o) => $o['ready'] == $prepared);
$inProgressOrders = array_filter($allorders, fn($o) => $o['ready'] == $inProgress);
$paidOrders       = array_filter($allorders, fn($o) => $o['ready'] == $paid);

function updateOrderStatus($orderId) {
    $data = load_json('commandes.json');

    if (isset($data[$orderId]) && $data[$orderId]['ready'] < 2) {
        $data[$orderId]['ready'] += 1;
        save_json('commandes.json', $data);
    }
}

function orderToCard($order, $id) {

    $filter = $_GET['filter'] ?? 'all';

    $status = match($order['ready']) {
        0 => 'Payée',
        1 => 'En préparation',
        2 => 'Prête',
        default => 'Inconnu'
    };

    $nextStatus = match($order['ready']) {
        0 => 'En préparation',
        1 => 'Prête',
        default => null
    };

    $commandHour = substr($order['comm_t'], strpos($order['comm_t'], '-') + 1, 5);
    $destHour    = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);

    if ($destHour == $commandHour) {
        $destHour = 'Immédiate';
    }

    $itemsString = htmlspecialchars(implode(', ', $order['commands']));

    $statusClass = match($order['ready']) {
        0 => 'status-paid',
        1 => 'status-progress',
        2 => 'status-ready',
        default => 'status-paid'
    };

    $button = $nextStatus
        ? "
            <form method='POST' action='cuisinier.php?filter=$filter'>
                <input type='hidden' name='order_id' value='$id'>
                <button type='submit' name='change_status' class='btn'>
                    Définir comme $nextStatus
                </button>
            </form>
        "
        : "
            <div class='order-ready-badge'>
                ✓ Commande Prête
            </div>
        ";

    return "
        <div class='order-card'>

            <h3 class='order-title'>
                Commande #$id
            </h3>

            <p>
                <strong>Statut :</strong>
                <span class='$statusClass'>$status</span>
            </p>

            <p>
                <strong>Commandée :</strong>
                $commandHour
            </p>

            <p>
                <strong>Livraison :</strong>
                $destHour
            </p>

            <p class='order-items'>
                <strong>Plats :</strong>
                $itemsString
            </p>

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

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Espace Cuisine</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1>🍳 Espace Cuisine</h1>
        <p>Gérez les commandes à préparer</p>
    </div>

    <section class="search search-panel">

        <form method="GET" action="cuisinier.php" class="lined">

            <div class="form-group">

                <label>Filtrer par statut</label>

                <select name="filter" class="filter-select">

                    <option value="all" <?= $selectedFilter == 'all' ? 'selected' : '' ?>>
                        Toutes
                    </option>

                    <option value="paid" <?= $selectedFilter == 'paid' ? 'selected' : '' ?>>
                        Payées
                    </option>

                    <option value="in-progress" <?= $selectedFilter == 'in-progress' ? 'selected' : '' ?>>
                        En préparation
                    </option>

                    <option value="prepared" <?= $selectedFilter == 'prepared' ? 'selected' : '' ?>>
                        Prêtes
                    </option>

                </select>

            </div>

            <div class="form-group">
                <button type="submit" class="search-button btn">
                    Appliquer
                </button>
            </div>

        </form>

    </section>

    <div class="orders">

        <?php

        $rendered = match($selectedFilter) {
            'paid'        => getOrders($paidOrders),
            'in-progress' => getOrders($inProgressOrders),
            'prepared'    => getOrders($preparedOrders),
            default       => getOrders($allorders),
        };

        echo $rendered ?: "
            <p class='empty-orders'>
                Aucune commande pour ce filtre.
            </p>
        ";

        ?>

    </div>

</main>

</body>
</html>
