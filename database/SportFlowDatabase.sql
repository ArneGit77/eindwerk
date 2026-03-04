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