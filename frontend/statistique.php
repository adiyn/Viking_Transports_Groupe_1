<?php
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../config/param_connexion.php';
    require_once __DIR__ . '/../config/pdo_agile.php';

    if(!isset($_SESSION['utilisateur']) || ($_SESSION['utilisateur']['role'] ?? '') !== 'admin'){
        header('Location: connexion.php');
        exit;
    }

    $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
 
    if (!$conn) {
        die("<div class='container mt-5'><div class='alert alert-danger'>Erreur de connexion à la base de données.</div>");
    }

    $sqlLignes = "SELECT e.lig_num, cd.com_nom as nom_depart, ca.com_nom as nom_arrivee, COUNT(*) as nb_voyage
    from vik_etape e
    join vik_ligne l on TRIM(l.lig_num) = TRIM(e.lig_num)
    join vik_commune cd on cd.com_code_insee = l.com_code_insee_debu
    join vik_commune ca on ca.com_code_insee = l.com_code_insee_term
    group by e.lig_num, cd.com_nom, ca.com_nom
    order by nb_voyage desc
    fetch first 10 rows only";

    $curLignes = preparerRequetePDO($conn, $sqlLignes);
    $curLignes->execute();
    $lignes = $curLignes->fetchAll(PDO::FETCH_ASSOC);

    $sqlFideles = " SELECT c.cli_num, c.cli_nom, c.cli_prenom, c.cli_ville, t.typ_nom as type_client, c.cli_nb_points_tot, COUNT(r.res_num) as nb_reservations
        from vik_client c
        join vik_type_client t on t.typ_num = c.typ_num
        left join vik_reservations r on r.cli_num = c.cli_num
        where c.cli_num <> 0
        group by c.cli_num, c.cli_nom, c.cli_prenom, c.cli_ville, t.typ_nom, c.cli_nb_points_tot
        order by c.cli_nb_points_tot desc
        fetch first 10 rows only";

    $curFideles = preparerRequetePDO($conn, $sqlFideles);
    $curFideles->execute();
    $fideles = $curFideles->fetchAll(PDO::FETCH_ASSOC);

    $sqlCA = " SELECT to_char(res_date, 'MM/YYYY') as Mois, to_char(res_date, 'YYYY-MM') as mois_tri, SUM(res_prix_tot) as CA
        from vik_reservations
        where res_date >= add_months(sysdate, -12)
        group by to_char(res_date, 'MM/YYYY'), to_char(res_date, 'YYYY-MM')
        order by mois_tri asc";

    $curCA = preparerRequetePDO($conn, $sqlCA);
    $curCA->execute();
    $caParMois = $curCA->fetchAll(PDO::FETCH_ASSOC);

    $sqlResume = " SELECT (SELECT COUNT(*) from vik_client where cli_num <> 0) as nb_clients,
            (SELECT COUNT(*) from vik_reservations) as nb_reservations,
            (SELECT nvl(SUM(res_prix_tot), 0) from vik_reservations) as ca_total,
            (SELECT COUNT(*) from vik_ligne) as nb_lignes
            from dual";

    $curResume = preparerRequetePDO($conn, $sqlResume);
    $curResume->execute();
    $resume = $curResume->fetch(PDO::FETCH_ASSOC);
    
    $conn = null;
?>

<main class="container my-5 flex-grow-1">
 
    <h2 class="fw-bold mb-1" style="color: #2b2438;">
        <i class="bi bi-bar-chart-line-fill me-2" style="color: #5d3ebb;"></i>
        Tableau de bord statistiques
    </h2>
    <p class="text-muted mb-5">Vue d'ensemble de l'activité Viking Transport</p>
 
    <div class="row g-4 mb-5">
 
        <!-- Nb clients -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mb-2" style="color: #5d3ebb; font-size: 2rem;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="fw-bold fs-3 text-dark">
                    <?= number_format((int)($resume['NB_CLIENTS'] ?? 0)) ?>
                </div>
                <div class="text-muted small">Clients inscrits</div>
            </div>
        </div>
 
        <!-- Nb réservations -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mb-2" style="color: #5d3ebb; font-size: 2rem;">
                    <i class="bi bi-ticket-perforated-fill"></i>
                </div>
                <div class="fw-bold fs-3 text-dark">
                    <?= number_format((int)($resume['NB_RESERVATIONS'] ?? 0)) ?>
                </div>
                <div class="text-muted small">Réservations totales</div>
            </div>
        </div>
 
        <!-- CA total -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mb-2" style="color: #5d3ebb; font-size: 2rem;">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="fw-bold fs-3 text-dark">
                    <?= number_format((float)($resume['CA_TOTAL'] ?? 0), 0, ',', ' ') ?> €
                </div>
                <div class="text-muted small">Chiffre d'affaires total</div>
            </div>
        </div>
 
        <!-- Nb lignes -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mb-2" style="color: #5d3ebb; font-size: 2rem;">
                    <i class="bi bi-signpost-split-fill"></i>
                </div>
                <div class="fw-bold fs-3 text-dark">
                    <?= (int)($resume['NB_LIGNES'] ?? 0) ?>
                </div>
                <div class="text-muted small">Lignes actives</div>
            </div>
        </div>
 
    </div><!-- fin row chiffres clés -->
 
    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body p-4">
 
            <h5 class="fw-bold mb-1" style="color: #2b2438;">
                <i class="bi bi-graph-up-arrow me-2" style="color: #5d3ebb;"></i>
                Lignes les plus empruntées
            </h5>
            <p class="text-muted small mb-4">Top 10 des lignes par nombre de voyages</p>
 
            <?php if (empty($lignes)): ?>
                <p class="text-muted fst-italic">Aucune donnée disponible.</p>
            <?php else: ?>
 
                <?php
                // On récupère le max pour calculer la largeur des barres en %
                $maxVoyages = (int)$lignes[0]['NB_VOYAGES'];
                ?>
 
                <?php foreach ($lignes as $i => $lig): ?>
                    <?php
                    $pct = $maxVoyages > 0
                        ? round((int)$lig['NB_VOYAGES'] / $maxVoyages * 100)
                        : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold text-dark">
                                <!-- Badge numéro de ligne -->
                                <span class="badge rounded-pill me-2 px-3 py-1 fw-bold"
                                      style="background-color: #ede9f5; color: #5d3ebb;">
                                    <?= htmlspecialchars(trim($lig['LIG_NUM'])) ?>
                                </span>
                                <?= htmlspecialchars($lig['NOM_DEPART']) ?>
                                <i class="bi bi-arrow-right mx-1 opacity-50"></i>
                                <?= htmlspecialchars($lig['NOM_ARRIVEE']) ?>
                            </span>
                            <span class="text-muted small fw-semibold">
                                <?= (int)$lig['NB_VOYAGES'] ?> voyage<?= (int)$lig['NB_VOYAGES'] > 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <!-- Barre de progression -->
                        <div class="progress rounded-pill" style="height: 10px; background-color: #ede9f5;">
                            <div class="progress-bar rounded-pill"
                                 style="width: <?= $pct ?>%; background-color: #5d3ebb;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
 
            <?php endif; ?>
        </div>
    </div>
 
    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body p-4">
 
            <h5 class="fw-bold mb-1" style="color: #2b2438;">
                <i class="bi bi-trophy-fill me-2" style="color: #5d3ebb;"></i>
                Clients les plus fidèles
            </h5>
            <p class="text-muted small mb-4">Top 10 par points cumulés depuis l'inscription</p>
 
            <?php if (empty($fideles)): ?>
                <p class="text-muted fst-italic">Aucune donnée disponible.</p>
            <?php else: ?>
 
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted small fw-semibold text-uppercase py-3">Rang</th>
                                <th class="text-muted small fw-semibold text-uppercase py-3">Client</th>
                                <th class="text-muted small fw-semibold text-uppercase py-3">Ville</th>
                                <th class="text-muted small fw-semibold text-uppercase py-3">Niveau</th>
                                <th class="text-muted small fw-semibold text-uppercase py-3 text-end">Points totaux</th>
                                <th class="text-muted small fw-semibold text-uppercase py-3 text-end">Réservations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fideles as $rang => $cli): ?>
                                <tr>
                                    <!-- Médaille pour le top 3, numéro sinon -->
                                    <td class="fw-bold text-center" style="width: 50px;">
                                        <?php if ($rang === 0): ?>
                                            <i class="bi bi-trophy-fill" style="color: #FFD700; font-size: 1.2rem;" title="1er"></i>
                                        <?php elseif ($rang === 1): ?>
                                            <i class="bi bi-trophy-fill" style="color: #C0C0C0; font-size: 1.1rem;" title="2ème"></i>
                                        <?php elseif ($rang === 2): ?>
                                            <i class="bi bi-trophy-fill" style="color: #CD7F32; font-size: 1rem;" title="3ème"></i>
                                        <?php else: ?>
                                            <span class="text-muted"><?= $rang + 1 ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <?= htmlspecialchars($cli['CLI_PRENOM'] . ' ' . $cli['CLI_NOM']) ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= htmlspecialchars($cli['CLI_VILLE'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-1"
                                              style="background-color: #ede9f5; color: #5d3ebb;">
                                            <?= htmlspecialchars($cli['TYPE_CLIENT']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold" style="color: #5d3ebb;">
                                        <?= number_format((int)$cli['CLI_NB_POINTS_TOT']) ?> pts
                                    </td>
                                    <td class="text-end text-muted">
                                        <?= (int)$cli['NB_RESERVATIONS'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
 
            <?php endif; ?>
        </div>
    </div>
 
    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body p-4">
 
            <h5 class="fw-bold mb-1" style="color: #2b2438;">
                <i class="bi bi-calendar3 me-2" style="color: #5d3ebb;"></i>
                Chiffre d'affaires mensuel
            </h5>
            <p class="text-muted small mb-4">12 derniers mois (en €)</p>
 
            <?php if (empty($caParMois)): ?>
                <p class="text-muted fst-italic">Aucune donnée disponible.</p>
            <?php else: ?>
 
                <?php
                // Prépare les données en JSON pour le graphique JS
                $labels = [];
                $valeurs = [];
                $caMax = 0;
                foreach ($caParMois as $row) {
                    $labels[]  = $row['MOIS'];
                    $valeurs[] = (float)$row['CA'];
                    if ((float)$row['CA'] > $caMax) $caMax = (float)$row['CA'];
                }
                ?>
 
                <!-- Graphique en barres CSS -->
                <div class="d-flex align-items-flex-end gap-2 mt-3" style="height: 200px;">
                    <?php foreach ($caParMois as $i => $row): ?>
                        <?php
                        $pct = $caMax > 0
                            ? round((float)$row['CA'] / $caMax * 100)
                            : 0;
                        ?>
                        <div class="d-flex flex-column align-items-center flex-grow-1"
                             style="height: 100%;">
                            
                            <div> class="flex-grow-1 d-flex align-items-end w-100"
                                 title="<?= htmlspecialchars($row['MOIS']) ?> : <?= number_format((float)$row['CA'], 0, ',', ' ') ?> €">
                                <div> class="w-100 rounded-top-3"
                                     style="height: <?= $pct ?>%; min-height: 4px; background-color: #5d3ebb; opacity: 0.75; transition: opacity 0.2s;"
                                </div>
                            </div>
                            <!-- Mois -->
                            <div class="text-muted mt-1"
                                 style="font-size: 0.65rem; white-space: nowrap;">
                                <?= htmlspecialchars($row['MOIS']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
 
                <!-- Légende des valeurs -->
                <div class="d-flex flex-wrap gap-3 mt-3">
                    <?php foreach ($caParMois as $row): ?>
                        <span class="badge rounded-pill px-3 py-2 fw-normal"
                              style="background-color: #ede9f5; color: #5d3ebb; font-size: 0.8rem;">
                            <?= htmlspecialchars($row['MOIS']) ?> :
                            <strong><?= number_format((float)$row['CA'], 0, ',', ' ') ?> €</strong>
                        </span>
                    <?php endforeach; ?>
                </div>
 
            <?php endif; ?>
        </div>
    </div>
 
</main>
 
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
