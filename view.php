<?php 
session_start(); 
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Vérification de l'ID du plat dans l'URL
if (!isset($_GET['id'])) {
    header("Location: menu.php");
    exit();
}

$platId = $_GET['id'];
$filePlats = 'plats.json';
$plats = file_exists($filePlats) ? json_decode(file_get_contents($filePlats), true) : [];

if (!isset($plats[$platId])) {
    header("Location: menu.php");
    exit();
}

// ==========================================
// FONCTION DE GÉNÉRATION DE PSEUDOS COURTS
// ==========================================
function generateAbsurdName($hash) {
    $prenoms = [
        'Ragnar', 'César', 'Astérix', 'Odin', 'Vercingétorix', 'Thor', 'Obélix', 
        'Loki', 'Spartacus', 'Björn', 'Auguste', 'Ivar', 'Romulus', 'Arthur'
    ];
    $adjectifs = [
        'le Frileux', 'l\'Enragé', 'le Mystique', 'l\'Étourdi', 'le Flamboyant', 
        'le Boiteux', 'l\'Héroïque', 'le Fou', 'le Croustillant', 
        'le Chauve', 'le Divin', 'le Sombre', 'le Joyeux', 'le Terrifiant'
    ];

    $num1 = abs(crc32($hash . "prenom"));
    $num2 = abs(crc32($hash . "adjectif"));

    $p = $prenoms[$num1 % count($prenoms)];
    $a = $adjectifs[$num2 % count($adjectifs)];

    return "$p $a";
}
// ==========================================

// --- LOGIQUE POUR AJOUTER OU MODIFIER UN COMMENTAIRE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_comment']) && $isLoggedIn) {
    // On enlève juste les espaces en trop avant/après
    $newComment = trim($_POST['new_comment']);
    
    if (!empty($newComment)) {
        if (!isset($plats[$platId]['comments'])) {
            $plats[$platId]['comments'] = [];
        }
        
        // CORRECTION : On sauvegarde le texte BRUT dans le JSON (sans le htmlspecialchars ici)
        $plats[$platId]['comments'][$userId] = $newComment;
        
        // On sauvegarde dans le fichier avec la protection pour les accents français
        file_put_contents($filePlats, json_encode($plats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        header("Location: view.php?id=" . urlencode($platId));
        exit();
    }
}

$plat = $plats[$platId];

// --- VÉRIFICATION POUR LA MODIFICATION ---
$existingComment = "";
$hasCommented = false;
if ($isLoggedIn && isset($plat['comments'][$userId])) {
    $hasCommented = true;
    // On récupère l'ancien commentaire brut depuis le JSON
    $existingComment = $plat['comments'][$userId];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8'); ?> - Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="logo"><h2>Restaurant</h2></div>
        <ul class="nav-links">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="menu.php" class="active">Menu</a></li>
            <?php if ($isLoggedIn): ?>
                <li><a href="profil.php">Mon Profil</a></li>
                <li><a href="connect.php?logout=1" class="logout-link">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="connect.php">Se Connecter</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main class="main-container">
        
        <section class="glass-panel medium">
            <a href="menu.php" style="color: var(--sapphire); text-decoration: none; display: inline-block; margin-bottom: 20px;">
                ← Retour au Menu
            </a>

            <?php if (!empty($plat['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($plat['image_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                     alt="<?php echo htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                     style="width: 100%; height: 300px; object-fit: cover; border-radius: 10px; border: 1px solid var(--overlay); margin-bottom: 20px;">
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h1 style="color: var(--mauve); font-family: 'Playfair Display', serif;">
                    <?php echo htmlspecialchars($plat['name'], ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <span class="item-price" style="font-size: 1.8rem;"><?php echo number_format($plat['price'], 2, ',', ' '); ?> €</span>
            </div>

            <?php if (isset($plat['is_vegetarian']) && $plat['is_vegetarian']): ?>
                <div style="color: var(--base); background-color: var(--softlime); padding: 5px 10px; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 0.8rem; margin-bottom: 15px;">
                    🌱 Végétarien
                </div>
            <?php endif; ?>

            <p style="color: var(--text-muted); line-height: 1.6; font-size: 1.1rem; margin-bottom: 30px;">
                <?php echo nl2br(htmlspecialchars($plat['text_description'], ENT_QUOTES, 'UTF-8')); ?>
            </p>

            <div style="display: flex; gap: 30px; font-weight: bold; font-size: 1.2rem; border-top: 1px solid var(--overlay); padding-top: 20px; justify-content: center;">
                <span style="color: var(--softlime);">👍 <?php echo count($plat['likes'] ?? []); ?> Likes</span>
                <span style="color: #f38ba8;">👎 <?php echo count($plat['dislikes'] ?? []); ?> Dislikes</span>
            </div>
        </section>

        <section class="glass-panel medium">
            <h2 style="color: var(--sapphire); margin-bottom: 20px;">Avis (<?php echo count($plat['comments'] ?? []); ?>)</h2>

            <?php if (empty($plat['comments'])): ?>
                <p style="color: var(--text-muted); font-style: italic; margin-bottom: 20px;">Soyez le premier à donner votre avis !</p>
            <?php else: ?>
                <ul class="item-list" style="margin-bottom: 30px;">
                    <?php foreach ($plat['comments'] as $key => $comment): ?>
                        
                        <?php 
                            $pseudo = is_string($key) ? generateAbsurdName($key) : "Voyageur Anonyme"; 
                            $shortHash = is_string($key) ? substr($key, 0, 8) : "";
                        ?>

                        <li style="background: var(--surface); padding: 15px; border-radius: 8px; border: 1px solid var(--overlay); color: var(--text); display: flex; flex-direction: column; gap: 8px;">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="color: var(--mauve); font-weight: bold; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($pseudo, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php if ($isLoggedIn && $key === $userId): ?>
                                    <span style="color: var(--sapphire); font-size: 0.8rem; font-style: italic;">(C'est vous)</span>
                                <?php endif; ?>
                            </div>

                            <span style="line-height: 1.5;">"<?php echo nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')); ?>"</span>
                            
                            <?php if ($shortHash): ?>
                                <small style="color: var(--overlay); font-size: 0.70rem; font-family: monospace; text-align: right;">
                                    ID: <?php echo htmlspecialchars($shortHash, ENT_QUOTES, 'UTF-8'); ?>...
                                </small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <div style="border-top: 1px solid var(--overlay); padding-top: 20px;">
                    <h3 style="margin-bottom: 15px; font-size: 1.2rem;">
                        <?php echo $hasCommented ? 'Modifier votre avis' : 'Ajouter un avis'; ?>
                    </h3>
                    
                    <?php if (!$hasCommented): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px; font-style: italic;">
                            Note : Pour protéger votre identité, votre commentaire sera publié sous un nom de guerrier généré aléatoirement !
                        </p>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group">
                            <textarea name="new_comment" rows="3" placeholder="Qu'avez-vous pensé de ce plat ?" required style="width: 100%; resize: vertical; padding: 10px; border-radius: 8px; border: 1px solid var(--overlay); background: var(--surface); color: var(--text);"><?php echo htmlspecialchars($existingComment, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <?php echo $hasCommented ? 'Mettre à jour mon avis' : 'Envoyer mon avis'; ?>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div style="text-align: center; border-top: 1px solid var(--overlay); padding-top: 20px;">
                    <p style="color: var(--text-muted); margin-bottom: 10px;">Connectez-vous pour laisser un avis.</p>
                    <a href="connect.php" class="btn" style="max-width: 200px; display: inline-block;">Se connecter</a>
                </div>
            <?php endif; ?>

        </section>
    </main>
</body>
</html>
