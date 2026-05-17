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
<<<<<<< HEAD
    <style>
        /* ── Cart Sidebar ───────────────────────────────────── */
        #cart-sidebar {
            position: fixed;
            top: 0;
            left: -420px;
            width: 380px;
            height: 100vh;
            background: rgba(18, 18, 40, 0.98);
            backdrop-filter: blur(28px) saturate(160%);
            -webkit-backdrop-filter: blur(28px) saturate(160%);
            border-right: 1px solid var(--glass-border);
            box-shadow: 8px 0 40px rgba(0,0,0,0.6);
            z-index: 200;
            display: flex;
            flex-direction: column;
            transition: left 0.38s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0;
        }

        #cart-sidebar.open {
            left: 0;
        }

        /* Overlay */
        #cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 199;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.38s ease;
        }

        #cart-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        /* Cart header */
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 28px 20px;
            border-bottom: 1px solid var(--glass-border);
            flex-shrink: 0;
        }

        .cart-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--mauve);
            text-shadow: 0 0 16px var(--mauve-glow);
        }

        .cart-close-btn {
            width: 34px !important;
            height: 34px !important;
            padding: 0 !important;
            margin-top: 0 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
            background: rgba(255,255,255,0.06) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-muted) !important;
            font-size: 1rem;
            transition: all 0.2s ease !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }

        .cart-close-btn::before { display: none !important; }

        .cart-close-btn:hover {
            background: rgba(255,107,138,0.15) !important;
            border-color: var(--rose) !important;
            color: var(--rose) !important;
            transform: none !important;
            box-shadow: none !important;
        }

        /* Cart items list */
        .cart-items-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 28px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .cart-items-list::-webkit-scrollbar { width: 4px; }
        .cart-items-list::-webkit-scrollbar-track { background: transparent; }
        .cart-items-list::-webkit-scrollbar-thumb { background: var(--overlay); border-radius: 4px; }

        /* Empty state */
        .cart-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 60px 20px;
            font-size: 0.95rem;
        }

        .cart-empty-icon {
            font-size: 3rem;
            margin-bottom: 14px;
            opacity: 0.4;
        }

        /* Cart item card */
        .cart-item {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 12px 14px;
            animation: slideInRight 0.25s cubic-bezier(0.34,1.56,0.64,1) both;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 0.92rem;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-price {
            color: var(--sapphire);
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 2px;
        }

        /* Quantity controls */
        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .qty-btn {
            width: 28px !important;
            height: 28px !important;
            padding: 0 !important;
            margin-top: 0 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
            background: rgba(255,255,255,0.07) !important;
            border: 1px solid var(--overlay-light) !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text) !important;
            font-size: 1rem;
            font-weight: 700;
            transition: all 0.18s ease !important;
            line-height: 1;
            flex-shrink: 0;
            box-shadow: none !important;
        }

        .qty-btn::before { display: none !important; }

        .qty-btn:hover {
            background: var(--overlay) !important;
            border-color: var(--mauve) !important;
            color: var(--mauve) !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .qty-btn.remove-btn:hover {
            background: rgba(255,107,138,0.15) !important;
            border-color: var(--rose) !important;
            color: var(--rose) !important;
        }

        .qty-value {
            font-weight: 700;
            font-size: 0.95rem;
            min-width: 20px;
            text-align: center;
            color: var(--text);
        }

        /* Cart footer */
        .cart-footer {
            border-top: 1px solid var(--glass-border);
            padding: 20px 28px 28px;
            flex-shrink: 0;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .cart-total-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }

        .cart-total-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--mauve);
            text-shadow: 0 0 12px var(--mauve-glow);
        }

        .cart-checkout-btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--mauve), #b87fff);
            color: #0a0a1a;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 20px var(--mauve-glow);
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .cart-checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px var(--mauve-glow);
            filter: brightness(1.08);
        }

        .cart-checkout-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .cart-clear-btn {
            width: 100% !important;
            margin-top: 10px !important;
            padding: 10px !important;
            border-radius: 10px !important;
            background: transparent !important;
            color: var(--rose) !important;
            border: 1px solid rgba(255,107,138,0.25) !important;
            font-size: 0.82rem !important;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease !important;
            letter-spacing: 0.03em;
            text-transform: none !important;
            box-shadow: none !important;
        }

        .cart-clear-btn::before { display: none !important; }

        .cart-clear-btn:hover {
            background: rgba(255,107,138,0.08) !important;
            border-color: var(--rose) !important;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ── Floating Cart Toggle Button ───────────────────── */
        #cart-fab {
            width: auto !important;
            margin-top: 0 !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            position: fixed;
            bottom: 32px;
            left: 32px;
            z-index: 198;
            background: linear-gradient(135deg, var(--mauve), #b87fff) !important;
            border: none !important;
            border-radius: 50px !important;
            padding: 12px 20px !important;
            display: flex !important;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 6px 28px var(--mauve-glow), 0 2px 8px rgba(0,0,0,0.4) !important;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1) !important;
            color: #0a0a1a !important;
            font-weight: 700;
            font-size: 0.88rem;
        }

        #cart-fab::before { display: none !important; }

        #cart-fab:hover {
            transform: translateY(-3px) scale(1.06) !important;
            box-shadow: 0 12px 36px var(--mauve-glow) !important;
            background: linear-gradient(135deg, #c99aff, #a060ff) !important;
        }

        #cart-fab-count {
            background: rgba(10,10,26,0.85);
            color: var(--mauve);
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 0.78rem;
            font-weight: 800;
            display: none;
        }

        #cart-fab-count.visible {
            display: inline-block;
        }

        .add-to-cart-btn {
            width: auto !important;
            margin-top: 0 !important;
            margin-left: auto;
            text-transform: none !important;
            letter-spacing: 0.03em !important;
            background: transparent !important;
            border: 1px solid rgba(212,168,255,0.35) !important;
            color: var(--mauve) !important;
            border-radius: 8px !important;
            padding: 6px 14px !important;
            font-size: 0.82rem !important;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.22s ease !important;
            display: flex !important;
            align-items: center;
            gap: 6px;
            box-shadow: none !important;
        }

        .add-to-cart-btn::before { display: none !important; }

        .add-to-cart-btn:hover {
            background: rgba(212,168,255,0.12) !important;
            border-color: var(--mauve) !important;
            box-shadow: 0 0 14px var(--mauve-glow) !important;
            transform: translateY(-1px) !important;
        }

        .add-to-cart-btn.added {
            background: rgba(126,203,163,0.15) !important;
            border-color: var(--softlime) !important;
            color: var(--softlime) !important;
        }

        @media (max-width: 440px) {
            #cart-sidebar { width: 100vw; right: -100vw; }
        }
    </style>
=======
    <script src="scripts.js" defer></script>

>>>>>>> 2d2bba251205532fcdf067575e3b129dee32b17f
</head>

<body>

<?php include '_nav.php'; ?>

<!-- Cart overlay -->
<div id="cart-overlay"></div>

<!-- Cart Sidebar -->
<div id="cart-sidebar">
    <div class="cart-header">
        <h2>🛒 Mon Panier</h2>
        <button class="cart-close-btn" id="cart-close-btn" title="Fermer">✕</button>
    </div>

    <div class="cart-items-list" id="cart-items-list">
        <div class="cart-empty">
            <div class="cart-empty-icon">🛒</div>
            <p>Votre panier est vide.<br>Ajoutez des plats depuis la carte !</p>
        </div>
    </div>

    <div class="cart-footer">
        <div class="cart-total-row">
            <span class="cart-total-label">Total</span>
            <span class="cart-total-price" id="cart-total">0,00 €</span>
        </div>
        <a href="commande.php" class="cart-checkout-btn" id="cart-checkout-btn">
            Commander →
        </a>
        <button class="cart-clear-btn" id="cart-clear-btn">
            🗑 Vider le panier
        </button>
    </div>
</div>

<!-- Floating cart button -->
<button id="cart-fab" title="Voir le panier">
    🛒 Panier
    <span id="cart-fab-count"></span>
</button>

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

                                <!-- Add to cart button -->
                                <button class="add-to-cart-btn"
                                        data-id="<?= urlencode($id) ?>"
                                        data-name="<?= htmlspecialchars($plat['name'], ENT_QUOTES) ?>"
                                        data-price="<?= $plat['price'] ?>">
                                    + Ajouter
                                </button>

                            </div>

                        </div>

                    </li>

                <?php endforeach; ?>

            <?php endif; ?>

        </ul>

    </section>

</main>

<script>
/* ═══════════════════════════════════════════
   LIKES
═══════════════════════════════════════════ */
document.querySelectorAll('.like-btn').forEach(btn => {

    btn.addEventListener('click', async function() {

        const id = this.dataset.id;
        const action = this.dataset.action;
        const card = this.closest('li');

        card.querySelectorAll('.like-btn').forEach(b => b.disabled = true);

        this.classList.remove('popping');
        void this.offsetWidth;
        this.classList.add('popping');
        this.addEventListener('animationend', () => this.classList.remove('popping'), { once: true });

        try {
            const res = await fetch(`menu.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`);
            const data = await res.json();
            card.querySelector('.like-count').textContent = data.likes;
            card.querySelector('.dislike-count').textContent = data.dislikes;
        } catch(e) {
            console.error(e);
        } finally {
            card.querySelectorAll('.like-btn').forEach(b => b.disabled = false);
        }
    });
});

/* ═══════════════════════════════════════════
   CART SYSTEM
═══════════════════════════════════════════ */
let cart = JSON.parse(localStorage.getItem('cart')) || [];

// ── Persist ──────────────────────────────
function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

// ── Add item ─────────────────────────────
function addToCart(id, name, price) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({ id, name, price: parseFloat(price), quantity: 1 });
    }
    saveCart();
    renderCart();
}

// ── Change quantity (+/-) ─────────────────
function changeQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) {
        cart = cart.filter(i => i.id !== id);
    }
    saveCart();
    renderCart();
}

// ── Remove item completely ────────────────
function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    saveCart();
    renderCart();
}

// ── Clear all ────────────────────────────
function clearCart() {
    cart = [];
    saveCart();
    renderCart();
}

// ── Render sidebar ────────────────────────
function renderCart() {
    const list    = document.getElementById('cart-items-list');
    const total   = document.getElementById('cart-total');
    const fabCount = document.getElementById('cart-fab-count');
    const navCount = document.getElementById('cart-count');     // navbar badge
    const checkoutBtn = document.getElementById('cart-checkout-btn');

    const totalItems = cart.reduce((s, i) => s + i.quantity, 0);
    const totalPrice = cart.reduce((s, i) => s + i.price * i.quantity, 0);

    // Navbar badge (from _nav.php)
    if (navCount) {
        navCount.textContent = totalItems;
        navCount.style.display = totalItems > 0 ? 'inline-block' : 'none';
    }

    // FAB count
    fabCount.textContent = totalItems;
    fabCount.classList.toggle('visible', totalItems > 0);

    // Total
    total.textContent = totalPrice.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

    // Checkout
    checkoutBtn.style.opacity = cart.length ? '1' : '0.4';
    checkoutBtn.style.pointerEvents = cart.length ? 'auto' : 'none';

    // Items
    if (cart.length === 0) {
        list.innerHTML = `
            <div class="cart-empty">
                <div class="cart-empty-icon">🛒</div>
                <p>Votre panier est vide.<br>Ajoutez des plats depuis la carte !</p>
            </div>`;
        return;
    }

    list.innerHTML = cart.map(item => {
        const subtotal = (item.price * item.quantity).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
        return `
        <div class="cart-item" data-id="${item.id}">
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">${subtotal} €</div>
            </div>
            <div class="cart-item-qty">
                <button class="qty-btn remove-btn" onclick="changeQty('${item.id}', -1)" title="Retirer un">−</button>
                <span class="qty-value">${item.quantity}</span>
                <button class="qty-btn" onclick="changeQty('${item.id}', 1)" title="Ajouter un">+</button>
                <button class="qty-btn remove-btn" onclick="removeFromCart('${item.id}')" title="Supprimer" style="margin-left:4px;">🗑</button>
            </div>
        </div>`;
    }).join('');
}

function openCart()  {
    document.getElementById('cart-sidebar').classList.add('open');
    document.getElementById('cart-overlay').classList.add('open');
}
function closeCart() {
    document.getElementById('cart-sidebar').classList.remove('open');
    document.getElementById('cart-overlay').classList.remove('open');
}

document.getElementById('cart-fab').addEventListener('click', openCart);
document.getElementById('cart-close-btn').addEventListener('click', closeCart);
document.getElementById('cart-overlay').addEventListener('click', closeCart);
document.getElementById('cart-clear-btn').addEventListener('click', () => {
    if (cart.length && confirm('Vider tout le panier ?')) clearCart();
});

// ── Add-to-cart buttons ───────────────────
document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const { id, name, price } = this.dataset;
        addToCart(id, name, price);

        // Flash feedback
        const orig = this.innerHTML;
        this.innerHTML = '✅ Ajouté !';
        this.classList.add('added');
        this.style.pointerEvents = 'none';
        setTimeout(() => {
            this.innerHTML = orig;
            this.classList.remove('added');
            this.style.pointerEvents = 'auto';
        }, 1100);
    });
});

// ── Init on page load ─────────────────────
renderCart();
</script>

</body>
</html>
