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
    $dist_cur = 0.0;
    $dur_cur  = 0.0;
    foreach ($hops as $hop) {
        if ($hop['lig'] !== $lig_cur) {
            $segments[] = ['lig' => $lig_cur, 'from' => $from_cur, 'to' => $hop['from'], 'dist' => $dist_cur, 'dur' => $dur_cur];
            $lig_cur  = $hop['lig'];
            $from_cur = $hop['from'];
            $dist_cur = 0.0;
            $dur_cur  = 0.0;
        }
        $dist_cur += $hop['dist'] ?? 0;
        $dur_cur  += $hop['dur']  ?? 0;
    }
    $dernier    = end($hops);
    $segments[] = ['lig' => $lig_cur, 'from' => $from_cur, 'to' => $dernier['to'], 'dist' => $dist_cur, 'dur' => $dur_cur];
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
            $nouveaux_hops = array_merge($hops, [['lig' => $lig, 'from' => $cur, 'to' => $next, 'dist' => $arete['dist'], 'dur' => $arete['dur']]]);
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
        if (!empty($sous)) $results[] = array_merge([$h], $sous[0]); // premiere correspondance disponible uniquement
    }
    return $results;
}

// --- Paramètres GET ---

$dep_iti   = isset($_GET['depart'])  ? trim($_GET['depart'])  : '';
$arr_iti   = isset($_GET['arrivee']) ? trim($_GET['arrivee']) : '';
$route_str = isset($_GET['route'])   ? trim($_GET['route'])   : '';
$tri       = in_array($_GET['tri'] ?? '', ['dist', 'dur']) ? $_GET['tri'] : 'seg';

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

$found_routes      = [];
$route_plus_rapide = null;
$route_plus_courte = null;
$all_leg_horaires  = [];
$valid_combos      = [];

if ($mode === 'routes') {
    $aretes = [];
    $cur_aretes = preparerRequetePDO($conn_iti,
        "SELECT TRIM(LIG_NUM) AS LIG_NUM, COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT,
                AVG(NVL(NOE_DISTANCE_PROCHAIN, 0)) AS DIST,
                AVG(NVL(NOE_DUREE_PROCHAIN, 0)) AS DUR
         FROM VIK_NOEUD
         WHERE COM_CODE_INSEE_SUIVANT IS NOT NULL
         GROUP BY TRIM(LIG_NUM), COM_CODE_INSEE_ARRET, COM_CODE_INSEE_SUIVANT"
    );
    LireDonneesPDOPreparee($cur_aretes, $aretes);
    $graphe = [];
    foreach ($aretes as $a) {
        $graphe[$a['COM_CODE_INSEE_ARRET']][] = [
            'lig'  => $a['LIG_NUM'],
            'to'   => $a['COM_CODE_INSEE_SUIVANT'],
            'dist' => (float)$a['DIST'],
            'dur'  => (float)$a['DUR'],
        ];
    }
    $raw_routes = bfs_itineraires($graphe, $dep_iti, $arr_iti);
    foreach ($raw_routes as $segs) {
        $total_dist = array_sum(array_column($segs, 'dist'));
        $total_dur  = array_sum(array_column($segs, 'dur'));
        $found_routes[] = ['segs' => $segs, 'n_seg' => count($segs), 'dist' => $total_dist, 'dur' => $total_dur];
    }
    usort($found_routes, function($a, $b) use ($tri) {
        if ($tri === 'dist') return $a['dist'] <=> $b['dist'];
        if ($tri === 'dur')  return $a['dur']  <=> $b['dur'];
        return $a['n_seg'] <=> $b['n_seg'];
    });
    foreach ($found_routes as $r) {
        if ($route_plus_rapide === null || $r['dur']  < $route_plus_rapide['dur'])  $route_plus_rapide = $r;
        if ($route_plus_courte === null || $r['dist'] < $route_plus_courte['dist']) $route_plus_courte = $r;
    }
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

$url_retour = 'lignes.php?depart=' . urlencode($dep_iti) . '&arrivee=' . urlencode($arr_iti) . '&tri=' . urlencode($tri);
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
            style="background-color: #ba181b; color: white;">
        <i class="bi bi-search me-1"></i> Rechercher
    </button>
</form>

<?php if ($mode === 'erreur'): ?>

    <div class="alert alert-warning small p-2 mb-0">Départ et arrivée identiques.</div>

<?php elseif ($mode === 'routes'): ?>

    <?php
    $routes_directes = array_values(array_filter($found_routes, fn($r) => $r['n_seg'] === 1));
    $routes_corresp  = array_values(array_filter($found_routes, fn($r) => $r['n_seg'] > 1));

    function route_url($dep, $arr, $segs, $extra = []) {
        $parts = [$segs[0]['lig']];
        for ($j = 1; $j < count($segs); $j++) { $parts[] = $segs[$j]['from']; $parts[] = $segs[$j]['lig']; }
        $url = 'lignes.php?depart=' . urlencode($dep) . '&arrivee=' . urlencode($arr) . '&route=' . implode(',', array_map('urlencode', $parts));
        foreach ($extra as $k => $v) $url .= '&' . urlencode($k) . '=' . urlencode($v);
        return $url;
    }

    function fmt_dur($min) {
        $min = (int)round($min);
        if ($min <= 0) return '—';
        if ($min < 60) return $min . ' min';
        return floor($min / 60) . 'h' . ($min % 60 ? sprintf('%02d', $min % 60) : '');
    }

    function render_route_segs($segs, $nom_dep_r, $nom_arr_r, $nom_commune_r) {
        $html = '<div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:0.78rem;">';
        $html .= '<span class="fw-semibold">' . htmlspecialchars($nom_dep_r) . '</span>';
        foreach ($segs as $idx_s => $seg) {
            $html .= '<i class="bi bi-arrow-right text-muted"></i>';
            $html .= '<span class="badge rounded-pill px-2" style="background-color:#fde8e8;color:#ba181b;">L.' . htmlspecialchars($seg['lig']) . '</span>';
            if ($idx_s < count($segs) - 1) {
                $html .= '<i class="bi bi-arrow-right text-muted"></i>';
                $html .= '<span class="fw-semibold">' . htmlspecialchars($nom_commune_r[$seg['to']] ?? $seg['to']) . '</span>';
            }
        }
        $html .= '<i class="bi bi-arrow-right text-muted"></i>';
        $html .= '<span class="fw-semibold">' . htmlspecialchars($nom_arr_r) . '</span>';
        $html .= '</div>';
        return $html;
    }
    ?>

    <div class="border-top pt-3">
        <p class="small fw-semibold mb-1">
            <?= htmlspecialchars($nom_dep) ?>
            <i class="bi bi-arrow-right text-muted mx-1"></i>
            <?= htmlspecialchars($nom_arr) ?>
        </p>

        <!-- Tri P21 -->
        <div class="d-flex gap-1 mb-3">
            <a href="lignes.php?depart=<?= urlencode($dep_iti) ?>&arrivee=<?= urlencode($arr_iti) ?>&tri=seg"
               class="tri-btn btn btn-sm rounded-pill px-3 <?= $tri === 'seg'  ? 'text-white' : 'btn-outline-secondary' ?>"
               style="<?= $tri === 'seg'  ? 'background-color:#ba181b;' : '' ?>font-size:0.75rem;">
                <i class="bi bi-signpost-split"></i> Correspondances
            </a>
            <a href="lignes.php?depart=<?= urlencode($dep_iti) ?>&arrivee=<?= urlencode($arr_iti) ?>&tri=dur"
               class="tri-btn btn btn-sm rounded-pill px-3 <?= $tri === 'dur'  ? 'text-white' : 'btn-outline-secondary' ?>"
               style="<?= $tri === 'dur'  ? 'background-color:#ba181b;' : '' ?>font-size:0.75rem;">
                <i class="bi bi-clock"></i> Durée
            </a>
            <a href="lignes.php?depart=<?= urlencode($dep_iti) ?>&arrivee=<?= urlencode($arr_iti) ?>&tri=dist"
               class="tri-btn btn btn-sm rounded-pill px-3 <?= $tri === 'dist' ? 'text-white' : 'btn-outline-secondary' ?>"
               style="<?= $tri === 'dist' ? 'background-color:#ba181b;' : '' ?>font-size:0.75rem;">
                <i class="bi bi-rulers"></i> Distance
            </a>
        </div>

        <!-- P19 : Plus rapide -->
        <?php if ($route_plus_rapide !== null): ?>
        <a href="<?= route_url($dep_iti, $arr_iti, $route_plus_rapide['segs']) ?>" class="text-decoration-none text-dark d-block rounded-3 mb-2 px-3 py-2 border" style="border-color:#ba181b !important; background-color:#fff5f5;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-uppercase fw-bold" style="font-size:0.65rem; color:#ba181b;">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Plus rapide
                </span>
                <span class="fw-semibold" style="font-size:0.78rem; color:#ba181b;">
                    <?= fmt_dur($route_plus_rapide['dur']) ?> &middot; <?= round($route_plus_rapide['dist'], 1) ?> km
                </span>
            </div>
            <?= render_route_segs($route_plus_rapide['segs'], $nom_dep, $nom_arr, $nom_commune) ?>
        </a>
        <?php endif; ?>

        <!-- P20 : Plus courte -->
        <?php if ($route_plus_courte !== null && $route_plus_courte !== $route_plus_rapide): ?>
        <a href="<?= route_url($dep_iti, $arr_iti, $route_plus_courte['segs']) ?>" class="text-decoration-none text-dark d-block rounded-3 mb-3 px-3 py-2 border" style="border-color:#1f2c3a !important; background-color:#f0f2f5;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-uppercase fw-bold" style="font-size:0.65rem; color:#1f2c3a;">
                    <i class="bi bi-geo-alt-fill me-1"></i>Plus courte
                </span>
                <span class="fw-semibold" style="font-size:0.78rem; color:#1f2c3a;">
                    <?= fmt_dur($route_plus_courte['dur']) ?> &middot; <?= round($route_plus_courte['dist'], 1) ?> km
                </span>
            </div>
            <?= render_route_segs($route_plus_courte['segs'], $nom_dep, $nom_arr, $nom_commune) ?>
        </a>
        <?php endif; ?>

        <p class="text-muted mb-2" style="font-size: 0.72rem;">Cliquez sur un trajet pour voir les horaires.</p>

        <!-- Directs -->
        <p class="text-uppercase text-muted small fw-semibold mb-1">
            Directs
            <span class="badge rounded-pill ms-1 px-2" style="background-color: #f1f3f5; color: #1f2c3a;"><?= count($routes_directes) ?></span>
        </p>
        <?php if (empty($routes_directes)): ?>
            <p class="text-muted small mb-3">Aucun trajet direct.</p>
        <?php else: ?>
            <div class="list-group mb-3">
                <?php foreach ($routes_directes as $r):
                    $segs = $r['segs'];
                    $url  = route_url($dep_iti, $arr_iti, $segs);
                ?>
                    <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                        <?= render_route_segs($segs, $nom_dep, $nom_arr, $nom_commune) ?>
                        <div class="text-muted mt-1" style="font-size:0.7rem;">
                            <?= fmt_dur($r['dur']) ?> &middot; <?= round($r['dist'], 1) ?> km
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
            <span class="badge rounded-pill ms-1 px-2" style="background-color: #f1f3f5; color: #1f2c3a;"><?= count($routes_corresp) ?></span>
        </p>
        <?php if (empty($routes_corresp)): ?>
            <p class="text-muted small mb-2">Aucune correspondance.</p>
        <?php else: ?>
            <div class="list-group mb-2">
                <?php foreach ($corresp_visibles as $r):
                    $segs = $r['segs'];
                    $url  = route_url($dep_iti, $arr_iti, $segs);
                ?>
                    <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                        <?= render_route_segs($segs, $nom_dep, $nom_arr, $nom_commune) ?>
                        <div class="text-muted mt-1" style="font-size:0.7rem;">
                            <?= fmt_dur($r['dur']) ?> &middot; <?= round($r['dist'], 1) ?> km
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($corresp_cachees)): ?>
                <div class="collapse" id="plusCorrespondances">
                    <div class="list-group mb-2">
                        <?php foreach ($corresp_cachees as $r):
                            $segs = $r['segs'];
                            $url  = route_url($dep_iti, $arr_iti, $segs);
                        ?>
                            <a href="<?= $url ?>" class="list-group-item list-group-item-action rounded-3 mb-1 border px-3 py-2">
                                <?= render_route_segs($segs, $nom_dep, $nom_arr, $nom_commune) ?>
                                <div class="text-muted mt-1" style="font-size:0.7rem;">
                                    <?= fmt_dur($r['dur']) ?> &middot; <?= round($r['dist'], 1) ?> km
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
        $points_noms = array_merge([$nom_dep], array_values($nom_trans), [$nom_arr]);
        ?>

        <?php if (empty($valid_combos)): ?>
            <p class="text-muted small">Aucun horaire trouvé.</p>
        <?php else: ?>
            <?php foreach ($valid_combos as $combo):
                $heure_dep_globale = $combo[0]['HEURE_DEPART'];
                $heure_arr_globale = $combo[count($combo) - 1]['HEURE_ARRIVEE'];
            ?>
            <div class="border rounded-3 mb-2 px-3 py-2">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fw-bold fs-5" style="color: #ba181b;"><?= htmlspecialchars($heure_dep_globale) ?></span>
                    <div class="flex-grow-1" style="height: 2px; background: linear-gradient(to right, #ba181b, #f1f3f5);"></div>
                    <i class="bi bi-arrow-right fw-bold" style="color: #ba181b;"></i>
                    <span class="fw-bold fs-5" style="color: #ba181b;"><?= htmlspecialchars($heure_arr_globale) ?></span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size: 0.75rem;">
                    <span class="fw-semibold"><?= htmlspecialchars($points_noms[0]) ?></span>
                    <?php foreach ($legs as $idx_l => $leg): ?>
                        <i class="bi bi-arrow-right text-muted" style="font-size: 0.65rem;"></i>
                        <span class="badge px-2 py-1" style="background-color: #1f2c3a; color: #ffffff;"><?= htmlspecialchars($leg) ?></span>
                        <i class="bi bi-arrow-right text-muted" style="font-size: 0.65rem;"></i>
                        <span class="fw-semibold"><?= htmlspecialchars($points_noms[$idx_l + 1]) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (count($combo) > 1): ?>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;">
                        <?php foreach ($combo as $idx_c => $leg_h):
                            if ($idx_c === count($combo) - 1) break; ?>
                            Corresp. <?= htmlspecialchars($points_noms[$idx_c + 1]) ?>
                            &middot; arr. <?= htmlspecialchars($leg_h['HEURE_ARRIVEE']) ?>
                            / dép. <?= htmlspecialchars($combo[$idx_c + 1]['HEURE_DEPART']) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<script>
(function() {
    var saved = sessionStorage.getItem('iti_scroll');
    if (saved !== null) {
        sessionStorage.removeItem('iti_scroll');
        window.scrollTo(0, parseInt(saved));
    }
    document.querySelectorAll('.tri-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            sessionStorage.setItem('iti_scroll', window.scrollY);
        });
    });
})();
</script>
