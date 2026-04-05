<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: connect.php"); exit();
}

function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded   = base64_decode($payload);
    $iv        = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

$usersFile    = 'users.json';
$commandsFile = 'commandes.json';
$platsFile    = 'plats.json';

$allUsers    = file_exists($usersFile)    ? json_decode(file_get_contents($usersFile),    true) : [];
$allOrders   = file_exists($commandsFile) ? json_decode(file_get_contents($commandsFile), true) : [];
$allPlats    = file_exists($platsFile)    ? json_decode(file_get_contents($platsFile),    true) : [];

$message = "";

// --- CHANGE USER ROLE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_role'])) {
    $targetId  = $_POST['user_id'];
    $newRole   = in_array($_POST['new_role'], ['client','cuisiner','livreur','admin']) ? $_POST['new_role'] : 'client';
    if (isset($allUsers[$targetId])) {
        $allUsers[$targetId]['role'] = $newRole;
        file_put_contents($usersFile, json_encode($allUsers, JSON_PRETTY_PRINT));
        $message = "<div class='msg-success'>Rôle mis à jour.</div>";
        $allUsers = json_decode(file_get_contents($usersFile), true);
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
    } else {
        $message = "<div class='msg-error'>ID de plat déjà existant ou invalide.</div>";
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
    }
}

// Stats
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
    <style>
        .admin-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:30px;}
        .tab-btn{padding:10px 22px;border-radius:8px;border:1px solid var(--overlay);background:rgba(255,255,255,.04);color:var(--text-muted);cursor:pointer;font-family:'Outfit',sans-serif;font-size:.88rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;transition:all .2s;width:auto;}
        .tab-btn.active,.tab-btn:hover{background:rgba(138,180,255,.1);border-color:var(--accent-btn);color:var(--accent-btn);}
        .tab-panel{display:none;} .tab-panel.active{display:block;}
        table{width:100%;border-collapse:collapse;font-size:.88rem;}
        th{color:var(--text-muted);text-align:left;padding:10px 12px;border-bottom:1px solid var(--overlay);font-weight:600;letter-spacing:.05em;text-transform:uppercase;font-size:.78rem;}
        td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        .role-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;}
        .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:30px;}
        .stat-card{background:rgba(255,255,255,.04);border:1px solid var(--overlay);border-radius:12px;padding:20px;text-align:center;}
        .stat-card .val{font-size:2rem;font-weight:700;color:var(--mauve);}
        .stat-card .lbl{color:var(--text-muted);font-size:.82rem;margin-top:4px;}
        select.inline{width:auto;padding:6px 10px;font-size:.82rem;border-radius:6px;}
        .btn-sm{display:inline-block;width:auto;padding:6px 14px;font-size:.78rem;margin-top:0;border-radius:6px;letter-spacing:.05em;}
        .btn-danger-sm{background:linear-gradient(135deg,var(--rose),#ff4a6e);color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:.78rem;font-family:'Outfit',sans-serif;font-weight:700;}
        .btn-danger-sm:hover{opacity:.85;transform:translateY(-1px);}
    </style>
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
<script>
function switchTab(id, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-'+id).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>
