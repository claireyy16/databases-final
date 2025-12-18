<?php
require 'config.php';

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        SELECT
          ar.artist_name,
          s.total_streams,
          s.num_billion_tracks
        FROM ArtistStats s
        JOIN Artist ar ON ar.artist_id = s.artist_id
        WHERE s.total_streams >= ?
        ORDER BY s.total_streams DESC
        LIMIT ?
    ");

    $stmt->execute([
        $_POST['min_streams'],
        $_POST['limit']
    ]);

    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>
<head><title>Top Artists</title></head>
<body>
<h2>Top Artists by Streams</h2>

<form method="post">
  Minimum Streams: <input type="number" name="min_streams" value="1000000000"><br>
  Limit: <input type="number" name="limit" value="10"><br>
  <button type="submit">Run</button>
</form>

<?php if ($results): ?>
<table border="1">
<tr><th>Artist</th><th>Total Streams</th><th># Billion Tracks</th></tr>
<?php foreach ($results as $r): ?>
<tr>
  <td><?= htmlspecialchars($r['artist_name']) ?></td>
  <td><?= number_format($r['total_streams']) ?></td>
  <td><?= htmlspecialchars($r['num_billion_tracks']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</body>
</html>
