<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId      = $isLoggedIn ? $_SESSION['user_id'] : null;

$filePlats = 'plats.json';
$plats     = file_exists($filePlats) ? json_decode(file_get_contents($filePlats), true) : [];

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
        file_put_contents($filePlats, json_encode($plats, JSON_PRETTY_PRINT));
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
            <p style="text-align:center;color:var(--text-muted);">Aucun plat disponible.</p>
        <?php else: foreach ($plats as $id => $plat): ?>
            <li class="item-card" style="flex-direction:row;align-items:flex-start;gap:20px;">
                <?php if (!empty($plat['image_url'])): ?>
                    <a href="view.php?id=<?= urlencode($id) ?>">
                        <img src="<?= htmlspecialchars($plat['image_url']) ?>" alt="<?= htmlspecialchars($plat['name']) ?>"
                             style="width:140px;height:140px;object-fit:cover;border-radius:10px;border:1px solid var(--overlay);flex-shrink:0;">
                    </a>
                <?php endif; ?>
                <div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;min-height:140px;">
                    <div>
                        <div class="item-card-header" style="border-bottom:none;padding-bottom:5px;">
                            <span class="item-title" style="font-size:1.4rem;">
                                <a href="view.php?id=<?= urlencode($id) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($plat['name']) ?></a>
                                <?php if ($plat['is_vegetarian'] ?? false): ?><span style="font-size:.7em;color:var(--softlime);margin-left:10px;">🌱 Végétarien</span><?php endif; ?>
                            </span>
                            <span class="item-price" style="font-size:1.4rem;"><?= number_format($plat['price'],2,',',' ') ?> €</span>
                        </div>
                        <p style="color:var(--text-muted);margin-bottom:15px;line-height:1.5;"><?= htmlspecialchars($plat['text_description']) ?></p>
                    </div>
                    <div style="display:flex;gap:20px;font-weight:bold;font-size:.9em;border-top:1px solid var(--overlay);padding-top:10px;align-items:center;">
                        <button class="like-btn" data-id="<?= urlencode($id) ?>" data-action="like" style="background:none;border:none;cursor:pointer;color:var(--softlime);font-weight:bold;font-size:.9em;padding:0;margin:0;width:auto;">
                            👍 <span class="like-count"><?= count($plat['likes'] ?? []) ?></span>
                        </button>
                        <button class="like-btn" data-id="<?= urlencode($id) ?>" data-action="dislike" style="background:none;border:none;cursor:pointer;color:#f38ba8;font-weight:bold;font-size:.9em;padding:0;margin:0;width:auto;">
                            👎 <span class="dislike-count"><?= count($plat['dislikes'] ?? []) ?></span>
                        </button>
                        <a href="view.php?id=<?= urlencode($id) ?>" style="color:var(--sapphire);text-decoration:none;">💬 <?= count($plat['comments'] ?? []) ?> avis</a>
                        <?php if ($isLoggedIn && ($_SESSION['user_role']??'') === 'client'): ?>
                            <a href="commande.php" style="margin-left:auto;color:var(--accent-btn);text-decoration:none;font-size:.85rem;">Commander →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; endif; ?>
        </ul>
    </section>
</main>
<style>
@keyframes likePop{0%{transform:scale(1)}40%{transform:scale(1.55)}70%{transform:scale(.88)}100%{transform:scale(1)}}
.like-btn.popping{animation:likePop .4s cubic-bezier(.34,1.56,.64,1) both}
.like-btn:disabled{opacity:.5;cursor:not-allowed}
</style>
<script>
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id, action = this.dataset.action, card = this.closest('li');
        card.querySelectorAll('.like-btn').forEach(b => b.disabled = true);
        this.classList.remove('popping'); void this.offsetWidth; this.classList.add('popping');
        this.addEventListener('animationend', () => this.classList.remove('popping'), {once:true});
        try {
            const res = await fetch(`menu.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`);
            const data = await res.json();
            card.querySelector('.like-count').textContent    = data.likes;
            card.querySelector('.dislike-count').textContent = data.dislikes;
        } catch(e){ console.error(e); }
        finally { card.querySelectorAll('.like-btn').forEach(b => b.disabled = false); }
    });
});
</script>
</body>
</html>
