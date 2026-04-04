<?php 
session_start(); 
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// --- CHARGEMENT DES PLATS ---
$filePlats = 'plats.json';
$plats = file_exists($filePlats) ? json_decode(file_get_contents($filePlats), true) : [];

// --- LOGIQUE DES LIKES / DISLIKES ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!$isLoggedIn) {
        // S'il n'est pas connecté, on l'envoie vers la page de connexion
        header("Location: connect.php");
        exit();
    }

    $action = $_GET['action'];
    $platId = $_GET['id'];

    if (isset($plats[$platId])) {
        // On récupère les tableaux actuels (ou on crée des tableaux vides)
        $likes = $plats[$platId]['likes'] ?? [];
        $dislikes = $plats[$platId]['dislikes'] ?? [];

        if ($action === 'like') {
            $dislikes = array_diff($dislikes, [$userId]); // On enlève le dislike s'il y en avait un
            if (in_array($userId, $likes)) {
                $likes = array_diff($likes, [$userId]); // S'il avait déjà liké, on enlève le like (Toggle)
            } else {
                $likes[] = $userId; // Sinon on l'ajoute
            }
        } elseif ($action === 'dislike') {
            $likes = array_diff($likes, [$userId]); // On enlève le like s'il y en avait un
            if (in_array($userId, $dislikes)) {
                $dislikes = array_diff($dislikes, [$userId]); // Toggle
            } else {
                $dislikes[] = $userId;
            }
        }

        // On remet les tableaux propres dans notre dictionnaire
        $plats[$platId]['likes'] = array_values($likes);
        $plats[$platId]['dislikes'] = array_values($dislikes);

        // On sauvegarde le JSON
        file_put_contents($filePlats, json_encode($plats, JSON_PRETTY_PRINT));
        
        // Réponse AJAX ou redirection classique
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
    <title>Menu - Le Restaurant</title>
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
            <h1>Notre Carte</h1>
            <p>Découvrez nos spécialités</p>
        </div>
   
        <section class="glass-panel large">
            <ul class="item-list">
                <?php if (empty($plats)): ?>
                    <p style="text-align: center; color: var(--text-muted);">Aucun plat disponible pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($plats as $id => $plat): ?>
                        
                        <li class="item-card" style="flex-direction: row; align-items: flex-start; gap: 20px;">
                            
                            <?php if (!empty($plat['image_url'])): ?>
                                <a href="view.php?id=<?php echo urlencode($id); ?>">
                                    <img src="<?php echo htmlspecialchars($plat['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($plat['name']); ?>" 
                                         style="width: 140px; height: 140px; object-fit: cover; border-radius: 10px; border: 1px solid var(--overlay); flex-shrink: 0; transition: transform 0.2s;">
                                </a>
                            <?php endif; ?>
                            
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
                                <div>
                                    <div class="item-card-header" style="border-bottom: none; padding-bottom: 5px;">
                                        <span class="item-title" style="font-size: 1.4rem;">
                                            <a href="view.php?id=<?php echo urlencode($id); ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo htmlspecialchars($plat['name']); ?>
                                            </a>
                                            <?php if (isset($plat['is_vegetarian']) && $plat['is_vegetarian']): ?>
                                                <span style="font-size: 0.7em; color: var(--softlime); margin-left: 10px;">🌱 Végétarien</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="item-price" style="font-size: 1.4rem;"><?php echo number_format($plat['price'], 2, ',', ' '); ?> €</span>
                                    </div>
                                    <p style="color: var(--text-muted); margin-bottom: 15px; line-height: 1.5;">
                                        <?php echo htmlspecialchars($plat['text_description']); ?>
                                    </p>
                                </div>
                                
                                <div style="display: flex; gap: 20px; font-weight: bold; font-size: 0.9em; border-top: 1px solid var(--overlay); padding-top: 10px;">
                                    <button class="like-btn" data-id="<?php echo urlencode($id); ?>" data-action="like"
                                        style="background:none;border:none;cursor:pointer;color:var(--softlime);font-weight:bold;font-size:0.9em;padding:0;margin:0;width:auto;">
                                        👍 <span class="like-count"><?php echo count($plat['likes'] ?? []); ?></span>
                                    </button>
                                    <button class="like-btn" data-id="<?php echo urlencode($id); ?>" data-action="dislike"
                                        style="background:none;border:none;cursor:pointer;color:#f38ba8;font-weight:bold;font-size:0.9em;padding:0;margin:0;width:auto;">
                                        👎 <span class="dislike-count"><?php echo count($plat['dislikes'] ?? []); ?></span>
                                    </button>
                                    <a href="view.php?id=<?php echo urlencode($id); ?>" style="color: var(--sapphire); text-decoration: none;">
                                        💬 <?php echo count($plat['comments'] ?? []); ?> avis
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </main>
<style>
@keyframes likePop {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.55); }
    70%  { transform: scale(0.88); }
    100% { transform: scale(1); }
}
.like-btn { transition: opacity 0.15s; }
.like-btn.popping { animation: likePop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
.like-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
<script>
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id     = this.dataset.id;
        const action = this.dataset.action;
        const card   = this.closest('li');

        // Prevent double-click
        card.querySelectorAll('.like-btn').forEach(b => b.disabled = true);

        // Pop animation on the clicked emoji
        this.classList.remove('popping');
        void this.offsetWidth; // reflow
        this.classList.add('popping');
        this.addEventListener('animationend', () => this.classList.remove('popping'), { once: true });

        try {
            const res  = await fetch(`menu.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`);
            const data = await res.json();
            card.querySelector('.like-count').textContent    = data.likes;
            card.querySelector('.dislike-count').textContent = data.dislikes;
        } catch(e) {
            console.error('Like failed', e);
        } finally {
            card.querySelectorAll('.like-btn').forEach(b => b.disabled = false);
        }
    });
});
</script>
</body>
</html>
