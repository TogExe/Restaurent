<?php
/**
 * init_admin.php
 * Run ONCE via browser or CLI to create the admin account.
 * Delete this file afterwards for security.
 */

$adminPassword = 'admin1234';
$adminEmail    = 'admin@restaurant.fr';
$adminName     = 'Administrateur';

$file     = 'users.json';
$allUsers = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

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

file_put_contents($file, json_encode($allUsers, JSON_PRETTY_PRINT));
echo "Admin account created. Email: $adminEmail / Password: $adminPassword\n";
echo "Delete this file (init_admin.php) for security.\n";
