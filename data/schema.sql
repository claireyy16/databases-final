CREATE DATABASE IF NOT EXISTS spotify_db;
USE spotify_db;

DROP TABLE IF EXISTS TrackArtist;
DROP TABLE IF EXISTS Track;
DROP TABLE IF EXISTS ArtistStats;
DROP TABLE IF EXISTS Album;
DROP TABLE IF EXISTS Artist;

-- Artist
CREATE TABLE Artist (
  artist_id         INT AUTO_INCREMENT PRIMARY KEY,
  artist_name       VARCHAR(255) NOT NULL,
  spotify_artist_id VARCHAR(64) NULL,
  CONSTRAINT uq_artist_spotify_artist_id UNIQUE (spotify_artist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Album
CREATE TABLE Album (
  album_id         INT AUTO_INCREMENT PRIMARY KEY,
  spotify_album_id VARCHAR(64) NULL,
  album_name       VARCHAR(255) NOT NULL,
  release_year     INT NULL,
  album_type       VARCHAR(50) NULL,
  CONSTRAINT uq_album_spotify_album_id UNIQUE (spotify_album_id),
  CONSTRAINT chk_album_year
    CHECK (release_year IS NULL OR (release_year BETWEEN 1900 AND 2100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Track
CREATE TABLE Track (
  track_id           VARCHAR(50) PRIMARY KEY,   -- Kaggle track id
  album_id           INT NOT NULL,
  track_name         VARCHAR(255) NOT NULL,
  track_number       INT NULL,
  duration_ms        INT NULL,
  explicit           TINYINT(1) NOT NULL,        -- 0/1

  danceability       DECIMAL(3,2) NULL,
  energy             DECIMAL(3,2) NULL,
  loudness           DECIMAL(5,2) NULL,
  speechiness        DECIMAL(3,2) NULL,
  acousticness       DECIMAL(3,2) NULL,
  instrumentalness   DECIMAL(3,2) NULL,
  liveness           DECIMAL(3,2) NULL,
  valence            DECIMAL(3,2) NULL,
  tempo              DECIMAL(6,2) NULL,
  popularity         INT NULL,

  CONSTRAINT fk_track_album
    FOREIGN KEY (album_id) REFERENCES Album(album_id),

  CONSTRAINT chk_explicit CHECK (explicit IN (0,1)),
  CONSTRAINT chk_popularity CHECK (popularity IS NULL OR popularity BETWEEN 0 AND 100),

  CONSTRAINT chk_danceability CHECK (danceability IS NULL OR (danceability BETWEEN 0 AND 1)),
  CONSTRAINT chk_energy       CHECK (energy IS NULL OR (energy BETWEEN 0 AND 1)),
  CONSTRAINT chk_speechiness  CHECK (speechiness IS NULL OR (speechiness BETWEEN 0 AND 1)),
  CONSTRAINT chk_acousticness CHECK (acousticness IS NULL OR (acousticness BETWEEN 0 AND 1)),
  CONSTRAINT chk_instrumental CHECK (instrumentalness IS NULL OR (instrumentalness BETWEEN 0 AND 1)),
  CONSTRAINT chk_liveness     CHECK (liveness IS NULL OR (liveness BETWEEN 0 AND 1)),
  CONSTRAINT chk_valence      CHECK (valence IS NULL OR (valence BETWEEN 0 AND 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TrackArtist 

CREATE TABLE TrackArtist (
  track_id   VARCHAR(50) NOT NULL,
  artist_id  INT NOT NULL,
  role       VARCHAR(20) DEFAULT 'primary',
  PRIMARY KEY (track_id, artist_id),
  FOREIGN KEY (track_id) REFERENCES Track(track_id) ON DELETE CASCADE,
  FOREIGN KEY (artist_id) REFERENCES Artist(artist_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ArtistStats
CREATE TABLE ArtistStats (
  artist_id           INT PRIMARY KEY,
  total_streams       BIGINT NULL,
  monthly_listeners   BIGINT NULL,
  followers           BIGINT NULL,
  world_rank          INT NULL,
  num_albums          INT NULL,
  num_tracks          INT NULL,
  num_features        INT NULL,
  primary_genre       VARCHAR(100) NULL,
  secondary_genre     VARCHAR(100) NULL,
  stats_date          DATE NULL,
  FOREIGN KEY (artist_id) REFERENCES Artist(artist_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_track_album ON Track(album_id);
CREATE INDEX idx_track_explicit ON Track(explicit);
CREATE INDEX idx_track_danceability ON Track(danceability);
CREATE INDEX idx_artiststats_streams ON ArtistStats(total_streams);
CREATE INDEX idx_album_year ON Album(release_year);
