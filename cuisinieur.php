<?php
session_start();

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$allorders = json_decode(file_get_contents('commandes.json'), true);
$currentPage = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';


$paid = 0;
$inProgress = 1;
$prepared = 2;


if (isset($_POST['change_status'])) {
    $orderId = $_POST['order_id'];
    updateOrderStatus($orderId);

    header("Location: cuisinieur.php?filter=" . $selectedFilter);
    exit;
}


$preparedOrders = array_filter($allorders, function($order) use ($prepared) {
    return $order['ready'] == $prepared;
});

$inProgressOrders = array_filter($allorders, function($order) use ($inProgress) {
    return $order['ready'] == $inProgress;
});

$paidOrders = array_filter($allorders, function($order) use ($paid) {
    return $order['ready'] == $paid;
});

function updateOrderStatus($orderId) {
    $data = json_decode(file_get_contents('commandes.json'), true);
    
    if (isset($data[$orderId])) {
        $data[$orderId]['ready'] += 1; // Marquer comme préparée
        file_put_contents('commandes.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
function orderToCard($order, $id) {
    $status = NULL;
    $nextStatus = NULL;
    $button = "";
    switch ($order['ready']) {
        case 0:
            $status = 'Payée';
            $nextStatus = 'En cours de préparation';
            break;
        case 1:
            $status = 'En cours de préparation';
            $nextStatus = 'Préparée';
            break;
        case 2:
            $status = 'Préparée';
            $nextStatus = NULL;
            break;
        default:
            $status = 'Inconnu';

    }
    $commandHour = substr($order['comm_t'], strpos($order['comm_t'], '-') + 1, 5);
    $destHour = substr($order['des_t'], strpos($order['des_t'], '-') + 1, 5);
    if ($destHour == $commandHour) {
        $destHour = 'Immédiate';
    }
    $itemsString = implode(', ', $order['commands']);
    if ($nextStatus) {
        $button = "
        <form method='POST' action='cuisinieur.php?filter=$_GET[filter]'>
            <input type='hidden' name='order_id' value='$id'>
            <button type='submit' name='change_status' class='btn'>
                Definir comme $nextStatus
            </button>
        </form>";
    }
    else {
       $button = "<div class='btn'>
        ✓ Commande Terminée
         </div>";
    }

    return "<div class='order-card'>
                <h3>Commande #$id</h3>
                <p><strong>Statut:</strong> $status</p>
                <p><strong>Heure de commande:</strong> $commandHour</p>
                <p><strong>Heure attendue:</strong> $destHour</p>
                <p><strong>Plats:</strong> $itemsString </p>
                <br>
                $button
                
            </div>";


}
function getOrders($orders){
    $html = "";
    
    foreach ($orders as $id => $order) {
        if (explode('-', $order['comm_t'])[0] == date("j/m/Y")) { 
            $html .= orderToCard($order, $id); 
        }
    }
    return $html; 
}

 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - Cuisinieur</title>
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
            <p>Découvrez les commandes à préparer</p>
        </div>
        <section class="search" style="max-width: 850px; margin-bottom: 60px;">
            <form method="GET" action="cuisinieur.php" class="lined">

            <div class="form-group">
                <label for="filter">Filtrer par:</label>
                <select id="filter" name="filter" style="width: 100%; padding: 12px; border-radius: 8px; background-color: var(--base); color: white; border: 1px solid var(--overlay); font-family: inherit; cursor: pointer;">
                    <option value="all" <?= $selectedFilter == 'all' ? 'selected' : '' ?>>Toutes les commandes</option>
                    <option value="paid" <?= $selectedFilter == 'paid' ? 'selected' : '' ?>>Payées</option>
                    <option value="in-progress" <?= $selectedFilter == 'in-progress' ? 'selected' : '' ?>>En cours de préparation</option>
                    <option value="prepared" <?= $selectedFilter == 'prepared' ? 'selected' : '' ?>>Préparées</option>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="search-button">
                    Appliquer
                </button>
            </div>

            </form>
        </section>
        <div class="orders">
            
            <?php
                if($selectedFilter == 'paid'){
                    echo getOrders($paidOrders); 
                    if (empty($paidOrders)) {
                        echo "<p>Aucune commande payée pour le moment.</p>";
                    }
                }
                elseif($selectedFilter == 'in-progress'){
                    echo getOrders($inProgressOrders); 
                    if (empty($inProgressOrders)) {
                        echo "<p>Aucune commande en cours de préparation pour le moment.</p>";
                    } 
                }
                elseif($selectedFilter == 'prepared'){
                    echo getOrders($preparedOrders); 
                    if (empty($preparedOrders)) {
                        echo "<p>Aucune commande préparée pour le moment.</p>";
                    }
                }
                else{
                    echo getOrders($allorders);   
                    if (empty($allorders)) {
                        echo "<p>Aucune commande pour le moment.</p>";
                    }
                }
            ?>

        </div>
    </main>
</body>
</html>