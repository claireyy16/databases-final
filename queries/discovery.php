<?php
require_once "../db.php";
// db connection

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// helper: escape html for output

$mode = $_POST['mode'] ?? null;
$rows = [];
$error = null;

// page data

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // handle post requests

        // q1: danceable tracks (danceability >= 0.8)
        if ($mode === "danceable") {
            $sql = "
                SELECT 
                    t.track_name,
                    t.danceability,
                    t.energy,
                    t.valence,
                    t.tempo,
                    a.artist_name
                FROM Track t
                JOIN TrackArtist ta ON t.track_id = ta.track_id
                JOIN Artist a ON ta.artist_id = a.artist_id
                WHERE t.danceability >= 0.80
                ORDER BY t.danceability DESC
                LIMIT 200;
            ";
            $rows = $pdo->query($sql)->fetchAll();
        }

        // q2: study mode (low energy, low speechiness, moderate tempo)
        if ($mode === "study") {
            $sql = "
                SELECT 
                    t.track_name,
                    t.energy,
                    t.speechiness,
                    t.tempo,
                    t.acousticness,
                    a.artist_name
                FROM Track t
                JOIN TrackArtist ta ON t.track_id = ta.track_id
                JOIN Artist a ON ta.artist_id = a.artist_id
                WHERE t.energy <= 0.35
                  AND t.speechiness <= 0.20
                  AND t.tempo BETWEEN 60 AND 130
                ORDER BY t.energy ASC, t.acousticness DESC
                LIMIT 200;
            ";
            $rows = $pdo->query($sql)->fetchAll();
        }

        // q3: happy / positive vibe (high valence)
        if ($mode === "happy") {
            $sql = "
                SELECT 
                    t.track_name,
                    t.valence,
                    t.energy,
                    t.danceability,
                    a.artist_name
                FROM Track t
                JOIN TrackArtist ta ON t.track_id = ta.track_id
                JOIN Artist a ON ta.artist_id = a.artist_id
                WHERE t.valence >= 0.75
                ORDER BY t.valence DESC
                LIMIT 200;
            ";
            $rows = $pdo->query($sql)->fetchAll();
        }

        // q4: angry / intense (high energy, low valence)
        if ($mode === "angry") {
            $sql = "
                SELECT 
                    t.track_name,
                    t.energy,
                    t.valence,
                    t.tempo,
                    a.artist_name
                FROM Track t
                JOIN TrackArtist ta ON t.track_id = ta.track_id
                JOIN Artist a ON ta.artist_id = a.artist_id
                WHERE t.energy >= 0.85
                  AND t.valence <= 0.35
                ORDER BY t.energy DESC
                LIMIT 200;
            ";
            $rows = $pdo->query($sql)->fetchAll();
        }

        // q5: custom filters (user-specified ranges)
        if ($mode === "custom") {
            $dmin = floatval($_POST['dance_min'] ?? 0);
            $emin = floatval($_POST['energy_min'] ?? 0);
            $vmin = floatval($_POST['valence_min'] ?? 0);
            $tmin = floatval($_POST['tempo_min'] ?? 0);
            $tmax = floatval($_POST['tempo_max'] ?? 300);

            $sql = "
                SELECT
                    t.track_name,
                    t.danceability,
                    t.energy,
                    t.valence,
                    t.tempo,
                    a.artist_name
                FROM Track t
                JOIN TrackArtist ta ON t.track_id = ta.track_id
                JOIN Artist a ON ta.artist_id = a.artist_id
                WHERE t.danceability >= :dmin
                  AND t.energy >= :emin
                  AND t.valence >= :vmin
                  AND t.tempo BETWEEN :tmin AND :tmax
                ORDER BY t.danceability DESC, t.energy DESC
                LIMIT 300;
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":dmin" => $dmin,
                ":emin" => $emin,
                ":vmin" => $vmin,
                ":tmin" => $tmin,
                ":tmax" => $tmax
            ]);
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
    <title>Track Discovery</title>
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

        <h1>🔍 Track Discovery Filters</h1>
        <p class="page-description">Use preset vibe filters or create your own custom audio feature filter.</p>

        <?php if ($error): ?>
        <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
        <?php endif; ?>

        <h2>Q1 — Highly Danceable Tracks</h2>
        <form method="post">
            <input type="hidden" name="mode" value="danceable">
            <button type="submit">Find Danceable Tracks</button>
        </form>

        <h2>Q2 — Study Mode (Low Energy / Low Speechiness)</h2>
        <form method="post">
            <input type="hidden" name="mode" value="study">
            <button type="submit">Find Study Tracks</button>
        </form>

        <h2>Q3 — Happy Vibe (High Valence)</h2>
        <form method="post">
            <input type="hidden" name="mode" value="happy">
            <button type="submit">Find Happy Tracks</button>
        </form>

        <h2>Q4 — Angry / Intense (High Energy, Low Valence)</h2>
        <form method="post">
            <input type="hidden" name="mode" value="angry">
            <button type="submit">Find Angry Tracks</button>
        </form>

        <h2>Q5 — Custom Audio Filter</h2>
        <form method="post">
            <input type="hidden" name="mode" value="custom">
            <label>Danceability ≥ <input type="number" step="0.01" name="dance_min" value="0.5"></label>
            <label>Energy ≥ <input type="number" step="0.01" name="energy_min" value="0.4"></label>
            <label>Valence ≥ <input type="number" step="0.01" name="valence_min" value="0.3"></label><br>
            <label>Tempo: <input type="number" name="tempo_min" value="60"> to 
            <input type="number" name="tempo_max" value="180"></label>
            <button type="submit">Run Custom Filter</button>
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