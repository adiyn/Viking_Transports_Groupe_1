<?php
// includes/itineraire.php — inclus depuis frontend/lignes.php

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) return;

$conn_iti = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
if ($conn_iti === null) return;

$communes_iti = [];
LireDonneesPDO1($conn_iti, "SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM", $communes_iti);

$nom_commune = [];
foreach ($communes_iti as $c) $nom_commune[$c['COM_CODE_INSEE']] = $c['COM_NOM'];

// --- Fonctions ---

function comprimer_chemin($hops) {
    if (empty($hops)) return [];
    $segments = [];
    $lig_cur  = $hops[0]['lig'];
    $from_cur = $hops[0]['from'];
    foreach ($hops as $hop) {
        if ($hop['lig'] !== $lig_cur) {
            $segments[] = ['lig' => $lig_cur, 'from' => $from_cur, 'to' => $hop['from']];
            $lig_cur    = $hop['lig'];
            $from_cur   = $hop['from'];
        }
    }
    $dernier    = end($hops);
    $segments[] = ['lig' => $lig_cur, 'from' => $from_cur, 'to' => $dernier['to']];
    return $segments;
}

function bfs_itineraires($graphe, $depart, $arrivee, $max_segs = 3) {
    $routes = [];
    $vus    = [];
    $file   = [[$depart, []]];
    while (!empty($file)) {
        [$cur, $hops] = array_shift($file);
        $visites = [$depart];
        foreach ($hops as $h) $visites[] = $h['to'];
        if (!isset($graphe[$cur])) continue;
        foreach ($graphe[$cur] as $arete) {
            $next = $arete['to'];
            $lig  = $arete['lig'];
            if (in_array($next, $visites) && $next !== $arrivee) continue;
            $nouveaux_hops = array_merge($hops, [['lig' => $lig, 'from' => $cur, 'to' => $next]]);
            if ($next === $arrivee) {
                $segs = comprimer_chemin($nouveaux_hops);
                if (count($segs) > $max_segs) continue;
                $cle = implode('|', array_map(function($s) { return $s['lig'].'-'.$s['from']; }, $segs));
                if (!isset($vus[$cle])) { $vus[$cle] = true; $routes[] = $segs; }
            } else {
                if (count(comprimer_chemin($nouveaux_hops)) >= $max_segs) continue;
                $file[] = [$next, $nouveaux_hops];
            }
        }
    }
    usort($routes, function($a, $b) { return count($a) - count($b); });
    return array_slice($routes, 0, 30);
}

function combiner_horaires($legs_horaires, $idx, $heure_min) {
    if ($idx >= count($legs_horaires)) return [[]];
    $results = [];
    foreach ($legs_horaires[$idx] as $h) {
        if ($heure_min !== '' && $h['HEURE_DEPART'] < $heure_min) continue;
        $sous = combiner_horaires($legs_horaires, $idx + 1, $h['HEURE_ARRIVEE']);
        foreach ($sous as $reste) $results[] = array_merge([$h], $reste);
    }
    return $results;
}

// --- Paramètres GET ---

$dep_iti   = isset($_GET['depart'])  ? trim($_GET['depart'])  : '';
$arr_iti   = isset($_GET['arrivee']) ? trim($_GET['arrivee']) : '';
$route_str = isset($_GET['route'])   ? trim($_GET['route'])   : '';

$route_parts = ($route_str !== '') ? explode(',', $route_str) : [];
$legs        = [];
$transfers   = [];
for ($i = 0; $i < count($route_parts); $i++) {
    if ($i % 2 === 0) $legs[]     = trim($route_parts[$i]);
    else              $transfers[] = trim($route_parts[$i]);
}

// --- Mode ---

if (!$dep_iti || !$arr_iti)    $mode = 'form';
elseif ($dep_iti === $arr_iti) $mode = 'erreur';
elseif (!empty($legs))         $mode = 'horaires';
else                           $mode = 'routes';

// --- Données ---

$found_routes     = [];
$all_leg_horaires = [];
$valid_combos     = [];

if ($mode === 'routes') {
    $aretes = [];
    LireDonneesPDO1($conn_iti,
        "SELECT TRIM(LIG_NUM) AS LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT
         FROM VIK_NOEUD WHERE COM_CODE_INSEE_SUIVANT IS NOT NULL",
        $aretes
    );
    $graphe = [];
    foreach ($aretes as $a) {
        $graphe[$a['COM_CODE_INSEE_ARRET']][] = ['lig' => $a['LIG_NUM'], 'to' => $a['COM_CODE_INSEE_SUIVANT']];
    }
    $found_routes = bfs_itineraires($graphe, $dep_iti, $arr_iti);
}

if ($mode === 'horaires' && !empty($legs)) {
    $points = array_merge([$dep_iti], $transfers, [$arr_iti]);
    for ($i = 0; $i < count($legs); $i++) {
        $cur_hor = [];
        $cur = preparerRequetePDO($conn_iti, "
            SELECT DISTINCT
                TO_CHAR(n1.NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE_DEPART,
                TO_CHAR(n2.NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE_ARRIVEE
            FROM VIK_NOEUD n1
            JOIN VIK_NOEUD n2 ON TRIM(n1.LIG_NUM) = TRIM(n2.LIG_NUM)
            WHERE n1.COM_CODE_INSEE_ARRET = :from
              AND n2.COM_CODE_INSEE_ARRET = :to
              AND TRIM(n1.LIG_NUM) = :lig
              AND n1.NOE_HEURE_PASSAGE < n2.NOE_HEURE_PASSAGE
            ORDER BY n1.NOE_HEURE_PASSAGE
        ");
        $cur->bindValue(':from', $points[$i],     PDO::PARAM_STR);
        $cur->bindValue(':to',   $points[$i + 1], PDO::PARAM_STR);
        $cur->bindValue(':lig',  $legs[$i],        PDO::PARAM_STR);
        LireDonneesPDOPreparee($cur, $cur_hor);
        $all_leg_horaires[] = $cur_hor;
    }
    $valid_combos = combiner_horaires($all_leg_horaires, 0, '');
}

$nom_dep  = $nom_commune[$dep_iti] ?? '';
$nom_arr  = $nom_commune[$arr_iti] ?? '';
$nom_trans = [];
foreach ($transfers as $t) $nom_trans[$t] = $nom_commune[$t] ?? $t;

$conn_iti = null;

$url_retour = 'lignes.php?depart=' . urlencode($dep_iti) . '&arrivee=' . urlencode($arr_iti);
?>

<div class="container-fluid px-4 mt-5">
    <hr class="mb-4">

    <div class="mb-4">
        <h2 class="fs-3 fw-medium mb-1">Recherche d'itinéraire</h2>
        <p class="text-muted mb-0">Trouvez les trajets disponibles entre deux communes.</p>
    </div>

    <!-- Formulaire toujours visible -->
    <div class="card border rounded-3 p-4 mb-4">
        <form method="GET" action="lignes.php" class="row g-3 align-items-end">
            <input type="hidden" name="ligne" value="<?= htmlspecialchars($selected ?? '1A') ?>">
            <div class="col-md-5">
                <label class="form-label text-uppercase text-muted small fw-semibold">Départ</label>
                <select name="depart" class="form-select">
                    <option value="">-- Choisir une commune --</option>
                    <?php foreach ($communes_iti as $c): ?>
                        <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                            <?= $c['COM_CODE_INSEE'] === $dep_iti ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['COM_NOM']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label text-uppercase text-muted small fw-semibold">Arrivée</label>
                <select name="arrivee" class="form-select">
                    <option value="">-- Choisir une commune --</option>
                    <?php foreach ($communes_iti as $c): ?>
                        <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                            <?= $c['COM_CODE_INSEE'] === $arr_iti ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['COM_NOM']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn w-100 fw-bold rounded-pill"
                        style="background-color: #5d3ebb; color: white;">
                    <i class="bi bi-search me-1"></i> Rechercher
                </button>
            </div>
        </form>
    </div>

    <?php if ($mode === 'erreur'): ?>

        <div class="alert alert-warning">Le départ et l'arrivée ne peuvent pas être identiques.</div>

    <?php elseif ($mode === 'routes'): ?>

        <h3 class="fs-5 fw-semibold mb-1">
            <?= htmlspecialchars($nom_dep) ?>
            <i class="bi bi-arrow-right mx-1 text-muted"></i>
            <?= htmlspecialchars($nom_arr) ?>
        </h3>
        <p class="text-muted small mb-4">Cliquez sur un trajet pour voir ses horaires.</p>

        <?php
        $routes_directes  = array_filter($found_routes, fn($r) => count($r) === 1);
        $routes_corresp   = array_filter($found_routes, fn($r) => count($r) > 1);
        ?>

        <!-- Trajets directs -->
        <p class="text-uppercase text-muted small fw-semibold mb-2">
            Trajets directs
            <span class="badge rounded-pill ms-1 px-2 py-1" style="background-color: #ede9f5; color: #5d3ebb;">
                <?= count($routes_directes) ?>
            </span>
        </p>

        <?php if (empty($routes_directes)): ?>
            <p class="text-muted small mb-4">Aucun trajet direct.</p>
        <?php else: ?>
            <div class="list-group mb-4">
                <?php foreach ($routes_directes as $segs):
                    $parts = [$segs[0]['lig']];
                    $url   = $url_retour . '&route=' . implode(',', array_map('urlencode', $parts));
                ?>
                    <a href="<?= $url ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 mb-2 border px-4 py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge rounded-pill px-3 py-2" style="background-color: #ede9f5; color: #5d3ebb;">
                                Ligne <?= htmlspecialchars($segs[0]['lig']) ?>
                            </span>
                            <span class="text-muted small">Trajet direct</span>
                        </div>
                        <i class="bi bi-chevron-right" style="color: #5d3ebb;"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Trajets avec correspondance(s) -->
        <p class="text-uppercase text-muted small fw-semibold mb-2">
            Trajets avec correspondance
            <span class="badge rounded-pill ms-1 px-2 py-1" style="background-color: #ede9f5; color: #5d3ebb;">
                <?= count($routes_corresp) ?>
            </span>
        </p>

        <?php if (empty($routes_corresp)): ?>
            <p class="text-muted small mb-4">Aucun trajet avec correspondance.</p>
        <?php else: ?>
            <div class="list-group mb-4">
                <?php foreach ($routes_corresp as $segs):
                    $parts = [$segs[0]['lig']];
                    for ($j = 1; $j < count($segs); $j++) {
                        $parts[] = $segs[$j]['from'];
                        $parts[] = $segs[$j]['lig'];
                    }
                    $url = $url_retour . '&route=' . implode(',', array_map('urlencode', $parts));
                ?>
                    <a href="<?= $url ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 mb-2 border px-4 py-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php foreach ($segs as $idx_s => $seg): ?>
                                <?php if ($idx_s > 0): ?>
                                    <i class="bi bi-arrow-right text-muted"></i>
                                    <span class="small fw-semibold">
                                        <?= htmlspecialchars($nom_commune[$seg['from']] ?? $seg['from']) ?>
                                    </span>
                                    <i class="bi bi-arrow-right text-muted"></i>
                                <?php endif; ?>
                                <span class="badge rounded-pill px-3 py-2" style="background-color: #ede9f5; color: #5d3ebb;">
                                    Ligne <?= htmlspecialchars($seg['lig']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <i class="bi bi-chevron-right flex-shrink-0" style="color: #5d3ebb;"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($mode === 'horaires'): ?>

        <div class="mb-3">
            <a href="<?= htmlspecialchars($url_retour) ?>"
               class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left"></i> Retour aux trajets
            </a>
        </div>

        <?php
        // Construire le titre du trajet sélectionné
        $titre_parts = [];
        foreach ($legs as $leg) $titre_parts[] = 'Ligne ' . htmlspecialchars($leg);
        $titre_lignes = implode(' + ', $titre_parts);

        // Construire l'en-tête des colonnes horaires
        $cols = [];
        $points_noms = array_merge([$nom_dep], array_values($nom_trans), [$nom_arr]);
        for ($i = 0; $i < count($legs); $i++) {
            $cols[] = ($i === 0 ? 'Départ' : 'Dép.') . ' ' . ($points_noms[$i] ?? '');
            $cols[] = ($i === count($legs) - 1 ? 'Arrivée' : 'Arr.') . ' ' . ($points_noms[$i + 1] ?? '');
        }
        ?>

        <h3 class="fs-5 fw-semibold mb-1">
            <?= $titre_lignes ?>
        </h3>
        <p class="text-muted small mb-4">
            <?= htmlspecialchars($nom_dep) ?>
            <?php foreach ($transfers as $t): ?>
                <i class="bi bi-arrow-right mx-1"></i>
                <?= htmlspecialchars($nom_commune[$t] ?? $t) ?>
            <?php endforeach; ?>
            <i class="bi bi-arrow-right mx-1"></i>
            <?= htmlspecialchars($nom_arr) ?>
        </p>

        <?php if (empty($valid_combos)): ?>
            <p class="text-muted small">Aucun horaire trouvé pour ce trajet.</p>
        <?php else: ?>
            <div class="card border rounded-3 p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($cols as $col): ?>
                                <th class="py-3 px-4 text-uppercase text-muted small fw-semibold">
                                    <?= htmlspecialchars($col) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valid_combos as $combo): ?>
                        <tr>
                            <?php foreach ($combo as $leg_h): ?>
                                <td class="px-4">
                                    <span class="badge rounded-pill px-3 py-2" style="background-color: #ede9f5; color: #5d3ebb;">
                                        <i class="bi bi-clock small me-1"></i><?= htmlspecialchars($leg_h['HEURE_DEPART']) ?>
                                    </span>
                                </td>
                                <td class="px-4">
                                    <span class="badge rounded-pill px-3 py-2" style="background-color: #ede9f5; color: #5d3ebb;">
                                        <i class="bi bi-clock small me-1"></i><?= htmlspecialchars($leg_h['HEURE_ARRIVEE']) ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
