<?php
require_once __DIR__ . '/inc/common.php';

ensure_ban();
$message = "";

$file = 'users.json';
$allUsers = load_json($file);

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
    $errors = [];

    // --- VALIDATION SERVEUR ---
    if (!validate_user_name($fullname)) {
        $errors[] = "Le nom complet est invalide (2 à 50 lettres).";
    }
    if (!validate_email($email)) {
        $errors[] = "L'adresse email est invalide.";
    }
    if (!validate_phone($phone)) {
        $errors[] = "Le numéro de téléphone est invalide (8 à 15 chiffres).";
    }
    if ($password !== $confirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (!validate_password($password)) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    $keyId = hash('sha256', $email);
    if (isset($allUsers[$keyId])) {
        $errors[] = "Cette adresse email est déjà utilisée.";
    }

    if (empty($errors)) {
        $allUsers[$keyId] = [
            "password_auth" => password_hash($password, PASSWORD_DEFAULT),
            "email_enc"     => encryptData($email, $password),
            "plain_email"   => $email,
            "plain_name"    => $fullname,
            "phone"         => $phone,
            "role"          => $role,
        ];

        if (save_json($file, $allUsers)) {
            $message = "
                <div class='msg-success'>
                    Compte créé avec succès !
                    <a href='connect.php' class='btn signup-login-btn'>Se connecter</a>
                </div>
            ";
            connectIntoAccount($role, $keyId, $password, $email, $fullname);
        } else {
            $message = "<div class='msg-error'>Erreur lors de la sauvegarde des données.</div>";
        }
    } else {
        $message = "<div class='msg-error'>" . implode('<br>', $errors) . "</div>";
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
    <style>
        /* CSS additionnel pour l'UX des formulaires (Identique à la page profil) */
        .field-feedback {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-top: 4px; font-size: 0.8rem; min-height: 18px;
        }
        .field-error { color: #e74c3c; font-weight: 600; display: none; }
        .signup-char-counter { color: var(--text-muted); font-variant-numeric: tabular-nums; margin-left: auto; }
        .signup-char-counter.limit-reached { color: #e74c3c; font-weight: bold; }
        .input-error { border-color: #e74c3c !important; background: rgba(231, 76, 60, 0.05) !important; }
    </style>
</head>
<body>

<?php include '_nav.php'; ?>

<main class="main-container">
    <section class="glass-panel medium">
        <div class="page-header">
            <h1>Créer un compte</h1>
            <p>Vos informations de compte sont enregistrées en toute sécurité.</p>
        </div>

        <?= $message ?>

        <form action="" method="POST" id="signupForm">
            <div class="form-group">
                <label>Nom Complet</label>
                <input type="text" id="fullname" name="fullname" required value="<?= htmlspecialchars($postedFullname, ENT_QUOTES) ?>" 
                       pattern="^[a-zA-ZÀ-ÿ\s\-\']{2,50}$" title="2 à 50 lettres (espaces et tirets acceptés)" maxlength="50">
                <div class="field-feedback">
                    <span class="field-error" id="error-fullname"></span>
                    <span class="signup-char-counter" id="counter-fullname"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" id="phone" name="phone" placeholder="06 12 34 56 78" required value="<?= htmlspecialchars($postedPhone, ENT_QUOTES) ?>" 
                       pattern="^\+?[0-9\s\-]{8,15}$" title="Uniquement des chiffres, de 8 à 15 caractères" maxlength="15">
                <div class="field-feedback">
                    <span class="field-error" id="error-phone"></span>
                    <span class="signup-char-counter" id="counter-phone"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($postedEmail, ENT_QUOTES) ?>" 
                       maxlength="100" title="Format d'email valide requis">
                <div class="field-feedback">
                    <span class="field-error" id="error-email"></span>
                    <span class="signup-char-counter" id="counter-email"></span>
                </div>
            </div>

            <div class="lined">
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" id="password" name="password" required minlength="6" maxlength="64">
                    <div class="field-feedback">
                        <span class="field-error" id="error-password"></span>
                        <span class="signup-char-counter" id="counter-password"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="64">
                    <div class="field-feedback">
                        <span class="field-error" id="error-confirm_password"></span>
                        <span class="signup-char-counter" id="counter-confirm_password"></span>
                    </div>
                </div>
            </div>

            <button type="submit" style="width: 100%; margin-top: 10px;">S'inscrire</button>
        </form>

        <div class="form-footer" style="margin-top: 20px; text-align: center;">
            <p style="color: var(--text-muted); display: inline;">Déjà un compte ?</p>
            <a href="connect.php" style="color: var(--sapphire); font-weight: bold; text-decoration: none; margin-left: 5px;">Connectez-vous</a>
        </div>
    </section>
</main>

<script src="scripts.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('signupForm');
    const inputs = form.querySelectorAll('input');

    // 1. RÈGLES DE VALIDATION JS
    const rules = {
        fullname: { regex: /^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/, msg: "2 à 50 lettres (espaces et tirets acceptés)." },
        email: { regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, msg: "Adresse email invalide." },
        phone: { regex: /^\+?[0-9\s\-]{8,15}$/, msg: "Uniquement chiffres, espaces ou tirets (8-15)." },
        password: { regex: /.{6,}/, msg: "Minimum 6 caractères." },
        confirm_password: { regex: /./, msg: "Les mots de passe ne correspondent pas." } // La regex n'importe pas ici, logique gérée manuellement
    };

    // 2. GESTION DU COMPTEUR
    const updateCounter = (input) => {
        const max = input.getAttribute('maxlength');
        const counterEl = document.getElementById(`counter-${input.id}`);
        if (counterEl && max) {
            const len = input.value.length;
            counterEl.textContent = `${len}/${max}`;
            if (len >= max * 0.9) {
                counterEl.classList.add('limit-reached');
            } else {
                counterEl.classList.remove('limit-reached');
            }
        }
    };

    // 3. LOGIQUE DE VALIDATION
    const validateInput = (input) => {
        const errorEl = document.getElementById(`error-${input.id}`);
        const rule = rules[input.id];
        let isValid = true;
        let val = input.value.trim();

        if (rule && val !== '') {
            // Logique spéciale pour la confirmation du mot de passe
            if (input.id === 'confirm_password') {
                const pwd = document.getElementById('password').value;
                if (val !== pwd) {
                    isValid = false;
                }
            } 
            // Logique standard pour les autres champs
            else if (!rule.regex.test(val)) {
                isValid = false;
            }
        }

        // Affichage des erreurs
        if (!isValid && val !== '') {
            input.classList.add('input-error');
            if (errorEl) {
                errorEl.textContent = rule.msg;
                errorEl.style.display = 'block';
            }
        } else {
            input.classList.remove('input-error');
            if (errorEl) {
                errorEl.style.display = 'none';
                errorEl.textContent = "";
            }
        }
        
        // Si on modifie le mot de passe principal, on re-valide la confirmation pour éviter les faux positifs
        if (input.id === 'password') {
            const confirmInput = document.getElementById('confirm_password');
            if (confirmInput.value !== '') validateInput(confirmInput);
        }

        return isValid;
    };

    // 4. ATTACHEMENT DES ÉVÉNEMENTS
    inputs.forEach(input => {
        updateCounter(input); // Initialisation au chargement
        
        input.addEventListener('input', () => {
            updateCounter(input);
            validateInput(input);
        });
        
        input.addEventListener('blur', () => {
            validateInput(input);
        });
    });

    // 5. BLOCAGE À LA SOUMISSION
    form.addEventListener('submit', (e) => {
        let isFormValid = true;
        
        inputs.forEach(input => {
            if (!validateInput(input)) {
                isFormValid = false;
            }
        });

        // Validation HTML5 de secours
        if (!isFormValid || !form.checkValidity()) {
            e.preventDefault();
            form.reportValidity(); // Fait apparaître les bulles natives du navigateur
        }
    });
});
</script>

</body>
</html>