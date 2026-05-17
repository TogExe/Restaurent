<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
$message = "";

$postedFullname = '';
$postedEmail    = '';
$postedPhone    = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $postedFullname = trim($_POST['fullname'] ?? '');
    $postedEmail    = strtolower(trim($_POST['email'] ?? ''));
    $postedPhone    = trim($_POST['phone'] ?? '');
    $fullname       = $postedFullname;
    $email          = $postedEmail;
    $phone          = $postedPhone;
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    $role = 'client';

    if ($password !== $confirm) {
        $message = "<div class='msg-error'>Les mots de passe ne correspondent pas.</div>";
    } elseif (strlen($password) < 6) {
        $message = "<div class='msg-error'>Le mot de passe doit faire au moins 6 caractères.</div>";
    } else {
        $file     = 'users.json';
        $allUsers = load_json($file);
        $keyId    = hash('sha256', $email);

        if (isset($allUsers[$keyId])) {
            $message = "<div class='msg-error'>Cet email est déjà utilisé.</div>";
        } else {
            $allUsers[$keyId] = [
                "password_auth" => password_hash($password, PASSWORD_DEFAULT),
                "email_enc"     => encryptData($email, $password),
                "fullname_enc"  => encryptData($fullname, $password),
                "phone_enc"     => encryptData($phone, $password),
                "role"          => $role,
            ];

            if (save_json($file, $allUsers)) {
                $message = "
                    <div class='msg-success'>
                        Compte créé !
                        <a href='connect.php' class='btn signup-login-btn'>Se connecter</a>
                    </div>
                ";
            } else {
                $message = "<div class='msg-error'>Erreur lors de la sauvegarde.</div>";
            }
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <section class="glass-panel medium">
        <div class="page-header">
            <h1>Créer un compte</h1>
            <p>Vos données sont protégées par chiffrement AES-256.</p>
        </div>

        <?= $message ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Nom Complet</label>
                <input type="text" name="fullname" required value="<?= htmlspecialchars($postedFullname, ENT_QUOTES) ?>">
            </div>

            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="phone" placeholder="06 12 34 56 78" required value="<?= htmlspecialchars($postedPhone, ENT_QUOTES) ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($postedEmail, ENT_QUOTES) ?>">
            </div>

            <div class="lined">
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" name="confirm_password" required>
                </div>
            </div>

            <button type="submit">S'inscrire</button>
        </form>

        <div class="form-footer">
            <p>Déjà un compte ?</p>
            <a href="connect.php">Connectez-vous</a>
        </div>
    </section>
</main>

</body>
</html>
