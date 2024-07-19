<?php
require __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use League\Csv\Reader;
use League\Csv\Statement;

$phinxConfig = require 'phinx.php';
$env = $phinxConfig['environments']['development'];

$connectionParams = [
    'dbname' => $env['name'],
    'user' => $env['user'],
    'password' => $env['pass'],
    'host' => $env['host'],
    'driver' => 'pdo_pgsql',
    'port' => $env['port'],
    'charset' => $env['charset'],
];

try {
    $conn = DriverManager::getConnection($connectionParams);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$csv = Reader::createFromPath('imports/utenti.csv', 'r');
$csv->setHeaderOffset(0);

$statement = (new Statement())->process($csv);

foreach ($statement as $record) {
    $firstName = $record['firstName'];
    $lastName = $record['lastName'];
    $email = $record['email'];
    $username = $record['username'];
    $password = password_hash($record['password'], PASSWORD_DEFAULT);
    $birthday = $record['birthday'];

    $existingUser = $conn->fetchAssociative('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $email]);

    if ($existingUser) {
        echo "User with username '$username' or email '$email' already exists.\n";
        continue;
    }

    $conn->insert('users', [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'birthday' => $birthday
    ]);

    echo "User '$username' inserted successfully.\n";
}
