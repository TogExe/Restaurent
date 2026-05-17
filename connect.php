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

    // Admin special login (plaintext email stored)
    $foundAdmin = false;
    foreach ($allUsers as $key => $u) {
        if (($u['role'] ?? '') === 'admin' && ($u['plain_email'] ?? '') === $email) {
            if (password_verify($password, $u['password_auth'])) {
                $_SESSION['logged_in']     = true;
                $_SESSION['user_id']       = $key;
                $_SESSION['user_role']     = 'admin';
                $_SESSION['user_email']    = $email;
                $_SESSION['user_fullname'] = $u['plain_name'] ?? 'Admin';
                $_SESSION['secret_key']    = $password;
                redirectByRole('admin');
            }
            $foundAdmin = true; break;
        }
    }

    if (!$foundAdmin) {
        $userKeyId = hash('sha256', $email);
        if (isset($allUsers[$userKeyId]) && password_verify($password, $allUsers[$userKeyId]['password_auth'])) {
            if (!isset($allUsers[$userKeyId]['is_banned']) || $allUsers[$userKeyId]['is_banned'] !== true) {
            $role = $allUsers[$userKeyId]['role'] ?? 'client';
            $_SESSION['logged_in']     = true;
            $_SESSION['user_id']       = $userKeyId;
            $_SESSION['user_role']     = $role;
            $_SESSION['secret_key']    = $password;
            $_SESSION['user_email']    = decryptData($allUsers[$userKeyId]['email_enc'],    $password);
            $_SESSION['user_fullname'] = decryptData($allUsers[$userKeyId]['fullname_enc'], $password);
            redirectByRole($role);
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
<head><meta charset="UTF-8"><title>Se Connecter</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include '_nav.php'; ?>
<main class="main-container">
    <section class="glass-panel medium" style="max-width:460px;">
        <div class="page-header">
            <h1>Se Connecter</h1>
            <p>Votre espace personnel sécurisé</p>
        </div>
        <?= $message ?>
        <form action="" method="POST">
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Mot de passe</label><input type="password" name="password" required></div>
            <button type="submit">Connexion</button>
        </form>
        <div class="form-footer"><p>Pas encore de compte ?</p><a href="compte.php">Créez un compte</a></div>

        <details style="margin-top:25px;border-top:1px solid var(--overlay);padding-top:15px;">
            <summary style="color:var(--text-muted);font-size:0.82rem;cursor:pointer;letter-spacing:.05em;">Comptes de démonstration ▾</summary>
            <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;font-size:0.82rem;">
                <div class="demo-badge" style="--c:var(--mauve)"><strong>⚙ Admin</strong> — admin@restaurant.fr / admin1234</div>
                <div class="demo-badge" style="--c:var(--softlime)"><strong>🍳 Cuisinier</strong> — définissez <code>role:"cuisinier"</code> dans users.json</div>
                <div class="demo-badge" style="--c:var(--sapphire)"><strong>🛵 Livreur</strong> — définissez <code>role:"livreur"</code> dans users.json</div>
                <div class="demo-badge" style="--c:var(--accent-btn)"><strong>👤 Client</strong> — créez un compte via le formulaire</div>
            </div>
        </details>
    </section>
</main>
<style>
.demo-badge{background:rgba(255,255,255,.04);border:1px solid var(--overlay);border-radius:8px;padding:10px 12px;color:var(--text-muted);border-left:3px solid var(--c);}
.demo-badge strong{color:var(--c);}
</style>
</body>
</html>
