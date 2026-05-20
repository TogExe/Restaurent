<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
if (isset($_GET['logout'])) { session_destroy(); header("Location: connect.php"); exit(); }

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) redirectByRole($_SESSION['user_role'] ?? 'client');

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $file     = 'users.json';
    $allUsers = load_json($file);

    $foundAdmin = false;

    foreach ($allUsers as $key => $u) {

        if (($u['role'] ?? '') === 'admin' && ($u['plain_email'] ?? '') === $email) {

            if (password_verify($password, $u['password_auth'])) {
                connectIntoAccount('admin', $key, $password, $email);
            }

            $foundAdmin = true;
            break;
        }
    }

    if (!$foundAdmin) {

        $userKeyId = hash('sha256', $email);
        if (isset($allUsers[$userKeyId]) && password_verify($password, $allUsers[$userKeyId]['password_auth'])) {
            if (!isset($allUsers[$userKeyId]['is_banned']) || $allUsers[$userKeyId]['is_banned'] !== true) {
            $role = $allUsers[$userKeyId]['role'] ?? 'client';

            connectIntoAccount($role, $userKeyId, $password, $email);
            }
            else { $message = "<div class='msg-error'>Votre compte a été banni. Contactez le support.</div>"; }
        } else {
            $message = "<div class='msg-error'>Identifiants incorrects.</div>";
        }

    } else {
        $message = "<div class='msg-error'>Identifiants incorrects.</div>";
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = false;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Se Connecter</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <section class="glass-panel medium login-panel">

        <div class="page-header">
            <h1>Se Connecter</h1>
            <p>Votre espace personnel sécurisé</p>
        </div>

        <?= $message ?>

        <form action="" method="POST">

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">
                Connexion
            </button>

        </form>

        <div class="form-footer">
            <p>Pas encore de compte ?</p>
            <a href="compte.php">Créez un compte</a>
        </div>

        <details class="demo-details">

            <summary class="demo-summary">
                Comptes de démonstration ▾
            </summary>

            <div class="demo-list">

                <div class="demo-badge demo-admin">
                    <strong>⚙ Admin</strong>
                    — admin@restaurant.fr / admin1234
                </div>

                <div class="demo-badge demo-cook">
                    <strong>🍳 Cuisinier</strong>
                    — définissez <code>role:"cuisinier"</code> dans users.json
                </div>

                <div class="demo-badge demo-delivery">
                    <strong>🛵 Livreur</strong>
                    — définissez <code>role:"livreur"</code> dans users.json
                </div>

                <div class="demo-badge demo-client">
                    <strong>👤 Client</strong>
                    — créez un compte via le formulaire
                </div>

            </div>

        </details>

    </section>

</main>

</body>
</html>
