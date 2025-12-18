<?php
require_once "../db.php";
// db connection

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// helper: escape html for output

$mode = $_POST['mode'] ?? null;
$rows = [];
$error = null;

// page data

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // handle post requests

      // q1: album audio summary (avg energy, danceability, loudness)
      if ($mode === 'summary') {
            $album = "%" . ($_POST['album_name'] ?? "") . "%";

            $sql = "
                SELECT 
                    a.album_name,
                    ar.artist_name,
                    COUNT(t.track_id) AS num_tracks,
                    ROUND(AVG(t.energy), 3) AS avg_energy,
                    ROUND(AVG(t.danceability), 3) AS avg_danceability,
                    ROUND(AVG(t.loudness), 3) AS avg_loudness
                FROM Album a
                JOIN Track t ON t.album_id = a.album_id
                JOIN TrackArtist ta ON ta.track_id = t.track_id
                JOIN Artist ar ON ar.artist_id = ta.artist_id
                WHERE a.album_name LIKE ?
                GROUP BY a.album_name, ar.artist_name
                ORDER BY a.album_name;
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$album]);
            $rows = $stmt->fetchAll();
        }

        // q2: loudest albums by average loudness (desc)
        if ($mode === 'loudest') {
            $limit = (int)($_POST['limit_n'] ?? 20);
            $minTracks = (int)($_POST['min_tracks'] ?? 3);

            $sql = "
                SELECT 
                    a.album_name,
                    ar.artist_name,
                    COUNT(t.track_id) AS num_tracks,
                    ROUND(AVG(t.loudness), 3) AS avg_loudness
                FROM Album a
                JOIN Track t ON t.album_id = a.album_id
                JOIN TrackArtist ta ON ta.track_id = t.track_id
                JOIN Artist ar ON ar.artist_id = ta.artist_id
                GROUP BY a.album_name, ar.artist_name
                HAVING COUNT(t.track_id) >= ?
                ORDER BY avg_loudness DESC
                LIMIT ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$minTracks, $limit]);
            $rows = $stmt->fetchAll();
        }

        // q3: albums with highest danceability (playlist-style)
        if ($mode === 'danceable') {
            $limit = (int)($_POST['limit_n2'] ?? 20);

            $sql = "
                SELECT 
                    a.album_name,
                    ar.artist_name,
                    COUNT(t.track_id) AS num_tracks,
                    ROUND(AVG(t.danceability), 3) AS avg_danceability,
                    ROUND(AVG(t.energy), 3) AS avg_energy
                FROM Album a
                JOIN Track t ON t.album_id = a.album_id
                JOIN TrackArtist ta ON ta.track_id = t.track_id
                JOIN Artist ar ON ar.artist_id = ta.artist_id
                GROUP BY a.album_name, ar.artist_name
                HAVING AVG(t.danceability) IS NOT NULL
                ORDER BY avg_danceability DESC
                LIMIT ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Album Audio Analysis</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    form {
      background: #f8f9fa;
      padding: 1.5rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      border: 1px solid #e0e0e0;
    }
    
    form input, form select, form button {
      margin: 0.5rem 0.5rem 0.5rem 0;
      padding: 0.5rem 0.75rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
    
    form button {
      background: #1DB954;
      color: white;
      border: none;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.3s ease;
    }
    
    form button:hover {
      background: #1ed760;
    }
    
    .back-link {
      display: inline-block;
      margin-bottom: 1.5rem;
      color: #1DB954;
      text-decoration: none;
      font-weight: 600;
    }
    
    .back-link:hover {
      text-decoration: underline;
    }
    
    .error {
      background: #fee;
      color: #c00;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      border-left: 4px solid #c00;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1.5rem;
      background: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-radius: 8px;
      overflow: hidden;
    }
    
    th {
      background: #1DB954;
      color: white;
      padding: 1rem;
      text-align: left;
      font-weight: 600;
    }
    
    td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #eee;
    }
    
    tr:hover {
      background: #f8f9fa;
    }
    
    .page-description {
      color: #666;
      line-height: 1.6;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../index.php" class="back-link">← Back</a>

    <h1>💿 Album-Level Audio Analysis</h1>
    <p class="page-description">This page shows summaries of energy, loudness, and danceability for albums.</p>

    <?php if ($error): ?>
    <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <h2>Q1 — Album Audio Summary</h2>
    <form method="post">
        <input type="hidden" name="mode" value="summary">
        <label>Album name: <input name="album_name" placeholder="e.g. Thriller"></label>
        <button type="submit">Search</button>
    </form>

    <h2>Q2 — Loudest Albums (Average Loudness)</h2>
    <form method="post">
        <input type="hidden" name="mode" value="loudest">
        <label>Minimum tracks: <input type="number" name="min_tracks" value="3"></label>
        <label>Limit: <input type="number" name="limit_n" value="20"></label>
        <button type="submit">Run</button>
    </form>

    <h2>Q3 — Albums with Highest Danceability</h2>
    <form method="post">
        <input type="hidden" name="mode" value="danceable">
        <label>Limit: <input type="number" name="limit_n2" value="20"></label>
        <button type="submit">Run</button>
    </form>

    <?php if ($rows): ?>
    <h2>Results</h2>
    <table>
        <tr>
            <?php foreach (array_keys($rows[0]) as $col): ?>
            <th><?= h($col) ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($rows as $r): ?>
        <tr>
            <?php foreach ($r as $v): ?>
            <td><?= h($v) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</body>
</html>