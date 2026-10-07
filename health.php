<?php
header('Content-Type: text/plain; charset=utf-8');
try {
  $host = getenv('DB_HOST');
  $port = getenv('DB_PORT') ?: '3306';
  $db   = getenv('DB_NAME');
  $user = getenv('DB_USER');
  $pass = getenv('DB_PASS');
  if (!$host || !$db || !$user) {
    throw new RuntimeException('Database environment variables are not configured.');
  }
  $pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
  );
  $pdo->query('SELECT 1');
  http_response_code(200);
  echo "OK";
} catch (Throwable $e) {
  error_log('Health check failed: '.$e->getMessage());
  http_response_code(503);
  echo "Database unavailable";
}
