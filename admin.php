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

$commandesParClient = [];
foreach ($allOrders as $oid => $o) {
    $clientId = $o['client_id'] ?? '';
    if (!empty($clientId)) {
        // On regroupe les commandes sous l'index du client
        $commandesParClient[$clientId][$oid] = $o;
    }
}

// Extension des libellés de statut pour couvrir les cas d'annulation ou défaut de paiement
$statusLabels = [
    -2 => 'Non payée',
    -1 => 'Annulée',
    0  => 'Payée',
    1  => 'En préparation',
    2  => 'Prête',
    3  => 'En livraison',
    4  => 'Livrée'
];

// --- RÉCUPÉRATION DES LIVREURS ---
$listeLivreurs = [];
foreach ($allUsers as $id => $u) {
    if (($u['role'] ?? '') === 'livreur') {
        $listeLivreurs[$id] = $u['plain_name'] ?? 'Livreur-' . strtoupper(substr($id, 0, 6));
    }
}

// --- UPDATE ORDER STATUS & LIVREUR ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_order'])) {
    $orderId = $_POST['order_id'];
    
    if (isset($allOrders[$orderId])) {
        // Mise à jour du statut
        $allOrders[$orderId]['ready'] = (int)$_POST['new_status'];
        
        // Mise à jour du livreur
        if (!empty($_POST['livreur_id'])) {
            $allOrders[$orderId]['livreur_id'] = $_POST['livreur_id'];
        } else {
            unset($allOrders[$orderId]['livreur_id']); // On supprime si "Aucun" est sélectionné
        }

        // Sauvegarde dans le JSON
        file_put_contents($commandsFile, json_encode($allOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "<div class='msg-success'>Commande #$orderId mise à jour avec succès.</div>";
        
        // Rechargement des données fraîches
        $allOrders = json_decode(file_get_contents($commandsFile), true);
    }
}

// --- CHANGE USER ROLE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_role'])) {
    $targetId  = $_POST['user_id'];
    $newRole   = in_array($_POST['new_role'], ['client','cuisinier','livreur','admin']) ? $_POST['new_role'] : 'client';
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
$roleBadge     = ['admin'=>'var(--mauve)','cuisinier'=>'var(--softlime)','livreur'=>'var(--sapphire)','client'=>'var(--text-muted)'];
$roleIcon      = ['admin'=>'⚙','cuisinier'=>'🍳','livreur'=>'🛵','client'=>'👤'];

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration</title>
    <link rel="stylesheet" href="style.css">
    <script src="scripts.js" defer></script>
    
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
                <thead>
                    <tr>
                        <th>ID (8c)</th>
                        <th>Rôle</th>
                        <th>Modifier Rôle</th>
                        <th>Historique Commandes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allUsers as $uid => $u):
                    $role = $u['role'] ?? 'client';
                    // On affiche directement $uid si le plain_name n'est pas dispo
                    $name = $u['plain_name'] ?? $uid; 
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
                                <option value="cuisinier" <?= $role==='cuisinier' ?'selected':'' ?>>🍳 cuisinier</option>
                                <option value="livreur"  <?= $role==='livreur'  ?'selected':'' ?>>🛵 Livreur</option>
                                <option value="admin"    <?= $role==='admin'    ?'selected':'' ?>>⚙ Admin</option>
                            </select>
                            <button type="submit" name="change_role" class="btn btn-sm">OK</button>
                        </form>
                        <?php else: echo '<span style="color:var(--text-muted);font-size:.8rem;">Compte système</span>'; endif; ?>
                    </td>
                    
                    <td>
                        <?php if (!empty($commandesParClient[$uid])): ?>
                            <details style="cursor: pointer;">
                                <summary style="color: var(--sapphire); font-weight: 600; font-size: 0.85rem;">
                                    Voir les <?= count($commandesParClient[$uid]) ?> commandes
                                </summary>
                                <div style="padding: 8px; background: rgba(0,0,0,0.2); border-radius: 4px; margin-top: 5px; max-height: 180px; overflow-y: auto; text-align: left; min-width: 250px;">
                                    <?php foreach ($commandesParClient[$uid] as $orderId => $orderData): 
                                        $rVal = $orderData['ready'] ?? 0;
                                        $lbl  = $statusLabels[$rVal] ?? 'Inconnu';
                                        
                                        // Préparation des données pour la modale
                                        $orderDataForJS = $orderData;
                                        $orderDataForJS['status_label'] = $lbl;
                                        $orderDataForJS['client_name'] = $name;
                                        $jsonString = htmlspecialchars(json_encode($orderDataForJS), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <div style="font-size: 0.75rem; margin-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: var(--softlime);"><?= number_format($orderData['price'], 2, ',', ' ') ?> €</strong><br>
                                                <span style="font-size: 0.7rem; color: var(--text-muted);">Statut : <em><?= $lbl ?></em></span>
                                            </div>
                                            <button type="button" class="btn-sm btn view-order-btn" style="padding: 4px 8px; font-size: 0.7rem;" data-id="<?= htmlspecialchars($orderId) ?>" data-order="<?= $jsonString ?>">🔍 Détails</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">Aucune commande</span>
                        <?php endif; ?>
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
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Adresse</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $statusColors = [-2=>'var(--text-muted)', -1=>'var(--danger)', 0=>'var(--text-muted)', 1=>'var(--accent-btn)', 2=>'var(--softlime)', 3=>'var(--sapphire)', 4=>'var(--mauve)'];

                foreach ($allOrders as $oid => $o):
                    $readyVal = $o['ready'] ?? 0;
                    $sc = $statusColors[$readyVal] ?? 'var(--text-muted)';
                    
                    $cid = $o['client_id'] ?? '';
                    $clientDisplay = ($cid && isset($allUsers[$cid])) ? htmlspecialchars($allUsers[$cid]['plain_name'] ?? $cid) : "⚠️ Supprimé";
                    $clientStyle = ($cid && isset($allUsers[$cid])) ? "color: var(--text);" : "color: var(--danger); font-style: italic;";

                    // Préparation des données complètes pour le JavaScript
                    $orderDataForJS = $o;
                    $orderDataForJS['status_label'] = $statusLabels[$readyVal] ?? 'Inconnu';
                    $orderDataForJS['client_name'] = $clientDisplay;
                    $jsonString = htmlspecialchars(json_encode($orderDataForJS), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td><code style="font-size:.75rem;color:var(--text-muted);"><?= substr($oid,0,8) ?>…</code></td>
                    <td style="<?= $clientStyle ?>; font-size:.82rem;"><?= $clientDisplay ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($o['adress'] ?? '') ?></td>
                    <td style="color:var(--softlime);font-weight:700;"><?= number_format($o['price'],2,',',' ') ?> €</td>
                    <td><span class="role-pill" style="background:rgba(255,255,255,.05);color:<?= $sc ?>;border:1px solid <?= $sc ?>;"><?= $statusLabels[$readyVal] ?? 'Inconnu' ?></span></td>
                    <td>
                        <button type="button" class="btn-sm btn view-order-btn" data-id="<?= htmlspecialchars($oid) ?>" data-order="<?= $jsonString ?>">🔍 Détails</button>
                    </td>
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
    <div class="tab-panel" id="tab-orders">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Adresse</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $statusColors = [-2=>'var(--text-muted)', -1=>'var(--danger)', 0=>'var(--text-muted)', 1=>'var(--accent-btn)', 2=>'var(--softlime)', 3=>'var(--sapphire)', 4=>'var(--mauve)'];

                foreach ($allOrders as $oid => $o):
                    $readyVal = $o['ready'] ?? 0;
                    $sc = $statusColors[$readyVal] ?? 'var(--text-muted)';
                    
                    $cid = $o['client_id'] ?? '';
                    $clientDisplay = ($cid && isset($allUsers[$cid])) ? htmlspecialchars($allUsers[$cid]['plain_name'] ?? substr($cid, 0, 8).'…') : "⚠️ Supprimé";
                    $clientStyle = ($cid && isset($allUsers[$cid])) ? "color: var(--text);" : "color: var(--danger); font-style: italic;";

                    // Préparation des données complètes pour le JavaScript
                    $orderDataForJS = $o;
                    $orderDataForJS['status_label'] = $statusLabels[$readyVal] ?? 'Inconnu';
                    $orderDataForJS['client_name'] = $clientDisplay;
                    $jsonString = htmlspecialchars(json_encode($orderDataForJS), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td><code style="font-size:.75rem;color:var(--text-muted);"><?= substr($oid,0,8) ?>…</code></td>
                    <td style="<?= $clientStyle ?>; font-size:.82rem;"><?= $clientDisplay ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($o['adress'] ?? '') ?></td>
                    <td style="color:var(--softlime);font-weight:700;"><?= number_format($o['price'],2,',',' ') ?> €</td>
                    <td><span class="role-pill" style="background:rgba(255,255,255,.05);color:<?= $sc ?>;border:1px solid <?= $sc ?>;"><?= $statusLabels[$readyVal] ?? 'Inconnu' ?></span></td>
                    <td>
                        <button type="button" class="btn-sm btn view-order-btn" data-id="<?= htmlspecialchars($oid) ?>" data-order="<?= $jsonString ?>">🔍 Détails</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
</main>
        <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="glass-panel" style="width: 100%; max-width: 600px; max-height: 85vh; overflow-y: auto; position: relative; padding: 25px;">
            <button type="button" id="closeModalBtn" class="btn-danger-sm" style=" font-size: 1.2rem; padding: 5px 10px; cursor: pointer; z-index: 10;">✕</button>
            <h2 style="color: var(--sapphire); margin-bottom: 5px; padding-right: 40px; line-height: 1.2;">
                Commande <br><code id="modal-oid" style="font-size: 0.9rem; color: var(--text-muted); word-break: break-all;"></code>
            </h2>
            
            <p id="modal-status" style="margin-bottom: 20px; font-weight: bold;"></p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px;">
                    <strong style="color: var(--text-muted); font-size: 0.8rem;">Client & Livraison</strong><br>
                    👤 <span id="modal-client" style="word-break: break-all;"></span><br>
                    📍 <span id="modal-address"></span>
                </div>
                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px;">
                    <strong style="color: var(--text-muted); font-size: 0.8rem;">Horaires</strong><br>
                    ⏱ Passée à: <span id="modal-comm-t"></span><br>
                    🎯 Désirée à: <span id="modal-des-t"></span>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <strong style="color: var(--text-muted); font-size: 0.8rem;">Plats commandés</strong>
                <ul id="modal-plats" style="background: rgba(0,0,0,0.2); padding: 10px 10px 10px 30px; border-radius: 8px; margin-top: 5px;"></ul>
                <div style="text-align: right; margin-top: 10px; font-size: 1.2rem;">
                    Total : <strong style="color: var(--softlime);" id="modal-price"></strong>
                </div>
            </div>

            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 15px; border-top: 1px solid var(--overlay); padding-top: 15px;">
                💳 ID Paiement : <code id="modal-paid-id"></code><br>
                🔗 Type : <span id="modal-is-add">Standard</span>
            </div>

            <div id="modal-rating-box" style="display: none; background: rgba(255, 215, 0, 0.1); border-left: 4px solid gold; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 5px 0; color: gold;">Avis Client</h4>
                <div style="font-size: 1.2rem; margin-bottom: 5px;" id="modal-rating-stars"></div>
                <div style="font-style: italic; color: var(--text);" id="modal-rating-comment"></div>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border: 1px solid var(--sapphire);">
                <h3 style="color: var(--sapphire); margin-top: 0; margin-bottom: 12px; font-size: 1.1rem;">⚙️ Gestion de la Commande</h3>
                <form method="POST" action="">
                    <input type="hidden" name="order_id" id="form-order-id" value="">
                    <input type="hidden" name="update_order" value="1">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom: 5px; font-size: 0.85rem;">Statut de la commande</label>
                            <select name="new_status" id="form-order-status" style="width:100%; padding: 8px; border-radius: 4px; background: #1a1c1e; border: 1px solid var(--overlay); color: var(--text);">
                                <option value="-2">Non payée</option>
                                <option value="-1">Annulée</option>
                                <option value="0">Payée</option>
                                <option value="1">En préparation</option>
                                <option value="2">Prête</option>
                                <option value="3">En livraison</option>
                                <option value="4">Livrée</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom: 5px; font-size: 0.85rem;">Attribuer à un livreur</label>
                            <select name="livreur_id" id="form-order-livreur" style="width:100%; padding: 8px; border-radius: 4px; background: #1a1c1e; border: 1px solid var(--overlay); color: var(--text);">
                                <option value="">-- Aucun --</option>
                                <?php foreach($listeLivreurs as $lId => $lName): ?>
                                    <option value="<?= htmlspecialchars($lId) ?>">🛵 <?= htmlspecialchars($lName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('orderModal');
        
        // Fermeture
        if (event.target.id === 'closeModalBtn' || event.target.id === 'orderModal') {
            if (modal) modal.style.display = 'none';
            return;
        }

        // Ouverture (délégation d'événement)
        const btn = event.target.closest('.view-order-btn');
        if (btn) {
            event.preventDefault(); 
            
            try {
                const oid = btn.dataset.id;
                const data = JSON.parse(btn.dataset.order); // C'est souvent ici que ça casse si le JSON est mal formaté

                document.getElementById('modal-oid').textContent = oid;
                document.getElementById('modal-status').innerHTML = `Statut actuel : <span style="color:var(--accent-btn)">${data.status_label}</span>`;
                // Préremplir le formulaire de mise à jour (ID, statut et livreur)
                const formOrderId = document.getElementById('form-order-id');
                const formOrderStatus = document.getElementById('form-order-status');
                const formOrderLivreur = document.getElementById('form-order-livreur');
                if (formOrderId) formOrderId.value = oid;
                if (formOrderStatus) formOrderStatus.value = (typeof data.ready !== 'undefined') ? String(data.ready) : '';
                if (formOrderLivreur) formOrderLivreur.value = data.livreur_id ?? '';
                document.getElementById('modal-client').textContent = data.client_name || 'Inconnu';
                document.getElementById('modal-address').textContent = data.adress || 'Non renseignée';
                document.getElementById('modal-comm-t').textContent = data.comm_t || '--';
                document.getElementById('modal-des-t').textContent = data.des_t || '--';
                document.getElementById('modal-price').textContent = Number(data.price || 0).toFixed(2).replace('.', ',') + ' €';
                
                document.getElementById('modal-paid-id').textContent = data.paid_id || 'Aucun';
                
                const isAdd = document.getElementById('modal-is-add');
                if (data.is_addition) {
                    isAdd.innerHTML = `<span style="color:var(--danger)">Ajout sur la commande ${data.parent_order_id}</span>`;
                } else {
                    isAdd.textContent = 'Commande Principale';
                }

                const listePlats = document.getElementById('modal-plats');
                listePlats.innerHTML = '';
                if (data.commands && Array.isArray(data.commands)) {
                    data.commands.forEach(plat => {
                        const li = document.createElement('li');
                        li.textContent = plat;
                        listePlats.appendChild(li);
                    });
                }

                const ratingBox = document.getElementById('modal-rating-box');
                if (data.rating) {
                    ratingBox.style.display = 'block';
                    const notesEtoiles = Math.ceil(data.rating / 2);
                    document.getElementById('modal-rating-stars').textContent = `${data.rating}/10 ` + '⭐'.repeat(notesEtoiles);
                    document.getElementById('modal-rating-comment').textContent = data.rating_comment ? `« ${data.rating_comment} »` : 'Aucun commentaire textuel.';
                } else {
                    ratingBox.style.display = 'none';
                }

                if (modal) {
                    modal.style.display = 'flex';
                }
            } catch (error) {
                console.error("Erreur lors de la lecture des données de la commande :", error);
                alert("Impossible d'ouvrir les détails. Vérifie la console (F12).");
            }
        }
    });
    </script>
</body>
</html>
