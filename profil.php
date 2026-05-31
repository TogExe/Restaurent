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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $isAjax = (isset($_POST['ajax']) && $_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (isset($_POST['update_profile'])) {
        $name  = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $existingAddr = ['street'=>'','number'=>'','complement'=>'','postal'=>'','city'=>''];

        if (!empty($currentUserData['address_enc'])) {
            $raw = decryptData($currentUserData['address_enc'], $secretKey);
            $dec = json_decode($raw, true);

            if (is_array($dec)) {
                $existingAddr = array_merge($existingAddr, $dec);
            }
        }

        $fields = [
            'addr_street' => 'street',
            'addr_number' => 'number',
            'addr_comp'   => 'complement',
            'addr_postal' => 'postal',
            'addr_city'   => 'city'
        ];

        $updatedAddr = $existingAddr;

        foreach ($fields as $postKey => $partKey) {
            if (array_key_exists($postKey, $_POST)) {
                $updatedAddr[$partKey] = trim($_POST[$postKey]);
            }
        }

        if ($name !== '') {
            $allUsers[$userId]['plain_name']   = $name;
            $allUsers[$userId]['fullname_enc'] = encryptData($name, $secretKey);
        }

        if ($email !== '') {
            $allUsers[$userId]['plain_email'] = $email;
            $allUsers[$userId]['email_enc']   = encryptData($email, $secretKey);
        }

        if ($phone !== '') {
            $allUsers[$userId]['phone_enc'] = encryptData($phone, $secretKey);
        }

        $anyAddr = false;

        foreach ($updatedAddr as $val) {
            if ($val !== '') {
                $anyAddr = true;
                break;
            }
        }

        if ($anyAddr) {
            $allUsers[$userId]['address_enc'] = encryptData(json_encode($updatedAddr), $secretKey);
        }

        save_json($file, $allUsers);
        $currentUserData = $allUsers[$userId];

        if ($isAjax) {
            $retAddr = ['street'=>'','number'=>'','complement'=>'','postal'=>'','city'=>''];

            if (!empty($currentUserData['address_enc'])) {
                $raw2 = decryptData($currentUserData['address_enc'], $secretKey);
                $dec2 = json_decode($raw2, true);

                if (is_array($dec2)) {
                    $retAddr = array_merge($retAddr, $dec2);
                }
            }

            $resp = [
                'success'       => true,
                'message'       => 'Profil mis à jour.',
                'address_parts' => $retAddr,
                'fullname'      => $currentUserData['plain_name'] ?? '',
                'email'         => $currentUserData['plain_email'] ?? ''
            ];

            header('Content-Type: application/json');
            echo json_encode($resp);
            exit();
        }
    }

    if (isset($_POST['new_address'])) {
        $allUsers[$userId]['address_enc'] = encryptData(trim($_POST['new_address']), $secretKey);
        save_json($file, $allUsers);
        $currentUserData = $allUsers[$userId];

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
    }
}

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

$address_raw = isset($currentUserData['address_enc'])
    ? decryptData($currentUserData['address_enc'], $secretKey)
    : '';

$addressParts = [
    'street'     => '',
    'number'     => '',
    'complement' => '',
    'postal'     => '',
    'city'       => ''
];

if ($address_raw !== '') {
    $decoded = json_decode($address_raw, true);

    if (is_array($decoded)) {
        $addressParts = array_merge($addressParts, $decoded);
    } else {
        $addressParts['street'] = $address_raw;
    }
}

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
    'admin'    => 'var(--mauve)',
    'cuisiner' => 'var(--softlime)',
    'livreur'  => 'var(--sapphire)',
    'client'   => 'var(--accent-btn)'
];

$roleIcons = [
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

        <form id="profileForm" action="" method="POST" class="profile-inline-form">
            <input type="hidden" name="update_profile" value="1">

            <div class="form-row">
                <label class="info-display-label">Nom Complet</label>

                <div class="inline-edit">
                    <input type="text"
                           id="fullname"
                           name="fullname"
                           value="<?= htmlspecialchars($fullname) ?>"
                           readonly>

                    <button type="button"
                            class="field-edit-btn"
                            data-target="fullname">
                        ✏️
                    </button>
                </div>
            </div>

            <div class="form-row">
                <label class="info-display-label">Email</label>

                <div class="inline-edit">
                    <input type="email"
                           id="email"
                           name="email"
                           value="<?= htmlspecialchars($email) ?>"
                           readonly>

                    <button type="button"
                            class="field-edit-btn"
                            data-target="email">
                        ✏️
                    </button>
                </div>
            </div>

            <?php if (!$isAdmin): ?>

                <div class="form-row">
                    <label class="info-display-label">Téléphone</label>

                    <div class="inline-edit">
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="<?= htmlspecialchars($phone) ?>"
                               readonly>

                        <button type="button"
                                class="field-edit-btn"
                                data-target="phone">
                            ✏️
                        </button>
                    </div>
                </div>

                <div class="address-inline">

                    <div class="profile-grid">
                        <div class="profile-col-large">
                            <label class="info-display-label">Rue</label>

                            <div class="inline-edit">
                                <input type="text"
                                       id="addr_street"
                                       name="addr_street"
                                       value="<?= htmlspecialchars($addressParts['street'] ?? '') ?>"
                                       readonly>

                                <button type="button"
                                        class="field-edit-btn"
                                        data-target="addr_street">
                                    ✏️
                                </button>
                            </div>
                        </div>

                        <div class="profile-col-small">
                            <label class="info-display-label">N°</label>

                            <div class="inline-edit">
                                <input type="text"
                                       id="addr_number"
                                       name="addr_number"
                                       value="<?= htmlspecialchars($addressParts['number'] ?? '') ?>"
                                       readonly>

                                <button type="button"
                                        class="field-edit-btn"
                                        data-target="addr_number">
                                    ✏️
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="profile-grid profile-grid-reverse">
                        <div class="profile-col-small">
                            <label class="info-display-label">Code Postal</label>

                            <div class="inline-edit">
                                <input type="text"
                                       id="addr_postal"
                                       name="addr_postal"
                                       value="<?= htmlspecialchars($addressParts['postal'] ?? '') ?>"
                                       readonly>

                                <button type="button"
                                        class="field-edit-btn"
                                        data-target="addr_postal">
                                    ✏️
                                </button>
                            </div>
                        </div>

                        <div class="profile-col-large">
                            <label class="info-display-label">Ville</label>

                            <div class="inline-edit">
                                <input type="text"
                                       id="addr_city"
                                       name="addr_city"
                                       value="<?= htmlspecialchars($addressParts['city'] ?? '') ?>"
                                       readonly>

                                <button type="button"
                                        class="field-edit-btn"
                                        data-target="addr_city">
                                    ✏️
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="info-display-label">Complément</label>

                        <div class="inline-edit">
                            <input type="text"
                                   id="addr_comp"
                                   name="addr_comp"
                                   value="<?= htmlspecialchars($addressParts['complement'] ?? '') ?>"
                                   readonly>

                            <button type="button"
                                    class="field-edit-btn"
                                    data-target="addr_comp">
                                ✏️
                            </button>
                        </div>
                    </div>

                </div>

            <?php endif; ?>

            <div class="profile-actions">
                <a href="connect.php?logout=1"
                   class="btn danger profile-logout-btn">
                    Se Déconnecter
                </a>
            </div>

        </form>

    </section>

    <?php if ($userRole === 'client'): ?>

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
                            <?= htmlspecialchars(implode(', ', $o['commands'] ?? [])) ?>
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

<script src="scripts.js" defer></script>

</body>
</html>
