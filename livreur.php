<?php
session_start();

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$allorders = json_decode(file_get_contents('commandes.json'), true);
$currentPage = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$statusPrepared = 2; 
$statusDelivery = 3;   
$statusDone = 4;       

if (isset($_POST['change_status'])) {
    $orderId = $_POST['order_id'];
    updateOrderStatus($orderId);
    header("Location: livreur.php?filter=" . $selectedFilter);
    exit;
}

$ordersToPickUp = array_filter($allorders, function($order) use ($statusPrepared) {
    return $order['ready'] == $statusPrepared;
});

$ordersInTransit = array_filter($allorders, function($order) use ($statusDelivery) {
    return $order['ready'] == $statusDelivery;
});

$ordersFinished = array_filter($allorders, function($order) use ($statusDone) {
    return $order['ready'] == $statusDone;
});

function updateOrderStatus($orderId) {
    $data = json_decode(file_get_contents('commandes.json'), true);
    if (isset($data[$orderId])) {
        $data[$orderId]['ready'] += 1; 
        file_put_contents('commandes.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function orderToCard($order, $id) {
    $statusText = "";
    $nextAction = "";
    $currentFilter = $_GET['filter'] ?? 'all';

    switch ($order['ready']) {
        case 2:
            $statusText = 'Prête (Attente ramassage)';
            $nextAction = 'Prendre en livraison';
            break;
        case 3:
            $statusText = 'En cours de livraison';
            $nextAction = 'Marquer comme Livrée';
            break;
        case 4:
            $statusText = 'Livrée ✅';
            $nextAction = null;
            break;
        default:
            $statusText = 'En préparation...'; 
            $nextAction = null;
    }

    $address = $order['adress'] ?? 'Adresse non spécifiée';
    $destHour = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);
    $itemsString = implode(', ', $order['commands']);
    if ($destHour == substr($order['comm_t'], strpos($order['comm_t'], '-') + 1, 5)) {
        $destHour = 'Immédiate';
    }

    $button = "";
    if ($nextAction) {
        $button = "
        <form method='POST' action='livreur.php?filter=$currentFilter'>
            <input type='hidden' name='order_id' value='$id'>
            <button type='submit' name='change_status' class='btn' style='background-color: #f59e0b;'>
                $nextAction
            </button>
        </form>";
    } else if ($order['ready'] == 4) {
        $button = "<div class='btn' style='background-color: #10b981; border:none;'>Livraison Terminée</div>";
    }

    $address = $order['adress'] ?? '';
    $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($address);

    $mapsButton = "";
    if (!empty($address)) {
        $mapsButton = "
        <a href='$mapUrl' target='_blank' class='btn-maps' style='text-decoration: none; display: inline-block; margin-bottom: 10px;'>
            📍 Ouvrir dans google maps
        </a>";
    }


    return "
    <div class='order-card' id='card-$id'>
        <h3>Commande #$id</h3>
        <p><strong>📍 Address:</strong> $address</p>
        
        $mapsButton <p><strong>Status:</strong> $statusText</p>
        <p><strong>Heure de livraison attendue:</strong> $destHour</p>
        <p><strong>Plats:</strong> $itemsString</p>
        <br>
        $button
    </div>";
}

function getOrders($orders) {
    $html = "";
    foreach ($orders as $id => $order) {
        
        if ($order['ready'] >= 2 && explode('-', $order['comm_t'])[0] == date("j/m/Y")) {
            $html .= orderToCard($order, $id);
        }
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commandes - Livreur</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="logo"><h2>Restaurant</h2></div>
        <ul class="nav-links">
            <li><a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">Accueil</a></li>
            <li><a href="menu.php" class="<?= $currentPage == 'menu.php' ? 'active' : '' ?>">Menu</a></li>
            <?php if ($isLoggedIn): ?>
                <li><a href="profil.php" class="<?= $currentPage == 'profil.php' ? 'active' : '' ?>">Mon Profil</a></li>
                <li><a href="connect.php?logout=1" class="logout-link">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="connect.php" class="<?= in_array($currentPage, ['connect.php', 'compte.php']) ? 'active' : '' ?>">Se Connecter</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <main class="main-container">
        <div class="page-header">
            <p>Gérez vos livraisons en temps réel</p>
        </div>

        <section class="search" style="max-width: 850px; margin-bottom: 60px;">
            <form method="GET" action="livreur.php" class="lined">
                <div class="form-group">
                    <label for="filter">Filtrer par:</label>
                    <select id="filter" name="filter" style="width: 100%; padding: 12px; border-radius: 8px; background-color: var(--base); color: white; border: 1px solid var(--overlay); font-family: inherit; cursor: pointer;">
                        <option value="all" <?= $selectedFilter == 'all' ? 'selected' : '' ?>>Tout voir</option>
                        <option value="to-pickup" <?= $selectedFilter == 'to-pickup' ? 'selected' : '' ?>>À récupérer (Prêtes)</option>
                        <option value="in-transit" <?= $selectedFilter == 'in-transit' ? 'selected' : '' ?>>En livraison</option>
                        <option value="delivered" <?= $selectedFilter == 'delivered' ? 'selected' : '' ?>>Livré</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="search-button">Filtrer</button>
                </div>
            </form>
        </section>

        <div class="orders">
            <?php
                if($selectedFilter == 'to-pickup') {
                    echo getOrders($ordersToPickUp) ?: "<p>Rien à récupérer.</p>";
                }
                elseif($selectedFilter == 'in-transit') {
                    echo getOrders($ordersInTransit) ?: "<p>Aucune livraison en cours.</p>";
                }
                elseif($selectedFilter == 'delivered') {
                    echo getOrders($ordersFinished) ?: "<p>Aucune commande livrée aujourd'hui.</p>";
                }
                else {
                    echo getOrders($allorders) ?: "<p>Aucune commande disponible.</p>";
                }
            ?>
        </div>
    </main>
</body>
</html>