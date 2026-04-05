<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: connect.php"); exit();
}

function encryptData($data, $password) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $password, 0, $iv);
    return base64_encode($iv . $encrypted);
}
function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded   = base64_decode($payload);
    $iv        = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

$userId    = $_SESSION['user_id'];
$secretKey = $_SESSION['secret_key'];
$userRole  = $_SESSION['user_role'] ?? 'client';

$file     = 'users.json';
$allUsers = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

if (!isset($allUsers[$userId]) || !is_array($allUsers[$userId])) {
    session_destroy(); header("Location: connect.php"); exit();
}

$currentUserData = $allUsers[$userId];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_address'])) {
    $allUsers[$userId]['address_enc'] = encryptData(trim($_POST['new_address']), $secretKey);
    file_put_contents($file, json_encode($allUsers, JSON_PRETTY_PRINT));
    $currentUserData = $allUsers[$userId];
}

// Decrypt
$isAdmin   = $userRole === 'admin';
$fullname  = $isAdmin ? ($currentUserData['plain_name'] ?? 'Admin') : decryptData($currentUserData['fullname_enc'] ?? '', $secretKey);
$email     = $isAdmin ? ($currentUserData['plain_email'] ?? '')     : decryptData($currentUserData['email_enc']    ?? '', $secretKey);
$phone     = $isAdmin ? 'N/A'                                       : decryptData($currentUserData['phone_enc']    ?? '', $secretKey);
$address   = isset($currentUserData['address_enc']) ? decryptData($currentUserData['address_enc'], $secretKey) : "Aucune adresse renseignée";

// Orders for clients
$myOrders = [];
if ($userRole === 'client') {
    $allOrders = file_exists('commandes.json') ? json_decode(file_get_contents('commandes.json'), true) : [];
    foreach ($allOrders as $oid => $o) {
        if (($o['client_id'] ?? '') === $userId) $myOrders[$oid] = $o;
    }
}

$statusLabels = [0=>'En attente',1=>'En préparation',2=>'Prête',3=>'En livraison',4=>'Livrée ✅'];
$statusColors = [0=>'var(--text-muted)',1=>'var(--accent-btn)',2=>'var(--softlime)',3=>'var(--sapphire)',4=>'var(--mauve)'];
$roleColors   = ['admin'=>'var(--mauve)','cuisiner'=>'var(--softlime)','livreur'=>'var(--sapphire)','client'=>'var(--accent-btn)'];
$roleIcons    = ['admin'=>'⚙','cuisiner'=>'🍳','livreur'=>'🛵','client'=>'👤'];

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main class="main-container">

    <section class="glass-panel medium">
        <div class="page-header">
            <h1>Mon Profil</h1>
            <p>
                <span style="display:inline-block;padding:4px 14px;border-radius:20px;background:rgba(255,255,255,.06);border:1px solid <?= $roleColors[$userRole] ?? 'var(--overlay)' ?>;color:<?= $roleColors[$userRole] ?? 'var(--text)' ?>;font-weight:700;font-size:.85rem;">
                    <?= $roleIcons[$userRole] ?? '👤' ?> <?= ucfirst($userRole) ?>
                </span>
            </p>
        </div>
        <div><label class="info-display-label">Nom Complet</label><div class="info-display"><?= htmlspecialchars($fullname) ?></div></div>
        <div><label class="info-display-label">Email</label><div class="info-display"><?= htmlspecialchars($email) ?></div></div>
        <?php if (!$isAdmin): ?>
        <div><label class="info-display-label">Téléphone</label><div class="info-display"><?= htmlspecialchars($phone) ?></div></div>
        <div><label class="info-display-label">Adresse de livraison</label><div class="info-display"><?= htmlspecialchars($address) ?></div></div>
        <?php endif; ?>
        <a href="connect.php?logout=1" class="btn danger" style="margin-top:20px;">Se Déconnecter</a>
    </section>

    <?php if ($userRole === 'client'): ?>
    <section class="glass-panel medium">
        <h2 style="color:var(--sapphire);margin-bottom:16px;">Modifier l'adresse</h2>
        <form action="" method="POST">
            <div class="form-group">
                <label>Nouvelle adresse (chiffrée)</label>
                <input type="text" name="new_address" placeholder="123 rue de la Paix" required>
            </div>
            <button type="submit">Enregistrer</button>
        </form>
    </section>

    <section class="glass-panel medium">
        <h2 style="color:var(--sapphire);margin-bottom:20px;">Mes commandes (<?= count($myOrders) ?>)</h2>
        <?php if (empty($myOrders)): ?>
            <p style="color:var(--text-muted);font-style:italic;">Aucune commande pour le moment. <a href="commande.php" style="color:var(--sapphire);">Passer une commande →</a></p>
        <?php else: ?>
            <?php foreach (array_reverse($myOrders, true) as $oid => $o):
                $st = $o['ready'] ?? 0;
                $sc = $statusColors[$st] ?? 'var(--text-muted)';
                $sl = $statusLabels[$st] ?? '?';
            ?>
            <div style="background:rgba(255,255,255,.04);border:1px solid var(--overlay);border-radius:12px;padding:16px;margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="color:var(--sapphire);">Commande #<?= $oid ?></strong>
                    <span style="padding:3px 10px;border-radius:20px;background:rgba(255,255,255,.05);color:<?= $sc ?>;border:1px solid <?= $sc ?>;font-size:.78rem;font-weight:700;"><?= $sl ?></span>
                </div>
                <p style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars(implode(', ', $o['commands'] ?? [])) ?></p>
                <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.85rem;">
                    <span style="color:var(--text-muted);"><?= htmlspecialchars($o['comm_t'] ?? '') ?></span>
                    <strong style="color:var(--softlime);"><?= number_format($o['price'],2,',',' ') ?> €</strong>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($userRole === 'admin'): ?>
    <section class="glass-panel medium" style="text-align:center;">
        <h2 style="color:var(--mauve);margin-bottom:16px;">⚙ Accès Administration</h2>
        <a href="admin.php" class="btn" style="max-width:240px;display:inline-block;">Ouvrir le panneau admin</a>
    </section>
    <?php endif; ?>

</main>
<style>
.info-display-label{display:block;font-weight:600;color:var(--text-muted);font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px;}
</style>
</body>
</html>
