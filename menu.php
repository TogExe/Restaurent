<?php
require_once __DIR__ . '/inc/common.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$filePlats = 'plats.json';
$plats     = load_json($filePlats);

if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!$isLoggedIn) {
        header("Location: connect.php");
        exit();
    }

    $action = $_GET['action'];
    $platId = $_GET['id'];

    if (isset($plats[$platId])) {
        $likes    = $plats[$platId]['likes'] ?? [];
        $dislikes = $plats[$platsId]['dislikes'] ?? [];

        if ($action === 'like') {
            $dislikes = array_diff($dislikes, [$userId]);
            $likes = in_array($userId, $likes) ? array_diff($likes, [$userId]) : [...$likes, $userId];
        } elseif ($action === 'dislike') {
            $likes = array_diff($likes, [$userId]);
            $dislikes = in_array($userId, $dislikes) ? array_diff($dislikes, [$userId]) : [...$dislikes, $userId];
        }

        $plats[$platId]['likes']    = array_values($likes);
        $plats[$platId]['dislikes'] = array_values($dislikes);

        save_json($filePlats, $plats);

        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'likes'    => count($plats[$platId]['likes']),
                'dislikes' => count($plats[$platId]['dislikes'])
            ]);
            exit();
        }
        header("Location: menu.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Menu — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <script src="main.js" defer></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('menuSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                const cards = document.querySelectorAll('.menu-item-card');
                
                cards.forEach(card => {
                    const title = card.querySelector('.menu-item-title').textContent.toLowerCase();
                    const desc = card.querySelector('.menu-item-description').textContent.toLowerCase();
                    
                    if (title.includes(term) || desc.includes(term)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <div class="page-header">
        <h1>Notre Carte</h1>
        <p>Découvrez nos spécialités</p>
    </div>

    <div class="menu-search-wrapper">
        <input type="text" id="menuSearch" class="menu-search-input" placeholder="Rechercher un plat, un ingrédient...">
    </div>

    <section class="glass-panel menu-panel">
        <ul class="item-list menu-layout-grid">
            <?php if (empty($plats)): ?>
                <p class="empty-menu">Aucun plat disponible.</p>
            <?php else: ?>
                <?php foreach ($plats as $id => $plat): ?>
                    <li class="item-card menu-item-card">
                        <?php if (!empty($plat['image_url'])): ?>
                            <a href="view.php?id=<?= urlencode($id) ?>">
                                <img src="<?= htmlspecialchars($plat['image_url']) ?>" alt="<?= htmlspecialchars($plat['name']) ?>" class="menu-item-image">
                            </a>
                        <?php endif; ?>
                        
                        <div class="menu-item-content">
                            <div class="menu-item-header">
                                <span class="item-title menu-item-title">
                                    <a href="view.php?id=<?= urlencode($id) ?>" class="menu-item-link">
                                        <?= htmlspecialchars($plat['name']) ?>
                                    </a>
                                    <?php if ($plat['is_vegetarian'] ?? false): ?>
                                        <span class="menu-veg-badge">🌱 Vég</span>
                                    <?php endif; ?>
                                </span>
                                <span class="item-price menu-item-price">
                                    <?= number_format($plat['price'], 2, ',', ' ') ?> €
                                </span>
                            </div>
                            
                            <p class="menu-item-description">
                                <?= htmlspecialchars($plat['text_description']) ?>
                            </p>
                            
                            <div class="menu-item-footer">
                                <button class="like-btn like-positive" data-id="<?= urlencode($id) ?>" data-action="like">
                                    👍 <span class="like-count"><?= count($plat['likes'] ?? []) ?></span>
                                </button>
                                <button class="like-btn like-negative" data-id="<?= urlencode($id) ?>" data-action="dislike">
                                    👎 <span class="dislike-count"><?= count($plat['dislikes'] ?? []) ?></span>
                                </button>
                                <a href="view.php?id=<?= urlencode($id) ?>" class="menu-comments-link">
                                    💬 <?= count($plat['comments'] ?? []) ?>
                                </a>
                                <button class="add-to-cart-btn" data-id="<?= urlencode($id) ?>" data-name="<?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>" data-price="<?= $plat['price'] ?>">
                                    + Ajouter
                                </button>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>
</main>

</body>
</html>