<?php
require_once __DIR__ . '/inc/common.php';
ensure_ban();
$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['logged_in']) 
    && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Bienvenue — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <section class="glass-panel large page-header">

        <h1>Le Restaurant</h1>

        <p>
            On ne sait pas encore cuisiner mais on apprend.
        </p>

        <div class="home-actions">

            <a href="menu.php" class="btn">
                Voir nos plats
            </a>

            <?php if (
                $isLoggedIn &&
                ($_SESSION['user_role'] ?? 'client') === 'client'
            ): ?>

                <a href="commande.php" class="btn home-order-btn">
                    Commander
                </a>

            <?php endif; ?>

        </div>

    </section>

    <section class="glass-panel large info-grid">

        <div class="info-block">

            <h3 class="info-title-blue">
                Notre Histoire
            </h3>

            <p>
                Né d'une passion pour la gastronomie, notre restaurant rassemble les meilleurs ingrédients locaux pour vous offrir une expérience inoubliable. Chaque plat est préparé par nos chefs experts depuis 2026.
            </p>

        </div>

        <div class="info-block">

            <h3 class="info-title-green">
                Horaires
            </h3>

            <ul class="hours-list">
                <li>
                    <strong>Lundi — Jeudi :</strong>
                    12h00 — 22h30
                </li>

                <li>
                    <strong>Vendredi — Samedi :</strong>
                    12h00 — 23h30
                </li>

                <li>
                    <strong>Dimanche :</strong>
                    Fermé
                </li>
            </ul>

        </div>

    </section>

</main>

</body>
</html>

