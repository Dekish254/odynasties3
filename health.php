<?php
header('Content-Type: text/plain; charset=utf-8');

// TEMPORARY Render/Railway diagnostic endpoint.
// This deliberately connects WITHOUT selecting DB_NAME so we can see
// what databases the MySQL server reached by Render actually exposes.
try {
  $host = getenv('DB_HOST');
  $port = getenv('DB_PORT') ?: '3306';
  $db   = getenv('DB_NAME');
  $user = getenv('DB_USER');
  $pass = getenv('DB_PASS');

  if (!$host || !$db || !$user) {
    throw new RuntimeException('Missing DB_HOST, DB_NAME, or DB_USER environment variable.');
  }

  $pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
  );

  $server = $pdo->query('SELECT @@hostname AS mysql_hostname, @@port AS mysql_port, DATABASE() AS selected_database')->fetch(PDO::FETCH_ASSOC);
  $databases = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);

  echo "Odynasties temporary database diagnostic\n";
  echo "DB_HOST: " . $host . "\n";
  echo "DB_PORT: " . $port . "\n";
  echo "DB_NAME requested: " . $db . "\n";
  echo "DB_USER: " . $user . "\n";
  echo "MySQL server hostname: " . ($server['mysql_hostname'] ?? '') . "\n";
  echo "MySQL server port: " . ($server['mysql_port'] ?? '') . "\n";
  echo "Databases visible to Render:\n";
  foreach ($databases as $name) {
    echo " - " . $name . "\n";
  }

  if (in_array($db, $databases, true)) {
    $check = new PDO(
      "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
      $user,
      $pass,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $tables = $check->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "\nDatabase '$db' IS visible.\n";
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) echo " - " . $table . "\n";
    http_response_code(200);
  } else {
    echo "\nDatabase '$db' IS NOT visible to Render.\n";
    http_response_code(503);
  }
} catch (Throwable $e) {
  error_log('Temporary health diagnostic failed: ' . $e->getMessage());
  http_response_code(503);
  echo "DIAGNOSTIC ERROR\n";
  echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
