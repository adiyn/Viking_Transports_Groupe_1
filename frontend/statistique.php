<?php

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/param_connexion.php';
require_once __DIR__ . '/../config/pdo_agile.php';

// Sécurité : seul un admin peut accéder à cette page
if (!isset($_SESSION['utilisateur']) || ($_SESSION['utilisateur']['role'] ?? '') !== 'admin') {
    header('Location: connexion.php');
    exit;
}

$conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);

if (!$conn) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Erreur de connexion à la base de données.</div></div>");
}

$sqlLignes = "
    SELECT e.LIG_NUM,
           cd.COM_NOM AS NOM_DEPART,
           ca.COM_NOM AS NOM_ARRIVEE,
           COUNT(*) AS NB_VOYAGES
    FROM VIK_ETAPE e
    JOIN VIK_COMMUNE cd ON cd.COM_CODE_INSEE = e.COM_CODE_INSEE_DEPART
    JOIN VIK_COMMUNE ca ON ca.COM_CODE_INSEE = e.COM_CODE_INSEE_ARRIVEE
    GROUP BY e.LIG_NUM, cd.COM_NOM, ca.COM_NOM
    ORDER BY NB_VOYAGES DESC
    FETCH FIRST 10 ROWS ONLY
";
$curLignes = preparerRequetePDO($conn, $sqlLignes);
$curLignes->execute();
$lignes = $curLignes->fetchAll(PDO::FETCH_ASSOC);

$sqlFideles = "
    SELECT c.CLI_NUM,
           c.CLI_NOM,
           c.CLI_PRENOM,
           c.CLI_VILLE,
           t.TYP_NOM AS TYPE_CLIENT,
           c.CLI_NB_POINTS_TOT,
           COUNT(r.RES_NUM) AS NB_RESERVATIONS
    FROM VIK_CLIENT c
    JOIN VIK_TYPE_CLIENT t ON t.TYP_NUM = c.TYP_NUM
    LEFT JOIN VIK_RESERVATION r ON r.CLI_NUM = c.CLI_NUM
    WHERE c.CLI_NUM <> 0
      AND NVL(c.CLI_NB_POINTS_TOT, 0) > 0
    GROUP BY c.CLI_NUM, c.CLI_NOM, c.CLI_PRENOM, c.CLI_VILLE, t.TYP_NOM, c.CLI_NB_POINTS_TOT
    ORDER BY NVL(c.CLI_NB_POINTS_TOT, 0) DESC
    FETCH FIRST 10 ROWS ONLY
";
$curFideles = preparerRequetePDO($conn, $sqlFideles);
$curFideles->execute();
$fideles = $curFideles->fetchAll(PDO::FETCH_ASSOC);

$sqlCA = "
    SELECT TO_CHAR(RES_DATE, 'MM/YYYY') AS MOIS,
           TO_CHAR(RES_DATE, 'YYYY-MM') AS MOIS_TRI,
           SUM(RES_PRIX_TOT) AS CA
    FROM VIK_RESERVATION
    WHERE RES_DATE >= ADD_MONTHS(SYSDATE, -12)
    GROUP BY TO_CHAR(RES_DATE, 'MM/YYYY'), TO_CHAR(RES_DATE, 'YYYY-MM')
    ORDER BY MOIS_TRI ASC
";
$curCA = preparerRequetePDO($conn, $sqlCA);
$curCA->execute();
$caParMois = $curCA->fetchAll(PDO::FETCH_ASSOC);

$sqlResume = "
    SELECT
        (SELECT COUNT(*) FROM VIK_CLIENT WHERE CLI_NUM <> 0) AS NB_CLIENTS,
        (SELECT COUNT(*) FROM VIK_RESERVATION) AS NB_RESERVATIONS,
        (SELECT NVL(SUM(RES_PRIX_TOT), 0) FROM VIK_RESERVATION) AS CA_TOTAL,
        (SELECT COUNT(*) FROM VIK_LIGNE) AS NB_LIGNES
    FROM DUAL
";
$curResume = preparerRequetePDO($conn, $sqlResume);
$curResume->execute();
$resume = $curResume->fetch(PDO::FETCH_ASSOC);

$conn = null; // Fermeture propre de la connexion
?>

<main class="container my-5 flex-grow-1">

    <h2 class="fw-bold mb-1" style="color: #2b2438;">
        <i class="bi bi-bar-chart-line-fill me-2" style="color: #ba181b;"></i>
        Tableau de bord statistiques
    </h2>
    <p class="text-muted mb-5">Vue d'ensemble de l'activité Viking Transport</p>

    <div class="row g-4 mb-5">

        <!-- Nb clients -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mb-2" style="color: #ba181b; font-size: 2rem;">
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
                <div class="mb-2" style="color: #ba181b; font-size: 2rem;">
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
                <div class="mb-2" style="color: #ba181b; font-size: 2rem;">
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
                <div class="mb-2" style="color: #ba181b; font-size: 2rem;">
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
                <i class="bi bi-graph-up-arrow me-2" style="color: #ba181b;"></i>
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
                                      style="background-color: #ede9f5; color: #ba181b;">
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
                                 style="width: <?= $pct ?>%; background-color: #ba181b;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-5">
        <div class="card-body p-4">

            <h5 class="fw-bold mb-1" style="color: #2b2438;">
                <i class="bi bi-trophy-fill me-2" style="color: #ba181b;"></i>
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
                                              style="background-color: #ede9f5; color: #ba181b;">
                                            <?= htmlspecialchars($cli['TYPE_CLIENT']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold" style="color: #ba181b;">
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
                <i class="bi bi-calendar3 me-2" style="color: #ba181b;"></i>
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
                            <!-- Tooltip au survol -->
                            <div class="flex-grow-1 d-flex align-items-end w-100"
                                 title="<?= htmlspecialchars($row['MOIS']) ?> : <?= number_format((float)$row['CA'], 0, ',', ' ') ?> €">
                                <div class="w-100 rounded-top-3"
                                     style="height: <?= $pct ?>%; min-height: 4px; background-color: #ba181b; opacity: 0.75; transition: opacity 0.2s;"
                                     onmouseover="this.style.opacity='1'"
                                     onmouseout="this.style.opacity='0.75'">
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
                              style="background-color: #ede9f5; color: #ba181b; font-size: 0.8rem;">
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