<?php
/**
 * _nav.php — Shared navigation partial.
 * Include AFTER session_start() and after setting $currentPage / $isLoggedIn / $userRole.
 */
$userRole = $_SESSION['user_role'] ?? 'client';
?>
<nav>
    <div class="logo"><h2>Restaurant</h2></div>
    <ul class="nav-links">
        <li><a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">Accueil</a></li>
        <li><a href="menu.php"  class="<?= $currentPage == 'menu.php'  ? 'active' : '' ?>">Menu</a></li>

        <?php if ($isLoggedIn): ?>

            <?php if ($userRole === 'admin'): ?>
                <li><a href="admin.php" class="<?= $currentPage == 'admin.php' ? 'active' : '' ?>" style="color:var(--mauve);">⚙ Admin</a></li>
            <?php endif; ?>

            <?php if ($userRole === 'cuisiner'): ?>
                <li><a href="cuisinieur.php" class="<?= $currentPage == 'cuisinieur.php' ? 'active' : '' ?>" style="color:var(--softlime);">🍳 Cuisine</a></li>
            <?php endif; ?>

            <?php if ($userRole === 'livreur'): ?>
                <li><a href="livreur.php" class="<?= $currentPage == 'livreur.php' ? 'active' : '' ?>" style="color:var(--sapphire);">🛵 Livraisons</a></li>
            <?php endif; ?>

            <?php if ($userRole === 'client'): ?>
                <li><a href="commande.php" class="<?= $currentPage == 'commande.php' ? 'active' : '' ?>">Commander</a></li>
            <?php endif; ?>

            <li><a href="profil.php" class="<?= $currentPage == 'profil.php' ? 'active' : '' ?>">Mon Profil</a></li>
            <li><a href="connect.php?logout=1" class="logout-link">Déconnexion</a></li>

        <?php else: ?>
            <li><a href="connect.php" class="<?= in_array($currentPage, ['connect.php','compte.php']) ? 'active' : '' ?>">Se Connecter</a></li>
        <?php endif; ?>
    </ul>
</nav>
