<?php
require 'config.php';

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "
        SELECT
          ar.artist_name,
          al.album_name,
          al.release_year,
          t.track_name,
          t.danceability,
          t.energy,
          t.valence
        FROM Artist ar
        JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
        JOIN Track t ON t.track_id = ta.track_id
        JOIN Album al ON al.album_id = t.album_id
        WHERE ar.artist_name = ?
          AND al.release_year BETWEEN ? AND ?
        ORDER BY al.release_year DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['artist_name'],
        $_POST['min_year'],
        $_POST['max_year']
    ]);

    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Artist Tracks</title>
</head>
<body>
  <h2>Tracks by Artist</h2>

  <form method="post">
    Artist Name: <input type="text" name="artist_name" required><br>
    Min Year: <input type="number" name="min_year" value="2000"><br>
    Max Year: <input type="number" name="max_year" value="2020"><br>
    <button type="submit">Search</button>
  </form>

  <?php if ($results): ?>
    <h3>Results</h3>
    <table border="1">
      <tr>
        <th>Artist</th>
        <th>Album</th>
        <th>Year</th>
        <th>Track</th>
        <th>Danceability</th>
        <th>Energy</th>
        <th>Valence</th>
      </tr>
      <?php foreach ($results as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['artist_name']) ?></td>
        <td><?= htmlspecialchars($row['album_name']) ?></td>
        <td><?= htmlspecialchars($row['release_year']) ?></td>
        <td><?= htmlspecialchars($row['track_name']) ?></td>
        <td><?= htmlspecialchars($row['danceability']) ?></td>
        <td><?= htmlspecialchars($row['energy']) ?></td>
        <td><?= htmlspecialchars($row['valence']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>
