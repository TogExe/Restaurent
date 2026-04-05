<?php
session_start();

// Garde: seuls cuisiniers et admins
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userRole   = $_SESSION['user_role'] ?? 'client';
if (!$isLoggedIn || !in_array($userRole, ['cuisiner', 'admin'])) {
    header("Location: connect.php"); exit();
}

$allorders      = json_decode(file_get_contents('commandes.json'), true);
$currentPage    = basename($_SERVER['PHP_SELF']);
$selectedFilter = $_GET['filter'] ?? 'all';

$paid = 0; $inProgress = 1; $prepared = 2;

if (isset($_POST['change_status'])) {
    updateOrderStatus($_POST['order_id']);
    header("Location: cuisinier.php?filter=" . $selectedFilter); exit;
}

$preparedOrders   = array_filter($allorders, fn($o) => $o['ready'] == $prepared);
$inProgressOrders = array_filter($allorders, fn($o) => $o['ready'] == $inProgress);
$paidOrders       = array_filter($allorders, fn($o) => $o['ready'] == $paid);

function updateOrderStatus($orderId) {
    $data = json_decode(file_get_contents('commandes.json'), true);
    if (isset($data[$orderId]) && $data[$orderId]['ready'] < 2) {
        $data[$orderId]['ready'] += 1;
        file_put_contents('commandes.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function orderToCard($order, $id) {
    $filter = $_GET['filter'] ?? 'all';
    $status = match($order['ready']) {
        0 => 'Payée', 1 => 'En préparation', 2 => 'Prête', default => 'Inconnu'
    };
    $nextStatus = match($order['ready']) {
        0 => 'En préparation', 1 => 'Prête', default => null
    };
    $commandHour = substr($order['comm_t'], strpos($order['comm_t'], '-') + 1, 5);
    $destHour    = substr($order['des_t'],  strpos($order['des_t'],  '-') + 1, 5);
    if ($destHour == $commandHour) $destHour = 'Immédiate';
    $itemsString = htmlspecialchars(implode(', ', $order['commands']));
    $button = $nextStatus
        ? "<form method='POST' action='cuisinier.php?filter=$filter'><input type='hidden' name='order_id' value='$id'><button type='submit' name='change_status' class='btn'>Définir comme $nextStatus</button></form>"
        : "<div class='btn' style='background:linear-gradient(135deg,var(--softlime),#5aab85);color:#0a0a1a;'>✓ Commande Prête</div>";

    $statusColor = match($order['ready']) { 0 => 'var(--text-muted)', 1 => 'var(--accent-btn)', 2 => 'var(--softlime)', default => 'var(--text-muted)' };

    return "<div class='order-card'>
                <h3 style='color:var(--sapphire);margin-bottom:10px;'>Commande #$id</h3>
                <p><strong>Statut :</strong> <span style='color:$statusColor;'>$status</span></p>
                <p><strong>Commandée :</strong> $commandHour</p>
                <p><strong>Livraison :</strong> $destHour</p>
                <p style='margin-top:8px;'><strong>Plats :</strong> $itemsString</p>
                <br>$button
            </div>";
}

function getOrders($orders) {
    $html = "";
    foreach ($orders as $id => $order) {
        $html .= orderToCard($order, $id);
    }
    return $html;
}
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

    <section class="search" style="max-width:850px;margin-bottom:40px;width:100%;">
        <form method="GET" action="cuisinier.php" class="lined">
            <div class="form-group">
                <label>Filtrer par statut</label>
                <select name="filter" style="width:100%;padding:12px;border-radius:8px;background-color:var(--base);color:white;border:1px solid var(--overlay);font-family:inherit;cursor:pointer;">
                    <option value="all"         <?= $selectedFilter=='all'         ?'selected':'' ?>>Toutes</option>
                    <option value="paid"        <?= $selectedFilter=='paid'        ?'selected':'' ?>>Payées</option>
                    <option value="in-progress" <?= $selectedFilter=='in-progress' ?'selected':'' ?>>En préparation</option>
                    <option value="prepared"    <?= $selectedFilter=='prepared'    ?'selected':'' ?>>Prêtes</option>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="search-button btn">Appliquer</button></div>
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
        echo $rendered ?: "<p style='color:var(--text-muted);'>Aucune commande pour ce filtre.</p>";
        ?>
    </div>
</main>
</body>
</html>
