<?php
require_once __DIR__ . '/inc/common.php';
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: connect.php"); exit();
}

$usersFile    = 'users.json';
$commandsFile = 'commandes.json';
$platsFile    = 'plats.json';

$allUsers    = file_exists($usersFile)    ? json_decode(file_get_contents($usersFile),    true) : [];
$allOrders   = file_exists($commandsFile) ? json_decode(file_get_contents($commandsFile), true) : [];
$allPlats    = file_exists($platsFile)    ? json_decode(file_get_contents($platsFile),    true) : [];

$message = "";
$isAjax = (isset($_POST['ajax']) && $_POST['ajax']) || (isset($_GET['ajax']) && $_GET['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// --- CHANGE USER ROLE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_role'])) {
    $targetId  = $_POST['user_id'];
    $newRole   = in_array($_POST['new_role'], ['client','cuisiner','livreur','admin']) ? $_POST['new_role'] : 'client';
    if (isset($allUsers[$targetId])) {
        $allUsers[$targetId]['role'] = $newRole;
        file_put_contents($usersFile, json_encode($allUsers, JSON_PRETTY_PRINT));
        $message = "<div class='msg-success'>Rôle mis à jour.</div>";
        $allUsers = json_decode(file_get_contents($usersFile), true);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'role' => $newRole,
                'color' => $roleBadge[$newRole] ?? 'var(--text-muted)',
                'icon' => $roleIcon[$newRole] ?? '👤',
                'message' => 'Rôle mis à jour.'
            ]);
            exit();
        }
    }
}

// --- DELETE USER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_user'])) {
    $targetId = $_POST['user_id'];
    if (isset($allUsers[$targetId]) && ($allUsers[$targetId]['role'] ?? '') !== 'admin') {
        unset($allUsers[$targetId]);
        file_put_contents($usersFile, json_encode($allUsers, JSON_PRETTY_PRINT));
        $message = "<div class='msg-success'>Utilisateur supprimé.</div>";
        $allUsers = json_decode(file_get_contents($usersFile), true);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Utilisateur supprimé.'
            ]);
            exit();
        }
    }
}

// --- ADD DISH ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_dish'])) {
    $dishId = strtolower(preg_replace('/\s+/', '_', trim($_POST['dish_name'])));
    $dishId = preg_replace('/[^a-z0-9_]/', '', $dishId);
    if ($dishId && !isset($allPlats[$dishId])) {
        $allPlats[$dishId] = [
            "name"             => trim($_POST['dish_name']),
            "image_url"        => trim($_POST['dish_image']),
            "text_description" => trim($_POST['dish_desc']),
            "price"            => floatval($_POST['dish_price']),
            "is_vegetarian"    => isset($_POST['dish_veg']),
            "likes"            => [], "dislikes" => [], "comments" => []
        ];
        file_put_contents($platsFile, json_encode($allPlats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $message = "<div class='msg-success'>Plat ajouté.</div>";
        $allPlats = json_decode(file_get_contents($platsFile), true);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Plat ajouté.',
                'dish' => [
                    'id' => $dishId,
                    'name' => trim($_POST['dish_name']),
                    'price' => floatval($_POST['dish_price']),
                    'is_vegetarian' => isset($_POST['dish_veg']),
                    'likes_count' => 0
                ]
            ]);
            exit();
        }
    } else {
        $message = "<div class='msg-error'>ID de plat déjà existant ou invalide.</div>";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'ID de plat déjà existant ou invalide.'
            ]);
            exit();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ban_user'])) {
    $targetId = $_POST['user_id'];
    if (isset($allUsers[$targetId]) && ($allUsers[$targetId]['role'] ?? '') !== 'admin') {
        $allUsers[$targetId]['is_banned'] = true;
        save_json($usersFile, $allUsers);
        $message = "<div class='msg-success'>Utilisateur banni.</div>";
        $allUsers = load_json($usersFile);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_banned' => true,
                'message' => 'Utilisateur banni.'
            ]);
            exit();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['unban_user'])) {
    $targetId = $_POST['user_id'];
    if (isset($allUsers[$targetId]) && ($allUsers[$targetId]['role'] ?? '') !== 'admin') {
        unset($allUsers[$targetId]['is_banned']);
        save_json($usersFile, $allUsers);
        $message = "<div class='msg-success'>Utilisateur débanni.</div>";
        $allUsers = load_json($usersFile);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_banned' => false,
                'message' => 'Utilisateur débanni.'
            ]);
            exit();
        }
    }
}

// --- DELETE DISH ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_dish'])) {
    $dishId = $_POST['dish_id'];
    if (isset($allPlats[$dishId])) {
        unset($allPlats[$dishId]);
        file_put_contents($platsFile, json_encode($allPlats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $message = "<div class='msg-success'>Plat supprimé.</div>";
        $allPlats = json_decode(file_get_contents($platsFile), true);
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Plat supprimé.'
            ]);
            exit();
        }
    }
}

// Stats
//$totalRevenue  = array_sum(array_column($allOrders, 'price'));
$totalRevenue  = array_sum(array_column($allOrders, 'price'));
$statusLabels  = [0=>'Payée',1=>'En préparation',2=>'Prête',3=>'En livraison',4=>'Livrée'];
$roleBadge     = ['admin'=>'var(--mauve)','cuisiner'=>'var(--softlime)','livreur'=>'var(--sapphire)','client'=>'var(--text-muted)'];
$roleIcon      = ['admin'=>'⚙','cuisiner'=>'🍳','livreur'=>'🛵','client'=>'👤'];

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration</title>
    <link rel="stylesheet" href="style.css">
    
</head>
<body>
<?php include '_nav.php'; ?>
<main class="main-container">
    <div class="page-header">
        <h1>⚙ Administration</h1>
        <p>Gestion complète du restaurant</p>
    </div>

    <?= $message ?>

    <!-- Stats -->
    <div class="stat-grid" style="max-width:850px;width:100%;">
        <div class="stat-card"><div class="val"><?= count($allUsers) ?></div><div class="lbl">Utilisateurs</div></div>
        <div class="stat-card"><div class="val"><?= count($allOrders) ?></div><div class="lbl">Commandes</div></div>
        <div class="stat-card"><div class="val"><?= count($allPlats) ?></div><div class="lbl">Plats au menu</div></div>
        <div class="stat-card"><div class="val" style="color:var(--softlime);"><?= number_format($totalRevenue,2,',',' ') ?> €</div><div class="lbl">Revenus du jour</div></div>
    </div>

    <!-- Tabs -->
    <div class="glass-panel large" style="max-width:950px;">
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('users',this)">👥 Utilisateurs</button>
            <button class="tab-btn" onclick="switchTab('orders',this)">📋 Commandes</button>
            <button class="tab-btn" onclick="switchTab('dishes',this)">🍽 Menu</button>
        </div>

        <!-- TAB: USERS -->
        <div class="tab-panel active" id="tab-users">
            <table>
                <thead><tr><th>ID (8c)</th><th>Rôle</th><th>Modifier</th><th>Supprimer</th></tr></thead>
                <tbody>
                <?php foreach ($allUsers as $uid => $u):
                    $role = $u['role'] ?? 'client';
                    $name = $u['plain_name'] ?? substr($uid, 0, 8).'…';
                    $color = $roleBadge[$role] ?? 'var(--text-muted)';
                    $icon  = $roleIcon[$role]  ?? '👤';
                ?>
                <tr>
                    <td><code style="color:var(--text-muted);font-size:.78rem;"><?= htmlspecialchars($name) ?></code></td>
                    <td><span class="role-pill" style="background:rgba(255,255,255,.06);color:<?= $color ?>;border:1px solid <?= $color ?>;"><?= $icon ?> <?= $role ?></span></td>
                    <td>
                        <?php if ($role !== 'admin'): ?>
                        <form method="POST" style="display:inline-flex;gap:8px;align-items:center;">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                            <select name="new_role" class="inline">
                                <option value="client"   <?= $role==='client'   ?'selected':'' ?>>👤 Client</option>
                                <option value="cuisiner" <?= $role==='cuisiner' ?'selected':'' ?>>🍳 Cuisiner</option>
                                <option value="livreur"  <?= $role==='livreur'  ?'selected':'' ?>>🛵 Livreur</option>
                                <option value="admin"    <?= $role==='admin'    ?'selected':'' ?>>⚙ Admin</option>
                            </select>
                            <button type="submit" name="change_role" class="btn btn-sm">OK</button>
                        </form>
                        <?php else: echo '<span style="color:var(--text-muted);font-size:.8rem;">Compte système</span>'; endif; ?>
                    </td>
                    <td>
                        <?php if ($role !== 'admin'): ?>
                        <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                            <button type="submit" name="delete_user" class="btn-danger-sm">🗑</button>
                        </form>
                         <?php if (!isset($u['is_banned']) || $u['is_banned'] !== true): ?>
                        <form method="POST" data-confirm="Bannir cet utilisateur ?" class="inline-form-small">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                            <button type="submit" name="ban_user" class="btn-danger-sm">🚫</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" data-confirm="Débannir cet utilisateur ?" class="inline-form-small">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                            <button type="submit" name="unban_user" class="btn-success-sm">✅</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB: ORDERS -->
        <div class="tab-panel" id="tab-orders">
            <table>
                <thead><tr><th>ID</th><th>Adresse</th><th>Plats</th><th>Prix</th><th>Heure</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($allOrders as $oid => $o):
                    $readyVal = $o['ready'] ?? 0;
                    $statusColors = [0=>'var(--text-muted)',1=>'var(--accent-btn)',2=>'var(--softlime)',3=>'var(--sapphire)',4=>'var(--mauve)'];
                    $sc = $statusColors[$readyVal] ?? 'var(--text-muted)';
                ?>
                <tr>
                    <td><code style="font-size:.75rem;color:var(--text-muted);"><?= substr($oid,0,8) ?>…</code></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($o['adress'] ?? '') ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars(implode(', ', $o['commands'] ?? [])) ?></td>
                    <td style="color:var(--softlime);font-weight:700;"><?= number_format($o['price'],2,',',' ') ?> €</td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= substr($o['comm_t'] ?? '', -8, 5) ?></td>
                    <td><span class="role-pill" style="background:rgba(255,255,255,.05);color:<?= $sc ?>;border:1px solid <?= $sc ?>;"><?= $statusLabels[$readyVal] ?? '?' ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB: DISHES -->
        <div class="tab-panel" id="tab-dishes">
            <table style="margin-bottom:30px;">
                <thead><tr><th>Plat</th><th>Prix</th><th>Végétarien</th><th>Likes</th><th>Supprimer</th></tr></thead>
                <tbody>
                <?php foreach ($allPlats as $pid => $p): ?>
                <tr>
                    <td style="font-weight:600;color:var(--sapphire);"><?= htmlspecialchars($p['name']) ?></td>
                    <td style="color:var(--softlime);"><?= number_format($p['price'],2,',',' ') ?> €</td>
                    <td><?= ($p['is_vegetarian'] ?? false) ? '🌱' : '—' ?></td>
                    <td style="color:var(--text-muted);">👍 <?= count($p['likes'] ?? []) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Supprimer ce plat ?');" style="display:inline;">
                            <input type="hidden" name="dish_id" value="<?= htmlspecialchars($pid) ?>">
                            <button type="submit" name="delete_dish" class="btn-danger-sm">🗑</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="border:none;border-top:1px solid var(--overlay);margin:20px 0;">
            <h3 style="color:var(--sapphire);margin-bottom:16px;">Ajouter un plat</h3>
            <form action="" method="POST">
                <div class="lined">
                    <div class="form-group"><label>Nom du plat</label><input type="text" name="dish_name" required></div>
                    <div class="form-group"><label>Prix (€)</label><input type="number" name="dish_price" step="0.5" min="0" required></div>
                </div>
                <div class="form-group"><label>URL Image</label><input type="url" name="dish_image" placeholder="https://…"></div>
                <div class="form-group"><label>Description</label><textarea name="dish_desc" rows="2" required></textarea></div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="dish_veg" id="veg" style="width:auto;padding:0;">
                    <label for="veg" style="text-transform:none;letter-spacing:0;font-size:1rem;color:var(--text);">🌱 Végétarien</label>
                </div>
                <button type="submit" name="add_dish">Ajouter au menu</button>
            </form>
        </div>
    </div>
</main>
<script src="scripts.js" defer></script>
</body>
</html>
