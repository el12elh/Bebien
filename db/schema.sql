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
  nom VARCHAR(100) NOT NULL,          -- "Poule A", "1 - Cup"...
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
  niveau ENUM('or','argent','bronze','partenaire') DEFAULT 'partenaire',
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
  ('points_victoire', '3'),
  ('points_nul', '2'),
  ('points_defaite', '1'),
  ('points_forfait', '0'),
  ('bonus_offensif_actif', '0'),        -- 1 = activer un point bonus si écart d'essais >= seuil
  ('bonus_offensif_seuil_essais', '3'),
  ('bonus_offensif_points', '1'),
  ('bonus_defensif_actif', '0'),        -- 1 = point bonus si défaite avec écart <= seuil
  ('bonus_defensif_seuil_ecart', '5'),
  ('bonus_defensif_points', '1'),
  ('ordre_departage', 'points,points_cartons,essais_pour,diff_essais,tirage_sort'),
  ('duree_match_minutes', '12'),
  ('duree_periode_minutes', '7'),
  ('nb_mi_temps', '2'),
  ('pause_entre_matchs_minutes', '3'),
  ('heure_debut_matin', '09:30'),
  ('heure_debut_aprem', '13:30'),
  ('nom_tournoi', 'Challenge Bébien 2026'),
  ('lieu_tournoi', 'Complexe Stade Pierre Albaladéjo'),
  ('date_tournoi', '2026-10-18'),
  ('logo_tournoi_path', 'uploads/logo_tournoi.png')
ON DUPLICATE KEY UPDATE v = v;

-- Terrains par défaut (modifiable ensuite)
INSERT INTO fields (nom) VALUES ('Terrain 1'), ('Terrain 2');

-- Catégories par défaut
INSERT INTO categories (nom) VALUES ('M8'), ('M10'), ('M12');

SET FOREIGN_KEY_CHECKS = 1;
