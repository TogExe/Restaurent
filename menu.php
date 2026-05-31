<?php
require_once __DIR__ . '/inc/common.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$filePlats = 'data/plats.json';
$plats     = load_json($filePlats);

// Build menu sections from dish tags for grouped display by category
$categories = [];
$untagged = [];

if (!empty($plats)) {
    foreach ($plats as $id => $plat) {
        if (isset($plat['tags']) && is_array($plat['tags']) && !empty($plat['tags'])) {
            foreach ($plat['tags'] as $tag) {
                $catName = ucfirst(trim($tag));
                $categories[$catName][$id] = $plat;
            }
        } else {
            $untagged[$id] = $plat;
        }
    }
}

ksort($categories);

if (!empty($untagged)) {
    $categories['Autres'] = $untagged;
}

// Process like/dislike actions and persist the current user's preference
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
            $likes = in_array($userId, $likes) ? array_diff($likes, [$userId]) : [...$likes, $userId];
        } elseif ($action === 'dislike') {
            $likes = array_diff($likes, [$userId]);
            $dislikes = in_array($userId, $dislikes) ? array_diff($dislikes, [$userId]) : [...$dislikes, $userId];
        }

        $plats[$platId]['likes']    = array_values($likes);
        $plats[$platId]['dislikes'] = array_values($dislikes);

        save_json($filePlats, $plats);
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
    // --- GESTION DU PANIER JAVASCRIPT SANS RECHARGEMENT ---
    function addToCartJS(event, btn) {
        event.preventDefault(); 
        
        const pid = btn.dataset.id;
        const price = parseFloat(btn.dataset.price);
        const name = btn.dataset.name;

        let cart = JSON.parse(sessionStorage.getItem('restaurantCart')) || {};
        
        if (!cart[pid]) {
            cart[pid] = { qty: 0, price: price, name: name };
        }
        cart[pid].qty++;
        
        const newQty = cart[pid].qty;
        sessionStorage.setItem('restaurantCart', JSON.stringify(cart));
        
        // On cible uniquement les spans pour ne pas casser le HTML du bouton
        const textSpan = btn.querySelector('.btn-text');
        const qtySpan = btn.querySelector('.btn-qty');
        
        if (textSpan) textSpan.textContent = "✔ Ajouté";
        if (qtySpan) qtySpan.textContent = ` (${newQty})`;
        
        // Effet visuel immédiat
        btn.style.color = "var(--softlime)";
        btn.style.borderColor = "var(--softlime)";
        btn.style.background = "rgba(126, 203, 163, 0.1)";
        btn.style.transform = "scale(1.05)"; 
        
        // Retour à la normale après 0.8s
        setTimeout(() => {
            if (textSpan) textSpan.textContent = "+ Ajouter";
            btn.style.color = "";
            btn.style.borderColor = "";
            btn.style.background = "";
            btn.style.transform = "";
        }, 800);
    }

    function updateCartBadges() {
        let cart = JSON.parse(sessionStorage.getItem('restaurantCart')) || {};
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            const pid = btn.dataset.id;
            const qtySpan = btn.querySelector('.btn-qty');
            
            if (qtySpan) {
                if (cart[pid] && cart[pid].qty > 0) {
                    qtySpan.textContent = ` (${cart[pid].qty})`;
                } else {
                    qtySpan.textContent = "";
                }
            }
        });
    }

    // --- GESTION DE LA RECHERCHE ET DES FILTRES ---
    function applyFilters() {
        const searchInput = document.getElementById('menuSearch');
        if (!searchInput) return;

        const term = searchInput.value.toLowerCase();
        const checkedBoxes = Array.from(document.querySelectorAll('.category-filter:checked')).map(cb => cb.value);

        document.querySelectorAll('.menu-category-wrapper').forEach(wrapper => {
            const catName = wrapper.querySelector('h2').textContent.trim();
            const isCatSelected = checkedBoxes.length === 0 || checkedBoxes.includes(catName);
            
            let hasVisibleCard = false;
            const cards = wrapper.querySelectorAll('.menu-item-card');
            
            cards.forEach(card => {
                const title = card.querySelector('.menu-item-title').textContent.toLowerCase();
                const desc = card.querySelector('.menu-item-description').textContent.toLowerCase();
                const matchesSearch = title.includes(term) || desc.includes(term);
                
                if (isCatSelected && matchesSearch) {
                    card.style.display = 'flex';
                    hasVisibleCard = true;
                } else {
                    card.style.display = 'none';
                }
            });

            wrapper.style.display = hasVisibleCard ? 'block' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateCartBadges();
        
        const searchInput = document.getElementById('menuSearch');
        if (searchInput) searchInput.addEventListener('input', applyFilters);
        
        document.querySelectorAll('.category-filter').forEach(cb => {
            cb.addEventListener('change', applyFilters);
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

    <div class="menu-search-wrapper" style="margin-bottom: 20px;">
        <label for="menuSearch" class="sr-only">Rechercher un plat ou un ingrédient</label>
        <input type="text" id="menuSearch" class="menu-search-input" placeholder="Rechercher un plat, un ingrédient..." aria-label="Recherche de plat" style="width: 100%; padding: 10px; margin-bottom: 15px;">
        
        <div class="menu-filters" style="display: flex; gap: 15px; flex-wrap: wrap; background: rgba(255,255,255,0.03); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--overlay);">
            <strong style="margin-right: 10px; color: var(--text-muted);">Filtrer par catégorie :</strong>
            <?php foreach (array_keys($categories) as $catName): ?>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: var(--text);">
                    <input type="checkbox" class="category-filter" value="<?= htmlspecialchars($catName) ?>">
                    <?= htmlspecialchars($catName) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="glass-panel menu-panel">
        <?php if (empty($categories)): ?>
            <p class="empty-menu">Aucun plat disponible.</p>
        <?php else: ?>
            <?php foreach ($categories as $catName => $catPlats): ?>
                <div class="menu-category-wrapper">
                    <h2 class="menu-category-title"><?= htmlspecialchars($catName) ?></h2>
                    
                    <ul class="item-list menu-layout-grid">
                        <?php foreach ($catPlats as $id => $plat): ?>
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
                                        <a href="menu.php?action=like&id=<?= urlencode($id) ?>" class="like-btn like-positive" style="text-decoration:none;" aria-label="Aimer <?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>">
                                            👍 <span class="like-count"><?= count($plat['likes'] ?? []) ?></span>
                                        </a>
                                        <a href="menu.php?action=dislike&id=<?= urlencode($id) ?>" class="like-btn like-negative" style="text-decoration:none;" aria-label="Ne pas aimer <?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>">
                                            👎 <span class="dislike-count"><?= count($plat['dislikes'] ?? []) ?></span>
                                        </a>
                                        <a href="view.php?id=<?= urlencode($id) ?>" class="menu-comments-link" aria-label="Voir les avis de <?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>">
                                            💬 <?= count($plat['comments'] ?? []) ?>
                                        </a>
                                        
                                        <button type="button" class="add-to-cart-btn" 
                                            data-id="<?= htmlspecialchars($id) ?>" 
                                            data-name="<?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>" 
                                            data-price="<?= $plat['price'] ?>"
                                            onclick="addToCartJS(event, this)" 
                                            aria-label="Ajouter <?= htmlspecialchars($plat['name'], ENT_QUOTES) ?> au panier">
                                            <span class="btn-text">+ Ajouter</span><span class="btn-qty"></span>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
