<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: connect.php");
    exit();
}

// --- FONCTIONS DE CHIFFREMENT/DÉCHIFFREMENT ---
function encryptData($data, $password) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $password, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded = base64_decode($payload);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

// Récupération de l'identifiant et de la clé secrète depuis la session
$userId = $_SESSION['user_id'];
$secretKey = $_SESSION['secret_key'];

$file = 'users.json';
// SÉCURITÉ : On vérifie si le fichier existe et on gère les erreurs
$allUsers = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

// --- LA CORRECTION ANTI-CRASH EST ICI ---
// Si l'utilisateur n'existe plus dans le JSON, on détruit la session fantôme !
if (!isset($allUsers[$userId]) || !is_array($allUsers[$userId])) {
    session_destroy();
    header("Location: connect.php");
    exit();
}

$currentUserData = $allUsers[$userId];

// --- AJOUT D'UNE NOUVELLE DONNÉE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_address'])) {
    $newAddress = $_POST['new_address'];
    // On chiffre la nouvelle donnée avec la clé de session !
    $allUsers[$userId]['address_enc'] = encryptData($newAddress, $secretKey);
    file_put_contents($file, json_encode($allUsers, JSON_PRETTY_PRINT));
    $currentUserData = $allUsers[$userId];
}

// Déchiffrement à la volée pour l'affichage
$fullname = decryptData($currentUserData['fullname_enc'], $secretKey);
$email = decryptData($currentUserData['email_enc'], $secretKey);
$phone = decryptData($currentUserData['phone_enc'], $secretKey);
$address = isset($currentUserData['address_enc']) ? decryptData($currentUserData['address_enc'], $secretKey) : "Aucune adresse renseignée";

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil - Le Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .info-display { width: 100%; padding: 12px 15px; background-color: var(--surface); border: 1px solid var(--overlay); border-radius: 8px; color: var(--text); font-size: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav>
        <div class="logo"><h2>Restaurant</h2></div>
        <ul class="nav-links">
            <li><a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">Accueil</a></li>
            <li><a href="menu.php" class="<?= $currentPage == 'menu.php' ? 'active' : '' ?>">Menu</a></li>
            <li><a href="profil.php" class="<?= $currentPage == 'profil.php' ? 'active' : '' ?>">Mon Profil</a></li>
            <li><a href="connect.php?logout=1" class="logout-link">Déconnexion</a></li>
        </ul>
    </nav>

    <main class="main-container">
        <section class="glass-panel medium">
            <div class="page-header">
                <h1>Mon Profil Sécurisé</h1>
                <p style="color: var(--softlime);">Vos données sont entièrement chiffrées.</p>
            </div>
            
            <div><label style="display: block; font-weight: 700; color: var(--text-muted);">Nom Complet</label><div class="info-display"><?php echo htmlspecialchars($fullname); ?></div></div>
            <div><label style="display: block; font-weight: 700; color: var(--text-muted);">Email</label><div class="info-display"><?php echo htmlspecialchars($email); ?></div></div>
            <div><label style="display: block; font-weight: 700; color: var(--text-muted);">Téléphone</label><div class="info-display"><?php echo htmlspecialchars($phone); ?></div></div>
            <div><label style="display: block; font-weight: 700; color: var(--text-muted);">Adresse de livraison</label><div class="info-display"><?php echo htmlspecialchars($address); ?></div></div>
            
            <a href="connect.php?logout=1" class="btn danger" style="text-align: center; margin-top: 20px;">Se Déconnecter</a>
        </section>

        <section class="glass-panel medium">
            <h2 style="color: var(--sapphire); margin-bottom: 20px;">Ajouter une adresse</h2>
            <form action="" method="POST">
                <div class="form-group">
                    <label>Nouvelle adresse (sera chiffrée immédiatement)</label>
                    <input type="text" name="new_address" placeholder="123 rue de la Paix" required>
                </div>
                <button type="submit" class="btn">Enregistrer l'adresse</button>
            </form>
        </section>
    </main>
</body>
</html>
