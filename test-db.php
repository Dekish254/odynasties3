<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Starting Direct Database Connection Test</h1>";

// Hardcoded live proxy credentials to completely bypass Render environment configuration bugs
$host = 'altaria.proxy.rlwy.net';
$port = '33553';
$user = 'root';
$pass = 'OGPjARzHlyssppysTWVWvrsYCszLHRHy';
$db   = 'odynasties';

try {
    echo "Attempting to connect to $host:$port...<br>";
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<h2 style='color:green;'>SUCCESS: Connected to Railway Database completely fine!</h2>";
    
    // Test if the schema/tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables found in database:</h3><ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>FAILED to connect:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
