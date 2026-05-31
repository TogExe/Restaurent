<?php
require_once __DIR__ . '/inc/common.php';

require_login();

$userId    = current_user_id();
$secretKey = $_SESSION['secret_key'];
$userRole  = $_SESSION['user_role'] ?? 'client';

$file     = 'data/users.json';
$allUsers = load_json($file);

if (!isset($allUsers[$userId]) || !is_array($allUsers[$userId])) {
    session_destroy();
    header("Location: connect.php");
    exit();
}

ensure_ban();

$currentUserData = $allUsers[$userId];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isAjax = (isset($_POST['ajax']) && $_POST['ajax'])
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (isset($_POST['update_profile'])) {
        $name  = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $fields = [
            'addr_street' => 'street',
            'addr_number' => 'number',
            'addr_comp'   => 'complement',
            'addr_postal' => 'postal',
            'addr_city'   => 'city'
        ];

        $updatedAddr = get_user_address_parts($currentUserData, $secretKey);

        foreach ($fields as $postKey => $partKey) {
            if (array_key_exists($postKey, $_POST)) {
                $updatedAddr[$partKey] = trim(strip_tags($_POST[$postKey]));
            }
        }

        $errors = [];

        // Validate updated profile data on the server
       // --- BEGIN SERVER-SIDE FIELD VALIDATION ---
        $errors = [];

        // Nom (Lettres)
        if ($name !== '' && !validate_user_name($name)) {
            $errors[] = "Le nom contient des caractères invalides (2 à 50 caractères).";
        }
        // Email
        if ($email !== '' && !validate_email($email)) {
            $errors[] = "Le format de l'adresse email est invalide.";
        }
        // Téléphone
        if ($phone !== '' && !validate_phone($phone)) {
            $errors[] = "Le format du téléphone est invalide.";
        }
        // Code Postal (Exactement 5 chiffres)
        if ($updatedAddr['postal'] !== '' && !validate_postal_code($updatedAddr['postal'])) {
            $errors[] = "Le code postal doit contenir exactement 5 chiffres.";
        }
        // Ville (Lettres uniquement, anti-chiffres)
        if ($updatedAddr['city'] !== '' && !validate_city($updatedAddr['city'])) {
            $errors[] = "Le nom de la ville ne doit contenir que des lettres.";
        }
        // N° de rue (Doit commencer par un chiffre, ex: 12, 12B, 1 bis)
        if ($updatedAddr['number'] !== '' && !validate_address_number($updatedAddr['number'])) {
            $errors[] = "Le numéro de rue doit commencer par un chiffre.";
        }
        // Rue (Lettres et chiffres)
        if ($updatedAddr['street'] !== '' && !validate_street($updatedAddr['street'])) {
            $errors[] = "Le nom de la rue contient des caractères invalides.";
        }

        if (!empty($errors)) {
            $errorText = implode('<br>', $errors);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errorText]);
                exit();
            } else {
                $message = "<div class='msg-error'>$errorText</div>";
            }
        } else {
            // Persist cleaned profile fields and remove any legacy encrypted payloads
            if ($name !== '') {
                $allUsers[$userId]['plain_name'] = $name;
                unset($allUsers[$userId]['fullname_enc']);
            }
            if ($email !== '') {
                $allUsers[$userId]['plain_email'] = $email;
                $allUsers[$userId]['email_enc']   = encryptData($email, $secretKey);
            }
            if ($phone !== '') {
                $allUsers[$userId]['phone'] = $phone;
                unset($allUsers[$userId]['phone_enc']);
            }

            $anyAddr = false;
            foreach ($updatedAddr as $val) {
                if ($val !== '') {
                    $anyAddr = true;
                    break;
                }
            }
            if ($anyAddr) {
                $allUsers[$userId]['address'] = $updatedAddr;
                unset($allUsers[$userId]['address_enc']);
            }

            save_json($file, $allUsers);
            $currentUserData = $allUsers[$userId];
            $message = "<div class='msg-success'>Profil mis à jour avec succès.</div>";

            if ($isAjax) {
                $retAddr = get_user_address_parts($currentUserData, $secretKey);
                header('Content-Type: application/json');
                echo json_encode([
                    'success'       => true,
                    'message'       => 'Profil mis à jour avec succès.',
                    'address_parts' => $retAddr,
                    'fullname'      => $currentUserData['plain_name'] ?? get_user_name($currentUserData, $secretKey),
                    'email'         => $currentUserData['plain_email'] ?? get_user_email($currentUserData, $secretKey)
                ]);
                exit();
            }
        }
    }
}

$isAdmin = $userRole === 'admin';

$fullname = $isAdmin ? ($currentUserData['plain_name'] ?? 'Admin') : get_user_name($currentUserData, $secretKey);
$email = get_user_email($currentUserData, $secretKey);
$phone = $isAdmin ? 'N/A' : get_user_phone($currentUserData, $secretKey);
$addressParts = get_user_address_parts($currentUserData, $secretKey);

$roleColors = ['admin'=>'var(--mauve)', 'cuisinier'=>'var(--softlime)', 'livreur'=>'var(--sapphire)', 'client'=>'var(--accent-btn)'];
$roleIcons  = ['admin'=>'⚙', 'cuisinier'=>'🍳', 'livreur'=>'🛵', 'client'=>'👤'];

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = true;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS additionnel pour l'UX des formulaires */
        .field-feedback {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-top: 4px; font-size: 0.8rem; min-height: 18px;
        }
        .field-error { color: #e74c3c; font-weight: 600; display: none; }
        .char-counter { color: var(--text-muted); font-variant-numeric: tabular-nums; margin-left: auto; }
        .char-counter.limit-reached { color: #e74c3c; font-weight: bold; }
        .input-error { border-color: #e74c3c !important; background: rgba(231, 76, 60, 0.05) !important; }
        
        /* Différenciation visuelle quand on édite */
        .inline-edit input:not([readonly]) {
            background: rgba(255, 255, 255, 0.08);
            border-bottom: 1px solid var(--softlime);
        }
    </style>
    <script src="scripts.js" defer></script>

</head>

<body>

<?php include '_nav.php'; ?>

<main class="main-container">

    <section class="glass-panel medium profile-settings-panel">

        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 style="margin-bottom: 5px;">Mon Profil</h1>
                <span class="profile-role-badge" style="border-color:<?= $roleColors[$userRole] ?? 'var(--overlay)' ?>; color:<?= $roleColors[$userRole] ?? 'var(--text)' ?>;">
                    <?= $roleIcons[$userRole] ?? '👤' ?> <?= ucfirst($userRole) ?>
                </span>
            </div>
            
            <button type="button" id="enableEditBtn" class="btn btn-sm" style="background: rgba(255,255,255,0.05); border: 1px solid var(--overlay);">
                ✏️ Modifier mes informations
            </button>
        </div>

        <div id="profile-messages"><?= $message ?></div>

        <form id="profileForm" action="" method="POST" class="profile-inline-form">
            <input type="hidden" name="update_profile" value="1">

            <div class="profile-field-full">
                <label class="info-display-label">Nom Complet</label>
                <div class="inline-edit">
                    <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($fullname) ?>" 
                           pattern="^[a-zA-ZÀ-ÿ\s\-\']{2,50}$" title="2 à 50 lettres (espaces et tirets acceptés)" maxlength="50" readonly>
                </div>
                <div class="field-feedback">
                    <span class="field-error" id="error-fullname"></span><span class="char-counter" id="counter-fullname"></span>
                </div>
            </div>

            <div class="profile-field-row">
                <div>
                    <label class="info-display-label">Email</label>
                    <div class="inline-edit">
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" 
                               maxlength="100" title="Format d'email valide requis" readonly>
                    </div>
                    <div class="field-feedback">
                        <span class="field-error" id="error-email"></span><span class="char-counter" id="counter-email"></span>
                    </div>
                </div>

                <?php if (!$isAdmin): ?>
                    <div>
                        <label class="info-display-label">Téléphone</label>
                        <div class="inline-edit">
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" 
                                   pattern="^\+?[0-9\s\-]{8,15}$" title="Uniquement des chiffres, de 8 à 15 caractères" maxlength="15" readonly>
                        </div>
                        <div class="field-feedback">
                            <span class="field-error" id="error-phone"></span><span class="char-counter" id="counter-phone"></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isAdmin): ?>
                <div>
                        <label class="info-display-label">Rue</label>
                        <div class="inline-edit">
                            <input type="text" id="addr_street" name="addr_street" value="<?= htmlspecialchars($addressParts['street'] ?? '') ?>" 
                                   pattern="^[a-zA-Z0-9À-ÿ\s\-\']{2,100}$" title="Lettres et chiffres uniquement" maxlength="100" readonly>
                        </div>
                        <div class="field-feedback">
                            <span class="field-error" id="error-addr_street"></span>
                            </div>
                    </div>

                    <div>
                        <label class="info-display-label">N°</label>
                        <div class="inline-edit">
                            <input type="text" id="addr_number" name="addr_number" value="<?= htmlspecialchars($addressParts['number'] ?? '') ?>" 
                                   pattern="^\d{1,4}[a-zA-Z\s]*$" title="Doit commencer par un chiffre (ex: 12, 45B)" maxlength="10" readonly>
                        </div>
                        <div class="field-feedback">
                            <span class="field-error" id="error-addr_number"></span>
                        </div>
                    </div>
                </div>

                <div class="profile-field-row profile-row-city">
                    <div>
                        <label class="info-display-label">Code Postal</label>
                        <div class="inline-edit">
                            <input type="text" id="addr_postal" name="addr_postal" value="<?= htmlspecialchars($addressParts['postal'] ?? '') ?>" 
                                   pattern="^\d{5}$" title="5 chiffres requis" maxlength="5" readonly>
                        </div>
                        <div class="field-feedback">
                            <span class="field-error" id="error-addr_postal"></span>
                        </div>
                    </div>

                    <div>
                        <label class="info-display-label">Ville</label>
                        <div class="inline-edit">
                            <input type="text" id="addr_city" name="addr_city" value="<?= htmlspecialchars($addressParts['city'] ?? '') ?>" 
                                   pattern="^[a-zA-ZÀ-ÿ\s\-\']{2,50}$" title="Uniquement des lettres" maxlength="50" readonly>
                        </div>
                        <div class="field-feedback">
                            <span class="field-error" id="error-addr_city"></span>
                        </div>
                    </div>
            <?php endif; ?>

            <div class="profile-actions" style="margin-top: 20px;">
                
                <div id="saveActions" style="display: none; flex-direction: column; gap: 10px; width: 100%; margin-bottom: 15px;">
                    <button type="submit" id="saveProfileBtn" class="btn" style="background: var(--softlime); color: var(--background); font-weight: bold; width: 100%;">
                        💾 Enregistrer les modifications
                    </button>
                    <button type="button" id="cancelEditBtn" class="btn danger" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c; width: 100%; border: 1px solid rgba(231, 76, 60, 0.3);">
                        ❌ Annuler
                    </button>
                </div>

                <a href="connect.php?logout=1" class="btn danger profile-logout-btn" id="logoutBtn" style="width: 100%; text-align: center;">
                    Se Déconnecter
                </a>
            </div>

        </form>

    </section>

    <?php if ($userRole === 'admin'): ?>
        <section class="glass-panel medium profile-admin-panel">
            <h2 class="profile-admin-title">⚙ Accès Administration</h2>
            <a href="admin.php" class="btn profile-admin-btn">Ouvrir le panneau admin</a>
        </section>
    <?php endif; ?>

</main>


<script>
document.addEventListener('DOMContentLoaded', () => {
    
    const form = document.getElementById('profileForm');
    const enableEditBtn = document.getElementById('enableEditBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const saveActions = document.getElementById('saveActions');
    const logoutBtn = document.getElementById('logoutBtn');
    const inputs = form.querySelectorAll('.inline-edit input');

    // Enable inline editing for profile inputs
    if (enableEditBtn) {
        enableEditBtn.addEventListener('click', () => {
            inputs.forEach(input => input.removeAttribute('readonly'));
            enableEditBtn.style.display = 'none';
            saveActions.style.display = 'flex';
            logoutBtn.style.display = 'none';
            if(inputs.length > 0) inputs[0].focus();
        });
    }

    // Cancel inline editing and restore the readonly view
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', () => {
            form.reset(); 
            inputs.forEach(input => {
                input.setAttribute('readonly', 'readonly');
                input.classList.remove('input-error');
                const errorEl = document.getElementById(`error-${input.id}`);
                if (errorEl) {
                    errorEl.style.display = 'none';
                    errorEl.textContent = "";
                }
            });
            enableEditBtn.style.display = 'inline-block';
            saveActions.style.display = 'none';
            logoutBtn.style.display = 'block';
        });
    }

    // JavaScript validation rules mirror the PHP server-side checks
    const rules = {
        fullname: { regex: /^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/, msg: "2 à 50 lettres (espaces et tirets acceptés)." },
        email: { regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, msg: "Adresse email invalide." },
        phone: { regex: /^\+?[0-9\s\-]{8,15}$/, msg: "Uniquement chiffres, espaces ou tirets (8-15)." },
        addr_postal: { regex: /^\d{5}$/, msg: "Exactement 5 chiffres." },
        addr_city: { regex: /^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/, msg: "Uniquement des lettres." },
        addr_number: { regex: /^\d{1,4}[a-zA-Z\s]*$/, msg: "Doit commencer par un chiffre." },
        addr_street: { regex: /^[a-zA-Z0-9À-ÿ\s\-\']{2,100}$/, msg: "Caractères invalides." }
    };

    const validateInput = (input) => {
        const errorEl = document.getElementById(`error-${input.id}`);
        const rule = rules[input.id];
        let isValid = true;
        let val = input.value.trim();

        if (rule && val !== '') {
            if (!rule.regex.test(val)) isValid = false;
        }

        if (!isValid) {
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
        return isValid;
    };

    // Validate fields in real time as the user types or leaves the field
    inputs.forEach(input => {
        input.addEventListener('input', () => validateInput(input));
        input.addEventListener('blur', () => validateInput(input));
    });

    // Prevent submit when a field fails inline validation
    form.addEventListener('submit', (e) => {
        let isFormValid = true;
        inputs.forEach(input => {
            if (!validateInput(input)) isFormValid = false;
        });

        if (!isFormValid || !form.checkValidity()) {
            e.preventDefault(); 
            e.stopPropagation();
            form.reportValidity(); 
        }
    });
});
</script>

</body>
</html>
