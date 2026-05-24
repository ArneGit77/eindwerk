CREATE DATABASE sportflow;
USE sportflow;

-- loginscherm
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) NOT NULL,
password VARCHAR(255) NOT NULL
);

-- trainingen
CREATE TABLE trainings (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT,
datum DATE NOT NULL,
workout_type VARCHAR(100),
duur_minuten INT,
FOREIGN KEY (user_id) REFERENCES users(id)
);

-- stats
CREATE TABLE IF NOT EXISTS system_stats (
id INT AUTO_INCREMENT PRIMARY KEY,
cpu_temp DECIMAL(5,2),      -- Temperatuur in graden Celsius
cpu_usage INT,              -- Belasting in %
ram_usage_mb INT,           -- Gebruikt geheugen in MB
disk_free_gb DECIMAL(5,2),  -- Vrije ruimte op SD-kaart in GB
db_size_mb DECIMAL(5,2),    -- Totale grootte van je database in MB
total_trainings INT,        -- Totaal aantal opgeslagen trainingen
uptime_days INT,            -- Aantal dagen dat de Pi online is
measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Database gebruiker voor de website
CREATE USER 'sportflow_user'@'localhost' IDENTIFIED BY 'SportFlow2026!';
GRANT ALL PRIVILEGES ON sportflow.* TO 'sportflow_user'@'localhost';
FLUSH PRIVILEGES;

-- ─── Nieuwe kolommen aan trainings tabel ─────────────────
ALTER TABLE trainings
    ADD COLUMN afstand_km    DECIMAL(6,2) NULL AFTER duur_minuten,
    ADD COLUMN sets          INT NULL AFTER afstand_km,
    ADD COLUMN reps          INT NULL AFTER sets,
    ADD COLUMN gewicht_kg    DECIMAL(6,2) NULL AFTER reps,
    ADD COLUMN oefeningen    TEXT NULL AFTER gewicht_kg,
    ADD COLUMN calorieen     INT NULL AFTER oefeningen,
    ADD COLUMN intensiteit   ENUM('laag', 'midden', 'hoog') NULL AFTER calorieen,
    ADD COLUMN notitie       TEXT NULL AFTER intensiteit;
 
-- ─── Workout types tabel ─────────────────────────────────
CREATE TABLE IF NOT EXISTS workout_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naam VARCHAR(50) NOT NULL UNIQUE,
    categorie ENUM('kracht', 'cardio', 'team', 'anders') NOT NULL
);
 
-- ─── Workout types invoegen ──────────────────────────────
INSERT IGNORE INTO workout_types (naam, categorie) VALUES
    ('Krachttraining',  'kracht'),
    ('Lopen',           'cardio'),
    ('Fietsen',         'cardio'),
    ('Zwemmen',         'cardio'),
    ('Wandelen',        'cardio'),
    ('Roeien',          'cardio'),
    ('Voetbal',         'team'),
    ('Basketbal',       'team'),
    ('Volleybal',       'team'),
    ('Handbal',         'team'),
    ('Hockey',          'team'),
    ('Rugby',           'team'),
    ('Tennis',          'team'),
    ('Padel',           'team'),
    ('Badminton',       'team'),
    ('Tafeltennis',     'team'),
    ('Andere',          'anders');
 
 
 CREATE TABLE IF NOT EXISTS training_oefeningen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    naam        VARCHAR(100) NOT NULL,
    sets        INT NULL,
    reps        INT NULL,
    gewicht_kg  DECIMAL(6,2) NULL,
    volgorde    INT NOT NULL DEFAULT 0,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
);
 