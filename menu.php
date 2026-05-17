<?php
require_once __DIR__ . '/inc/common.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId      = $isLoggedIn ? $_SESSION['user_id'] : null;

$filePlats = 'plats.json';
$plats     = load_json($filePlats);

if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!$isLoggedIn) { header("Location: connect.php"); exit(); }
    $action = $_GET['action'];
    $platId = $_GET['id'];
    if (isset($plats[$platId])) {
        $likes    = $plats[$platId]['likes']    ?? [];
        $dislikes = $plats[$platId]['dislikes'] ?? [];
        if ($action === 'like') {
            $dislikes = array_diff($dislikes, [$userId]);
            $likes    = in_array($userId, $likes) ? array_diff($likes, [$userId]) : [...$likes, $userId];
        } elseif ($action === 'dislike') {
            $likes    = array_diff($likes, [$userId]);
            $dislikes = in_array($userId, $dislikes) ? array_diff($dislikes, [$userId]) : [...$dislikes, $userId];
        }
        $plats[$platId]['likes']    = array_values($likes);
        $plats[$platId]['dislikes'] = array_values($dislikes);
        save_json($filePlats, $plats);
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['likes' => count($plats[$platId]['likes']), 'dislikes' => count($plats[$platId]['dislikes'])]);
            exit();
        }
        header("Location: menu.php"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Menu — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main class="main-container">
    <div class="page-header"><h1>Notre Carte</h1><p>Découvrez nos spécialités</p></div>
    <section class="glass-panel large">
        <ul class="item-list">
        <?php if (empty($plats)): ?>
            <p class="empty-message">Aucun plat disponible.</p>
        <?php else: foreach ($plats as $id => $plat): ?>
            <li class="item-card item-card-row">
                <?php if (!empty($plat['image_url'])): ?>
                    <a href="view.php?id=<?= urlencode($id) ?>">
                        <img class="item-card-img" src="<?= htmlspecialchars($plat['image_url']) ?>" alt="<?= htmlspecialchars($plat['name']) ?>">
                    </a>
                <?php endif; ?>
                <div class="item-card-content">
                    <div>
                        <div class="item-card-header no-border">
                            <span class="item-title item-title-large">
                                <a href="view.php?id=<?= urlencode($id) ?>"><?= htmlspecialchars($plat['name']) ?></a>
                                <?php if ($plat['is_vegetarian'] ?? false): ?><span class="badge-vegetarian">🌱 Végétarien</span><?php endif; ?>
                            </span>
                            <span class="item-price item-price-large"><?= number_format($plat['price'],2,',',' ') ?> €</span>
                        </div>
                        <p class="item-details"><?= htmlspecialchars($plat['text_description']) ?></p>
                    </div>
                    <div class="item-actions">
                        <button class="like-btn" data-id="<?= urlencode($id) ?>" data-action="like">
                            👍 <span class="like-count"><?= count($plat['likes'] ?? []) ?></span>
                        </button>
                        <button class="like-btn" data-id="<?= urlencode($id) ?>" data-action="dislike">
                            👎 <span class="dislike-count"><?= count($plat['dislikes'] ?? []) ?></span>
                        </button>
                        <a href="view.php?id=<?= urlencode($id) ?>" class="link-sapphire">💬 <?= count($plat['comments'] ?? []) ?> avis</a>
                        <?php if ($isLoggedIn && ($_SESSION['user_role']??'') === 'client'): ?>
                            <a href="commande.php" class="menu-order-link">Commander →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; endif; ?>
        </ul>
    </section>
</main>
<script src="scripts.js"></script>
</body>
</html>
