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
 

-- ─── Lichaamsgewicht metingen ────────────────────────────
CREATE TABLE IF NOT EXISTS body_weight (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    gewicht_kg  DECIMAL(5,2) NOT NULL,
    gemeten_op  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── Doelen (één rij per user) ───────────────────────────
CREATE TABLE IF NOT EXISTS goals (
    user_id          INT PRIMARY KEY,
    weekly_sessions  INT NULL,
    weekly_minutes   INT NULL,
    target_weight_kg DECIMAL(5,2) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- ════════════════════════════════════════════════════════════
-- TESTDATA
-- ════════════════════════════════════════════════════════════

SET @user_id := (SELECT id FROM users WHERE username = 'Arne' LIMIT 1);

DELETE FROM trainings   WHERE user_id = @user_id;
DELETE FROM body_weight WHERE user_id = @user_id;

INSERT INTO goals (user_id, weekly_sessions, weekly_minutes, target_weight_kg)
VALUES (@user_id, 4, 240, 75.00)
ON DUPLICATE KEY UPDATE
    weekly_sessions = 4,
    weekly_minutes = 240,
    target_weight_kg = 75.00;

-- WEEK 8 terug
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 56 DAY, 'Lopen', 35, 320, 'midden', 'Rustige opstart');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 54 DAY, 'Fietsen', 60, 480, 'midden', 'Mooi weer');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 52 DAY, 'Krachttraining', 75, 'Borst en triceps');
SET @t1 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t1, 'Bench Press',    4, 10, 60.0, 0),
    (@t1, 'Incline Press',  3, 12, 22.5, 1),
    (@t1, 'Tricep Pushdown',3, 15, 35.0, 2);

-- WEEK 7
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 49 DAY, 'Lopen', 40, 380, 'midden', 'Beter tempo');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 47 DAY, 'Voetbal', 90, 'hoog', 'Match gewonnen 3-1');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 45 DAY, 'Krachttraining', 70, 'Rug en biceps');
SET @t2 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t2, 'Pull-ups',        4,  8, 0.0,  0),
    (@t2, 'Bent Over Row',   4, 10, 50.0, 1),
    (@t2, 'Bicep Curls',     3, 12, 14.0, 2);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 43 DAY, 'Fietsen', 75, 600, 'hoog', 'Heuvel-rit');

-- WEEK 6
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 42 DAY, 'Lopen', 45, 430, 'hoog', '8 km gelopen');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 40 DAY, 'Krachttraining', 80, 'Benen dag');
SET @t3 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t3, 'Squats',          5,  8, 80.0, 0),
    (@t3, 'Leg Press',       4, 12, 120.0,1),
    (@t3, 'Lunges',          3, 10, 20.0, 2),
    (@t3, 'Calf Raises',     4, 15, 60.0, 3);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 38 DAY, 'Tennis', 60, 'midden', 'Met vriend gespeeld');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit)
VALUES (@user_id, CURDATE() - INTERVAL 36 DAY, 'Zwemmen', 45, 350, 'midden');

-- WEEK 5
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 35 DAY, 'Lopen', 40, 400, 'midden', 'Lekker gelopen');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 33 DAY, 'Krachttraining', 75, 'Borst en triceps');
SET @t4 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t4, 'Bench Press',    4, 10, 62.5, 0),
    (@t4, 'Incline Press',  3, 12, 25.0, 1),
    (@t4, 'Tricep Pushdown',3, 15, 37.5, 2);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 31 DAY, 'Voetbal', 95, 'hoog', 'Training en match');

-- WEEK 4
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 28 DAY, 'Fietsen', 90, 720, 'hoog', 'Lange tocht');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 26 DAY, 'Krachttraining', 65, 'Rug en biceps');
SET @t5 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t5, 'Pull-ups',        4,  9, 0.0,  0),
    (@t5, 'Bent Over Row',   4, 10, 55.0, 1),
    (@t5, 'Bicep Curls',     3, 12, 16.0, 2);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 24 DAY, 'Lopen', 50, 500, 'hoog', '10 km doel gehaald');

-- WEEK 3
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 21 DAY, 'Lopen', 35, 340, 'midden', 'Herstellopje');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 19 DAY, 'Krachttraining', 85, 'Benen dag');
SET @t6 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t6, 'Squats',          5,  8, 85.0, 0),
    (@t6, 'Leg Press',       4, 12, 130.0,1),
    (@t6, 'Lunges',          3, 10, 22.5, 2),
    (@t6, 'Calf Raises',     4, 15, 65.0, 3);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 17 DAY, 'Tennis', 75, 'midden', 'Goed gespeeld');

-- WEEK 2
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 14 DAY, 'Fietsen', 65, 520, 'midden', 'Korte rit');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 12 DAY, 'Krachttraining', 80, 'Borst en triceps - PR!');
SET @t7 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t7, 'Bench Press',    4, 10, 65.0, 0),
    (@t7, 'Incline Press',  3, 12, 27.5, 1),
    (@t7, 'Tricep Pushdown',3, 15, 40.0, 2);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 10 DAY, 'Lopen', 45, 450, 'hoog', 'Lekker getraind');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 8 DAY, 'Voetbal', 90, 'hoog', 'Wedstrijd');

-- WEEK 1
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 7 DAY, 'Krachttraining', 70, 'Rug en biceps');
SET @t8 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t8, 'Pull-ups',        4, 10, 0.0,  0),
    (@t8, 'Bent Over Row',   4, 10, 57.5, 1),
    (@t8, 'Bicep Curls',     3, 12, 17.5, 2);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 5 DAY, 'Lopen', 40, 410, 'midden', 'Stevig tempo');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 3 DAY, 'Fietsen', 70, 560, 'midden', 'Mooie tocht');

-- DEZE WEEK
INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 2 DAY, 'Krachttraining', 85, 'Benen dag - nieuwe PR squats!');
SET @t9 := LAST_INSERT_ID();
INSERT INTO training_oefeningen (training_id, naam, sets, reps, gewicht_kg, volgorde) VALUES
    (@t9, 'Squats',          5,  8, 90.0, 0),
    (@t9, 'Leg Press',       4, 12, 140.0,1),
    (@t9, 'Lunges',          3, 10, 25.0, 2),
    (@t9, 'Calf Raises',     4, 15, 70.0, 3);

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE() - INTERVAL 1 DAY, 'Lopen', 45, 460, 'hoog', 'Goed gevoeld');

INSERT INTO trainings (user_id, datum, workout_type, duur_minuten, calorieen, intensiteit, notitie)
VALUES (@user_id, CURDATE(), 'Zwemmen', 50, 400, 'midden', 'Verfrissende sessie');

-- GEWICHT METINGEN
INSERT INTO body_weight (user_id, gewicht_kg, gemeten_op) VALUES
    (@user_id, 79.5, NOW() - INTERVAL 56 DAY),
    (@user_id, 79.2, NOW() - INTERVAL 53 DAY),
    (@user_id, 79.4, NOW() - INTERVAL 49 DAY),
    (@user_id, 78.8, NOW() - INTERVAL 45 DAY),
    (@user_id, 78.5, NOW() - INTERVAL 42 DAY),
    (@user_id, 78.7, NOW() - INTERVAL 38 DAY),
    (@user_id, 78.2, NOW() - INTERVAL 35 DAY),
    (@user_id, 78.0, NOW() - INTERVAL 31 DAY),
    (@user_id, 77.8, NOW() - INTERVAL 28 DAY),
    (@user_id, 77.9, NOW() - INTERVAL 24 DAY),
    (@user_id, 77.4, NOW() - INTERVAL 21 DAY),
    (@user_id, 77.2, NOW() - INTERVAL 17 DAY),
    (@user_id, 77.5, NOW() - INTERVAL 14 DAY),
    (@user_id, 77.0, NOW() - INTERVAL 10 DAY),
    (@user_id, 76.8, NOW() - INTERVAL 7  DAY),
    (@user_id, 76.5, NOW() - INTERVAL 4  DAY),
    (@user_id, 76.3, NOW() - INTERVAL 1  DAY);