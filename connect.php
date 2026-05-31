<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
if (isset($_GET['logout'])) { session_destroy(); header("Location: connect.php"); exit(); }

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) redirectByRole($_SESSION['user_role'] ?? 'client');

$message = "";

$postedEmail = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $postedEmail = strtolower(trim($_POST['email'] ?? ''));
    $password    = $_POST['password'] ?? '';
    $errors      = [];

    if (!validate_email($postedEmail)) {
        $errors[] = "L'adresse email est invalide.";
    }
    if (!validate_password($password)) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if (empty($errors)) {
        $file     = 'data/users.json';
        $allUsers = load_json($file);

        $foundAdmin = false;

        foreach ($allUsers as $key => $u) {
            if (($u['role'] ?? '') === 'admin' && ($u['plain_email'] ?? '') === $postedEmail) {
                if (password_verify($password, $u['password_auth'])) {
                    connectIntoAccount('admin', $key, $password, $postedEmail);
                }
                $foundAdmin = true;
                break;
            }
        }

        if (!$foundAdmin) {
            $userKeyId = hash('sha256', $postedEmail);
            if (isset($allUsers[$userKeyId]) && password_verify($password, $allUsers[$userKeyId]['password_auth'])) {
                if (!isset($allUsers[$userKeyId]['is_banned']) || $allUsers[$userKeyId]['is_banned'] !== true) {
                    $role = $allUsers[$userKeyId]['role'] ?? 'client';
                    connectIntoAccount($role, $userKeyId, $password, $postedEmail);
                } else {
                    $errors[] = "Votre compte a été banni. Contactez le support.";
                }
            } else {
                $errors[] = "Identifiants incorrects.";
            }
        } else {
            $errors[] = "Identifiants incorrects.";
        }
    }

    if (!empty($errors)) {
        $message = "<div class='msg-error'>" . implode('<br>', $errors) . "</div>";
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

        <form action="" method="POST" id="loginForm" data-login-form>

            <div class="form-group">
                <label>Email</label>
                <input type="email" id="email" name="email" required maxlength="100" value="<?= htmlspecialchars($postedEmail, ENT_QUOTES) ?>" title="Format d'email valide requis">
                <div class="field-feedback">
                    <span class="field-error" id="error-email"></span>
                    <span class="signup-char-counter" id="counter-email"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="password" id="password" name="password" required minlength="6" maxlength="64">
                    <button type="button" class="pwd-toggle" aria-pressed="false" title="Afficher / Masquer">👁</button>
                </div>
                <div class="field-feedback">
                    <span class="field-error" id="error-password"></span>
                    <span class="signup-char-counter" id="counter-password"></span>
                </div>
            </div>

            <button type="submit">
                Connexion
            </button>

        </form>

        <div class="form-footer">
            <p>Pas encore de compte ?</p>
            <a href="compte.php">Créez un compte</a>
        </div>

        <script src="scripts.js" defer></script>

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
                    marie@themarie.com / 13151615
                </div>

                <div class="demo-badge demo-delivery">
                    <strong>🛵 Livreur</strong>
                    johndoe@john.com / 13151615
                </div>

                <div class="demo-badge demo-client">
                    <strong>👤 Client</strong>
                    itsmemaario@gmail.com / 13151615
                </div>

            </div>

        </details>

    </section>

</main>

</body>
</html>

