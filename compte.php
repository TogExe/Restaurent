<?php
session_start();

// --- FONCTION DE CHIFFREMENT (AES-256) ---
function encryptData($data, $password) {
    $iv = openssl_random_pseudo_bytes(16); // Vecteur d'initialisation aléatoire
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $password, 0, $iv);
    return base64_encode($iv . $encrypted); // On combine et on encode pour le JSON
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'] ?? '';
    $email = strtolower($_POST['email'] ?? '');
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $message = "<div class='msg-error'>Erreur : Les mots de passe ne correspondent pas.</div>";
    } else {
        $file = 'users.json';
        $allUsers = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

        // L'identifiant public du JSON est maintenant l'email haché (impossible à inverser)
        $userKeyId = hash('sha256', $email);

        if (isset($allUsers[$userKeyId])) {
            $message = "<div class='msg-error'>Erreur : Cet email est déjà utilisé !</div>";
        } else {
            // Le hachage classique pour la connexion
            $hashedPasswordForAuth = password_hash($password, PASSWORD_DEFAULT);

            // On sauvegarde les données CHIFFRÉES avec le mot de passe
            $allUsers[$userKeyId] = [
                "password_auth" => $hashedPasswordForAuth,
                "email_enc" => encryptData($email, $password),
                "fullname_enc" => encryptData($fullname, $password),
                "phone_enc" => encryptData($phone, $password)
            ];

            if (file_put_contents($file, json_encode($allUsers, JSON_PRETTY_PRINT))) {
                $message = "<div class='msg-success'>Compte créé et chiffré avec succès ! <br><br><a href='connect.php' class='btn'>Connectez-vous</a></div>";
            } else {
                $message = "<div class='msg-error'>Erreur lors de la sauvegarde.</div>";
            }
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
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
        <section class="glass-panel medium">
            <div class="page-header">
                <h1>Créer un compte</h1>
                <p>Vos données seront protégées par un chiffrement de bout en bout.</p>
            </div>
            <?php echo $message; ?>
 			<form action="" method="POST">
                 <div class="form-group">
                     <label>Nom Complet</label>
                     <input type="text" name="fullname" required>
                 </div>
 
                 <div class="form-group">
                     <label>Téléphone</label>
                     <input type="tel" name="phone" placeholder="06 12 34 56 78" required>
                 </div>
 
                 <div class="form-group">
                     <label>Email</label>
                     <input type="email" name="email" placeholder="votre@email.com" required>
                 </div>
 
                 <div class="lined">
                     <div class="form-group">
                         <label>Mot de passe (Clé de chiffrement)</label>
                         <input type="password" name="password" required>
                     </div>
                     <div class="form-group">
                         <label>Confirmez</label>
                         <input type="password" name="confirm_password" required>
                     </div>
                 </div>
                 <button type="submit">S' inscrire </button>
             </form>
            <div class="form-footer"><p>Déjà un compte ?</p><a href="connect.php">Connectez-vous ici</a></div>
        </section>
    </main>
</body>
</html>
