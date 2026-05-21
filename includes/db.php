<?php
/*
  includes/db.php - Database connection
  Include this at the top of any page that needs DB access:
  like so (require_once 'includes/db.php';)
  Then use $pdo for all queries.
*/

$host = 'sql305.infinityfree.com';
$dbname = 'if0_41986425_cvsu_marketplace';
$user = 'if0_41986425';
$pass = 'yAFKHWM1hlep';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
  die('Database connection failed. Please try again later.');
}