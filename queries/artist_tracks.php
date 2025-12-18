<?php
require_once "../db.php";
// db connection

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// helper: escape html for safe output

$mode = $_POST['mode'] ?? null;
$rows = [];
$error = null;

// page data

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // handle post requests

      if ($mode === 'q1') {
        // q1: list all tracks for an artist (by name search)
            $name = "%" . ($_POST['artist_name'] ?? "") . "%";

            $sql = "
                SELECT ar.artist_name, t.track_name, t.danceability, t.energy, t.duration_ms
                FROM Artist ar
                JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
                JOIN Track t ON t.track_id = ta.track_id
                WHERE ar.artist_name LIKE ?
                ORDER BY t.track_name;
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name]);
            $rows = $stmt->fetchAll();
        }

        if ($mode === 'longest') {
          // q2: longest tracks in the db
            $limit = (int)($_POST['limit_n'] ?? 20);

            $sql = "
                SELECT t.track_name, ar.artist_name, t.duration_ms
                FROM Track t
                JOIN TrackArtist ta ON ta.track_id = t.track_id
                JOIN Artist ar ON ar.artist_id = ta.artist_id
                ORDER BY t.duration_ms DESC
                LIMIT ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll();
        }

        if ($mode === 'q3') {
          // q3: top tracks for an artist by chosen metric
            $artist = "%" . ($_POST['artist_name2'] ?? "") . "%";
            $metric = $_POST['metric'] ?? "energy";
            $limit = (int)($_POST['limit_n2'] ?? 10);

          // safety: only allow known metrics
          if (!in_array($metric, ["energy", "valence", "danceability"])) {
            throw new Exception("invalid metric.");
          }

            $sql = "
                SELECT ar.artist_name, t.track_name, t.$metric, t.energy, t.valence
                FROM Track t
                JOIN TrackArtist ta ON ta.track_id = t.track_id
                JOIN Artist ar ON ar.artist_id = ta.artist_id
                WHERE ar.artist_name LIKE ?
                ORDER BY t.$metric DESC
                LIMIT ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$artist, $limit]);
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
  <title>Artist Tracks</title>
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
  </style>
</head>
<body>
  <div class="container">
    <a href="../index.php" class="back-link">← Back</a>
    
    <h1>🎸 Artist Tracks, Longest Tracks, and Ranked Tracks</h1>

    <?php if ($error): ?>
    <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <h2>Q1: List all tracks by an artist</h2>
    <form method="post">
        <input type="hidden" name="mode" value="q1">
        <label>Artist name: <input name="artist_name"></label>
        <button type="submit">Search</button>
    </form>

    <h2>Q2: Longest tracks in the database</h2>
    <form method="post">
        <input type="hidden" name="mode" value="longest">
        <label>Show top N: <input type="number" name="limit_n" value="20"></label>
        <button type="submit">Go</button>
    </form>

    <h2>Q3: Top tracks by metric for an artist</h2>
    <form method="post">
        <input type="hidden" name="mode" value="q3">
        <label>Artist name: <input name="artist_name2"></label><br>
        <label>Sort by:
        <select name="metric">
            <option value="energy">Energy</option>
            <option value="valence">Valence</option>
            <option value="danceability">Danceability</option>
        </select>
        </label>
        <label>Limit: <input type="number" name="limit_n2" value="10"></label>
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