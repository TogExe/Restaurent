<?php
require_once __DIR__ . '/inc/common.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true;

$userId = $isLoggedIn
    ? $_SESSION['user_id']
    : null;

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
        $dislikes = $plats[$platId]['dislikes'] ?? [];

        if ($action === 'like') {

            $dislikes = array_diff($dislikes, [$userId]);

            $likes = in_array($userId, $likes)
                ? array_diff($likes, [$userId])
                : [...$likes, $userId];

        } elseif ($action === 'dislike') {

            $likes = array_diff($likes, [$userId]);

            $dislikes = in_array($userId, $dislikes)
                ? array_diff($dislikes, [$userId])
                : [...$dislikes, $userId];
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
    <script src="scripts.js" defer></script>

</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1>Notre Carte</h1>
        <p>Découvrez nos spécialités</p>
    </div>

    <section class="glass-panel large">

        <ul class="item-list">

            <?php if (empty($plats)): ?>

                <p class="empty-menu">
                    Aucun plat disponible.
                </p>

            <?php else: ?>

                <?php foreach ($plats as $id => $plat): ?>

                    <li class="item-card menu-item-card">

                        <?php if (!empty($plat['image_url'])): ?>

                            <a href="view.php?id=<?= urlencode($id) ?>">

                                <img
                                    src="<?= htmlspecialchars($plat['image_url']) ?>"
                                    alt="<?= htmlspecialchars($plat['name']) ?>"
                                    class="menu-item-image"
                                >

                            </a>

                        <?php endif; ?>

                        <div class="menu-item-content">

                            <div>

                                <div class="item-card-header menu-item-header">

                                    <span class="item-title menu-item-title">

                                        <a href="view.php?id=<?= urlencode($id) ?>"
                                           class="menu-item-link">

                                            <?= htmlspecialchars($plat['name']) ?>

                                        </a>

                                        <?php if ($plat['is_vegetarian'] ?? false): ?>

                                            <span class="menu-veg-badge">
                                                🌱 Végétarien
                                            </span>

                                        <?php endif; ?>

                                    </span>

                                    <span class="item-price menu-item-price">
                                        <?= number_format($plat['price'],2,',',' ') ?> €
                                    </span>

                                </div>

                                <p class="menu-item-description">
                                    <?= htmlspecialchars($plat['text_description']) ?>
                                </p>

                            </div>

                            <div class="menu-item-footer">

                                <button class="like-btn like-positive"
                                        data-id="<?= urlencode($id) ?>"
                                        data-action="like">

                                    👍
                                    <span class="like-count">
                                        <?= count($plat['likes'] ?? []) ?>
                                    </span>

                                </button>

                                <button class="like-btn like-negative"
                                        data-id="<?= urlencode($id) ?>"
                                        data-action="dislike">

                                    👎
                                    <span class="dislike-count">
                                        <?= count($plat['dislikes'] ?? []) ?>
                                    </span>

                                </button>

                                <a href="view.php?id=<?= urlencode($id) ?>"
                                   class="menu-comments-link">

                                    💬 <?= count($plat['comments'] ?? []) ?> avis

                                </a>

                                <?php if (
                                    $isLoggedIn &&
                                    ($_SESSION['user_role'] ?? '') === 'client'
                                ): ?>

                                    <a href="commande.php"
                                       class="menu-order-link">

                                        Commander →

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </li>

                <?php endforeach; ?>

            <?php endif; ?>

        </ul>

    </section>

</main>

<script>
document.querySelectorAll('.like-btn').forEach(btn => {

    btn.addEventListener('click', async function() {

        const id = this.dataset.id;
        const action = this.dataset.action;
        const card = this.closest('li');

        card.querySelectorAll('.like-btn')
            .forEach(b => b.disabled = true);

        this.classList.remove('popping');
        void this.offsetWidth;
        this.classList.add('popping');

        this.addEventListener(
            'animationend',
            () => this.classList.remove('popping'),
            { once: true }
        );

        try {

            const res = await fetch(
                `menu.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`
            );

            const data = await res.json();

            card.querySelector('.like-count').textContent = data.likes;

            card.querySelector('.dislike-count').textContent = data.dislikes;

        } catch(e) {

            console.error(e);

        } finally {

            card.querySelectorAll('.like-btn')
                .forEach(b => b.disabled = false);
        }
    });
});
</script>

</body>
</html>
