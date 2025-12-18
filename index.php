<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Spotify DB Project</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <h1>Spotify Database Project</h1>
    <p class="subtitle">
      This is a web app for my final project for EN.601.315, Databases, at Johns Hopkins University. It uses data from two CSVs I found on Kaggle, 
      It uses data from two CSVs I found on Kaggle: 
      <a href="https://www.kaggle.com/datasets/adnananam/spotify-artist-stats" target="_blank">Spotify Artist Stats</a> and 
      <a href="https://www.kaggle.com/datasets/rodolfofigueroa/spotify-12m-songs" target="_blank">Spotify 12M Songs</a>.
    </p>
    
    <h2>Example Queries</h2>
    <ul class="query-grid">
      <li class="query-card"><a href="queries/artist_tracks.php">Artist Tracks & Longest Tracks</a></li>
      <li class="query-card"><a href="queries/top_artists.php">Top Artists & Billion-Track Coverage</a></li>
      <li class="query-card"><a href="queries/albums_audio.php">Album Audio Summaries & Loud Albums</a></li>
      <li class="query-card"><a href="queries/trends.php">Trends (By Year) & Explicit Analysis</a></li>
      <li class="query-card"><a href="queries/discovery.php">Discovery Filters (Danceable / Study / Angry)</a></li>
      <li class="query-card"><a href="queries/similarity.php">Similar Songs & Top-K Per Year</a></li>
    </ul>

    <div class="footer">
      Spotify Database Analysis Tool
    </div>
  </div>
</body>
</html>