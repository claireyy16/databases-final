<?php
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
  if ($action === 'top_artists') {
    $stmt = $pdo->query("
      SELECT ar.artist_name, s.total_streams
      FROM ArtistStats s
      JOIN Artist ar ON ar.artist_id = s.artist_id
      ORDER BY s.total_streams DESC
      LIMIT 20
    ");
    echo json_encode($stmt->fetchAll());
    exit;
  }

  echo json_encode(['error' => 'unknown action']);
} catch (Throwable $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
