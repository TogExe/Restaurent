<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId      = $isLoggedIn ? $_SESSION['user_id'] : null;

if (!isset($_GET['id'])) { header("Location: menu.php"); exit(); }
$platId    = $_GET['id'];
$filePlats = 'plats.json';
$plats     = file_exists($filePlats) ? json_decode(file_get_contents($filePlats), true) : [];
if (!isset($plats[$platId])) { header("Location: menu.php"); exit(); }

function generateAbsurdName($hash) {
    $prenoms   = ['Ragnar','César','Astérix','Odin','Vercingétorix','Thor','Obélix','Loki','Spartacus','Björn','Auguste','Ivar','Romulus','Arthur'];
    $adjectifs = ['le Frileux','l\'Enragé','le Mystique','l\'Étourdi','le Flamboyant','le Boiteux','l\'Héroïque','le Fou','le Croustillant','le Chauve','le Divin','le Sombre','le Joyeux','le Terrifiant'];
    return $prenoms[abs(crc32($hash.'p')) % count($prenoms)] . ' ' . $adjectifs[abs(crc32($hash.'a')) % count($adjectifs)];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_comment']) && $isLoggedIn) {
    $newComment = trim($_POST['new_comment']);
    if (!empty($newComment)) {
        $plats[$platId]['comments'] = $plats[$platId]['comments'] ?? [];
        $plats[$platId]['comments'][$userId] = $newComment;
        file_put_contents($filePlats, json_encode($plats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header("Location: view.php?id=" . urlencode($platId)); exit();
    }
}

$plat            = $plats[$platId];
$existingComment = '';
$hasCommented    = false;
if ($isLoggedIn && isset($plat['comments'][$userId])) {
    $hasCommented    = true;
    $existingComment = $plat['comments'][$userId];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($plat['name'],ENT_QUOTES,'UTF-8') ?> — Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main class="main-container">
    <section class="glass-panel medium">
        <a href="menu.php" style="color:var(--sapphire);text-decoration:none;display:inline-block;margin-bottom:20px;">← Retour au Menu</a>
        <?php if (!empty($plat['image_url'])): ?>
            <img src="<?= htmlspecialchars($plat['image_url'],ENT_QUOTES,'UTF-8') ?>"
                 alt="<?= htmlspecialchars($plat['name'],ENT_QUOTES,'UTF-8') ?>"
                 style="width:100%;height:300px;object-fit:cover;border-radius:10px;border:1px solid var(--overlay);margin-bottom:20px;">
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <h1 style="color:var(--mauve);font-family:'Playfair Display',serif;"><?= htmlspecialchars($plat['name'],ENT_QUOTES,'UTF-8') ?></h1>
            <span class="item-price" style="font-size:1.8rem;"><?= number_format($plat['price'],2,',',' ') ?> €</span>
        </div>
        <?php if ($plat['is_vegetarian'] ?? false): ?>
            <div style="color:var(--base);background:var(--softlime);padding:5px 10px;border-radius:5px;display:inline-block;font-weight:bold;font-size:.8rem;margin-bottom:15px;">🌱 Végétarien</div>
        <?php endif; ?>
        <p style="color:var(--text-muted);line-height:1.6;font-size:1.1rem;margin-bottom:30px;"><?= nl2br(htmlspecialchars($plat['text_description'],ENT_QUOTES,'UTF-8')) ?></p>
        <div style="display:flex;gap:30px;font-weight:bold;font-size:1.2rem;border-top:1px solid var(--overlay);padding-top:20px;justify-content:center;">
            <span style="color:var(--softlime);">👍 <?= count($plat['likes'] ?? []) ?></span>
            <span style="color:#f38ba8;">👎 <?= count($plat['dislikes'] ?? []) ?></span>
        </div>
        <?php if ($isLoggedIn && ($_SESSION['user_role']??'') === 'client'): ?>
            <a href="commande.php" class="btn" style="margin-top:20px;">Commander ce plat</a>
        <?php endif; ?>
    </section>

    <section class="glass-panel medium">
        <h2 style="color:var(--sapphire);margin-bottom:20px;">Avis (<?= count($plat['comments'] ?? []) ?>)</h2>
        <?php if (empty($plat['comments'])): ?>
            <p style="color:var(--text-muted);font-style:italic;margin-bottom:20px;">Soyez le premier à donner votre avis !</p>
        <?php else: ?>
            <ul class="item-list" style="margin-bottom:30px;">
            <?php foreach ($plat['comments'] as $key => $comment):
                $pseudo    = is_string($key) ? generateAbsurdName($key) : "Voyageur Anonyme";
                $shortHash = is_string($key) ? substr($key, 0, 8) : "";
            ?>
            <li style="background:var(--surface);padding:15px;border-radius:8px;border:1px solid var(--overlay);color:var(--text);display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="color:var(--mauve);font-weight:bold;font-size:1.1rem;"><?= htmlspecialchars($pseudo,ENT_QUOTES,'UTF-8') ?></div>
                    <?php if ($isLoggedIn && $key === $userId): ?><span style="color:var(--sapphire);font-size:.8rem;font-style:italic;">(C'est vous)</span><?php endif; ?>
                </div>
                <span style="line-height:1.5;">"<?= nl2br(htmlspecialchars($comment,ENT_QUOTES,'UTF-8')) ?>"</span>
                <?php if ($shortHash): ?><small style="color:var(--overlay);font-size:.7rem;font-family:monospace;text-align:right;">ID: <?= htmlspecialchars($shortHash) ?>…</small><?php endif; ?>
            </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <div style="border-top:1px solid var(--overlay);padding-top:20px;">
                <h3 style="margin-bottom:15px;font-size:1.2rem;"><?= $hasCommented ? 'Modifier votre avis' : 'Ajouter un avis' ?></h3>
                <?php if (!$hasCommented): ?>
                    <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:15px;font-style:italic;">Votre commentaire sera publié sous un nom de guerrier généré aléatoirement !</p>
                <?php endif; ?>
                <form action="" method="POST">
                    <div class="form-group">
                        <textarea name="new_comment" rows="3" placeholder="Qu'avez-vous pensé ?" required style="width:100%;resize:vertical;padding:10px;border-radius:8px;border:1px solid var(--overlay);background:var(--surface);color:var(--text);"><?= htmlspecialchars($existingComment,ENT_QUOTES,'UTF-8') ?></textarea>
                    </div>
                    <button type="submit" class="btn"><?= $hasCommented ? 'Mettre à jour' : 'Envoyer mon avis' ?></button>
                </form>
            </div>
        <?php else: ?>
            <div style="text-align:center;border-top:1px solid var(--overlay);padding-top:20px;">
                <p style="color:var(--text-muted);margin-bottom:10px;">Connectez-vous pour laisser un avis.</p>
                <a href="connect.php" class="btn" style="max-width:200px;display:inline-block;">Se connecter</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
