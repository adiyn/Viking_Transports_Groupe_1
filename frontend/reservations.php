<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";

// Définition de la page courante pour la navigation
$_SESSION['current_page'] = 'reservations.php'; 

// Vérification de la connexion de l'utilisateur
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] && isset($_SESSION["utilisateur"]) && isset($_SESSION["utilisateur"]['num'])) {
    if (isset($_GET["id"])){
    $idClient = $_GET["id"];
        }
    else{
        $_SESSION['utilisateur']['num'];
    }
} else {
    die("Vous n'êtes pas connecté. Veuillez vous connecter pour voir vos réservations.");
}

// Connexion à la base de données Oracle (comme dans compte.php)
$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;
$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

// 1. Récupération des réservations du client
$sqlResa = "SELECT res_num, res_date, res_prix_tot, res_nb_points 
            FROM VIK_RESERVATION 
            WHERE cli_num = :cli 
            ORDER BY res_date DESC";
$stResa = preparerRequetePDO($conn, $sqlResa);
ajouterParamPDO($stResa, ':cli', $idClient, 'nombre');
$reservations = [];
LireDonneesPDOPreparee($stResa, $reservations);

// 2. Récupération des étapes (trajets) pour ce client
// On fait une jointure avec VIK_COMMUNE pour obtenir le nom des villes de départ et d'arrivée
$sqlEtape = "SELECT e.res_num, e.lig_num, TO_CHAR(e.eta_heure, 'HH24:MI') as heure_formatee, 
                    cd.com_nom AS ville_depart, 
                    ca.com_nom AS ville_arrivee
             FROM VIK_ETAPE e
             JOIN VIK_COMMUNE cd ON e.com_code_insee_depart = cd.com_code_insee
             JOIN VIK_COMMUNE ca ON e.com_code_insee_arrivee = ca.com_code_insee
             WHERE e.cli_num = :cli
             ORDER BY e.res_num, e.eta_heure ASC";
$stEtape = preparerRequetePDO($conn, $sqlEtape);
ajouterParamPDO($stEtape, ':cli', $idClient, 'nombre');
$etapesData = [];
LireDonneesPDOPreparee($stEtape, $etapesData);

// 3. Regroupement des étapes par numéro de réservation pour un affichage facile
$etapesParResa = [];
foreach ($etapesData as $etape) {
    // Gestion de la casse (Oracle renvoie souvent en majuscules, PDO selon config)
    $resNum = $etape['RES_NUM'] ?? $etape['res_num'];
    $etapesParResa[$resNum][] = $etape;
}
?>

<div class="container mt-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h2 class="mb-0 fw-bold" style="color: #333;">Mes Réservations</h2>
                <a href="compte.php?id=<?=$idClient?>" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i>Retour au compte
                </a>
            </div>

            <?php if (empty($reservations)): ?>
                <div class="bg-light p-5 rounded shadow-sm border text-center">
                    <i class="bi bi-ticket-detailed text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">Aucune réservation</h5>
                    <p class="text-muted mb-4">Vous n'avez pas encore effectué de réservation sur notre réseau.</p>
                    <a href="reserver.php" class="btn rounded-pill px-4 fw-semibold text-white" style="background-color:#5d3ebb;">
                        Réserver un trajet
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($reservations as $resa): 
                        $resNum = $resa['RES_NUM'] ?? $resa['res_num'];
                        $resDate = $resa['RES_DATE'] ?? $resa['res_date'];
                        $prix = $resa['RES_PRIX_TOT'] ?? $resa['res_prix_tot'];
                        $points = $resa['RES_NB_POINTS'] ?? $resa['res_nb_points'];
                        
                        // Formatage de la date 
                        $dateObj = strtotime($resDate);
                        $dateFormatee = $dateObj ? date('d/m/Y', $dateObj) : $resDate;
                    ?>
                        <div class="bg-white p-4 rounded shadow-sm border">
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <span class="badge rounded-pill mb-2" style="background-color: #f5e9e9; color: #bb3e3e;">
                                        Réf: VIK-<?= str_pad((string)$resNum, 5, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <h5 class="mb-0 fw-semibold">Voyage du <?= htmlspecialchars($dateFormatee) ?></h5>
                                </div>
                                <div class="text-end">
                                    <div class="fs-4 fw-bold" style="color: #bb3e3e;"><?= number_format((float)$prix, 2) ?> €</div>
                                    <small class="text-muted"><i class="bi bi-star-fill text-warning"></i> <?= (int)$points ?> points</small>
                                </div>
                            </div>

                            <h6 class="text-muted small text-uppercase fw-semibold mb-3">Détails du trajet</h6>
                            
                            <?php if (isset($etapesParResa[$resNum]) && !empty($etapesParResa[$resNum])): ?>
                                <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                    <?php foreach ($etapesParResa[$resNum] as $index => $etape): 
                                        $villeDep = $etape['VILLE_DEPART'] ?? $etape['ville_depart'];
                                        $villeArr = $etape['VILLE_ARRIVEE'] ?? $etape['ville_arrivee'];
                                        $heure = $etape['HEURE_FORMATEE'] ?? $etape['heure_formatee'];
                                        $ligne = $etape['LIG_NUM'] ?? $etape['lig_num'];
                                    ?>
                                        <li class="d-flex align-items-start gap-3 p-2 rounded" style="background-color: #fbf9f9; border: 1px solid #e0e0e0;">
                                            <div class="d-flex flex-column align-items-center mt-1">
                                                <i class="bi bi-geo-alt-fill" style="color:#5d3ebb;"></i>
                                                <?php if ($index < count($etapesParResa[$resNum]) - 1): ?>
                                                    <div style="border-left: 2px dashed #ccc; height: 15px; margin: 2px 0;"></div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark">
                                                    <?= htmlspecialchars($heure) ?> — Ligne <?= htmlspecialchars($ligne) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    De <strong><?= htmlspecialchars($villeDep) ?></strong> à <strong><?= htmlspecialchars($villeArr) ?></strong>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0 fst-italic">Aucune étape détaillée trouvée pour cette réservation.</p>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>