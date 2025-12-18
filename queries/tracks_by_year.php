<?php
require_once "../db.php";
// db connection

$year = $_GET['year'] ?? null;
// get year from query params
$rows = [];
// validate year is numeric then query
if ($year !== null && ctype_digit($year)) {
    // query tracks for the requested year
    $stmt = $pdo->prepare("
        SELECT 
            t.track_name,
            a.album_name,
            t.danceability,
            t.energy,
            t.tempo,
            t.valence
        FROM Track t
        JOIN Album a ON t.album_id = a.album_id
        WHERE a.release_year = ?
        ORDER BY t.energy DESC
        LIMIT 50;
    ");
    $stmt->execute([$year]);
    $rows = $stmt->fetchAll();
}

// render page below
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tracks by Year</title>
</head>
<body>
    <h1>Tracks Released in a Given Year</h1>

    <form method="GET">
        <label>Enter a year:</label>
        <input type="text" name="year" placeholder="e.g. 2015" required>
        <button type="submit">Search</button>
    </form>

    <?php if ($year !== null): ?>
        <h2>Results for <?= htmlspecialchars($year) ?></h2>
        <?php if (count($rows) > 0): ?>
            <table border="1" cellpadding="6" cellspacing="0">
                <tr>
                    <th>Track Name</th>
                    <th>Album Name</th>
                    <th>Danceability</th>
                    <th>Energy</th>
                    <th>Tempo</th>
                    <th>Valence</th>
                </tr>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['track_name']) ?></td>
                        <td><?= htmlspecialchars($r['album_name']) ?></td>
                        <td><?= htmlspecialchars($r['danceability']) ?></td>
                        <td><?= htmlspecialchars($r['energy']) ?></td>
                        <td><?= htmlspecialchars($r['tempo']) ?></td>
                        <td><?= htmlspecialchars($r['valence']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p><i>No results found.</i></p>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="../index.php">Back to Home</a></p>
</body>
</html>
