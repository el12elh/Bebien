# Application Tournoi de Rugby (M8 / M10 / M12)

Application PHP + MySQL "clé en main" pour gérer un tournoi de rugby jeunes sur une journée :
équipes, tirage des poules du matin, calendrier & terrains automatiques, saisie des scores
(admin + interface tdm mobile), reclassement de l'après-midi (tableaux de niveau),
classement général configurable, espace public en temps réel, et bandeau sponsors.

## 1. Prérequis

- PHP 8.1+ avec l'extension PDO MySQL
- MySQL / MariaDB
- Un hébergement mutualisé classique suffit (pas de framework, pas de dépendances Composer)

## 2. Installation

1. Copiez tout le dossier sur votre serveur (ou en local avec MAMP/XAMPP/Laragon...).
2. Créez une base de données MySQL, puis importez `schema.sql` :
   ```
   mysql -u root -p rugby_tournoi < schema.sql
   ```
   (créez d'abord la base avec `CREATE DATABASE rugby_tournoi CHARACTER SET utf8mb4;`)
3. Ouvrez `config` et renseignez vos identifiants de connexion à la base.
4. Créez votre premier compte administrateur
5. Connectez-vous sur `/admin/signin`.
6. Assurez-vous que le dossier `uploads/` est accessible en écriture par le serveur web
   (`chmod -R 775 uploads`).

## 3. Utilisation le jour du tournoi

1. **Catégories** : vérifiez/ajustez M8, M10, M12 (nombre de poules prévues).
2. **Équipes** : saisissez toutes les équipes engagées par catégorie (ou faites-le saisir
   en amont par les clubs si vous branchez un formulaire, non inclus par défaut).
3. **Terrains** (menu *Planning & terrains*) : renseignez vos terrains disponibles.
4. **Paramètres** : ajustez le barème de points, l'ordre de départage, la durée des matchs
   et les horaires de début, **en stricte conformité avec votre dossier sportif**.
5. **Poules du matin** : lancez le tirage au sort automatique par catégorie. Le calendrier
   des matchs (tous les matchs de poule) et l'attribution terrains/horaires sont générés
   instantanément.
6. **Saisie des scores** : au fil de la matinée, les responsable de tables de marque se connectent
   sur `/tdm/` depuis un smartphone pour saisir les scores en direct
   (comptes créés dans le menu *Table de marque*). L'admin peut aussi tout saisir depuis
   *Matchs & scores*.
7. **Poules de l'après-midi** : une fois les poules du matin terminées, ouvrez
   *Poules de l'après-midi*, définissez vos tableaux de niveau (ex: "Poule des As" = rangs 1-2,
   "Poule de classement" = rangs 3-4...) et générez. Calendrier + terrains sont recalculés
   automatiquement.
8. **Espace public** (`/public/`) : à projeter sur un écran ou à partager aux
   parents/coachs (QR code). Résultats, classements et prochains matchs s'actualisent
   automatiquement (rafraîchissement toutes les 30s).
9. **Sponsors** : ajoutez vos logos partenaires dans le menu *Sponsors* — ils s'affichent en
   bandeau défilant sur toutes les pages publiques.

## 4. Barème de points & départage

Le barème par défaut (4 pts victoire / 2 pts nul / 1 pt défaite / -2 forfait, sans bonus) est
purement indicatif. Modifiez-le dans **Admin > Paramètres** pour qu'il corresponde exactement
à votre dossier sportif (points, bonus offensif/défensif, ordre des critères de départage :
points, différence, points marqués, confrontation directe, tirage au sort).

## 5. Structure du projet

```
config                   Connexion base de données
schema.sql               Structure de la base + valeurs par défaut
create_admin             Script de création du 1er compte admin
includes/                Logique métier (tournament), auth, DB, layout
admin/                   Back-office (équipes, poules, matchs, planning, sponsors, réglages)
tdm/                     Interface table de marque (mobile)
public/                  Espace public (accueil, calendrier, classements)
uploads/                 Logos équipes & sponsors (à rendre accessible en écriture)
assets/                  CSS
```

## 6. Limites connues / pistes d'évolution

- Le classement "confrontation directe" ne gère que le cas de deux équipes ex-aequo
  (pas les égalités à 3 équipes ou plus, à départager manuellement si besoin).
- Pas de gestion de forfait automatique (à saisir manuellement en mettant un score et le
  statut "Terminé").
- Pas d'import CSV des équipes : ajout un par un via le formulaire (facile à ajouter si besoin).
- Le bandeau sponsors et les espaces publicitaires sont volontairement simples (logos +
  lien cliquable) ; des emplacements "Partenaire du match" pourraient être ajoutés en
  associant un sponsor à un match spécifique.
