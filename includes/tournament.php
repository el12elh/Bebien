<?php
require_once __DIR__ . '/db.php';

/**
 * ============================================================
 *  TIRAGE AU SORT DES POULES (phase matin)
 * ============================================================
 * Répartit aléatoirement les équipes d'une catégorie dans N poules,
 * en évitant qu'une même poule contienne deux équipes du même club.
 */
function assignerEquipesParPoules(array $teams, array $poolIds): array {
    if (!$poolIds) {
        throw new Exception('Aucune poule disponible pour le tirage.');
    }

    $teamCount = count($teams);
    $poolCount = count($poolIds);
    $baseSize = intdiv($teamCount, $poolCount);
    $extraTeams = $teamCount % $poolCount;

    $targetSizes = [];
    foreach ($poolIds as $index => $poolId) {
        $targetSizes[$poolId] = $baseSize + ($index < $extraTeams ? 1 : 0);
    }

    $maxAttempts = 500;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        shuffle($teams);
        $assign = [];
        foreach ($poolIds as $poolId) {
            $assign[$poolId] = [];
        }

        $ok = true;
        foreach ($teams as $team) {
            $eligiblePoolIds = [];
            foreach ($poolIds as $poolId) {
                if (count($assign[$poolId]) >= $targetSizes[$poolId]) {
                    continue;
                }

                $clubAlreadyInPool = false;
                foreach ($assign[$poolId] as $assignedTeam) {
                    if ((int) $assignedTeam['club_id'] === (int) $team['club_id']) {
                        $clubAlreadyInPool = true;
                        break;
                    }
                }
                if (!$clubAlreadyInPool) {
                    $eligiblePoolIds[] = $poolId;
                }
            }

            if (!$eligiblePoolIds) {
                $ok = false;
                break;
            }

            $minCount = min(array_map(function ($poolId) use ($assign) {
                return count($assign[$poolId]);
            }, $eligiblePoolIds));
            $bestPoolIds = array_values(array_filter($eligiblePoolIds, function ($poolId) use ($assign, $minCount) {
                return count($assign[$poolId]) === $minCount;
            }));

            $selectedPoolId = $bestPoolIds[array_rand($bestPoolIds)];
            $assign[$selectedPoolId][] = $team;
        }

        $sizesOk = true;
        foreach ($poolIds as $poolId) {
            if (count($assign[$poolId]) !== $targetSizes[$poolId]) {
                $sizesOk = false;
                break;
            }
        }

        if ($ok && $sizesOk) {
            return $assign;
        }
    }

    throw new Exception('Impossible de répartir les équipes de façon équitable sans mettre deux équipes du même club dans la même poule.');
}

function tirerPoulesMatin(int $categoryId, int $nbPoules): array {
    $pdo = db();

    // Récupère les équipes de la catégorie
    $stmt = $pdo->prepare('SELECT id, club_id FROM teams WHERE category_id = ?');
    $stmt->execute([$categoryId]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($teams) < 2) {
        throw new Exception('Il faut au moins 2 équipes dans cette catégorie pour tirer les poules.');
    }
    if ($nbPoules < 1) $nbPoules = 1;

    // Supprime les anciennes poules "matin" de cette catégorie (et les matchs liés, cascade)
    $pdo->prepare("DELETE FROM pools WHERE category_id = ? AND phase = 'matin'")->execute([$categoryId]);

    // Crée les nouvelles poules
    $poolIds = [];
    $lettres = range('A', 'Z');
    for ($i = 0; $i < $nbPoules; $i++) {
        $nomPoule = 'Poule ' . ($lettres[$i] ?? ($i + 1));
        $stmt = $pdo->prepare("INSERT INTO pools (category_id, nom, phase, ordre_niveau) VALUES (?, ?, 'matin', ?)");
        $stmt->execute([$categoryId, $nomPoule, $i]);
        $poolIds[] = $pdo->lastInsertId();
    }

    $assign = assignerEquipesParPoules($teams, $poolIds);

    foreach ($assign as $poolId => $teamsInPool) {
        $stmt = $pdo->prepare('INSERT INTO pool_teams (pool_id, team_id) VALUES (?, ?)');
        foreach ($teamsInPool as $team) {
            $stmt->execute([$poolId, $team['id']]);
        }
        // Génère automatiquement le calendrier des matchs de cette poule
        genererMatchsPoule($poolId, 'matin');
    }

    // Planifie automatiquement horaires + terrains pour la catégorie
    planifierMatchs($categoryId, 'matin');

    return $poolIds;
}

/**
 * ============================================================
 *  GÉNÉRATION DES MATCHS D'UNE POULE (méthode du round-robin / cercle)
 * ============================================================
 * Génère tous les matchs (une confrontation entre chaque paire d'équipes)
 * et les répartit en "journées" (rounds) pour qu'une équipe ne joue
 * jamais deux fois dans le même round.
 */
function genererMatchsPoule(int $poolId, string $phase): void {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT p.category_id, pt.team_id FROM pools p
                            JOIN pool_teams pt ON pt.pool_id = p.id
                            WHERE p.id = ?
                            ORDER BY pt.id');
    $stmt->execute([$poolId]);
    $rows = $stmt->fetchAll();
    if (!$rows) return;
    $categoryId = $rows[0]['category_id'];
    $teams = array_column($rows, 'team_id');

    // Nettoie les matchs existants de cette poule (permet de régénérer si besoin)
    $pdo->prepare('DELETE FROM matches WHERE pool_id = ?')->execute([$poolId]);

    if ($phase === 'aprem') {
        $rounds = genererRoundRobinParClassement($teams);
    } else {
        $rounds = genererRoundRobin($teams);
    }

    $stmt = $pdo->prepare('INSERT INTO matches (category_id, pool_id, phase, round_number, team1_id, team2_id)
                            VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rounds as $roundNum => $pairs) {
        foreach ($pairs as [$t1, $t2]) {
            $stmt->execute([$categoryId, $poolId, $phase, $roundNum + 1, $t1, $t2]);
        }
    }
}

/**
 * Algorithme du "cercle" pour générer un round-robin complet.
 * Retourne un tableau de rounds, chaque round = liste de paires [teamA, teamB].
 * Si nombre impair d'équipes, une équipe est au repos (bye) par round (pas de match généré pour elle).
 */
function genererRoundRobin(array $teams): array {
    $teams = array_values($teams);
    $n = count($teams);
    $hasBye = false;
    if ($n % 2 !== 0) {
        $teams[] = null; // équipe fictive = "bye"
        $n++;
        $hasBye = true;
    }
    $rounds = [];
    $half = $n / 2;
    $arr = $teams;
    for ($r = 0; $r < $n - 1; $r++) {
        $pairs = [];
        for ($i = 0; $i < $half; $i++) {
            $a = $arr[$i];
            $b = $arr[$n - 1 - $i];
            if ($a !== null && $b !== null) {
                $pairs[] = [$a, $b];
            }
        }
        $rounds[] = $pairs;
        // rotation (on garde le premier élément fixe)
        $last = array_pop($arr);
        array_splice($arr, 1, 0, [$last]);
    }
    return $rounds;
}

/**
 * Version de round-robin utilisée pour les poules d'après-midi.
 * On garde le même principe que le round-robin classique, sans dépendre
 * d'un ordre de classement plus complexe en entrée.
 */
function genererRoundRobinParClassement(array $teams): array {
    $teams = array_values(array_unique(array_map('intval', $teams)));
    if (count($teams) < 2) {
        return [];
    }

    return genererRoundRobin($teams);
}

/**
 * ============================================================
 *  PLANIFICATION : attribution automatique horaires + terrains
 * ============================================================
 * Répartit les matchs "Programmé" d'une phase (toutes catégories confondues
 * ou une catégorie donnée) sur les terrains disponibles, round par round,
 * afin de jouer le maximum de matchs en simultané.
 */

function planifierMatchs(?int $categoryId, string $phase): void {
    $pdo = db();

    $sql = 'SELECT id FROM fields WHERE actif = 1';
    $params = [];
    if ($categoryId !== null) {
        $sql .= ' AND (category_id IS NULL OR category_id = ?)';
        $params[] = $categoryId;
    }
    $sql .= ' ORDER BY id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fields = $stmt->fetchAll();
    $fieldIds = array_column($fields, 'id');
    if (!$fieldIds) throw new Exception('Aucun terrain actif configuré pour cette catégorie.');

    $dureeMatch = (int) getSetting('duree_match_minutes', 12);
    $pause = (int) getSetting('pause_entre_matchs_minutes', 3);
    $slotMinutes = $dureeMatch + $pause;
    $heureDebut = $phase === 'matin' ? getSetting('heure_debut_matin', '09:00') : getSetting('heure_debut_aprem', '13:30');

    $date_tournoi = getSetting('date_tournoi', date('Y-m-d'));
    $start = new DateTime($date_tournoi . ' ' . $heureDebut);

    $sql = 'SELECT id, category_id, round_number FROM matches WHERE phase = ? ' .
           ($categoryId ? 'AND category_id = ? ' : '') .
           'ORDER BY category_id, round_number, id';
    $stmt = $pdo->prepare($sql);
    $categoryId ? $stmt->execute([$phase, $categoryId]) : $stmt->execute([$phase]);
    $matches = $stmt->fetchAll();

    $categoryNames = [];
    $categories = $pdo->query('SELECT id, nom FROM categories')->fetchAll();
    foreach ($categories as $cat) {
        $categoryNames[(int) $cat['id']] = $cat['nom'];
    }

    // Regroupe par (category_id, round_number) pour garder les rounds "simultanés"
    $grouped = [];
    foreach ($matches as $m) {
        $key = $m['category_id'] . '-' . $m['round_number'];
        $grouped[$key][] = ['id' => $m['id'], 'category_id' => (int) $m['category_id']];
    }

    $update = $pdo->prepare('UPDATE matches SET field_id = ?, scheduled_at = ? WHERE id = ?');
    $slotIndexByCategory = [];
    foreach ($grouped as $groupData) {
        $matchIds = array_column($groupData, 'id');
        $categoryIdForGroup = $groupData[0]['category_id'];
        $categoryName = $categoryNames[$categoryIdForGroup] ?? '';

        $slotIndex = $slotIndexByCategory[$categoryIdForGroup] ?? 0;
        $batchSize = count($fieldIds);
        $batches = array_chunk($matchIds, $batchSize);

        foreach ($batches as $batch) {
            $slotTime = clone $start;
            $slotTime->modify('+' . (($slotIndex * $slotMinutes)) . ' minutes');
            foreach ($batch as $i => $matchId) {
                $fieldId = $fieldIds[$i];
                $update->execute([$fieldId, $slotTime->format('Y-m-d H:i:s'), $matchId]);
            }
            $slotIndex++;
        }

        $slotIndexByCategory[$categoryIdForGroup] = $slotIndex;
    }
}

/**
 * ============================================================
 *  CLASSEMENT D'UNE POULE
 * ============================================================
 * Retourne les équipes triées selon le barème de points configuré
 * dans "settings" et l'ordre de départage choisi.
 */
function classementPoule(int $poolId): array {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT t.id, t.nom_equipe, t.club_id, cl.nom_club, cl.logo_path
                            FROM pool_teams pt
                            JOIN teams t ON t.id = pt.team_id
                            JOIN clubs cl ON cl.id = t.club_id
                            WHERE pt.pool_id = ?');
    $stmt->execute([$poolId]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Determine for this pool's category whether a club has multiple teams
    $catStmt = $pdo->prepare('SELECT category_id FROM pools WHERE id = ?');
    $catStmt->execute([$poolId]);
    $poolCategoryId = $catStmt->fetchColumn();
    $clubCounts = [];
    if ($poolCategoryId) {
        $countStmt = $pdo->prepare('SELECT club_id, COUNT(*) AS cnt FROM teams WHERE category_id = ? GROUP BY club_id');
        $countStmt->execute([$poolCategoryId]);
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $clubCounts[$r['club_id']] = (int) $r['cnt'];
        }
    }
    $stats = [];
    foreach ($teams as $t) {
        $showNomEquipe = ($clubCounts[$t['club_id']] ?? 0) > 1;
        $stats[$t['id']] = [
            'team_id' => $t['id'],
            'nom_equipe' => $showNomEquipe ? $t['nom_equipe'] : '',
            'nom_club' => $t['nom_club'],
            'logo_path' => $t['logo_path'] ?? null,
            'joues' => 0, 'v' => 0, 'n' => 0, 'd' => 0,
            'pts_pour' => 0, 'pts_contre' => 0, 'points' => 0,
            'jaunes' => 0, 'rouges' => 0,
            'confrontations' => [], // team_id adverse => 'v'|'n'|'d'
        ];
    }

    $ptsV = (float) getSetting('points_victoire', 3);
    $ptsN = (float) getSetting('points_nul', 2);
    $ptsD = (float) getSetting('points_defaite', 1);
    $bonusOffActif = getSetting('bonus_offensif_actif', '0') === '1';
    $bonusOffSeuil = (int) getSetting('bonus_offensif_seuil_essais', 3);
    $bonusOffPts = (float) getSetting('bonus_offensif_points', 1);
    $bonusDefActif = getSetting('bonus_defensif_actif', '0') === '1';
    $bonusDefSeuil = (int) getSetting('bonus_defensif_seuil_ecart', 5);
    $bonusDefPts = (float) getSetting('bonus_defensif_points', 1);

    $stmt = $pdo->prepare('SELECT * FROM matches WHERE pool_id = ? AND status = "Terminé"');
    $stmt->execute([$poolId]);
    $matches = $stmt->fetchAll();

    foreach ($matches as $m) {
        $t1 = $m['team1_id']; $t2 = $m['team2_id'];
        $s1 = (int) $m['score1']; $s2 = (int) $m['score2'];
        if (!isset($stats[$t1]) || !isset($stats[$t2])) continue;

        $stats[$t1]['jaunes'] += (int) ($m['cartons_jaunes1'] ?? 0);
        $stats[$t1]['rouges'] += (int) ($m['cartons_rouges1'] ?? 0);
        $stats[$t2]['jaunes'] += (int) ($m['cartons_jaunes2'] ?? 0);
        $stats[$t2]['rouges'] += (int) ($m['cartons_rouges2'] ?? 0);

        $stats[$t1]['joues']++; $stats[$t2]['joues']++;
        $stats[$t1]['pts_pour'] += $s1; $stats[$t1]['pts_contre'] += $s2;
        $stats[$t2]['pts_pour'] += $s2; $stats[$t2]['pts_contre'] += $s1;

        if ($s1 > $s2) {
            $stats[$t1]['v']++; $stats[$t2]['d']++;
            $stats[$t1]['points'] += $ptsV; $stats[$t2]['points'] += $ptsD;
            $stats[$t1]['confrontations'][$t2] = 'v';
            $stats[$t2]['confrontations'][$t1] = 'd';
            if ($bonusDefActif && ($s1 - $s2) <= $bonusDefSeuil) $stats[$t2]['points'] += $bonusDefPts;
        } elseif ($s2 > $s1) {
            $stats[$t2]['v']++; $stats[$t1]['d']++;
            $stats[$t2]['points'] += $ptsV; $stats[$t1]['points'] += $ptsD;
            $stats[$t2]['confrontations'][$t1] = 'v';
            $stats[$t1]['confrontations'][$t2] = 'd';
            if ($bonusDefActif && ($s2 - $s1) <= $bonusDefSeuil) $stats[$t1]['points'] += $bonusDefPts;
        } else {
            $stats[$t1]['n']++; $stats[$t2]['n']++;
            $stats[$t1]['points'] += $ptsN; $stats[$t2]['points'] += $ptsN;
            $stats[$t1]['confrontations'][$t2] = 'n';
            $stats[$t2]['confrontations'][$t1] = 'n';
        }

        if ($bonusOffActif) {
            $e1 = $m['essais1']; $e2 = $m['essais2'];
            if ($e1 !== null && $e2 !== null) {
                if (($e1 - $e2) >= $bonusOffSeuil) $stats[$t1]['points'] += $bonusOffPts;
                if (($e2 - $e1) >= $bonusOffSeuil) $stats[$t2]['points'] += $bonusOffPts;
            }
        }
    }

    foreach ($stats as &$s) {
        $s['diff'] = $s['pts_pour'] - $s['pts_contre'];
        $s['points_cartons'] = -($s['jaunes'] + 5 * $s['rouges']);
    }
    unset($s);

    $ordre = explode(',', getSetting('ordre_departage', 'points,points_cartons,essais_pour,diff_essais,tirage_sort'));
    $liste = array_values($stats);

    usort($liste, function ($a, $b) use ($ordre) {
        foreach ($ordre as $critere) {
            $critere = trim($critere);
            $cmp = 0;
            if ($critere === 'points') $cmp = $b['points'] <=> $a['points'];
            elseif ($critere === 'points_cartons') $cmp = $b['points_cartons'] <=> $a['points_cartons'];
            elseif ($critere === 'essais_pour') $cmp = $b['pts_pour'] <=> $a['pts_pour'];
            elseif ($critere === 'diff_essais') $cmp = $b['diff'] <=> $a['diff'];
            elseif ($critere === 'confrontation_directe') {
                if (isset($a['confrontations'][$b['team_id']])) {
                    $res = $a['confrontations'][$b['team_id']];
                    $cmp = $res === 'v' ? -1 : ($res === 'd' ? 1 : 0);
                }
            }
            // 'tirage_sort' : on laisse égal (ordre stable / aléatoire léger), dernier recours
            if ($cmp !== 0) return $cmp;
        }
        return 0;
    });

    foreach ($liste as $i => &$row) {
        $row['rang'] = $i + 1;
    }
    return $liste;
}

/**
 * ============================================================
 *  RECLASSEMENT / GÉNÉRATION DES TABLEAUX DE NIVEAU (après-midi)
 * ============================================================
 * $repartition : tableau ordonné de tailles de poules à former, en piochant
 * les équipes par rang dans chaque poule du matin.
 * Ex pour 2 poules de 4 équipes -> Poule des As (les 1ers et 2èmes),
 * Poule de classement (les 3èmes et 4èmes) : repartition = [
 *   ['nom' => 'Poule des As', 'rangs' => [1, 2]],
 *   ['nom' => 'Poule de classement', 'rangs' => [3, 4]],
 * ]
 */
function genererPoulesApresMidi(int $categoryId, array $repartition): array {
    $pdo = db();

    // Récupère toutes les poules du matin de la catégorie et leur classement
    $stmt = $pdo->prepare("SELECT id FROM pools WHERE category_id = ? AND phase = 'matin' ORDER BY ordre_niveau");
    $stmt->execute([$categoryId]);
    $poolsMatin = array_column($stmt->fetchAll(), 'id');
    if (!$poolsMatin) throw new Exception('Aucune poule du matin trouvée pour cette catégorie.');

    $classementsParPoule = [];
    foreach ($poolsMatin as $pid) {
        $classementsParPoule[$pid] = classementPoule($pid);
    }

    // Supprime les anciennes poules de l'après-midi (et matchs liés)
    $pdo->prepare("DELETE FROM pools WHERE category_id = ? AND phase = 'aprem'")->execute([$categoryId]);

    $newPoolIds = [];
    foreach ($repartition as $niveauIdx => $niveau) {
        $stmt = $pdo->prepare("INSERT INTO pools (category_id, nom, phase, ordre_niveau) VALUES (?, ?, 'aprem', ?)");
        $stmt->execute([$categoryId, $niveau['nom'], $niveauIdx]);
        $newPoolId = $pdo->lastInsertId();
        $newPoolIds[] = $newPoolId;

        $teamsForThisPool = [];
        foreach ($classementsParPoule as $classement) {
            foreach ($classement as $row) {
                if (in_array($row['rang'], $niveau['rangs'], true)) {
                    $teamsForThisPool[] = $row['team_id'];
                }
            }
        }

        $ins = $pdo->prepare('INSERT INTO pool_teams (pool_id, team_id) VALUES (?, ?)');
        foreach ($teamsForThisPool as $teamId) {
            $ins->execute([$newPoolId, $teamId]);
        }

        genererMatchsPoule($newPoolId, 'aprem');
    }

    planifierMatchs($categoryId, 'aprem');

    return $newPoolIds;
}

/**
 * Génère automatiquement les poules de l'après-midi à partir du classement
 * global de la catégorie (classement général).
 * $buckets : tableau de ['nom'=>string, 'from'=>int, 'to'=>int]
 */
/**
 * Transforme une chaîne de rangs ("1-7,9,12-14") en tableau d'entiers triés/dédupliqués.
 * (déplacé depuis pools_draw.php pour être réutilisable / testable ici)
 */
function parseRankList(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return [];
    }
    $ranks = [];
    foreach (preg_split('/\s*,\s*/', $value) as $segment) {
        if ($segment === '') {
            continue;
        }
        if (preg_match('/^(\d+)-(\d+)$/', $segment, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];
            if ($start > $end) {
                list($start, $end) = [$end, $start];
            }
            for ($i = $start; $i <= $end; $i++) {
                $ranks[] = $i;
            }
        } elseif (preg_match('/^\d+$/', $segment)) {
            $ranks[] = (int) $segment;
        }
    }
    sort($ranks, SORT_NUMERIC);
    return array_values(array_unique($ranks));
}

function formatRankList(array $ranks): string {
    $ranks = array_values(array_unique(array_map('intval', $ranks)));
    sort($ranks, SORT_NUMERIC);
    if (!$ranks) {
        return '';
    }
    $parts = [];
    $start = $ranks[0];
    $prev = $ranks[0];
    $n = count($ranks);
    for ($i = 1; $i <= $n; $i++) {
        $cur = $ranks[$i] ?? null;
        if ($cur !== null && $cur === $prev + 1) {
            $prev = $cur;
            continue;
        }
        $parts[] = $start === $prev ? (string) $start : "$start-$prev";
        if ($cur !== null) {
            $start = $cur;
            $prev = $cur;
        }
    }
    return implode(',', $parts);
}

/**
 * Calcule des tranches de rangs par défaut, réparties le plus équitablement
 * possible sur $nbTableaux tableaux, en fonction du nombre d'équipes réellement
 * engagées dans la catégorie (au lieu d'un 1-7/8-14/15-20 fixe).
 * Ex: 20 équipes / 3 tableaux -> [1-7, 8-14, 15-20]
 *     10 équipes / 3 tableaux -> [1-4, 5-7, 8-10]
 */
function calculerRangsParDefaut(int $nbTeams, int $nbTableaux = 3): array {
    $buckets = [];
    if ($nbTeams < 1 || $nbTableaux < 1) {
        return $buckets;
    }
    $base = intdiv($nbTeams, $nbTableaux);
    $reste = $nbTeams % $nbTableaux;
    $from = 1;
    for ($i = 0; $i < $nbTableaux; $i++) {
        $taille = $base + ($i < $reste ? 1 : 0);
        if ($taille < 1) {
            // Plus de tableaux demandés que d'équipes disponibles : tableau vide.
            $buckets[] = ['from' => $nbTeams + 1, 'to' => $nbTeams];
            continue;
        }
        $to = $from + $taille - 1;
        $buckets[] = ['from' => $from, 'to' => $to];
        $from = $to + 1;
    }
    return $buckets;
}

/**
 * Vérifie la cohérence des tranches de rangs avant génération :
 * - lève une Exception si un même rang apparaît dans plusieurs tableaux
 *   (empêche qu'une équipe soit affectée à deux poules d'après-midi) ;
 * - retourne une liste d'avertissements (non bloquants) pour les rangs
 *   valides (1..$nbTeamsCat) qui ne sont couverts par aucun tableau.
 * Chaque bucket doit déjà avoir une clé 'ranks' (tableau d'entiers).
 */
function validerBucketsRangs(array $buckets, int $nbTeamsCat): array {
    $seen = [];
    $doublons = [];
    foreach ($buckets as $b) {
        foreach ($b['ranks'] as $r) {
            if (isset($seen[$r])) {
                $doublons[$r] = true;
            }
            $seen[$r] = true;
        }
    }
    if ($doublons) {
        $liste = implode(', ', array_keys($doublons));
        throw new Exception(
            "Chevauchement détecté sur le(s) rang(s) $liste : une même équipe ne peut pas ".
            "être affectée à plusieurs tableaux de l'après-midi. Corrigez les plages saisies."
        );
    }

    $manquants = [];
    for ($r = 1; $r <= $nbTeamsCat; $r++) {
        if (!isset($seen[$r])) {
            $manquants[] = $r;
        }
    }
    $warnings = [];
    if ($manquants) {
        $warnings[] = "Attention : le(s) rang(s) " . implode(', ', $manquants) .
            " ne sont couverts par aucun tableau — les équipes correspondantes ne joueront pas l'après-midi.";
    }
    return $warnings;
}

/**
 * Génère les poules de l'après-midi (Cup/Plate/Bowl ou équivalent) à partir
 * du classement général des poules du matin.
 *
 * $buckets : liste de ['nom' => string, 'ranks' => int[]] ou ['nom' => string, 'from' => int, 'to' => int].
 *
 * Retourne ['poolIds' => int[], 'warnings' => string[]].
 * Toutes les écritures sont regroupées dans une transaction : en cas d'erreur
 * en cours de génération, rien n'est laissé dans un état partiel.
 */
function genererPoulesApresMidiGlobal(int $categoryId, array $buckets): array {
    $pdo = db();

    // Classement général (toutes poules du matin)
    $classement = classementGeneral($categoryId, 'matin');
    if (!$classement) throw new Exception('Classement général introuvable.');

    // Map rang->team_id
    $byRank = [];
    foreach ($classement as $row) {
        $byRank[$row['rang']] = $row['team_id'];
    }

    // Normalise chaque bucket vers une clé 'ranks' (tableau d'entiers triés/dédupliqués)
    foreach ($buckets as &$b) {
        if (empty($b['ranks']) && isset($b['from'], $b['to'])) {
            $from = (int) $b['from'];
            $to = (int) $b['to'];
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $b['ranks'] = range($from, $to);
        }
        $b['ranks'] = array_values(array_unique(array_map('intval', $b['ranks'] ?? [])));
    }
    unset($b);

    // Valide les tranches AVANT toute écriture : chevauchement -> Exception bloquante,
    // rangs manquants -> avertissement non bloquant renvoyé à l'appelant.
    $warnings = validerBucketsRangs($buckets, count($classement));

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM pools WHERE category_id = ? AND phase = 'aprem'")->execute([$categoryId]);

        $newPoolIds = [];
        foreach ($buckets as $idx => $b) {
            $nom = $b['nom'];
            $ranks = $b['ranks'];
            $rangsConfig = formatRankList($ranks);

            $stmt = $pdo->prepare(
                "INSERT INTO pools (category_id, nom, phase, ordre_niveau) VALUES (?, ?, 'aprem', ?)"
            );
            $stmt->execute([$categoryId, $nom, $idx]);
            $newPoolId = $pdo->lastInsertId();
            $newPoolIds[] = $newPoolId;

            $ins = $pdo->prepare('INSERT INTO pool_teams (pool_id, team_id) VALUES (?, ?)');
            foreach ($ranks as $r) {
                if (isset($byRank[$r])) {
                    $ins->execute([$newPoolId, $byRank[$r]]);
                }
            }

            // Génère les matchs pour la poule créée
            genererMatchsPoule($newPoolId, 'aprem');
        }

        // Planifie les matchs après création
        planifierMatchs($categoryId, 'aprem');

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['poolIds' => $newPoolIds, 'warnings' => $warnings];
}

/**
 * Classement général d'une catégorie pour une phase donnée
 * (toutes les poules de cette phase mises côte à côte, triées par poule/niveau puis rang).
 */
function classementCategorie(int $categoryId, string $phase): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, nom, ordre_niveau FROM pools WHERE category_id = ? AND phase = ? ORDER BY ordre_niveau, id');
    $stmt->execute([$categoryId, $phase]);
    $pools = $stmt->fetchAll();

    $result = [];
    foreach ($pools as $p) {
        $result[] = [
            'pool_id' => $p['id'],
            'pool_nom' => $p['nom'],
            'classement' => classementPoule($p['id']),
        ];
    }
    return $result;
}

/**
 * ============================================================
 *  CLASSEMENT GÉNÉRAL (toutes les poules d'une catégorie/phase)
 * ============================================================
 * Retourne une liste plate (tous les équipes de toutes les poules)
 * triée selon l'ordre de départage.
 */
function classementGeneral(int $categoryId, string $phase): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM pools WHERE category_id = ? AND phase = ?');
    $stmt->execute([$categoryId, $phase]);
    $poolIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $all = [];
    foreach ($poolIds as $pid) {
        $cl = classementPoule((int)$pid);
        foreach ($cl as $row) {
            $all[$row['team_id']] = $row;
        }
    }

    $liste = array_values($all);
    $ordre = explode(',', getSetting('ordre_departage', 'points,points_cartons,essais_pour,diff_essais,tirage_sort'));

    usort($liste, function ($a, $b) use ($ordre) {
        foreach ($ordre as $critere) {
            $critere = trim($critere);
            $cmp = 0;
            if ($critere === 'points') $cmp = $b['points'] <=> $a['points'];
            elseif ($critere === 'points_cartons') $cmp = $b['points_cartons'] <=> $a['points_cartons'];
            elseif ($critere === 'essais_pour') $cmp = $b['pts_pour'] <=> $a['pts_pour'];
            elseif ($critere === 'diff_essais') $cmp = $b['diff'] <=> $a['diff'];
            elseif ($critere === 'confrontation_directe') {
                if (isset($a['confrontations'][$b['team_id']])) {
                    $res = $a['confrontations'][$b['team_id']];
                    $cmp = $res === 'v' ? -1 : ($res === 'd' ? 1 : 0);
                }
            }
            if ($cmp !== 0) return $cmp;
        }
        return 0;
    });

    foreach ($liste as $i => &$row) {
        $row['rang'] = $i + 1;
    }

    return $liste;
}