<?php
// includes/itineraire.php inclus depuis frontend/lignes.php

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) return;

$conn_iti = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
if ($conn_iti === null) return;

$communes_iti = [];
$cur_communes = preparerRequetePDO($conn_iti, "SELECT COM_CODE_INSEE, COM_NOM FROM VIK_COMMUNE ORDER BY COM_NOM");
LireDonneesPDOPreparee($cur_communes, $communes_iti);

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
    $cur_aretes = preparerRequetePDO($conn_iti,
        "SELECT DISTINCT TRIM(LIG_NUM) AS LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT
         FROM VIK_NOEUD WHERE COM_CODE_INSEE_SUIVANT IS NOT NULL"
    );
    LireDonneesPDOPreparee($cur_aretes, $aretes);
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
            WITH trip_chain(LIG_CUR, HEURE_DEP, HEURE_CUR, SUIVANT_CUR, DUREE_CUR) AS (
                SELECT TRIM(LIG_NUM), NOE_HEURE_PASSAGE, NOE_HEURE_PASSAGE,
                       COM_CODE_INSEE_SUIVANT, NOE_DUREE_PROCHAIN
                FROM VIK_NOEUD
                WHERE COM_CODE_INSEE_ARRET = :dep
                  AND TRIM(LIG_NUM) = :lig
                UNION ALL
                SELECT t.LIG_CUR, t.HEURE_DEP, n.NOE_HEURE_PASSAGE,
                       n.COM_CODE_INSEE_SUIVANT, n.NOE_DUREE_PROCHAIN
                FROM VIK_NOEUD n
                JOIN trip_chain t ON TRIM(n.LIG_NUM) = t.LIG_CUR
                    AND n.COM_CODE_INSEE_ARRET = t.SUIVANT_CUR
                    AND n.NOE_HEURE_PASSAGE = t.HEURE_CUR + t.DUREE_CUR / 1440
            )
            SELECT DISTINCT
                TO_CHAR(HEURE_DEP, 'HH24:MI') AS HEURE_DEPART,
                TO_CHAR(HEURE_CUR + DUREE_CUR / 1440, 'HH24:MI') AS HEURE_ARRIVEE
            FROM trip_chain
            WHERE SUIVANT_CUR = :arr
            ORDER BY 1
        ");
        $cur->bindValue(':dep', $points[$i],     PDO::PARAM_STR);
        $cur->bindValue(':arr', $points[$i + 1], PDO::PARAM_STR);
        $cur->bindValue(':lig', $legs[$i],        PDO::PARAM_STR);
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

<p class="text-uppercase text-muted small fw-bold mb-3">
    <i class="bi bi-map-fill me-2 text-danger"></i>Recherche itinéraire
</p>

<form method="GET" action="lignes.php" class="mb-3">
    <input type="hidden" name="ligne" value="<?= htmlspecialchars($selected ?? '1A') ?>">
    <div class="mb-2">
        <label class="form-label text-muted small fw-semibold mb-1">Départ</label>
        <select name="depart" class="form-select form-select-sm">
            <option value="">-- Commune --</option>
            <?php foreach ($communes_iti as $c): ?>
                <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                    <?= $c['COM_CODE_INSEE'] === $dep_iti ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['COM_NOM']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label text-muted small fw-semibold mb-1">Arrivée</label>
        <select name="arrivee" class="form-select form-select-sm">
            <option value="">-- Commune --</option>
            <?php foreach ($communes_iti as $c): ?>
                <option value="<?= htmlspecialchars($c['COM_CODE_INSEE']) ?>"
                    <?= $c['COM_CODE_INSEE'] === $arr_iti ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['COM_NOM']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn w-100 btn-sm fw-bold rounded-pill"
            style="background-color: #5d3ebb; color: white;">
        <i class="bi bi-search me-1"></i> Rechercher
    </button>
</form>

<?php if ($mode === 'erreur'): ?>

    <div class="alert alert-warning small p-2 mb-0">Départ et arrivée identiques.</div>

<?php elseif ($mode === 'routes'): ?>

    <?php
    $routes_directes = array_values(array_filter($found_routes, fn($r) => count($r) === 1));
    $routes_corresp  = array_values(array_filter($found_routes, fn($r) => count($r) > 1));
    ?>

    <div class="border-top pt-3">
        <p class="small fw-semibold mb-0">
            <?= htmlspecialchars($nom_dep) ?>
            <i class="bi bi-arrow-right text-muted mx-1"></i>
            <?= htmlspecialchars($nom_arr) ?>
        </p>
        <p class="text-muted mb-3" style="font-size: 0.75rem;">Cliquez pour voir les horaires.</p>

        <!-- Directs -->
        <p class="text-uppercase text-muted small fw-semibold mb-1">
            Directs
            <span class="badge rounded-pill ms-1 px-2" style="background-color: #ede9f5; color: #5d3ebb;"><?= count($routes_directes) ?></span>
        </p>
        <?php if (empty($routes_directes)): ?>
            <p class="text-muted small mb-3">Aucun trajet direct.</p>
        <?php else: ?>
            <div class="list-group mb-3">
                <?php foreach ($routes_directes as $segs):
                    $url = 'lignes.php?depart=' . urlencode($dep_iti) . '&arrivee=' . urlencode($arr_iti) . '&route=' . urlencode($segs[0]['lig']);
                ?>
                    <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size: 0.8rem;">
                            <span class="fw-semibold"><?= htmlspecialchars($nom_dep) ?></span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="badge rounded-pill px-2" style="background-color: #ede9f5; color: #5d3ebb;">Ligne <?= htmlspecialchars($segs[0]['lig']) ?></span>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="fw-semibold"><?= htmlspecialchars($nom_arr) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Correspondances -->
        <?php
        $corresp_visibles = array_slice($routes_corresp, 0, 3);
        $corresp_cachees  = array_slice($routes_corresp, 3);
        ?>
        <p class="text-uppercase text-muted small fw-semibold mb-1">
            Correspondances
            <span class="badge rounded-pill ms-1 px-2" style="background-color: #ede9f5; color: #5d3ebb;"><?= count($routes_corresp) ?></span>
        </p>
        <?php if (empty($routes_corresp)): ?>
            <p class="text-muted small mb-2">Aucune correspondance.</p>
        <?php else: ?>
            <div class="list-group mb-2">
                <?php foreach ($corresp_visibles as $segs):
                    $parts = [$segs[0]['lig']];
                    for ($j = 1; $j < count($segs); $j++) { $parts[] = $segs[$j]['from']; $parts[] = $segs[$j]['lig']; }
                    $url = 'lignes.php?depart=' . urlencode($dep_iti) . '&arrivee=' . urlencode($arr_iti) . '&route=' . implode(',', array_map('urlencode', $parts));
                ?>
                    <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size: 0.8rem;">
                            <span class="fw-semibold"><?= htmlspecialchars($nom_dep) ?></span>
                            <?php foreach ($segs as $idx_s => $seg): ?>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge rounded-pill px-2" style="background-color: #ede9f5; color: #5d3ebb;">Ligne <?= htmlspecialchars($seg['lig']) ?></span>
                                <?php if ($idx_s < count($segs) - 1): ?>
                                    <i class="bi bi-arrow-right text-muted"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars($nom_commune[$seg['to']] ?? $seg['to']) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <i class="bi bi-arrow-right text-muted"></i>
                            <span class="fw-semibold"><?= htmlspecialchars($nom_arr) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($corresp_cachees)): ?>
                <div class="collapse" id="plusCorrespondances">
                    <div class="list-group mb-2">
                        <?php foreach ($corresp_cachees as $segs):
                            $parts = [$segs[0]['lig']];
                            for ($j = 1; $j < count($segs); $j++) { $parts[] = $segs[$j]['from']; $parts[] = $segs[$j]['lig']; }
                            $url = 'lignes.php?depart=' . urlencode($dep_iti) . '&arrivee=' . urlencode($arr_iti) . '&route=' . implode(',', array_map('urlencode', $parts));
                        ?>
                            <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                                <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size: 0.8rem;">
                                    <span class="fw-semibold"><?= htmlspecialchars($nom_dep) ?></span>
                                    <?php foreach ($segs as $idx_s => $seg): ?>
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <span class="badge rounded-pill px-2" style="background-color: #ede9f5; color: #5d3ebb;">Ligne <?= htmlspecialchars($seg['lig']) ?></span>
                                        <?php if ($idx_s < count($segs) - 1): ?>
                                            <i class="bi bi-arrow-right text-muted"></i>
                                            <span class="fw-semibold"><?= htmlspecialchars($nom_commune[$seg['to']] ?? $seg['to']) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <i class="bi bi-arrow-right text-muted"></i>
                                    <span class="fw-semibold"><?= htmlspecialchars($nom_arr) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm rounded-pill w-100 mt-1"
                        data-bs-toggle="collapse" data-bs-target="#plusCorrespondances">
                    Voir plus (<?= count($corresp_cachees) ?> autres)
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php elseif ($mode === 'horaires'): ?>

    <div class="border-top pt-3">
        <a href="<?= htmlspecialchars($url_retour) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 mb-3">
            <i class="bi bi-arrow-left"></i> Retour
        </a>

        <?php
        $titre_parts = [];
        foreach ($legs as $leg) $titre_parts[] = 'Ligne ' . htmlspecialchars($leg);
        $titre_lignes = implode(' + ', $titre_parts);

        $cols = [];
        $points_noms = array_merge([$nom_dep], array_values($nom_trans), [$nom_arr]);
        for ($i = 0; $i < count($legs); $i++) {
            $cols[] = ($i === 0 ? 'Dép.' : 'Dép.') . ' ' . ($points_noms[$i] ?? '');
            $cols[] = ($i === count($legs) - 1 ? 'Arr.' : 'Arr.') . ' ' . ($points_noms[$i + 1] ?? '');
        }
        ?>

        <p class="small fw-semibold mb-0"><?= $titre_lignes ?></p>
        <p class="text-muted mb-3" style="font-size: 0.75rem;">
            <?= htmlspecialchars($nom_dep) ?>
            <?php foreach ($transfers as $t): ?>
                <i class="bi bi-arrow-right mx-1"></i><?= htmlspecialchars($nom_commune[$t] ?? $t) ?>
            <?php endforeach; ?>
            <i class="bi bi-arrow-right mx-1"></i><?= htmlspecialchars($nom_arr) ?>
        </p>

        <?php if (empty($valid_combos)): ?>
            <p class="text-muted small">Aucun horaire trouvé.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($cols as $col): ?>
                                <th class="py-2 px-3 text-uppercase text-muted fw-semibold" style="font-size: 0.7rem; white-space: nowrap;">
                                    <?= htmlspecialchars($col) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valid_combos as $combo): ?>
                        <tr>
                            <?php foreach ($combo as $leg_h): ?>
                                <td class="px-3">
                                    <span class="badge rounded-pill px-2 py-1" style="background-color: #ede9f5; color: #5d3ebb;">
                                        <i class="bi bi-clock me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($leg_h['HEURE_DEPART']) ?>
                                    </span>
                                </td>
                                <td class="px-3">
                                    <span class="badge rounded-pill px-2 py-1" style="background-color: #ede9f5; color: #5d3ebb;">
                                        <i class="bi bi-clock me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($leg_h['HEURE_ARRIVEE']) ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
