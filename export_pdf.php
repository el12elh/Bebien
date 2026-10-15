<?php
/**
 * Téléchargement direct du PDF : poules, matchs (état / score), classements,
 * et challenge (classement final des clubs + serial marqueur).
 * Paramètres GET : category_id (0 = toutes), phase ('' = matin + après-midi), poules / matchs / classements / challenge.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tournament.php';
require_once __DIR__ . '/includes/SimplePdf.php';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$categoryId = (int) ($_GET['category_id'] ?? 0);
$phaseChoice = $_GET['phase'] ?? '';
$phases = in_array($phaseChoice, ['matin', 'aprem'], true) ? [$phaseChoice] : ['matin', 'aprem'];
$submitted = isset($_GET['go']);
$show = [
    'poules' => !$submitted || isset($_GET['poules']),
    'matchs' => !$submitted || isset($_GET['matchs']),
    'classements' => !$submitted || isset($_GET['classements']),
    'challenge' => !$submitted || isset($_GET['challenge']),
];
$phaseLabels = ['matin' => 'Matin', 'aprem' => 'Après-midi'];

// Couleurs des zones du classement général du matin (comme standings.php)
$zoneColors = [
    1 => [0.129, 0.231, 0.439], // #213b70
    2 => [0.376, 0.647, 0.980], // #60A5FA
    3 => [0.980, 0.800, 0.082], // #FACC15
    4 => [0.996, 0.941, 0.541], // #FEF08A
];

/** Zone (1..4) d'une position (index 0) selon le découpage en $nbPools poules d'après-midi. */
function zoneForIndex(int $index, int $total, int $nbPools): int
{
    $nbPools = max(1, $nbPools);
    $base = intdiv($total, $nbPools);
    $extra = $total % $nbPools;
    $start = 0;
    for ($n = 1; $n <= $nbPools; $n++) {
        $end = $start + $base + ($n <= $extra ? 1 : 0);
        if ($index < $end) {
            return min($n, 4);
        }
        $start = $end;
    }
    return min($nbPools, 4);
}

/** Formate un nombre de points sans décimales inutiles (12 / 12,5). */
function fmtPoints($value): string
{
    return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',');
}

/** Charge le logo d'un club pour le PDF (null si absent / illisible / SVG). */
function pdfLogo(SimplePdf $pdf, ?string $path): ?array
{
    if (empty($path) || strpos($path, '..') !== false) {
        return null;
    }
    return $pdf->loadImage(__DIR__ . '/' . ltrim($path, '/'));
}

function pdfTeamLabel(string $club, ?string $team, int $count): string
{
    return $club . ($count > 1 && $team ? ' ' . $team : '');
}

$stmtPools = $pdo->prepare('SELECT id, nom FROM pools WHERE category_id = ? AND phase = ? ORDER BY ordre_niveau, id');
$stmtTeams = $pdo->prepare(
    'SELECT t.nom_equipe, cl.nom_club, cl.logo_path,
            (SELECT COUNT(*) FROM teams t2 WHERE t2.club_id = t.club_id AND t2.category_id = t.category_id) AS nb_equipes_club
     FROM pool_teams pt
     JOIN teams t ON t.id = pt.team_id
     JOIN clubs cl ON cl.id = t.club_id
     WHERE pt.pool_id = ?
     ORDER BY cl.nom_club, t.nom_equipe'
);
$stmtMatches = $pdo->prepare(
    'SELECT m.status, m.score1, m.score2, m.scheduled_at, f.nom AS field_nom, p.nom AS pool_nom,
            c1.nom_club AS club1, c1.logo_path AS logo1, t1.nom_equipe AS eq1,
            (SELECT COUNT(*) FROM teams x WHERE x.club_id = t1.club_id AND x.category_id = t1.category_id) AS nb1,
            c2.nom_club AS club2, c2.logo_path AS logo2, t2.nom_equipe AS eq2,
            (SELECT COUNT(*) FROM teams y WHERE y.club_id = t2.club_id AND y.category_id = t2.category_id) AS nb2
     FROM matches m
     JOIN pools p ON p.id = m.pool_id
     LEFT JOIN fields f ON f.id = m.field_id
     JOIN teams t1 ON t1.id = m.team1_id
     JOIN clubs c1 ON c1.id = t1.club_id
     JOIN teams t2 ON t2.id = m.team2_id
     JOIN clubs c2 ON c2.id = t2.club_id
     WHERE m.category_id = ? AND m.phase = ?
     ORDER BY m.scheduled_at, f.nom, m.id'
);

$navy = [0.129, 0.231, 0.439];
$soft = [0.933, 0.949, 0.980];
$white = [1, 1, 1];

$tournoi = (string) getSetting('nom_tournoi', 'Tournoi');
$pdf = new SimplePdf($tournoi . ' - Export du ' . date('d/m/Y H:i:s'));
$cw = $pdf->contentWidth();
$any = false;

foreach ($categories as $cat) {
    if ($categoryId !== 0 && $categoryId !== (int) $cat['id']) {
        continue;
    }
    foreach ($phases as $phase) {
        $stmtPools->execute([(int) $cat['id'], $phase]);
        $pools = $stmtPools->fetchAll();
        $stmtMatches->execute([(int) $cat['id'], $phase]);
        $matches = $stmtMatches->fetchAll();
        if (!$pools && !$matches) {
            continue;
        }
        $any = true;

        // Nouvelle page + bandeau de titre
        $pdf->addPage();
        $pdf->rect($pdf->margin, $pdf->y, $cw, 30, $navy);
        $pdf->text($pdf->margin + 10, $pdf->y + 20, $tournoi, 13, true, $white);
        $title = $cat['nom'] . ' - ' . $phaseLabels[$phase];
        $pdf->text($pdf->margin + $cw - 10 - $pdf->textWidth($title, 13, true), $pdf->y + 20, $title, 13, true, $white);
        $pdf->y += 44;

        // ---- Poules
        if ($show['poules'] && $pools) {
            $pdf->ensureSpace(40);
            $pdf->text($pdf->margin, $pdf->y + 10, 'Poules', 12, true, $navy);
            $pdf->y += 20;
            // 4 poules : toutes sur la même ligne ; sinon 3 par ligne
            $perRow = count($pools) === 4 ? 4 : 3;
            $colW = $cw / $perRow;
            $boxW = $colW - 8;
            $border = [0.72, 0.78, 0.88];
            foreach (array_chunk($pools, $perRow) as $line) {
                $blocks = [];
                $maxTeams = 0;
                foreach ($line as $pool) {
                    $stmtTeams->execute([$pool['id']]);
                    $teams = $stmtTeams->fetchAll();
                    $blocks[] = ['pool' => $pool, 'teams' => $teams];
                    $maxTeams = max($maxTeams, count($teams));
                }
                $boxH = 16 + 14 * $maxTeams + 6;
                $pdf->ensureSpace($boxH + 8);
                foreach ($blocks as $i => $block) {
                    $x = $pdf->margin + $i * $colW;
                    // Cadre de la poule + bandeau de titre
                    $pdf->rect($x, $pdf->y, $boxW, $boxH, null, $border);
                    $pdf->rect($x, $pdf->y, $boxW, 16, $soft, $border);
                    $pdf->text($x + 6, $pdf->y + 11.5, $pdf->fit($block['pool']['nom'], $boxW - 12, 9.5, true), 9.5, true, $navy);
                    $ty = $pdf->y + 16 + 11;
                    foreach ($block['teams'] as $t) {
                        $label = pdfTeamLabel($t['nom_club'], $t['nom_equipe'], (int) $t['nb_equipes_club']);
                        $logo = pdfLogo($pdf, $t['logo_path'] ?? null);
                        if ($logo) {
                            $pdf->image($logo, $x + 6, $ty - 9, 14, 11);
                        }
                        $pdf->text($x + 25, $ty, $pdf->fit($label, $boxW - 31, 8.5), 8.5);
                        $ty += 14;
                    }
                }
                $pdf->y += $boxH + 8;
            }
            $pdf->y += 6;
        }

        // ---- Matchs
        if ($show['matchs'] && $matches) {
            $widths = [34, 64, 48, 138, 40, 138, 61];
            $header = static function () use ($pdf, $widths, $soft) {
                $pdf->row(['Heure', 'Terrain', 'Poule', 'Équipe 1', 'Score', 'Équipe 2', 'État'], $widths, 16, [
                    'bold' => true, 'size' => 8.5, 'fill' => $soft, 'align' => ['C', 'L', 'L', 'L', 'C', 'L', 'L'],
                ]);
            };
            $pdf->ensureSpace(60);
            $pdf->text($pdf->margin, $pdf->y + 10, 'Matchs', 12, true, $navy);
            $pdf->y += 20;
            $header();
            foreach ($matches as $m) {
                if ($pdf->ensureSpace(18)) {
                    $header();
                }
                $played = in_array($m['status'], ['Terminé', 'Live'], true);
                $pdf->row([
                    $m['scheduled_at'] ? date('H:i', strtotime($m['scheduled_at'])) : '-',
                    $m['field_nom'] ?? '-',
                    $m['pool_nom'],
                    pdfTeamLabel($m['club1'], $m['eq1'], (int) $m['nb1']),
                    $played ? (int) $m['score1'] . ' - ' . (int) $m['score2'] : '-',
                    pdfTeamLabel($m['club2'], $m['eq2'], (int) $m['nb2']),
                    $m['status'],
                ], $widths, 18, [
                    'size' => 8.5, 'bold' => false, 'align' => ['C', 'L', 'L', 'L', 'C', 'L', 'L'],
                    'indent' => [3 => 28, 5 => 28],
                    'images' => [3 => pdfLogo($pdf, $m['logo1'] ?? null), 5 => pdfLogo($pdf, $m['logo2'] ?? null)],
                ]);
            }
            $pdf->y += 12;
        }

        // ---- Classements
        if ($show['classements'] && $pools) {
            $pdf->ensureSpace(60);
            $pdf->text($pdf->margin, $pdf->y + 10, 'Classements', 12, true, $navy);
            $pdf->y += 20;
            // Matin : colonne supplémentaire avec la couleur de zone
            $withZone = ($phase === 'matin');
            $nbAprem = max(1, (int) ($cat['nb_poules_aprem'] ?? 3));
            $tail = [32, 28, 28, 28, 28, 28, 28, 38, 38, 38];
            $widths = $withZone ? array_merge([22, 8, 179], $tail) : array_merge([22, 187], $tail);
            $align = array_fill(0, count($widths), 'C');
            $iTeam = $withZone ? 2 : 1;
            $align[$iTeam] = 'L';
            $iCard = $withZone ? 8 : 7;
            $yellow = [1.0, 0.86, 0.30];
            $red = [1.0, 0.17, 0.17];
            $poolBar = static function (string $name) use ($pdf, $cw, $navy, $white) {
                $pdf->row([$name], [$cw], 16, ['bold' => true, 'size' => 9.5, 'fill' => $navy, 'color' => $white]);
            };
            // Matin : un classement général par catégorie ; après-midi : un classement par tableau
            $tables = $phase === 'matin'
                ? [['pool_nom' => 'Classement général ' . $cat['nom'], 'classement' => classementGeneral((int) $cat['id'], 'matin')]]
                : classementCategorie((int) $cat['id'], $phase);
            foreach ($tables as $pool) {
                $rows = $pool['classement'];
                $pdf->ensureSpace(34 + 18 * min(count($rows), 4));
                $poolBar($pool['pool_nom']);
                $head = ['#', 'Équipe', 'Pts', 'J', 'V', 'N', 'D', '', '', '+', '-', '='];
                if ($withZone) {
                    array_splice($head, 1, 0, ['']);
                }
                $pdf->row($head, $widths, 18, [
                    'bold' => true, 'size' => 8.5, 'fill' => $soft, 'align' => $align,
                    'cards' => [$iCard => $yellow, $iCard + 1 => $red],
                ]);
                $totalRows = count($rows);
                foreach ($rows as $idx => $r) {
                    if ($pdf->ensureSpace(18)) {
                        $poolBar($pool['pool_nom']);
                    }
                    $yRow = $pdf->y;
                    $logo = pdfLogo($pdf, $r['logo_path'] ?? null);
                    $cells = [
                        (int) $r['rang'],
                        $r['nom_club'] . ($r['nom_equipe'] ? ' ' . $r['nom_equipe'] : ''),
                        $r['points'], (int) $r['joues'], (int) $r['v'], (int) $r['n'], (int) $r['d'],
                        (int) ($r['jaunes'] ?? 0), (int) ($r['rouges'] ?? 0),
                        (int) $r['pts_pour'], (int) $r['pts_contre'], (int) $r['diff'],
                    ];
                    if ($withZone) {
                        array_splice($cells, 1, 0, ['']);
                    }
                    $pdf->row($cells, $widths, 18, [
                        'size' => 8.5, 'align' => $align,
                        'indent' => [$iTeam => 28], 'images' => [$iTeam => $logo],
                    ]);
                    if ($withZone) {
                        $zone = zoneForIndex((int) $idx, $totalRows, $nbAprem);
                        $pdf->rect($pdf->margin + 22 + 1, $yRow + 1, 8 - 2, 16, $zoneColors[$zone]);
                    }
                }
                $pdf->y += 10;
            }
        }
    }
}

// ---- Challenge : classement final des clubs + serial marqueur (global, tous matchs)
if ($show['challenge']) {
    $final = classementFinalClubs();
    $marqueur = classementSerialMarqueur();
    $border = [0.72, 0.78, 0.88];
    $medals = [1 => [0.98, 0.80, 0.20], 2 => [0.78, 0.80, 0.84], 3 => [0.80, 0.50, 0.22]];
    $podium = static fn(int $rang): array => $rang <= 3 ? ['fill' => [0.965, 0.973, 0.992]] : [];

    /** Pastille de rang : médaille or / argent / bronze pour le podium (colonne 0, largeur $colW). */
    $drawRank = static function (float $y, int $rang, float $colW) use ($pdf, $medals, $navy) {
        $cx = $pdf->margin + $colW / 2;
        $label = (string) $rang;
        if (isset($medals[$rang])) {
            $pdf->rect($cx - 9, $y + 2.5, 18, 13, $medals[$rang], [0.55, 0.55, 0.55]);
        }
        $pdf->text($cx - $pdf->textWidth($label, 8.5, true) / 2, $y + 12, $label, 8.5, true, $navy);
    };

    if ($final['clubs'] || $marqueur['clubs']) {
        $any = true;
        $pdf->addPage();
        $pdf->rect($pdf->margin, $pdf->y, $cw, 30, $navy);
        $pdf->text($pdf->margin + 10, $pdf->y + 20, $tournoi, 13, true, $white);
        $title = 'Challenge - classement des clubs';
        $pdf->text($pdf->margin + $cw - 10 - $pdf->textWidth($title, 13, true), $pdf->y + 20, $title, 13, true, $white);
        $pdf->y += 44;
    }

    // ---- Classement final des clubs
    if ($final['clubs']) {
        $cats = $final['categories'];
        $nc = count($cats);
        $widths = array_merge([30, $cw - 30 - 50 * ($nc + 1)], array_fill(0, $nc + 1, 50));
        $align = array_merge(['C', 'L'], array_fill(0, $nc + 1, 'R'));
        $head = array_merge(['#', 'Club'], array_map(fn($c) => $c['nom'], $cats), ['Total']);
        $header = static function () use ($pdf, $widths, $head, $align, $soft) {
            $pdf->row($head, $widths, 16, ['bold' => true, 'size' => 8.5, 'fill' => $soft, 'align' => $align]);
        };
        $pdf->ensureSpace(80);
        $pdf->text($pdf->margin, $pdf->y + 10, 'Classement final', 12, true, $navy);
        $pdf->y += 18;
        // Pastilles de coefficient
        $bx = $pdf->margin;
        foreach ($cats as $c) {
            $lbl = $c['nom'] . ' x' . (int) $c['coef'];
            $bw = $pdf->textWidth($lbl, 8, true) + 12;
            $pdf->rect($bx, $pdf->y, $bw, 14, $soft, $border);
            $pdf->text($bx + 6, $pdf->y + 10.5, $lbl, 8, true, $navy);
            $bx += $bw + 6;
        }
        $pdf->y += 20;
        $note = $final['complete']
            ? "Toutes categories confondues, d'apres les resultats de l'apres-midi."
            : "Classement provisoire : tous les matchs de l'apres-midi ne sont pas termines.";
        $pdf->text($pdf->margin, $pdf->y + 8, $note, 8);
        $pdf->y += 14;
        $header();
        foreach ($final['clubs'] as $club) {
            if ($pdf->ensureSpace(18)) {
                $header();
            }
            $yRow = $pdf->y;
            $cells = ['', $club['nom_club']];
            foreach ($cats as $c) {
                $cells[] = isset($club['par_categorie'][$c['id']]) ? fmtPoints($club['par_categorie'][$c['id']]) : '-';
            }
            $cells[] = fmtPoints($club['total']);
            $pdf->row($cells, $widths, 18, array_merge([
                'size' => 8.5, 'align' => $align,
                'indent' => [1 => 28], 'images' => [1 => pdfLogo($pdf, $club['logo_path'] ?? null)],
            ], $podium((int) $club['rang'])));
            $drawRank($yRow, (int) $club['rang'], 30);
        }
        $pdf->y += 6;
        $pdf->text($pdf->margin, $pdf->y + 8, "Dans chaque categorie, seule la meilleure equipe de chaque club est prise en compte. Le club avec le moins de points est premier.", 7.5);
        $pdf->y += 11;
        $pdf->text($pdf->margin, $pdf->y + 8, "En cas d'egalite : essais marques l'apres-midi.", 7.5);
        $pdf->y += 20;
    }

    // ---- Serial marqueur
    if ($marqueur['clubs']) {
        $cats = $marqueur['categories']; // [id => nom]
        $nc = count($cats);
        $widths = array_merge([30, $cw - 30 - 45 * ($nc + 3)], array_fill(0, $nc + 3, 45));
        $align = array_merge(['C', 'L'], array_fill(0, $nc + 3, 'R'));
        $head = array_merge(['#', 'Club'], array_values($cats), ['Matchs', 'Moy.', 'Essais']);
        $header = static function () use ($pdf, $widths, $head, $align, $soft) {
            $pdf->row($head, $widths, 16, ['bold' => true, 'size' => 8.5, 'fill' => $soft, 'align' => $align]);
        };
        $pdf->ensureSpace(80);
        $pdf->text($pdf->margin, $pdf->y + 10, 'Serial marqueur', 12, true, $navy);
        $pdf->y += 18;
        $pdf->text($pdf->margin, $pdf->y + 8, 'Essais marques, toutes categories et tous matchs confondus (matin et apres-midi).', 8);
        $pdf->y += 14;
        $header();
        foreach ($marqueur['clubs'] as $club) {
            if ($pdf->ensureSpace(18)) {
                $header();
            }
            $yRow = $pdf->y;
            $cells = ['', $club['nom_club']];
            foreach ($cats as $catId => $catNom) {
                $cells[] = $club['par_categorie'][$catId] ?? '-';
            }
            $cells[] = (int) $club['matchs'];
            $cells[] = $club['matchs'] > 0 ? number_format($club['essais'] / $club['matchs'], 1, ',', '') : '-';
            $cells[] = (int) $club['essais'];
            $pdf->row($cells, $widths, 18, array_merge([
                'size' => 8.5, 'align' => $align,
                'indent' => [1 => 28], 'images' => [1 => pdfLogo($pdf, $club['logo_path'] ?? null)],
            ], $podium((int) $club['rang'])));
            $drawRank($yRow, (int) $club['rang'], 30);
        }
        $pdf->y += 6;
        $pdf->text($pdf->margin, $pdf->y + 8, "A egalite d'essais, le club qui a joue le moins de matchs passe devant.", 7.5);
        $pdf->y += 14;
    }
}

if (!$any) {
    $pdf->addPage();
    $pdf->text($pdf->margin, $pdf->y + 12, 'Rien a exporter pour cette selection.', 12, false);
}

$data = $pdf->output();
$filename = $tournoi . ' - ' . date('YmdHis') . '.pdf';
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
header('Content-Type: application/pdf');
// inline=1 : ouverture dans le navigateur (pour imprimer) ; sinon téléchargement
$disposition = isset($_GET['inline']) ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($data));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $data;
exit;