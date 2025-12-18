<?php
require_once 'db.php';

$rows = [];

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

    $rows = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<body>
<h2>Artist Tracks</h2>

<form method="post">
  Artist: <input name="artist_name" required><br>
  From Year: <input type="number" name="min_year" value="2000"><br>
  To Year: <input type="number" name="max_year" value="2020"><br>
  <button type="submit">Search</button>
</form>

<?php if ($rows): ?>
<table border="1">
<tr>
  <th>Artist</th><th>Album</th><th>Year</th>
  <th>Track</th><th>Dance</th><th>Energy</th><th>Valence</th>
</tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= htmlspecialchars($r['artist_name']) ?></td>
  <td><?= htmlspecialchars($r['album_name']) ?></td>
  <td><?= htmlspecialchars($r['release_year']) ?></td>
  <td><?= htmlspecialchars($r['track_name']) ?></td>
  <td><?= htmlspecialchars($r['danceability']) ?></td>
  <td><?= htmlspecialchars($r['energy']) ?></td>
  <td><?= htmlspecialchars($r['valence']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</body>
</html>
