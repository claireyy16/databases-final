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
    // handle form submissions

    if ($mode === 'q2') {
      // q2: top artists by total streams (with min + limit)
      $minStreams = (int)($_POST['min_streams'] ?? 0);
      $limitN = (int)($_POST['limit_n'] ?? 20);

      $sql = "
        SELECT
          ar.artist_name,
          s.total_streams,
          s.num_billion_tracks,
          s.world_rank,
          s.stats_date
        FROM ArtistStats s
        JOIN Artist ar ON ar.artist_id = s.artist_id
        WHERE s.total_streams IS NOT NULL
          AND s.total_streams >= ?
        ORDER BY s.total_streams DESC
        LIMIT ?;
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(1, $minStreams, PDO::PARAM_INT);
      $stmt->bindValue(2, $limitN, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
    }

    if ($mode === 'q11') {
      // q11: artists with >= x billion-stream tracks + coverage in db
      $minBillionTracks = (int)($_POST['min_billion_tracks'] ?? 1);
      $limitN = (int)($_POST['limit_n'] ?? 50);

      $sql = "
        SELECT
          ar.artist_name,
          s.num_billion_tracks,
          s.total_streams,
          COUNT(ta.track_id) AS tracks_in_db
        FROM ArtistStats s
        JOIN Artist ar ON ar.artist_id = s.artist_id
        LEFT JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
        WHERE s.num_billion_tracks IS NOT NULL
          AND s.num_billion_tracks >= ?
        GROUP BY ar.artist_id, ar.artist_name, s.num_billion_tracks, s.total_streams
        ORDER BY s.num_billion_tracks DESC, tracks_in_db DESC
        LIMIT ?;
      ";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(1, $minBillionTracks, PDO::PARAM_INT);
      $stmt->bindValue(2, $limitN, PDO::PARAM_INT);
      $stmt->execute();
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
  <title>Top Artists</title>
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
    
    <h1>🌟 Top Artists & Billion-Track Coverage</h1>
    <p class="page-description">
      This page demonstrates join queries across <strong>Artist</strong> and <strong>ArtistStats</strong>,
      plus aggregation over <strong>TrackArtist</strong>.
    </p>

    <?php if ($error): ?>
    <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <h2>Q2: Top artists by total streams</h2>
    <form method="post">
      <input type="hidden" name="mode" value="q2">
      <label>Minimum total streams: <input type="number" name="min_streams" value="<?= h($_POST['min_streams'] ?? '0') ?>"></label><br>
      <label>Limit: <input type="number" name="limit_n" value="<?= h($_POST['limit_n'] ?? '20') ?>"></label>
      <button type="submit">Run Q2</button>
    </form>

    <h2>Q11: Artists with at least X billion-stream tracks (and coverage in your DB)</h2>
    <form method="post">
      <input type="hidden" name="mode" value="q11">
      <label>Minimum billion-stream tracks: <input type="number" name="min_billion_tracks" value="<?= h($_POST['min_billion_tracks'] ?? '1') ?>"></label><br>
      <label>Limit: <input type="number" name="limit_n" value="<?= h($_POST['limit_n'] ?? '50') ?>"></label>
      <button type="submit">Run Q11</button>
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
            <?php foreach ($r as $k => $v): ?>
              <td>
                <?php if ($k === 'total_streams' && $v !== null): ?>
                  <?= h(number_format((float)$v)) ?>
                <?php else: ?>
                  <?= h($v) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>