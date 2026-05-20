<?php
// Common helpers for the application
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function load_json(string $path): array {
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

function save_json(string $path, array $data): bool {
    return (bool) file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function decryptData($payload, $password) {
    if (!$payload) return "";
    $decoded   = base64_decode($payload);
    $iv        = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, $iv);
}

function require_login($role = null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: connect.php"); exit();
    }
    if ($role !== null) {
        $current = $_SESSION['user_role'] ?? '';
        if (is_array($role)) {
            if (!in_array($current, $role, true)) { header("Location: index.php"); exit(); }
        } else {
            if ($current !== $role) { header("Location: index.php"); exit(); }
        }
    }
}

function redirectByRole($role) {
    switch ($role) {
        case 'admin':     header("Location: admin.php");      break;
        case 'cuisinier': header("Location: cuisinier.php"); break;
        case 'livreur':   header("Location: livreur.php");    break;
        default:          header("Location: profil.php");     break;
    }
    exit();
}

function encryptData($data, $password) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $password, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_secret_key() {
    return $_SESSION['secret_key'] ?? null;
}

function gen_pay_ref(): string {
    try { $r = bin2hex(random_bytes(6)); } catch (Exception $e) { $r = substr(md5(uniqid('', true)),0,12); }
    return 'PAY_DEMO_' . strtoupper($r);
}

function gen_order_id(): string {
    return (string) random_int(10000000000, 99999999999);
}

function format_price(float $p): string {
    return number_format($p, 2, ',', ' ') . ' €';
}

function ensure_ban() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) 
        return;
    if (!isset($allUsers)) {
        $allUsers = load_json('users.json');
    }
    if (!isset($userId)) {
        $userId = current_user_id();
    }
   if (isset($allUsers[$userId]['is_banned']) && $allUsers[$userId]['is_banned'] === true) {
        session_destroy();
        header("Location: connect.php?banned=1");
        exit();
    }
}

function connectIntoAccount($userRole, $key, $password, $email = NULL, $name = NULL){
    if ($userRole == 'admin'){
        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = $key;
        $_SESSION['user_role']     = 'admin';
        $_SESSION['user_email']    = $email;
        $_SESSION['user_fullname'] = $u['plain_name'] ?? 'Admin';
        $_SESSION['secret_key']    = $password;
        redirectByRole('admin');
    }
    else{
        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = $key;
        $_SESSION['user_role']     = $userRole;
        $_SESSION['secret_key']    = $password;
        $_SESSION['user_email']    = $email || decryptData($allUsers[$key]['email_enc'], $password);
        $_SESSION['user_fullname'] = $name || decryptData($allUsers[$key]['fullname_enc'], $password);

        redirectByRole($role);
    }
}

?>