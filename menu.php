<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId      = $isLoggedIn ? $_SESSION['user_id'] : null;

$filePlats = 'plats.json';
$allPlats  = file_exists($filePlats) ? json_decode(file_get_contents($filePlats), true) : [];

if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!$isLoggedIn) { header("Location: connect.php"); exit(); }
    $action = $_GET['action'];
    $platId = $_GET['id'];
    if (isset($allPlats[$platId])) {
        $likes    = $allPlats[$platId]['likes']    ?? [];
        $dislikes = $allPlats[$platId]['dislikes'] ?? [];
        if ($action === 'like') {
            $dislikes = array_diff($dislikes, [$userId]);
            $likes    = in_array($userId, $likes) ? array_diff($likes, [$userId]) : [...$likes, $userId];
        } elseif ($action === 'dislike') {
            $likes    = array_diff($likes, [$userId]);
            $dislikes = in_array($userId, $dislikes) ? array_diff($dislikes, [$userId]) : [...$dislikes, $userId];
        }
        $allPlats[$platId]['likes']    = array_values($likes);
        $allPlats[$platId]['dislikes'] = array_values($dislikes);
        file_put_contents($filePlats, json_encode($allPlats, JSON_PRETTY_PRINT));
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['likes' => count($allPlats[$platId]['likes']), 'dislikes' => count($allPlats[$platId]['dislikes'])]);
            exit();
        }
        header("Location: menu.php"); exit();
    }
}

// --- LOGIQUE DE RECHERCHE ET FILTRAGE ---
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, veg
$sort   = $_GET['sort']   ?? 'name'; // name, price_asc, price_desc, popular

$plats = $allPlats;

// 1. Recherche par texte
if ($search !== '') {
    $plats = array_filter($plats, function($p) use ($search) {
        return mb_stripos($p['name'], $search) !== false || mb_stripos($p['text_description'], $search) !== false;
    });
}

// 2. Filtrage (Végétarien)
if ($filter === 'veg') {
    $plats = array_filter($plats, function($p) {
        return $p['is_vegetarian'] ?? false;
    });
}

// 3. Tri
uasort($plats, function($a, $b) use ($sort) {
    if ($sort === 'price_asc')  return $a['price'] <=> $b['price'];
    if ($sort === 'price_desc') return $b['price'] <=> $a['price'];
    if ($sort === 'popular') {
        $popA = count($a['likes'] ?? []) - count($a['dislikes'] ?? []);
        $popB = count($b['likes'] ?? []) - count($b['dislikes'] ?? []);
        return $popB <=> $popA;
    }
    return strcasecmp($a['name'], $b['name']); // Par défaut : Nom A-Z
});
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
    <section class="search-box" style="margin-bottom: 30px;">
        <form method="GET" action="menu.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            
            <div style="flex: 2; min-width: 250px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">Rechercher un plat</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ex: Pizza, Burger..." 
                       style="width: 100%; padding: 12px; border-radius: 8px; background: var(--base); color: white; border: 1px solid var(--overlay);">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">Trier par</label>
                <select name="sort" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--base); color: white; border: 1px solid var(--overlay); cursor: pointer;">
                    <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Nom (A-Z)</option>
                    <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    <option value="popular"    <?= $sort === 'popular' ? 'selected' : '' ?>>Les mieux notés</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">Régime</label>
                <select name="filter" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--base); color: white; border: 1px solid var(--overlay); cursor: pointer;">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tous les plats</option>
                    <option value="veg" <?= $filter === 'veg' ? 'selected' : '' ?>>🌱 Végétarien</option>
                </select>
            </div>

           <a href="commande.php?add=<?= urlencode($id) ?>" style="margin-left:auto;color:var(--accent-btn);text-decoration:none;font-size:.85rem;">Commander →</a>
                Filtrer
            </button>

            <?php if($search || $filter !== 'all' || $sort !== 'name'): ?>
                <a href="menu.php" style="padding: 12px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </section>
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
