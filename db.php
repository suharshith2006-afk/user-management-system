<?php
// db.php - Centralized Secure PDO Database Connector Module

$host    = 'sql108.infinityfree.com';
$db      = 'users_management_system';
$user    = 'if0_42305032'; 
$pass    = 'Kamma2006'; // Leave blank for default local XAMPP setups
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Enforces native database prepared statements
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     header('Content-Type: application/json');
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
     exit;
}