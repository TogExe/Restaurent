<?php
require_once __DIR__ . '/inc/common.php';

require_login();

$userId    = current_user_id();
$secretKey = $_SESSION['secret_key'];
$userRole  = $_SESSION['user_role'] ?? 'client';

$file     = 'users.json';
$allUsers = load_json($file);

if (!isset($allUsers[$userId]) || !is_array($allUsers[$userId])) {
    session_destroy();
    header("Location: connect.php");
    exit();
}

ensure_ban();

$currentUserData = $allUsers[$userId];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_address'])) {

    $allUsers[$userId]['address_enc'] = encryptData(
        trim($_POST['new_address']),
        $secretKey
    );

    save_json($file, $allUsers);

    $currentUserData = $allUsers[$userId];
}

/* Decrypt */

$isAdmin = $userRole === 'admin';

$fullname = $isAdmin
    ? ($currentUserData['plain_name'] ?? 'Admin')
    : decryptData($currentUserData['fullname_enc'] ?? '', $secretKey);

$email = $isAdmin
    ? ($currentUserData['plain_email'] ?? '')
    : decryptData($currentUserData['email_enc'] ?? '', $secretKey);

$phone = $isAdmin
    ? 'N/A'
    : decryptData($currentUserData['phone_enc'] ?? '', $secretKey);

$address = isset($currentUserData['address_enc'])
    ? decryptData($currentUserData['address_enc'], $secretKey)
    : "Aucune adresse renseignée";

/* Orders */

$myOrders = [];

if ($userRole === 'client') {

    $allOrders = load_json('commandes.json');

    foreach ($allOrders as $oid => $o) {

        if (($o['client_id'] ?? '') === $userId) {
            $myOrders[$oid] = $o;
        }
    }
}

$statusLabels = [
    0 => 'En attente',
    1 => 'En préparation',
    2 => 'Prête',
    3 => 'En livraison',
    4 => 'Livrée ✅'
];

$statusColors = [
    0 => 'var(--text-muted)',
    1 => 'var(--accent-btn)',
    2 => 'var(--softlime)',
    3 => 'var(--sapphire)',
    4 => 'var(--mauve)'
];

$roleColors = [
    'admin'     => 'var(--mauve)',
    'cuisiner'  => 'var(--softlime)',
    'livreur'   => 'var(--sapphire)',
    'client'    => 'var(--accent-btn)'
];

$roleIcons = [
    'admin'     => '⚙',
    'cuisiner'  => '🍳',
    'livreur'   => '🛵',
    'client'    => '👤'
];

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

                <span class="profile-role-badge"
                      style="
                        border-color:<?= $roleColors[$userRole] ?? 'var(--overlay)' ?>;
                        color:<?= $roleColors[$userRole] ?? 'var(--text)' ?>;
                      ">

                    <?= $roleIcons[$userRole] ?? '👤' ?>
                    <?= ucfirst($userRole) ?>

                </span>

            </p>

        </div>

        <div>
            <label class="info-display-label">
                Nom Complet
            </label>

            <div class="info-display">
                <?= htmlspecialchars($fullname) ?>
            </div>
        </div>

        <div>
            <label class="info-display-label">
                Email
            </label>

            <div class="info-display">
                <?= htmlspecialchars($email) ?>
            </div>
        </div>

        <?php if (!$isAdmin): ?>

            <div>

                <label class="info-display-label">
                    Téléphone
                </label>

                <div class="info-display">
                    <?= htmlspecialchars($phone) ?>
                </div>

            </div>

            <div>

                <label class="info-display-label">
                    Adresse de livraison
                </label>

                <div class="info-display">
                    <?= htmlspecialchars($address) ?>
                </div>

            </div>

        <?php endif; ?>

        <a href="connect.php?logout=1"
           class="btn danger profile-logout-btn">

            Se Déconnecter

        </a>

    </section>

    <?php if ($userRole === 'client'): ?>

        <section class="glass-panel medium">

            <h2 class="profile-section-title">
                Modifier l'adresse
            </h2>

            <form action="" method="POST">

                <div class="form-group">

                    <label>
                        Nouvelle adresse (chiffrée)
                    </label>

                    <input type="text"
                           name="new_address"
                           placeholder="123 rue de la Paix"
                           required>

                </div>

                <button type="submit">
                    Enregistrer
                </button>

            </form>

        </section>

        <section class="glass-panel medium">

            <h2 class="profile-section-title profile-orders-title">
                Mes commandes (<?= count($myOrders) ?>)
            </h2>

            <?php if (empty($myOrders)): ?>

                <p class="profile-empty-orders">

                    Aucune commande pour le moment.

                    <a href="commande.php"
                       class="profile-order-link">

                        Passer une commande →

                    </a>

                </p>

            <?php else: ?>

                <?php foreach (array_reverse($myOrders, true) as $oid => $o):

                    $st = $o['ready'] ?? 0;
                    $sc = $statusColors[$st] ?? 'var(--text-muted)';
                    $sl = $statusLabels[$st] ?? '?';

                ?>

                    <div class="profile-order-card">

                        <div class="profile-order-header">

                            <strong class="profile-order-id">
                                Commande #<?= $oid ?>
                            </strong>

                            <span class="profile-order-status"
                                  style="
                                    color:<?= $sc ?>;
                                    border-color:<?= $sc ?>;
                                  ">

                                <?= $sl ?>

                            </span>

                        </div>

                        <p class="profile-order-items">

                            <?= htmlspecialchars(
                                implode(', ', $o['commands'] ?? [])
                            ) ?>

                        </p>

                        <div class="profile-order-footer">

                            <span class="profile-order-date">
                                <?= htmlspecialchars($o['comm_t'] ?? '') ?>
                            </span>

                            <strong class="profile-order-price">
                                <?= number_format($o['price'],2,',',' ') ?> €
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

    <?php if ($userRole === 'admin'): ?>

        <section class="glass-panel medium profile-admin-panel">

            <h2 class="profile-admin-title">
                ⚙ Accès Administration
            </h2>

            <a href="admin.php"
               class="btn profile-admin-btn">

                Ouvrir le panneau admin

            </a>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
