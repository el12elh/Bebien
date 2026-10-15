# Application Tournoi de Rugby (M8 / M10 / M12)

Application PHP + MySQL "clé en main" pour gérer un tournoi de rugby jeunes sur une journée :
équipes, tirage des poules du matin, calendrier & terrains automatiques, saisie des scores
(admin + interface tdm mobile), reclassement de l'après-midi (tableaux de niveau),
classement général configurable, **classement final des clubs (challenge)**, **serial marqueur**,
espace public en temps réel, **export PDF** et bandeau sponsors.

## 1. Prérequis

- PHP 8.1+ avec l'extension PDO MySQL
- MySQL / MariaDB
- Un hébergement mutualisé classique suffit (pas de framework, pas de dépendances Composer :
  le PDF est généré par la classe maison `includes/SimplePdf.php`)

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

1. **Catégories** : vérifiez/ajustez M8, M10, M12 (nombre de poules du matin et de l'après-midi,
   coefficient utilisé pour le classement des clubs).
2. **Équipes** : saisissez toutes les équipes engagées par catégorie et indiquez si chacune est
   tête de série (Oui / Non), ou faites-le saisir en amont par les clubs si vous branchez un
   formulaire, non inclus par défaut.
3. **Terrains** (menu *Planning & terrains*) : renseignez vos terrains disponibles.
4. **Paramètres** : ajustez le nom et la date du tournoi, le barème de points, l'ordre de départage,
   la durée des matchs et les horaires de début, **en stricte conformité avec votre dossier sportif**.
5. **Poules du matin** : lancez le tirage au sort automatique par catégorie. Le calendrier
   des matchs (tous les matchs de poule) et l'attribution terrains/horaires sont générés
   instantanément.
   > **Sécurité** : une fois tous les matchs du matin terminés, le bouton *Lancer le tirage au sort*
   > est masqué (et le tirage refusé côté serveur). Il ne réapparaît que lorsque tous les matchs
   > de l'après-midi de la catégorie sont terminés. Cela évite d'écraser des résultats par erreur.
6. **Saisie des scores** : au fil de la matinée, les responsables de tables de marque se connectent
   sur `/tdm/` depuis un smartphone pour saisir les scores en direct (essais, cartons jaunes et
   rouges, comptes créés dans le menu *Table de marque*). L'admin peut aussi tout saisir depuis
   *Matchs & scores*.
7. **Poules de l'après-midi** : une fois les poules du matin terminées, ouvrez
   *Poules de l'après-midi*, nommez vos tableaux de niveau (ex : "1 Cup", "2 Plat", "3 Bowl",
   "4 Shield") et générez. Les équipes sont réparties **automatiquement et équitablement** selon leur
   rang au classement général du matin (nombre de tableaux défini par catégorie). Calendrier +
   terrains sont recalculés automatiquement.
8. **Espace public** : à projeter sur un écran ou à partager aux parents/coachs (QR code).
   Résultats, classements et prochains matchs s'actualisent automatiquement (rafraîchissement
   toutes les 30 s dès qu'un match est en direct).
9. **Sponsors** : ajoutez vos logos partenaires dans le menu *Sponsors* — ils s'affichent en
   bandeau défilant sur toutes les pages publiques.
10. **Export PDF** : choisissez la catégorie, la phase et le contenu, puis téléchargez ou imprimez
    le PDF (voir §5).

## 4. Classements

### Classement des poules (page *Classements > Poules*)

- **Matin** : un classement général par catégorie. Les lignes sont colorées par **zone**, c'est-à-dire
  par tableau de destination de l'après-midi (les équipes sont réparties en autant de zones que de
  tableaux).
- **Après-midi** : un classement par tableau, chacun avec sa couleur.
- Colonnes : points, joués, victoires / nuls / défaites, cartons jaunes et rouges, points pour,
  points contre, différence.

### Classement final des clubs (challenge)

- Toutes catégories confondues, d'après les résultats **de l'après-midi**.
- Dans chaque catégorie, une équipe d'un tableau supérieur est toujours classée devant une équipe
  d'un tableau inférieur. **Seule la meilleure équipe de chaque club est prise en compte.**
- La 1<sup>re</sup> place rapporte 1 point, la 2<sup>e</sup> 2 points, etc., **multipliés par le
  coefficient de la catégorie**. Le club avec le **moins** de points est premier.
- En cas d'égalité : nombre d'essais marqués l'après-midi.
- Le classement est marqué **provisoire** tant que tous les matchs de l'après-midi ne sont pas terminés.

### Serial marqueur

- Classement des clubs qui ont marqué le plus d'essais, toutes catégories et tous matchs confondus
  (matin et après-midi). Affiche aussi le nombre de matchs et la moyenne d'essais par match.
- À égalité d'essais, le club qui a joué le moins de matchs passe devant.

## 5. Export PDF

La page *Export PDF* propose les filtres suivants, puis deux boutons :

| Filtre | Valeurs |
|---|---|
| Catégorie | Toutes, ou une catégorie |
| Phase | Matin et après-midi, Matin, Après-midi |
| Contenu | Poules, Matchs (état / score), Classements, Challenge clubs |

- **Télécharger le PDF** : télécharge le fichier avec les filtres choisis.
- **Imprimer** : ouvre le même PDF dans un nouvel onglet pour l'imprimer depuis le navigateur.
- Le PDF contient une page par catégorie et par phase, avec les zones colorées du classement du
  matin. La dernière page regroupe le classement final des clubs (médailles pour le podium,
  coefficients par catégorie) et le serial marqueur.

L'export peut aussi être appelé directement :
`export_pdf?go=1&category_id=0&phase=&poules=1&matchs=1&classements=1&challenge=1`
(ajouter `&inline=1` pour l'ouvrir dans le navigateur au lieu de le télécharger).

## 6. Barème de points & départage

Le barème par défaut (4 pts victoire / 2 pts nul / 1 pt défaite / -2 forfait, sans bonus) est
purement indicatif. Modifiez-le dans **Admin > Paramètres** pour qu'il corresponde exactement
à votre dossier sportif (points, bonus offensif/défensif, ordre des critères de départage :
points, différence, points marqués, confrontation directe, tirage au sort).

## 7. Structure du projet

```
config                   Connexion base de données
schema.sql               Structure de la base + valeurs par défaut
create_admin             Script de création du 1er compte admin
includes/                Logique métier (tournament), auth, DB, layout, SimplePdf (générateur PDF)
admin/                   Back-office (équipes, poules, matchs, planning, sponsors, réglages)
tdm/                     Interface table de marque (mobile)
standings.php            Classements publics (poules, clubs, serial marqueur)
export.php               Page de choix des filtres de l'export PDF
export_pdf.php           Génération / téléchargement du PDF
uploads/                 Logos équipes & sponsors (à rendre accessible en écriture)
assets/                  CSS
```

## 8. Limites connues / pistes d'évolution

- Le classement "confrontation directe" ne gère que le cas de deux équipes ex-aequo
  (pas les égalités à 3 équipes ou plus, à départager manuellement si besoin).
- Pas de gestion de forfait automatique (à saisir manuellement en mettant un score et le
  statut "Terminé").
- Pas d'import CSV des équipes : ajout un par un via le formulaire (facile à ajouter si besoin).
- Le bandeau sponsors et les espaces publicitaires sont volontairement simples (logos +
  lien cliquable) ; des emplacements "Partenaire du match" pourraient être ajoutés en
  associant un sponsor à un match spécifique.

## 9. Évolutions récentes

- Couleurs de zone dans le classement général du matin (page publique et PDF).
- Classement final des clubs (challenge) et serial marqueur, même style que la page *Classements*.
- Export PDF : choix des filtres puis téléchargement ou impression, sans aperçu à l'écran.
- Verrouillage du tirage au sort du matin entre la fin des matchs du matin et la fin de ceux de
  l'après-midi.