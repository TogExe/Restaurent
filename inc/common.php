<?php
// Shared helpers for JSON persistence, validation, encryption, session handling and role utilities
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function load_json(string $path): array {
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $decoded = json_decode($content, true);

    return is_array($decoded) ? $decoded : [];
}

function save_json(string $path, array $data): bool {
    return (bool) file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function validate_user_name(string $name): bool {
    return $name !== '' && preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', $name);
}

function validate_email(string $email): bool {
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone(string $phone): bool {
    return $phone !== '' && preg_match('/^\+?[0-9\s\-]{8,15}$/', $phone);
}

function validate_password(string $password): bool {
    return strlen($password) >= 6;
}

function validate_postal_code(string $postal): bool {
    return $postal === '' || preg_match('/^\d{5}$/', $postal);
}

function validate_city(string $city): bool {
    return $city === '' || preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', $city);
}

function validate_street(string $street): bool {
    return $street === '' || preg_match('/^[a-zA-Z0-9À-ÿ\s\-\']{2,100}$/u', $street);
}

function validate_address_number(string $number): bool {
    return $number === '' || preg_match('/^\d{1,4}[a-zA-Z\s]*$/', $number);
}

function get_user_name(array $userData, ?string $secretKey = null): string {
    if (!empty($userData['plain_name'])) {
        return $userData['plain_name'];
    }
    if (!empty($userData['fullname_enc']) && $secretKey !== null) {
        return decryptData($userData['fullname_enc'], $secretKey);
    }
    return '';
}

function get_user_email(array $userData, ?string $secretKey = null): string {
    if (!empty($userData['plain_email'])) {
        return $userData['plain_email'];
    }
    if (!empty($userData['email_enc']) && $secretKey !== null) {
        return decryptData($userData['email_enc'], $secretKey);
    }
    return '';
}

function get_user_phone(array $userData, ?string $secretKey = null): string {
    if (!empty($userData['phone'])) {
        return $userData['phone'];
    }
    if (!empty($userData['phone_enc']) && $secretKey !== null) {
        return decryptData($userData['phone_enc'], $secretKey);
    }
    return '';
}

function get_user_address_parts(array $userData, ?string $secretKey = null): array {
    $parts = ['street' => '', 'number' => '', 'complement' => '', 'postal' => '', 'city' => ''];
    if (!empty($userData['address'])) {
        if (is_array($userData['address'])) {
            return array_merge($parts, $userData['address']);
        }
        $decoded = json_decode($userData['address'], true);
        if (is_array($decoded)) {
            return array_merge($parts, $decoded);
        }
        $parts['street'] = $userData['address'];
        return $parts;
    }
    if (!empty($userData['address_enc']) && $secretKey !== null) {
        $raw = decryptData($userData['address_enc'], $secretKey);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_merge($parts, $decoded);
            }
            $parts['street'] = $raw;
        }
    }
    return $parts;
}

function format_address_parts(array $addressParts): string {
    $segments = [];
    if (!empty($addressParts['street'])) {
        $segments[] = $addressParts['street'];
    }
    if (!empty($addressParts['number'])) {
        $segments[] = $addressParts['number'];
    }
    if (!empty($addressParts['complement'])) {
        $segments[] = $addressParts['complement'];
    }
    if (!empty($addressParts['postal'])) {
        $segments[] = $addressParts['postal'];
    }
    if (!empty($addressParts['city'])) {
        $segments[] = $addressParts['city'];
    }
    return implode(' ', $segments);
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
    global $allUsers;

    if ($userRole == 'admin'){
        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = $key;
        $_SESSION['user_role']     = 'admin';
        $_SESSION['user_email']    = $email;
        $_SESSION['user_fullname'] = $name ?? $email ?? 'Admin';
        $_SESSION['secret_key']    = $password;
        redirectByRole('admin');
    }
    else{
        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = $key;
        $_SESSION['user_role']     = $userRole;
        $_SESSION['secret_key']    = $password;

        $userRecord = $allUsers[$key] ?? [];
        $_SESSION['user_email']    = $email ?? get_user_email($userRecord, $password);
        $_SESSION['user_fullname'] = $name ?? get_user_name($userRecord, $password) ?: ($_SESSION['user_email'] ?? 'Client');

        redirectByRole($userRole);
    }
}

?>