<?php
require_once __DIR__ . '/includes/auth.php';

$dateTournoi = getSetting('date_tournoi', '');
if ($dateTournoi) {
    $tournamentDeadline = new DateTime($dateTournoi . ' 13:00');
    $phase = (new DateTime() > $tournamentDeadline) ? 'aprem' : 'matin';
}

if ($dateTournoi) {
    $anneeTournoi = (int) (new DateTime($dateTournoi))->format('Y');
    $anneeNaissanceMin = $anneeTournoi - 6;
    $anneeNaissanceMax = $anneeTournoi - 1;
}

$pageTitle = 'Règlement';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/public_nav.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <h2 class="mb-3">Règlement</h2>
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="mb-4">Règlement sportif</h5>
          <h6><strong>HORAIRES</strong></h6>
          <p>
            Les équipes devront être présentes sur le terrain attribué
            <strong>5 minutes avant le début des rencontres</strong>.
          </p>
          <h6><strong>ÂGES DES JOUEURS</strong></h6>
          <p>
            Sont autorisés à participer au tournoi les joueurs et joueuses
            né(e)s de <strong><?= $anneeNaissanceMin ?></strong> à <strong><?= $anneeNaissanceMax ?></strong>.
          </p>
          <p>
            Le joueur qui ne pourra présenter sa licence ne sera pas autorisé
            à participer au tournoi.
          </p>
          <h6><strong>NOMBRE DE JOUEURS</strong></h6>
          <p>
            Le nombre de joueurs par équipe est de <strong>6 minimum</strong> et <strong>9 maximum</strong>.
          </p>
          <h6><strong>FEUILLES DE MATCH</strong></h6>
          <p>
            Elles seront déposées à l'accueil comme convenu dans le programme,
            avec les licences des joueurs et des éducateurs
            (en vue des badges d'accès terrain).
            Seules les licences des participants seront déposées et classées
            dans l'ordre inscrit sur la feuille.
          </p>
          <h6><strong>CHANGEMENT DE JOUEURS</strong></h6>
          <p>
            Le nombre de changements de joueurs est <strong>illimité</strong>
            pendant les temps de pause, les arrêts de jeu ou en cas de blessure.
            Le changement s'effectue par le centre du terrain après accord de l'arbitre.
          </p>

          <h6><strong>EXPULSION</strong></h6>
          <ul>
          <li>
          <p>
            CARTON JAUNE :
              Tout joueur écopant d'un carton jaune sera exclu pendant
              <strong>deux minutes</strong>. Il sera alors remplacé.
              Pour un même joueur, un deuxième carton jaune équivaudra à la sanction
              d'un carton rouge.
              Chaque carton jaune aura une incidence sur les cas d'égalité.
            </p>
          </li>
          <li>
            <p>
              CARTON ROUGE :
              Tout joueur écopant d'un carton rouge sera exclu définitivementdu du match en cours. 
              Le joueur sera alors remplacé. La Commission de discipline se donne la possibilité d'exclure définitivement
              le joueur ou l'éducateur du tournoi après examen du dossier.
            </p>
          </li>
          </ul>

          <h6><strong>FAUTE GRAVE</strong></h6>
          <p>
            Toute agression sur l'arbitre ou tout autre comportement très grave
            sera sanctionné par la Commission de discipline, qui rendra sa décision.
            Toute intrusion sur le terrain par un joueur, éducateur, dirigeant
            ou supporter sans autorisation du chef de plateau ou du délégué
            entraînera un rapport de la Commission de discipline.
          </p>

          <h6><strong>ABANDON DU TERRAIN</strong></h6>
          <p>
            Toute équipe quittant le terrain avant la fin d'une partie et sans y avoir
            été invitée par les organisateurs pour force majeure aura
            <strong>match perdu avec -5 essais</strong>.
            La commission du tournoi statuera sur les décisions à prendre.
          </p>

          <h6><strong>MAILLOTS — COULEURS</strong></h6>
          <p>
            En cas de couleurs identiques, l'équipe étant la plus proche
            (au niveau des kilomètres) changera de maillots.
            Munissez-vous de <strong>2 jeux de maillots de couleurs différentes</strong>.
          </p>

          <h6><strong>TERRAIN</strong></h6>
          <p>
            Terrain de jeu normal. Voir Rugby Digest.
          </p>

          <h6><strong>COUPS DE PIED</strong></h6>
          <p>
            Règle normale Rugby Digest.
          </p>

          <h6>TRANSFORMATIONS</h6>
          <p>
            Aucune.
          </p>

          <h6><strong>DÉCOMPTE DES POINTS</strong></h6>
          <ul>
            <li><strong>Victoire :</strong> 4 points</li>
            <li><strong>Nul :</strong> 2 points</li>
            <li><strong>Défaite :</strong> 0 point</li>
            <li><strong>Abandon du terrain :</strong> -2 points</li>
          </ul>

          <h6><strong>ARBITRAGE</strong></h6>
          <p>
            Le club organisateur est en charge de la désignation des arbitres
            mis à disposition.
          </p>

          <h6><strong>BANC DE TOUCHE</strong></h6>
          <p>
            Ne seront admis sur le banc de touche que
            <strong>trois éducateurs maximum</strong> en possession de leur badge. 
            Ceux-ci devront être inscrits sur la feuille de match.
          </p>

        </div>
      </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
