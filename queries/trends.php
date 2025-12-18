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

        if ($mode === "year_overview") {
            // q1: yearly counts and avg features
            $sql = "
                SELECT 
                    t.year,
                    COUNT(*) AS num_tracks,
                    ROUND(AVG(t.danceability), 3) AS avg_danceability,
                    ROUND(AVG(t.energy), 3) AS avg_energy,
                    ROUND(AVG(t.valence), 3) AS avg_valence
                FROM Track t
                WHERE t.year IS NOT NULL
                GROUP BY t.year
                ORDER BY t.year;
            ";

            $rows = $pdo->query($sql)->fetchAll();
        }

        if ($mode === "explicit_trend") {
            // q2: explicit vs clean per year
            $sql = "
                SELECT 
                    t.year,
                    SUM(t.explicit = 1) AS num_explicit,
                    SUM(t.explicit = 0) AS num_clean,
                    ROUND(
                        SUM(t.explicit = 1) / COUNT(*) * 100,
                        2
                    ) AS explicit_pct
                FROM Track t
                WHERE t.year IS NOT NULL
                GROUP BY t.year
                ORDER BY t.year;
            ";

            $rows = $pdo->query($sql)->fetchAll();
        }

        if ($mode === "feature_trends") {
            // q3: trends for a chosen feature over years
            $feature = $_POST['feature'] ?? "energy";

            $allowed = ["energy", "valence", "speechiness", "acousticness"];
            // safety: only allow valid columns
            if (!in_array($feature, $allowed)) {
                throw new Exception("Invalid feature selected.");
            }

            $sql = "
                SELECT 
                    t.year,
                    ROUND(AVG(t.$feature), 3) AS avg_value,
                    COUNT(*) AS num_tracks
                FROM Track t
                WHERE t.year IS NOT NULL
                GROUP BY t.year
                ORDER BY t.year;
            ";

            $stmt = $pdo->query($sql);
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
    <title>Trends & Explicit Analysis</title>
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

        <h1>📈 Trends Over Time</h1>
        <p class="page-description">This page explores yearly patterns in streaming audio features and explicit song frequency.</p>

        <?php if ($error): ?>
        <div class="error"><strong>Error:</strong> <?= h($error) ?></div>
        <?php endif; ?>

        <h2>Q1 — Tracks Per Year + Average Audio Features</h2>
        <form method="post">
            <input type="hidden" name="mode" value="year_overview">
            <button type="submit">Run</button>
        </form>

        <h2>Q2 — Explicit vs Clean Songs by Year</h2>
        <form method="post">
            <input type="hidden" name="mode" value="explicit_trend">
            <button type="submit">Run</button>
        </form>

        <h2>Q3 — Feature Trends Over Time (Energy, Valence, Speechiness, Acousticness)</h2>
        <form method="post">
            <input type="hidden" name="mode" value="feature_trends">
            <label>Select feature:
            <select name="feature">
                <option value="energy">Energy</option>
                <option value="valence">Valence</option>
                <option value="speechiness">Speechiness</option>
                <option value="acousticness">Acousticness</option>
            </select>
            </label>
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