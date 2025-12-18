<?php
require_once "../db.php";
// db connection

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "utf-8"); }
// helper: escape html for output

$rows = [];
$error = null;
$title = "";

// page data

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // handle post requests
        $mode = $_POST['mode'] ?? null;

        if ($mode === "sim_basic") {
          // q1 basic similarity
            $track = $_POST['track_name'] ?? "";
            $k = intval($_POST['k'] ?? 20);

            $sql_vec = "
                select track_id, danceability, energy, valence
                from Track
                where track_name like :name
                limit 1;
            ";
          // get feature vector for input track
            $stmt = $pdo->prepare($sql_vec);
            $stmt->execute([":name" => "%$track%"]);
            $base = $stmt->fetch();

            if (!$base) {
                $error = "track not found.";
            } else {
                $base_id = $base['track_id'];
                $d = $base['danceability'];
                $e = $base['energy'];
                $v = $base['valence'];

                $sql = "
                    select
                        t.track_name,
                        a.artist_name,
                        sqrt(
                            pow(t.danceability - :d, 2) +
                            pow(t.energy - :e, 2) +
                            pow(t.valence - :v, 2)
                        ) as distance
                    from Track t
                    join TrackArtist ta on t.track_id = ta.track_id
                    join Artist a on ta.artist_id = a.artist_id
                    where t.track_id <> :id
                    order by distance asc
                    limit $k;
                ";
                // compute euclidean distance across features
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":d" => $d,
                    ":e" => $e,
                    ":v" => $v,
                    ":id" => $base_id
                ]);
                $rows = $stmt->fetchAll();
                $title = "top $k similar tracks";
            }
        }

        if ($mode === "sim_same_year") {
          // q2 same-year similarity
            $track = $_POST['track_name'] ?? "";
            $k = intval($_POST['k'] ?? 20);

            $sql_vec = "
                select t.track_id, t.danceability, t.energy, t.valence,
                       al.release_year
                from Track t
                join Album al on t.album_id = al.album_id
                where t.track_name like :name
                limit 1;
            ";
          // get base vector and year
            $stmt = $pdo->prepare($sql_vec);
            $stmt->execute([":name" => "%$track%"]);
            $base = $stmt->fetch();

            if (!$base) {
                $error = "track not found.";
            } else {
                $base_id = $base['track_id'];
                $year = $base['release_year'];

                $sql = "
                    select
                        t.track_name,
                        a.artist_name,
                        sqrt(
                            pow(t.danceability - :d, 2) +
                            pow(t.energy - :e, 2) +
                            pow(t.valence - :v, 2)
                        ) as distance
                    from Track t
                    join TrackArtist ta on t.track_id = ta.track_id
                    join Artist a on ta.artist_id = a.artist_id
                    join Album al on t.album_id = al.album_id
                    where t.track_id <> :id
                      and al.release_year = :y
                    order by distance asc
                    limit $k;
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":d" => $base['danceability'],
                    ":e" => $base['energy'],
                    ":v" => $base['valence'],
                    ":id" => $base_id,
                    ":y" => $year
                ]);
                $rows = $stmt->fetchAll();
                $title = "similar tracks from the same year ($year)";
            }
        }

        if ($mode === "sim_weighted") {
          // q3 weighted similarity
            $track = $_POST['track_name'] ?? "";
            $w_d = floatval($_POST['w_dance'] ?? 1.0);
            $w_e = floatval($_POST['w_energy'] ?? 1.0);
            $w_v = floatval($_POST['w_valence'] ?? 1.0);

            $sql_vec = "
                select track_id, danceability, energy, valence
                from Track
                where track_name like :name
                limit 1;
            ";
          // get base vector
            $stmt = $pdo->prepare($sql_vec);
            $stmt->execute([":name" => "%$track%"]);
            $base = $stmt->fetch();

            if (!$base) {
                $error = "track not found.";
            } else {
                $sql = "
                    select
                        t.track_name,
                        a.artist_name,
                        sqrt(
                            pow((t.danceability - :d) * :wd, 2) +
                            pow((t.energy - :e) * :we, 2) +
                            pow((t.valence - :v) * :wv, 2)
                        ) as distance
                    from Track t
                    join TrackArtist ta on t.track_id = ta.track_id
                    join Artist a on ta.artist_id = a.artist_id
                    where t.track_id <> :id
                    order by distance asc
                    limit 30;
                ";
                // apply weights to each feature when computing distance
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":d" => $base['danceability'],
                    ":e" => $base['energy'],
                    ":v" => $base['valence'],
                    ":wd" => $w_d,
                    ":we" => $w_e,
                    ":wv" => $w_v,
                    ":id" => $base['track_id']
                ]);
                $rows = $stmt->fetchAll();
                $title = "weighted similarity results";
            }
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
  <title>Song Similarity</title>
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

    <h1>🎵 Song Similarity Search</h1>
    <p class="page-description">Find tracks that are acoustically similar based on normalized Spotify audio features.</p>

    <?php if ($error): ?>
    <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
    <?php endif; ?>

    <h2>Q1 — Top-K Similar Tracks</h2>
    <form method="post">
      <input type="hidden" name="mode" value="sim_basic">
      <label>Track name: <input name="track_name" required></label>
      <label>K: <input type="number" name="k" value="20" min="5" max="50"></label>
      <button type="submit">Go</button>
    </form>

    <h2>Q2 — Similar Tracks from the Same Release Year</h2>
    <form method="post">
      <input type="hidden" name="mode" value="sim_same_year">
      <label>Track name: <input name="track_name" required></label>
      <label>K: <input type="number" name="k" value="20" min="5" max="50"></label>
      <button type="submit">Go</button>
    </form>

    <h2>Q3 — Weighted Similarity (Custom Feature Importance)</h2>
    <form method="post">
      <input type="hidden" name="mode" value="sim_weighted">
      <label>Track name: <input name="track_name" required></label><br>
      <label>Danceability weight: <input type="number" name="w_dance" step="0.1" value="1.0"></label>
      <label>Energy weight: <input type="number" name="w_energy" step="0.1" value="1.0"></label>
      <label>Valence weight: <input type="number" name="w_valence" step="0.1" value="1.0"></label>
      <button type="submit">Go</button>
    </form>

    <?php if ($rows): ?>
    <h2><?= h($title) ?></h2>
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