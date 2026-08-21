<?php
/**
 * Configuration - à adapter à votre hébergement.
 * Ne pas versionner ce fichier avec vos vrais identifiants en public.
 */
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Chemin absolu du projet (pour les uploads de logos sponsors/équipes)
define('BASE_PATH', __DIR__);
define('BASE_URL', '/bebien'); // ex: '/rugby-tournoi' si l'app n'est pas à la racine du domaine
