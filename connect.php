<?php
session_start();

// --- FONCTION DE DÉCHIFFREMENT ---
function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded = base64_decode($payload);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: connect.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $file = 'users.json';

    // On transforme l'email tapé en identifiant pour chercher dans le JSON
    $userKeyId = hash('sha256', $email);

    if (file_exists($file)) {
        $allUsers = json_decode(file_get_contents($file), true); 
        
        // On vérifie l'existence et le mot de passe
        if (isset($allUsers[$userKeyId]) && password_verify($password, $allUsers[$userKeyId]['password_auth'])) {
            
            // CONSERVATION DE LA CLÉ DANS LA SESSION
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userKeyId; // Pour le retrouver dans le JSON
            $_SESSION['secret_key'] = $password; // La clé pour chiffrer/déchiffrer à la volée !
            
            // On déchiffre les infos basiques pour la navigation
            $_SESSION['user_email'] = decryptData($allUsers[$userKeyId]['email_enc'], $password);
            $_SESSION['user_fullname'] = decryptData($allUsers[$userKeyId]['fullname_enc'], $password);
            
            header("Location: profil.php");
            exit();
        } else {
            $message = "<div class='msg-error'>Identifiants incorrects.</div>";
        }
    } else {
        $message = "<div class='msg-error'>Erreur: Base de données introuvable.</div>";
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: profil.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Se Connecter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="logo"><h2>Restaurant</h2></div>
        <ul class="nav-links">
            <li><a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">Accueil</a></li>
            <li><a href="menu.php" class="<?= $currentPage == 'menu.php' ? 'active' : '' ?>">Menu</a></li>
            <li><a href="connect.php" class="<?= in_array($currentPage, ['connect.php', 'compte.php']) ? 'active' : '' ?>">Se Connecter</a></li>
        </ul>
    </nav>

    <main class="main-container">
        <section class="glass-panel medium" style="max-width: 450px;">
            <div class="page-header">
                <h1>Se Connecter</h1>
            </div>
            <?php echo $message; ?>
            <form action="" method="POST">
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Mot de passe</label><input type="password" name="password" required></div>
                <button type="submit">Connexion & Déchiffrement</button>
            </form>
            <div class="form-footer"><p>Pas encore de compte ?</p><a href="compte.php">Créez un compte</a></div>
        </section>
    </main>
</body>
</html>
