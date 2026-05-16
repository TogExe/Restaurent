<?php
/**
 * init_admin.php
 * Run ONCE via browser or CLI to create the admin account.
 * Delete this file afterwards for security.
 */

$adminPassword = 'admin1234';
$adminEmail    = 'admin@restaurant.fr';
$adminName     = 'Administrateur';

require_once __DIR__ . '/inc/common.php';

$file     = 'users.json';
$allUsers = load_json($file);

// Remove any existing admin
foreach ($allUsers as $key => $u) {
    if (($u['role'] ?? '') === 'admin') unset($allUsers[$key]);
}

$allUsers['__admin__'] = [
    "password_auth" => password_hash($adminPassword, PASSWORD_DEFAULT),
    "email_enc"     => "",
    "fullname_enc"  => "",
    "phone_enc"     => "",
    "plain_email"   => $adminEmail,
    "plain_name"    => $adminName,
    "role"          => "admin",
];

save_json($file, $allUsers);
echo "Admin account created. Email: $adminEmail / Password: $adminPassword\n";
echo "Delete this file (init_admin.php) for security.\n";
