USE spotify_db;

DROP TABLE IF EXISTS RawArtistStats;
DROP TABLE IF EXISTS RawTrackFeatures;


CREATE TABLE RawArtistStats (
  row_num        INT NULL,
  artist_name    VARCHAR(255) NULL,
  lead_streams   VARCHAR(32) NULL,
  feats_streams  VARCHAR(32) NULL,   
  tracks         VARCHAR(32),
  one_billion    VARCHAR(32),
  hundred_million VARCHAR(32),
  last_updated   VARCHAR(32) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS RawTrackFeatures;

CREATE TABLE RawTrackFeatures (
  track_id         VARCHAR(64) NULL,
  track_name       VARCHAR(255) NULL,
  album_name       VARCHAR(255) NULL,
  spotify_album_id VARCHAR(64) NULL,
  artists_raw      TEXT NULL,
  artist_ids_raw   TEXT NULL,
  track_number     INT NULL,
  disc_number      INT NULL,
  explicit_raw     VARCHAR(16) NULL,

  danceability     DOUBLE NULL,
  energy           DOUBLE NULL,
  `key`            INT NULL,
  loudness         DOUBLE NULL,
  mode             INT NULL,
  speechiness      DOUBLE NULL,
  acousticness     DOUBLE NULL,
  instrumentalness DOUBLE NULL,
  liveness         DOUBLE NULL,
  valence          DOUBLE NULL,
  tempo            DOUBLE NULL,

  duration_ms      INT NULL,
  time_signature   DOUBLE NULL,
  `year`           INT NULL,
  release_date_raw VARCHAR(32) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- loading in data

-- load artist stats (spotify.csv)

LOAD DATA LOCAL INFILE '/Users/clairec/Desktop/JHU_F25/databases/project/project/data/spotify_artist_data.csv'
INTO TABLE RawArtistStats
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(row_num, artist_name, lead_streams, feats_streams, tracks, one_billion, hundred_million, last_updated);

-- load tracks (tracks_features.csv)
LOAD DATA LOCAL INFILE '/Users/clairec/Desktop/JHU_F25/databases/project/project/data/tracks_features.csv'
INTO TABLE RawTrackFeatures
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(track_id, track_name, album_name, spotify_album_id, artists_raw, artist_ids_raw,
 track_number, disc_number, explicit_raw, danceability, energy, `key`, loudness,
 mode, speechiness, acousticness, instrumentalness, liveness, valence, tempo,
 duration_ms, time_signature, `year`, release_date_raw);

SET SQL_SAFE_UPDATES = 0;
DELETE FROM RawTrackFeatures
WHERE danceability IS NOT NULL
  AND (danceability < 0 OR danceability > 1);
  
SET SQL_SAFE_UPDATES = 1;

-- Album, Artist, Track, TrackArtist, ArtistStats

-- Album

INSERT INTO Album (spotify_album_id, album_name, release_year, album_type)
SELECT DISTINCT
  r.spotify_album_id,
  r.album_name,
  CASE
    WHEN r.`year` BETWEEN 1900 AND 2100 THEN r.`year`
    ELSE NULL
  END AS release_year,
  NULL
FROM RawTrackFeatures r
WHERE r.spotify_album_id IS NOT NULL AND r.spotify_album_id <> ''
ON DUPLICATE KEY UPDATE
  album_name = VALUES(album_name),
  release_year = VALUES(release_year);


-- artists but from the tracks table
INSERT INTO Artist (artist_name, spotify_artist_id)
SELECT DISTINCT
  TRIM(BOTH ' ' FROM
    REPLACE(REPLACE(REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(r.artists_raw, ',', 1), ']', 1),
    '[', ''), ']', ''), '''', '')
  ) AS artist_name,
  TRIM(BOTH ' ' FROM
    REPLACE(REPLACE(REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(r.artist_ids_raw, ',', 1), ']', 1),
    '[', ''), ']', ''), '''', '')
  ) AS spotify_artist_id
FROM RawTrackFeatures r
WHERE r.artists_raw IS NOT NULL
  AND r.artist_ids_raw IS NOT NULL
  AND r.artists_raw <> '[]'
  AND r.artist_ids_raw <> '[]'
ON DUPLICATE KEY UPDATE
  artist_name = VALUES(artist_name);

-- tracks
INSERT INTO Track (
  track_id, album_id, track_name, track_number, duration_ms, explicit,
  danceability, energy, loudness, speechiness, acousticness, instrumentalness,
  liveness, valence, tempo, popularity
)
SELECT
  r.track_id,
  al.album_id,
  r.track_name,
  r.track_number,
  r.duration_ms,
  CASE
    WHEN LOWER(r.explicit_raw) IN ('true','1','t') THEN 1
    ELSE 0
  END AS explicit,
  r.danceability,
  r.energy,
  r.loudness,
  r.speechiness,
  r.acousticness,
  r.instrumentalness,
  r.liveness,
  r.valence,
  r.tempo,
  NULL
FROM RawTrackFeatures r
JOIN Album al ON al.spotify_album_id = r.spotify_album_id
WHERE r.track_id IS NOT NULL AND r.track_id <> ''
ON DUPLICATE KEY UPDATE
  track_name = VALUES(track_name),
  track_number = VALUES(track_number),
  duration_ms = VALUES(duration_ms),
  explicit = VALUES(explicit),
  danceability = VALUES(danceability),
  energy = VALUES(energy),
  loudness = VALUES(loudness),
  speechiness = VALUES(speechiness),
  acousticness = VALUES(acousticness),
  instrumentalness = VALUES(instrumentalness),
  liveness = VALUES(liveness),
  valence = VALUES(valence),
  tempo = VALUES(tempo);

-- TrackArtist
INSERT INTO TrackArtist (track_id, artist_id, role)
SELECT
  r.track_id,
  a.artist_id,
  'primary'
FROM RawTrackFeatures r
JOIN Artist a
  ON a.spotify_artist_id =
     TRIM(BOTH ' ' FROM
       REPLACE(REPLACE(REPLACE(
         SUBSTRING_INDEX(SUBSTRING_INDEX(r.artist_ids_raw, ',', 1), ']', 1),
       '[', ''), ']', ''), '''', '')
     )
WHERE r.track_id IS NOT NULL
ON DUPLICATE KEY UPDATE role = VALUES(role);


-- ArtistStats
-- clean up stats and dates

INSERT INTO ArtistStats (
  artist_id,
  total_streams,
  num_tracks,
  num_features,
  stats_date
)
SELECT
  a.artist_id,
  CAST(REPLACE(r.lead_streams, ',', '') AS UNSIGNED),
  CAST(REPLACE(r.tracks, ',', '') AS UNSIGNED),
  NULL,
  STR_TO_DATE(r.last_updated, '%d.%m.%y')
FROM RawArtistStats r
JOIN Artist a
  ON a.artist_name = r.artist_name
ON DUPLICATE KEY UPDATE
  total_streams = VALUES(total_streams),
  num_tracks = VALUES(num_tracks),
  num_features = VALUES(num_features),
  stats_date = VALUES(stats_date);
  
-- data loading
  
ALTER TABLE ArtistStats
  ADD COLUMN num_billion_tracks INT DEFAULT NULL;
  
INSERT INTO Album (spotify_album_id, album_name, release_year, album_type)
SELECT DISTINCT
  r.spotify_album_id,
  r.album_name,
  CASE WHEN r.`year` BETWEEN 1900 AND 2100 THEN r.`year` ELSE NULL END AS release_year,
  NULL
FROM RawTrackFeatures r
WHERE r.spotify_album_id IS NOT NULL AND r.spotify_album_id <> ''
ON DUPLICATE KEY UPDATE
  album_name = VALUES(album_name),
  release_year = VALUES(release_year);

INSERT INTO Artist (artist_name, spotify_artist_id)
SELECT DISTINCT
  TRIM(BOTH ' ' FROM
    REPLACE(REPLACE(REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(r.artists_raw, ',', 1), ']', 1),
    '[', ''), ']', ''), '''', '')
  ) AS artist_name,
  TRIM(BOTH ' ' FROM
    REPLACE(REPLACE(REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(r.artist_ids_raw, ',', 1), ']', 1),
    '[', ''), ']', ''), '''', '')
  ) AS spotify_artist_id
FROM RawTrackFeatures r
WHERE r.artists_raw IS NOT NULL
  AND r.artist_ids_raw IS NOT NULL
  AND r.artists_raw <> '[]'
  AND r.artist_ids_raw <> '[]'
ON DUPLICATE KEY UPDATE
  artist_name = VALUES(artist_name);

INSERT INTO Track (
  track_id, album_id, track_name, track_number, duration_ms, explicit,
  danceability, energy, loudness, speechiness, acousticness, instrumentalness,
  liveness, valence, tempo, popularity
)
SELECT
  r.track_id,
  al.album_id,
  r.track_name,
  r.track_number,
  r.duration_ms,
  CASE WHEN LOWER(r.explicit_raw) IN ('true','1','t') THEN 1 ELSE 0 END,

  CASE WHEN r.danceability BETWEEN 0 AND 1 THEN ROUND(r.danceability,2) ELSE NULL END,
  CASE WHEN r.energy BETWEEN 0 AND 1 THEN ROUND(r.energy,2) ELSE NULL END,
  ROUND(r.loudness,2),
  CASE WHEN r.speechiness BETWEEN 0 AND 1 THEN ROUND(r.speechiness,2) ELSE NULL END,
  CASE WHEN r.acousticness BETWEEN 0 AND 1 THEN ROUND(r.acousticness,2) ELSE NULL END,
  CASE WHEN r.instrumentalness BETWEEN 0 AND 1 THEN ROUND(r.instrumentalness,2) ELSE NULL END,
  CASE WHEN r.liveness BETWEEN 0 AND 1 THEN ROUND(r.liveness,2) ELSE NULL END,
  CASE WHEN r.valence BETWEEN 0 AND 1 THEN ROUND(r.valence,2) ELSE NULL END,
  ROUND(r.tempo,2),
  NULL
FROM RawTrackFeatures r
JOIN Album al ON al.spotify_album_id = r.spotify_album_id
WHERE r.track_id IS NOT NULL AND r.track_id <> ''
ON DUPLICATE KEY UPDATE
  track_name = VALUES(track_name),
  danceability = VALUES(danceability),
  energy = VALUES(energy),
  loudness = VALUES(loudness),
  speechiness = VALUES(speechiness),
  acousticness = VALUES(acousticness),
  instrumentalness = VALUES(instrumentalness),
  liveness = VALUES(liveness),
  valence = VALUES(valence),
  tempo = VALUES(tempo);

INSERT INTO TrackArtist (track_id, artist_id, role)
SELECT
  r.track_id,
  a.artist_id,
  'primary'
FROM RawTrackFeatures r
JOIN Artist a
  ON a.spotify_artist_id =
     TRIM(BOTH ' ' FROM
       REPLACE(REPLACE(REPLACE(
         SUBSTRING_INDEX(SUBSTRING_INDEX(r.artist_ids_raw, ',', 1), ']', 1),
       '[', ''), ']', ''), '''', '')
     )
WHERE r.track_id IS NOT NULL AND r.track_id <> ''
ON DUPLICATE KEY UPDATE role = VALUES(role);

INSERT INTO ArtistStats (
  artist_id,
  total_streams,
  num_tracks,
  num_billion_tracks,
  stats_date
)
SELECT
  a.artist_id,
  CAST(REPLACE(r.lead_streams, ',', '') AS UNSIGNED),
  CAST(REPLACE(r.tracks, ',', '') AS UNSIGNED),
  CAST(REPLACE(r.one_billion, ',', '') AS UNSIGNED),
  STR_TO_DATE(r.last_updated, '%d.%m.%y')
FROM RawArtistStats r
JOIN Artist a ON a.artist_name = r.artist_name
ON DUPLICATE KEY UPDATE
  total_streams = VALUES(total_streams),
  num_tracks = VALUES(num_tracks),
  num_billion_tracks = VALUES(num_billion_tracks),
  stats_date = VALUES(stats_date);


SELECT COUNT(*) AS albums FROM Album;
SELECT COUNT(*) AS artists FROM Artist;
SELECT COUNT(*) AS tracks FROM Track;
SELECT COUNT(*) AS trackartist_links FROM TrackArtist;
SELECT COUNT(*) AS artiststats FROM ArtistStats;

-- querying

-- params: (1) artist_name (2) min_year (3) max_year
SELECT
  ar.artist_name,
  al.album_name,
  al.release_year,
  t.track_id,
  t.track_name,
  t.explicit,
  t.danceability, t.energy, t.valence, t.tempo, t.loudness,
  t.duration_ms
FROM Artist ar
JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
JOIN Track t ON t.track_id = ta.track_id
JOIN Album al ON al.album_id = t.album_id
WHERE ar.artist_name = ?
  AND (al.release_year BETWEEN ? AND ? OR al.release_year IS NULL)
ORDER BY al.release_year DESC, al.album_name, t.track_number;

-- params: (1) min_total_streams (2) limit_n
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

-- params: (1) artist_name (2) min_tracks_per_album (3) limit_n
SELECT
  ar.artist_name,
  al.album_name,
  al.release_year,
  COUNT(*) AS track_count,
  ROUND(AVG(t.danceability), 3) AS avg_danceability
FROM Artist ar
JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
JOIN Track t ON t.track_id = ta.track_id
JOIN Album al ON al.album_id = t.album_id
WHERE ar.artist_name = ?
  AND t.danceability IS NOT NULL
GROUP BY ar.artist_name, al.album_id, al.album_name, al.release_year
HAVING COUNT(*) >= ?
ORDER BY avg_danceability DESC, track_count DESC
LIMIT ?;

-- params: (1) min_year (2) max_year (3) limit_n
SELECT
  al.release_year,
  ar.artist_name,
  t.track_id,
  t.track_name,
  t.danceability,
  t.energy,
  t.valence,
  t.tempo
FROM Track t
JOIN Album al ON al.album_id = t.album_id
JOIN TrackArtist ta ON ta.track_id = t.track_id
JOIN Artist ar ON ar.artist_id = ta.artist_id
WHERE al.release_year BETWEEN ? AND ?
  AND t.danceability IS NOT NULL
ORDER BY t.danceability DESC, t.energy DESC
LIMIT ?;

-- params: (1) min_year (2) max_year
SELECT
  al.release_year,
  COUNT(*) AS tracks,
  ROUND(AVG(t.danceability), 3) AS avg_danceability,
  ROUND(AVG(t.energy), 3) AS avg_energy,
  ROUND(AVG(t.valence), 3) AS avg_valence,
  ROUND(AVG(t.tempo), 2) AS avg_tempo
FROM Track t
JOIN Album al ON al.album_id = t.album_id
WHERE al.release_year BETWEEN ? AND ?
GROUP BY al.release_year
ORDER BY al.release_year;

-- params: (1) artist_name (2) limit_n
SELECT
  ar.artist_name,
  t.track_id,
  t.track_name,
  al.album_name,
  al.release_year,
  t.duration_ms,
  ROUND(t.duration_ms/60000, 2) AS duration_min
FROM Artist ar
JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
JOIN Track t ON t.track_id = ta.track_id
JOIN Album al ON al.album_id = t.album_id
WHERE ar.artist_name = ?
  AND t.duration_ms IS NOT NULL
ORDER BY t.duration_ms DESC
LIMIT ?;

-- params: (1) min_year (2) max_year
SELECT
  al.release_year,
  COUNT(*) AS total_tracks,
  SUM(CASE WHEN t.explicit = 1 THEN 1 ELSE 0 END) AS explicit_tracks,
  ROUND(100 * AVG(CASE WHEN t.explicit = 1 THEN 1 ELSE 0 END), 2) AS explicit_pct
FROM Track t
JOIN Album al ON al.album_id = t.album_id
WHERE al.release_year BETWEEN ? AND ?
GROUP BY al.release_year
ORDER BY al.release_year;

-- params: (1) min_year (2) max_year (3) min_instr (4) max_speech (5) min_tempo (6) max_tempo (7) limit_n
SELECT
  ar.artist_name,
  t.track_id,
  t.track_name,
  al.album_name,
  al.release_year,
  t.instrumentalness,
  t.speechiness,
  t.tempo
FROM Track t
JOIN Album al ON al.album_id = t.album_id
JOIN TrackArtist ta ON ta.track_id = t.track_id
JOIN Artist ar ON ar.artist_id = ta.artist_id
WHERE al.release_year BETWEEN ? AND ?
  AND t.instrumentalness IS NOT NULL AND t.instrumentalness >= ?
  AND t.speechiness IS NOT NULL AND t.speechiness <= ?
  AND t.tempo IS NOT NULL AND t.tempo BETWEEN ? AND ?
ORDER BY t.instrumentalness DESC, t.speechiness ASC, t.tempo ASC
LIMIT ?;

-- params: (1) min_tracks (2) limit_n
SELECT
  ar.artist_name,
  COUNT(*) AS track_count,
  ROUND(MIN(t.tempo), 2) AS min_tempo,
  ROUND(MAX(t.tempo), 2) AS max_tempo,
  ROUND(MAX(t.tempo) - MIN(t.tempo), 2) AS tempo_range
FROM Artist ar
JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
JOIN Track t ON t.track_id = ta.track_id
WHERE t.tempo IS NOT NULL
GROUP BY ar.artist_id, ar.artist_name
HAVING COUNT(*) >= ?
ORDER BY tempo_range DESC
LIMIT ?;

-- params: (1) min_tracks (2) limit_n
SELECT
  al.album_name,
  al.release_year,
  COUNT(*) AS tracks,
  ROUND(AVG(t.loudness), 2) AS avg_loudness
FROM Album al
JOIN Track t ON t.album_id = al.album_id
WHERE t.loudness IS NOT NULL
GROUP BY al.album_id, al.album_name, al.release_year
HAVING COUNT(*) >= ?
ORDER BY avg_loudness DESC, tracks DESC
LIMIT ?;

-- params: (1) min_billion_tracks (2) limit_n
SELECT
  ar.artist_name,
  s.num_billion_tracks,
  COUNT(ta.track_id) AS tracks_in_db
FROM ArtistStats s
JOIN Artist ar ON ar.artist_id = s.artist_id
LEFT JOIN TrackArtist ta ON ta.artist_id = ar.artist_id
WHERE s.num_billion_tracks IS NOT NULL
  AND s.num_billion_tracks >= ?
GROUP BY ar.artist_id, ar.artist_name, s.num_billion_tracks
ORDER BY s.num_billion_tracks DESC, tracks_in_db DESC
LIMIT ?;

-- params: (1) seed_track_id (2) limit_n
WITH seed AS (
  SELECT track_id, danceability, energy, valence, tempo
  FROM Track
  WHERE track_id = ?
)
SELECT
  t.track_id,
  t.track_name,
  ar.artist_name,
  al.album_name,
  al.release_year,
  (ABS(t.danceability - s.danceability)
   + ABS(t.energy - s.energy)
   + ABS(t.valence - s.valence)
   + (ABS(t.tempo - s.tempo) / 200.0)
  ) AS similarity_score
FROM seed s
JOIN Track t ON t.track_id <> s.track_id
JOIN Album al ON al.album_id = t.album_id
JOIN TrackArtist ta ON ta.track_id = t.track_id
JOIN Artist ar ON ar.artist_id = ta.artist_id
WHERE t.danceability IS NOT NULL AND t.energy IS NOT NULL AND t.valence IS NOT NULL AND t.tempo IS NOT NULL
ORDER BY similarity_score ASC
LIMIT ?;

-- params: (1) min_year (2) max_year (3) min_energy (4) max_valence (5) limit_n
SELECT
  ar.artist_name,
  t.track_id,
  t.track_name,
  al.album_name,
  al.release_year,
  t.energy,
  t.valence,
  t.loudness
FROM Track t
JOIN Album al ON al.album_id = t.album_id
JOIN TrackArtist ta ON ta.track_id = t.track_id
JOIN Artist ar ON ar.artist_id = ta.artist_id
WHERE al.release_year BETWEEN ? AND ?
  AND t.energy IS NOT NULL AND t.energy >= ?
  AND t.valence IS NOT NULL AND t.valence <= ?
ORDER BY t.energy DESC, t.valence ASC
LIMIT ?;

-- params: (1) min_year (2) max_year (3) top_k
WITH ranked AS (
  SELECT
    al.release_year,
    t.track_id,
    t.track_name,
    t.danceability,
    ar.artist_name,
    ROW_NUMBER() OVER (
      PARTITION BY al.release_year
      ORDER BY t.danceability DESC
    ) AS rn
  FROM Track t
  JOIN Album al ON al.album_id = t.album_id
  JOIN TrackArtist ta ON ta.track_id = t.track_id
  JOIN Artist ar ON ar.artist_id = ta.artist_id
  WHERE al.release_year BETWEEN ? AND ?
    AND t.danceability IS NOT NULL
)
SELECT
  release_year, rn,
  artist_name, track_name, danceability
FROM ranked
WHERE rn <= ?
ORDER BY release_year, rn;

-- params: none
SELECT
  t.explicit,
  COUNT(*) AS tracks,
  ROUND(AVG(t.danceability), 3) AS avg_danceability,
  ROUND(AVG(t.energy), 3) AS avg_energy,
  ROUND(AVG(t.valence), 3) AS avg_valence,
  ROUND(AVG(t.tempo), 2) AS avg_tempo
FROM Track t
GROUP BY t.explicit
ORDER BY t.explicit;
