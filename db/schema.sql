-- ============================================================
-- Application Tournoi Rugby - Schéma de base de données
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS pool_teams;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS pools;
DROP TABLE IF EXISTS clubs;
DROP TABLE IF EXISTS fields;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS sponsors;
DROP TABLE IF EXISTS settings;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(20) NOT NULL,          -- M8, M10, M12...
  nb_poules_matin INT DEFAULT 2,
  nb_poules_aprem INT DEFAULT 2,
  nb_mi_temps INT NOT NULL DEFAULT 2,       -- nombre de mi-temps / périodes par match
  duree_mi_temps INT NOT NULL DEFAULT 7,    -- durée d'une mi-temps (minutes)
  pause_minutes INT NOT NULL DEFAULT 3,     -- pause entre deux matchs (minutes)
  coef_classement INT NOT NULL DEFAULT 1,   -- coefficient dans le classement final des clubs
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clubs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom_club VARCHAR(100) NOT NULL,
  logo_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  club_id INT NOT NULL,
  nom_equipe VARCHAR(50) DEFAULT NULL,   -- ex: "Club X # 1" si plusieurs équipes/club
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  nom VARCHAR(50) NOT NULL,          -- Terrain 1, Terrain 2...
  actif TINYINT(1) DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  nom VARCHAR(100) NOT NULL,          -- "Poule A", "1 Cup"...
  phase ENUM('matin','aprem') NOT NULL DEFAULT 'matin',
  ordre_niveau INT DEFAULT 0,         -- 0 = plus haut niveau, pour trier l'affichage
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pool_teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pool_id INT NOT NULL,
  team_id INT NOT NULL,
  UNIQUE KEY uniq_pool_team (pool_id, team_id),
  FOREIGN KEY (pool_id) REFERENCES pools(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  pool_id INT NOT NULL,
  phase ENUM('matin','aprem') NOT NULL DEFAULT 'matin',
  round_number INT DEFAULT 1,
  team1_id INT NOT NULL,
  team2_id INT NOT NULL,
  field_id INT DEFAULT NULL,
  scheduled_at DATETIME DEFAULT NULL,
  status ENUM('Programmé','Live','Terminé') DEFAULT 'Programmé',
  score1 INT DEFAULT 0,
  score2 INT DEFAULT 0,
  essais1 INT DEFAULT 0,
  essais2 INT DEFAULT 0,
  cartons_jaunes1 INT DEFAULT 0,
  cartons_jaunes2 INT DEFAULT 0,
  cartons_rouges1 INT DEFAULT 0,
  cartons_rouges2 INT DEFAULT 0,
  updated_by VARCHAR(100) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (pool_id) REFERENCES pools(id) ON DELETE CASCADE,
  FOREIGN KEY (team1_id) REFERENCES teams(id),
  FOREIGN KEY (team2_id) REFERENCES teams(id),
  FOREIGN KEY (field_id) REFERENCES fields(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  role ENUM('admin','tdm') NOT NULL DEFAULT 'tdm',
  nom_affichage VARCHAR(100) DEFAULT NULL,
  field_id INT DEFAULT NULL, -- terrain assigné pour table de marque, si applicable
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (field_id) REFERENCES fields(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sponsors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  logo_path VARCHAR(255) NOT NULL,
  lien_url VARCHAR(255) DEFAULT NULL,
  ordre_affichage INT DEFAULT 0,
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) PRIMARY KEY,
  v TEXT
) ENGINE=InnoDB;

-- Paramètres par défaut du barème de points (modifiable dans l'admin,
-- à ajuster précisément selon votre dossier sportif officiel)
INSERT INTO settings (k, v) VALUES
  ('points_victoire', '4'),
  ('points_nul', '2'),
  ('points_defaite', '0'),
  ('points_forfait', '0'),
  ('bonus_offensif_actif', '0'),        -- 1 = activer un point bonus si écart d'essais >= seuil
  ('bonus_offensif_seuil_essais', '3'),
  ('bonus_offensif_points', '1'),
  ('bonus_defensif_actif', '0'),        -- 1 = point bonus si défaite avec écart <= seuil
  ('bonus_defensif_seuil_ecart', '5'),
  ('bonus_defensif_points', '1'),
  ('ordre_departage', 'points,points_cartons,essais_pour,diff_essais,tirage_sort'),
  ('classement_final_mode', 'rang'),   -- 'rang' = place finale par catégorie, 'points' = points de l'après-midi
  ('heure_debut_matin', '10:00'),
  ('heure_debut_aprem', '14:00'),
  ('nom_tournoi', 'Challenge Robert Bébien'),
  ('edition_tournoi', '26'),
  ('lieu_tournoi', 'Stade Albaladéjo'),
  ('ville_tournoi', 'Joué-lès-Tours'),
  ('date_tournoi', '2026-10-18'),
  ('logo_tournoi_path', 'uploads/logo_tournoi.png')
ON DUPLICATE KEY UPDATE v = v;

-- Catégories par défaut
-- M8 : 2 mi-temps de 7 min, M10/M12 : 1 période continue de 14 min. Coefficients : M8 x1, M10 x2, M12 x3.
INSERT INTO categories (nom, nb_mi_temps, duree_mi_temps, pause_minutes, coef_classement) VALUES
  ('M8', 2, 7, 3, 1),
  ('M10', 1, 14, 3, 2),
  ('M12', 1, 14, 3, 3);

SET FOREIGN_KEY_CHECKS = 1;
