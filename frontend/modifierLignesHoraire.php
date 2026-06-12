<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";

$_SESSION['current_page'] = 'modifierLignesHoraires.php';

// Vérification stricte des droits d'administration
if (!isset($_SESSION["logged_in"]) || !$_SESSION["logged_in"] || !isset($_SESSION["utilisateur"])) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Accès refusé : Vous devez être connecté.</div></div>");
}

$isAdminCheck = isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] === 'admin';
// Vérification supplémentaire en base si la session n'est pas à jour
if (!$isAdminCheck) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Accès interdit : Vous n'avez pas les droits d'administration.</div></div>");
}

// Connexion à la BDD
define("MOD_BDD", "ORACLE"); 
if (MOD_BDD == "MYSQL") {
    $db_username = $db_usernameMySQL;        
    $db_password = $db_passwordMySQL;
    $db = $dbMySQL;
} else {
    $db_username = $db_usernameOracle;        
    $db_password = $db_passwordOracle;    
    $db = $dbOracle;
}
$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

$message = '';
$typeMessage = '';

// --- TRAITEMENT DU FORMULAIRE DE MODIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_horaire') {
        $ligNum = trim((string) $_POST['lig_num']);
        $arretInsee = trim((string) $_POST['arret_insee']);
        $oldHeure = trim((string) $_POST['old_heure']);
        $newHeure = trim((string) $_POST['new_heure']);

        if (!empty($newHeure) && $newHeure !== $oldHeure) {
            try {
                // 1. Calcul de l'intervalle de décalage en minutes
                $datetimeOld = new DateTime($oldHeure);
                $datetimeNew = new DateTime($newHeure);
                $interval = $datetimeOld->diff($datetimeNew);
                
                // Conversion de l'intervalle en minutes (positives ou négatives)
                $minutesDiff = ($interval->h * 60) + $interval->i;
                if ($interval->invert === 1) {
                    $minutesDiff = -$minutesDiff;
                }

                // 2. Requête SQL pour appliquer le décalage à l'arrêt et aux suivants
                // On ajoute X minutes (minutesDiff / 1440) à la date Oracle
                $sqlUpdate = "UPDATE VIK_NOEUD 
                              SET NOE_HEURE_PASSAGE = NOE_HEURE_PASSAGE + (:minutes_diff / 1440)
                              WHERE TRIM(LIG_NUM) = :lig 
                              AND NOE_HEURE_PASSAGE >= TO_DATE(:old_h, 'HH24:MI')";
                
                $stUpdate = preparerRequetePDO($conn, $sqlUpdate);
                ajouterParamPDO($stUpdate, ':minutes_diff', $minutesDiff, 'nombre'); // Type adapté pour le calcul
                ajouterParamPDO($stUpdate, ':lig', $ligNum, 'texte');
                ajouterParamPDO($stUpdate, ':old_h', $oldHeure, 'texte');
                
                if (majDonneesPrepareesPDO($stUpdate)) {
                    $message = "L'horaire et les arrêts suivants ont été mis à jour avec un décalage de " . $minutesDiff . " minute(s).";
                    $typeMessage = "success";
                } else {
                    $message = "Erreur lors de la mise à jour des horaires en cascade.";
                    $typeMessage = "danger";
                }
            } catch (Exception $e) {
                $message = "Erreur technique : " . $e->getMessage();
                $typeMessage = "danger";
            }
        }
    }
}

// --- RECUPERATION DES DONNEES POUR L'AFFICHAGE ---
// 1. Liste des lignes pour le menu déroulant
$lignes = [];
LireDonneesPDO1($conn, "SELECT TRIM(LIG_NUM) AS LIG_NUM FROM VIK_LIGNE ORDER BY LENGTH(TRIM(LIG_NUM)), TRIM(LIG_NUM)", $lignes);

// Ligne sélectionnée par défaut ou via GET
$selectedLigne = isset($_GET['ligne']) ? trim((string) $_GET['ligne']) : ($lignes[0]['LIG_NUM'] ?? '1A');

// 2. Récupération des horaires pour la ligne sélectionnée
$horaires = [];
$sqlHoraires = "SELECT n.LIG_NUM, 
                       n.COM_CODE_INSEE_ARRET, 
                       c.COM_NOM AS NOM_ARRET, 
                       TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI') AS HEURE_PASSAGE,
                       n.COM_CODE_INSEE_SUIVANT,
                       cs.COM_NOM AS NOM_SUIVANT,
                       n.NOE_DISTANCE_PROCHAIN
                FROM VIK_NOEUD n
                JOIN VIK_COMMUNE c ON n.COM_CODE_INSEE_ARRET = c.COM_CODE_INSEE
                LEFT JOIN VIK_COMMUNE cs ON n.COM_CODE_INSEE_SUIVANT = cs.COM_CODE_INSEE
                WHERE TRIM(n.LIG_NUM) = :ligne
                ORDER BY n.NOE_HEURE_PASSAGE ASC";

$stHoraires = preparerRequetePDO($conn, $sqlHoraires);
ajouterParamPDO($stHoraires, ':ligne', $selectedLigne, 'texte');
LireDonneesPDOPreparee($stHoraires, $horaires);
?>

<main class="container my-4 flex-grow-1">
    
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <h1 class="fs-3 fw-bold mb-0 text-dark">
            <i class="bi bi-pencil-square text-danger me-2"></i>Administration : Lignes & Horaires
        </h1>
        <span class="badge bg-danger rounded-pill px-3 py-2 fs-6">Espace Admin</span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $typeMessage ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <p class="text-uppercase text-muted small fw-bold mb-3">
                    <i class="bi bi-bus-front me-2 text-danger"></i>Sélectionner une ligne
                </p>
                <form method="get" action="">
                    <select class="form-select mb-3 rounded-3" name="ligne" onchange="this.form.submit()">
                        <?php foreach ($lignes as $ligne): 
                            $num = $ligne['LIG_NUM'] ?? $ligne['lig_num'];
                        ?>
                            <option value="<?= htmlspecialchars($num) ?>" <?= $num === $selectedLigne ? 'selected' : '' ?>>
                                Ligne <?= htmlspecialchars($num) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <hr>
                
                <div class="text-center mt-3">
                    <i class="bi bi-info-circle text-muted mb-2 fs-3"></i>
                    <p class="small text-muted mb-0">Sélectionnez une ligne pour afficher l'ensemble de ses arrêts (nœuds) et modifier leurs horaires de passage.</p>
                </div>
            </div>
        </div>

        <div class="col-md-8 col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">Itinéraires de la Ligne <?= htmlspecialchars($selectedLigne) ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="py-3 px-4 text-uppercase text-muted small fw-semibold">Arrêt</th>
                                    <th scope="col" class="py-3 px-4 text-uppercase text-muted small fw-semibold">Direction (Arrêt Suivant)</th>
                                    <th scope="col" class="py-3 px-4 text-uppercase text-muted small fw-semibold">Heure</th>
                                    <th scope="col" class="py-3 px-4 text-uppercase text-muted small fw-semibold text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($horaires)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted fst-italic">Aucun horaire défini pour cette ligne.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($horaires as $h): 
                                        $lig = $h['LIG_NUM'] ?? $h['lig_num'];
                                        $arretInsee = $h['COM_CODE_INSEE_ARRET'] ?? $h['com_code_insee_arret'];
                                        $nomArret = $h['NOM_ARRET'] ?? $h['nom_arret'];
                                        $nomSuivant = $h['NOM_SUIVANT'] ?? $h['nom_suivant'] ?? 'Terminus';
                                        $heure = $h['HEURE_PASSAGE'] ?? $h['heure_passage'];
                                    ?>
                                        <tr>
                                            <td class="py-3 px-4 fw-medium text-dark">
                                                <i class="bi bi-geo-alt-fill text-danger me-2 small"></i>
                                                <?= htmlspecialchars($nomArret) ?>
                                            </td>
                                            <td class="py-3 px-4 text-muted">
                                                <?= htmlspecialchars($nomSuivant) ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <form method="post" action="?ligne=<?= urlencode($selectedLigne) ?>" class="d-flex gap-2 align-items-center mb-0">
                                                    <input type="hidden" name="action" value="update_horaire">
                                                    <input type="hidden" name="lig_num" value="<?= htmlspecialchars($lig) ?>">
                                                    <input type="hidden" name="arret_insee" value="<?= htmlspecialchars($arretInsee) ?>">
                                                    <input type="hidden" name="old_heure" value="<?= htmlspecialchars($heure) ?>">
                                                    
                                                    <input type="time" class="form-control form-control-sm border-secondary fw-bold" 
                                                           name="new_heure" value="<?= htmlspecialchars($heure) ?>" 
                                                           style="width: 100px; color: #bb3e3e;">
                                            </td>
                                            <td class="py-3 px-4 text-end">
                                                    <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
