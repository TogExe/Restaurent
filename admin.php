<?php
require_once __DIR__ . '/inc/common.php';
require_login('admin');

$usersFile    = 'users.json';
$commandsFile = 'commandes.json';
$platsFile    = 'plats.json';

$allUsers  = load_json($usersFile);
$allOrders = load_json($commandsFile);
$allPlats  = load_json($platsFile);

$message = "";

// --- CHANGE USER ROLE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_role'])) {
    $targetId = $_POST['user_id'];
    $newRole = in_array($_POST['new_role'], ['client','cuisiner','livreur','admin']) ? $_POST['new_role'] : 'client';

    if (isset($allUsers[$targetId])) {
        $allUsers[$targetId]['role'] = $newRole;
        save_json($usersFile, $allUsers);
        $message = "<div class='msg-success'>Rôle mis à jour.</div>";
        $allUsers = load_json($usersFile);
    }
}

// --- DELETE USER ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_user'])) {
    $targetId = $_POST['user_id'];

    if (isset($allUsers[$targetId]) && ($allUsers[$targetId]['role'] ?? '') !== 'admin') {
        unset($allUsers[$targetId]);
        save_json($usersFile, $allUsers);
        $message = "<div class='msg-success'>Utilisateur supprimé.</div>";
        $allUsers = load_json($usersFile);
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
            "likes"            => [],
            "dislikes"         => [],
            "comments"         => []
        ];

        save_json($platsFile, $allPlats);
        $message = "<div class='msg-success'>Plat ajouté.</div>";
        $allPlats = load_json($platsFile);
    } else {
        $message = "<div class='msg-error'>ID de plat déjà existant ou invalide.</div>";
    }
}

// --- DELETE DISH ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_dish'])) {
    $dishId = $_POST['dish_id'];

    if (isset($allPlats[$dishId])) {
        unset($allPlats[$dishId]);
        save_json($platsFile, $allPlats);
        $message = "<div class='msg-success'>Plat supprimé.</div>";
        $allPlats = load_json($platsFile);
    }
}

// Stats
$totalRevenue = array_sum(array_column($allOrders, 'price'));

$statusLabels = [
    0 => 'Payée',
    1 => 'En préparation',
    2 => 'Prête',
    3 => 'En livraison',
    4 => 'Livrée'
];

$roleIcon = [
    'admin'    => '⚙',
    'cuisiner' => '🍳',
    'livreur'  => '🛵',
    'client'   => '👤'
];

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
    <div class="stat-grid admin-stat-grid">
        <div class="stat-card">
            <div class="val"><?= count($allUsers) ?></div>
            <div class="lbl">Utilisateurs</div>
        </div>

        <div class="stat-card">
            <div class="val"><?= count($allOrders) ?></div>
            <div class="lbl">Commandes</div>
        </div>

        <div class="stat-card">
            <div class="val"><?= count($allPlats) ?></div>
            <div class="lbl">Plats au menu</div>
        </div>

        <div class="stat-card">
            <div class="val revenue-value">
                <?= number_format($totalRevenue, 2, ',', ' ') ?> €
            </div>
            <div class="lbl">Revenus du jour</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="glass-panel large admin-panel">

        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('users', this)">👥 Utilisateurs</button>
            <button class="tab-btn" onclick="switchTab('orders', this)">📋 Commandes</button>
            <button class="tab-btn" onclick="switchTab('dishes', this)">🍽 Menu</button>
        </div>

        <!-- TAB: USERS -->
        <div class="tab-panel active" id="tab-users">
            <table>
                <thead>
                    <tr>
                        <th>ID 8c</th>
                        <th>Rôle</th>
                        <th>Modifier</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($allUsers as $uid => $u):
                    $role = $u['role'] ?? 'client';
                    $name = $u['plain_name'] ?? substr($uid, 0, 8) . '…';
                    $icon = $roleIcon[$role] ?? '👤';
                ?>
                    <tr>
                        <td>
                            <code class="admin-code-muted">
                                <?= htmlspecialchars($name) ?>
                            </code>
                        </td>

                        <td>
                            <span class="role-pill role-<?= htmlspecialchars($role) ?>">
                                <?= $icon ?> <?= htmlspecialchars($role) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($role !== 'admin'): ?>
                                <form method="POST" class="admin-form-inline">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">

                                    <select name="new_role" class="inline">
                                        <option value="client" <?= $role === 'client' ? 'selected' : '' ?>>👤 Client</option>
                                        <option value="cuisiner" <?= $role === 'cuisiner' ? 'selected' : '' ?>>🍳 Cuisiner</option>
                                        <option value="livreur" <?= $role === 'livreur' ? 'selected' : '' ?>>🛵 Livreur</option>
                                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>⚙ Admin</option>
                                    </select>

                                    <button type="submit" name="change_role" class="btn btn-sm">OK</button>
                                </form>
                            <?php else: ?>
                                <span class="admin-muted">Compte système</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($role !== 'admin'): ?>
                                <form method="POST" class="admin-form-delete" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                                    <button type="submit" name="delete_user" class="btn-danger-sm">🗑</button>
                                </form>
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
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Adresse</th>
                        <th>Plats</th>
                        <th>Prix</th>
                        <th>Heure</th>
                        <th>Statut</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($allOrders as $oid => $o):
                    $readyVal = $o['ready'] ?? 0;
                ?>
                    <tr>
                        <td>
                            <code class="admin-code-small">
                                <?= substr($oid, 0, 8) ?>…
                            </code>
                        </td>

                        <td class="admin-address">
                            <?= htmlspecialchars($o['adress'] ?? '') ?>
                        </td>

                        <td class="admin-muted-small">
                            <?= htmlspecialchars(implode(', ', $o['commands'] ?? [])) ?>
                        </td>

                        <td class="admin-price">
                            <?= number_format($o['price'], 2, ',', ' ') ?> €
                        </td>

                        <td class="admin-muted-small">
                            <?= substr($o['comm_t'] ?? '', -8, 5) ?>
                        </td>

                        <td>
                            <span class="role-pill status-<?= intval($readyVal) ?>">
                                <?= $statusLabels[$readyVal] ?? '?' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB: DISHES -->
        <div class="tab-panel" id="tab-dishes">
            <table class="admin-table-spaced">
                <thead>
                    <tr>
                        <th>Plat</th>
                        <th>Prix</th>
                        <th>Végétarien</th>
                        <th>Likes</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($allPlats as $pid => $p): ?>
                    <tr>
                        <td class="admin-dish-name">
                            <?= htmlspecialchars($p['name']) ?>
                        </td>

                        <td class="admin-price">
                            <?= number_format($p['price'], 2, ',', ' ') ?> €
                        </td>

                        <td>
                            <?= ($p['is_vegetarian'] ?? false) ? '🌱' : '—' ?>
                        </td>

                        <td class="admin-muted-small">
                            👍 <?= count($p['likes'] ?? []) ?>
                        </td>

                        <td>
                            <form method="POST" class="admin-form-delete" onsubmit="return confirm('Supprimer ce plat ?');">
                                <input type="hidden" name="dish_id" value="<?= htmlspecialchars($pid) ?>">
                                <button type="submit" name="delete_dish" class="btn-danger-sm">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="admin-separator">

            <h3 class="admin-subtitle">Ajouter un plat</h3>

            <form action="" method="POST">
                <div class="lined">
                    <div class="form-group">
                        <label>Nom du plat</label>
                        <input type="text" name="dish_name" required>
                    </div>

                    <div class="form-group">
                        <label>Prix €</label>
                        <input type="number" name="dish_price" step="0.5" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Image</label>
                    <input type="url" name="dish_image" placeholder="https://…">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="dish_desc" rows="2" required></textarea>
                </div>

                <div class="form-group checkbox-row">
                    <input type="checkbox" name="dish_veg" id="veg">
                    <label for="veg">🌱 Végétarien</label>
                </div>

                <button type="submit" name="add_dish">Ajouter au menu</button>
            </form>
        </div>

    </div>
</main>

<script>
function switchTab(id, el) {
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });

    document.querySelectorAll('.tab-btn').forEach(button => {
        button.classList.remove('active');
    });

    document.getElementById('tab-' + id).classList.add('active');
    el.classList.add('active');
}
</script>

</body>
</html>
