<?php
require_once __DIR__ . '/inc/common.php';

$currentPage = basename($_SERVER['PHP_SELF']);

ensure_ban();

if (!isset($_GET['id'])) { header("Location: menu.php"); exit(); }
$platId    = $_GET['id'];
$filePlats = 'data/plats.json';
$plats     = load_json($filePlats);
if (!isset($plats[$platId])) { header("Location: menu.php"); exit(); }
$isLoggedIn = isset($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true;

$userId = $isLoggedIn
    ? $_SESSION['user_id']
    : null;

if (!isset($_GET['id'])) {
    header("Location: menu.php");
    exit();
}

$platId = $_GET['id'];

$filePlats = 'data/plats.json';
$plats     = load_json($filePlats);

if (!isset($plats[$platId])) {
    header("Location: menu.php");
    exit();
}

function generateAbsurdName($hash) {

    $prenoms = [
        'Ragnar','César','Astérix','Odin',
        'Vercingétorix','Thor','Obélix',
        'Loki','Spartacus','Björn',
        'Auguste','Ivar','Romulus','Arthur'
    ];

    $adjectifs = [
        'le Frileux','l\'Enragé','le Mystique',
        'l\'Étourdi','le Flamboyant',
        'le Boiteux','l\'Héroïque',
        'le Fou','le Croustillant',
        'le Chauve','le Divin',
        'le Sombre','le Joyeux',
        'le Terrifiant'
    ];

    return
        $prenoms[abs(crc32($hash.'p')) % count($prenoms)]
        . ' ' .
        $adjectifs[abs(crc32($hash.'a')) % count($adjectifs)];
}

if (
    $_SERVER["REQUEST_METHOD"] == "POST"
    && isset($_POST['new_comment'])
    && $isLoggedIn
) {

    $newComment = trim($_POST['new_comment']);

    if (!empty($newComment)) {

        $plats[$platId]['comments'] =
            $plats[$platId]['comments'] ?? [];

        $plats[$platId]['comments'][$userId] = $newComment;

        save_json($filePlats, $plats);

        header("Location: view.php?id=" . urlencode($platId));
        exit();
    }
}

$plat = $plats[$platId];

$existingComment = '';
$hasCommented = false;

if ($isLoggedIn && isset($plat['comments'][$userId])) {

    $hasCommented = true;

    $existingComment = $plat['comments'][$userId];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    <title>
        <?= htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8') ?>
        — Le Restaurant
    </title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <section class="glass-panel medium">

        <a href="menu.php" class="view-back-link">
            ← Retour au Menu
        </a>

        <?php if (!empty($plat['image_url'])): ?>

            <img
                src="<?= htmlspecialchars($plat['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8') ?>"
                class="view-image"
            >

        <?php endif; ?>

        <div class="view-header">

            <h1 class="view-title">
                <?= htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <span class="item-price view-price">
                <?= number_format($plat['price'],2,',',' ') ?> €
            </span>

        </div>

        <?php if ($plat['is_vegetarian'] ?? false): ?>

            <div class="view-veg-badge">
                🌱 Végétarien
            </div>

        <?php endif; ?>

        <p class="view-description">

            <?= nl2br(htmlspecialchars(
                $plat['text_description'],
                ENT_QUOTES,
                'UTF-8'
            )) ?>

        </p>

        <div class="view-stats">

            <span class="view-like-count">
                👍 <?= count($plat['likes'] ?? []) ?>
            </span>

            <span class="view-dislike-count">
                👎 <?= count($plat['dislikes'] ?? []) ?>
            </span>

        </div>

        <?php if (
            $isLoggedIn &&
            ($_SESSION['user_role'] ?? '') === 'client'
        ): ?>

            <a href="commande.php"
               class="btn view-order-btn">

                Commander ce plat

            </a>

        <?php endif; ?>

    </section>

    <section class="glass-panel medium">

        <h2 class="view-comments-title">

            Avis (<?= count($plat['comments'] ?? []) ?>)

        </h2>

        <?php if (empty($plat['comments'])): ?>

            <p class="view-empty-comments">
                Soyez le premier à donner votre avis !
            </p>

        <?php else: ?>

            <ul class="item-list view-comments-list">

                <?php foreach ($plat['comments'] as $key => $comment):

                    $pseudo = is_string($key)
                        ? generateAbsurdName($key)
                        : "Voyageur Anonyme";

                    $shortHash = is_string($key)
                        ? substr($key, 0, 8)
                        : "";

                ?>

                    <li class="view-comment-card">

                        <div class="view-comment-header">

                            <div class="view-comment-author">

                                <?= htmlspecialchars(
                                    $pseudo,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                            <?php if ($isLoggedIn && $key === $userId): ?>

                                <span class="view-comment-self">
                                    (C'est vous)
                                </span>

                            <?php endif; ?>

                        </div>

                        <span class="view-comment-text">

                            "
                            <?= nl2br(htmlspecialchars(
                                $comment,
                                ENT_QUOTES,
                                'UTF-8'
                            )) ?>
                            "

                        </span>

                        <?php if ($shortHash): ?>

                            <small class="view-comment-id">

                                ID:
                                <?= htmlspecialchars($shortHash) ?>…

                            </small>

                        <?php endif; ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

        <?php if ($isLoggedIn): ?>

            <div class="view-comment-form-wrapper">

                <h3 class="view-comment-form-title">

                    <?= $hasCommented
                        ? 'Modifier votre avis'
                        : 'Ajouter un avis'
                    ?>

                </h3>

                <?php if (!$hasCommented): ?>

                    <p class="view-anonymous-note">

                        Votre commentaire sera publié
                        sous un nom de guerrier généré
                        aléatoirement !

                    </p>

                <?php endif; ?>

                <form action="" method="POST">

                    <div class="form-group">

                        <textarea
                            name="new_comment"
                            rows="3"
                            placeholder="Qu'avez-vous pensé ?"
                            required
                            class="view-comment-textarea"
                        ><?= htmlspecialchars(
                            $existingComment,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                    </div>

                    <button type="submit" class="btn">

                        <?= $hasCommented
                            ? 'Mettre à jour'
                            : 'Envoyer mon avis'
                        ?>

                    </button>

                </form>

            </div>

        <?php else: ?>

            <div class="view-login-prompt">

                <p class="view-login-text">
                    Connectez-vous pour laisser un avis.
                </p>

                <a href="connect.php"
                   class="btn view-login-btn">

                    Se connecter

                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

