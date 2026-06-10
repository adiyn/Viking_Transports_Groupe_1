<?php
declare(strict_types=1);

/**
 * Page : reservation d'un voyage sur une ligne complete (priorite 3, client non inscrit).
 * Rendu serveur : affiche le formulaire (GET) puis la confirmation (POST).
 */

/* Inclusion des configurations et des fonctions PDO de l'application */
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

// Définition du mode de BDD à utiliser (comme dans db_exemple2.php)
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

// Initialisation de la connexion $conn
$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

$erreur      = null;
$reservation = null;

/* Requete commune : distance, tarif, communes et heure de depart d'une ligne. */
$sqlInfo = "
    SELECT l.lig_num,
           l.com_code_insee_debu,
           l.com_code_insee_term,
           cd.com_nom AS depart,
           ct.com_nom AS arrivee,
           d.distance_totale,
           t.tar_num_tranche,
           t.tar_prix,
           TO_CHAR(
             (SELECT MIN(noe_heure_passage)
                FROM vik_noeud
               WHERE TRIM(lig_num) = TRIM(l.lig_num)
                 AND com_code_insee_arret = l.com_code_insee_debu),
             'hh24:mi') AS heure_depart
    FROM vik_ligne l
    JOIN vik_commune cd ON cd.com_code_insee = l.com_code_insee_debu
    JOIN vik_commune ct ON ct.com_code_insee = l.com_code_insee_term
    JOIN (
        SELECT TRIM(lig_num) AS lig_num, SUM(dist) AS distance_totale
        FROM (
            SELECT lig_num, com_code_insee_arret, com_code_insee_suivant,
                   MAX(noe_distance_prochain) AS dist
            FROM vik_noeud
            WHERE TRIM(lig_num) = :lig_num
            GROUP BY lig_num, com_code_insee_arret, com_code_insee_suivant
        )
        GROUP BY TRIM(lig_num)
    ) d ON TRIM(l.lig_num) = d.lig_num
    JOIN vik_tarif t
      ON t.tar_num_tranche = (
            SELECT MIN(tar_num_tranche)
            FROM vik_tarif
            WHERE tar_max_dist >= d.distance_totale
         )
    WHERE TRIM(l.lig_num) = :lig_num";

/* ===================== TRAITEMENT DE LA RESERVATION (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ligNum = isset($_POST['lig_num']) ? trim((string) $_POST['lig_num']) : '';
    $cliNum = 0; // 0 = client NON INSCRIT (priorite 3)

    if ($ligNum === '') {
        $erreur = 'Veuillez sélectionner une ligne.';
    } else {
        // Préparation de la requête avec pdo_agile
        $stInfo = preparerRequetePDO($conn, $sqlInfo);
        ajouterParamPDO($stInfo, ':lig_num', $ligNum, 'texte');
        
        $tabInfo = [];
        LireDonneesPDOPreparee($stInfo, $tabInfo);
        $info = !empty($tabInfo) ? $tabInfo[0] : false;

        if ($info === false) {
            $erreur = 'Ligne inconnue ou tarif introuvable pour cette distance.';
        } else {
            // Note : Oracle PDO renvoie souvent les clés de tableaux en MAJUSCULES.
            // On utilise l'opérateur de fusion null (??) pour parer à toute éventualité de casse.
            $debu     = $info['COM_CODE_INSEE_DEBU'] ?? $info['com_code_insee_debu'];
            $term     = $info['COM_CODE_INSEE_TERM'] ?? $info['com_code_insee_term'];
            $distance = (float) ($info['DISTANCE_TOTALE'] ?? $info['distance_totale']);
            $tranche  = (int) ($info['TAR_NUM_TRANCHE'] ?? $info['tar_num_tranche']);
            $prix     = (int) ($info['TAR_PRIX'] ?? $info['tar_prix']);
            $heureDep = $info['HEURE_DEPART'] ?? $info['heure_depart'];
            $depart   = $info['DEPART'] ?? $info['depart'];
            $arrivee  = $info['ARRIVEE'] ?? $info['arrivee'];
            $points   = 0; 

            // Gestion de la transaction native avec PDO
            $conn->beginTransaction();

            try {
                /* Prochain RES_NUM pour ce client */
                $sqlNum = "SELECT NVL(MAX(res_num), 0) + 1 AS next_num FROM vik_reservation WHERE cli_num = :cli";
                $stNum = preparerRequetePDO($conn, $sqlNum);
                ajouterParamPDO($stNum, ':cli', $cliNum, 'nombre');
                
                $tabNum = [];
                LireDonneesPDOPreparee($stNum, $tabNum);
                $resNum = (int) ($tabNum[0]['NEXT_NUM'] ?? $tabNum[0]['next_num'] ?? 1);

                /* Insertion de la Réservation */
                $sqlRes = "INSERT INTO vik_reservation
                            (cli_num, res_num, tar_num_tranche, res_date, res_nb_points, res_prix_tot)
                           VALUES (:cli, :res, :tar, SYSDATE, :pts, :prix)";
                $stRes = preparerRequetePDO($conn, $sqlRes);
                ajouterParamPDO($stRes, ':cli',  $cliNum, 'nombre');
                ajouterParamPDO($stRes, ':res',  $resNum, 'nombre');
                ajouterParamPDO($stRes, ':tar',  $tranche, 'nombre');
                ajouterParamPDO($stRes, ':pts',  $points, 'nombre');
                ajouterParamPDO($stRes, ':prix', $prix, 'nombre');
                $okRes = majDonneesPrepareesPDO($stRes);

                /* Insertion de l'Étape */
                $okEta = false;
                if ($okRes) {
                    $sqlEta = "INSERT INTO vik_etape
                                (lig_num, cli_num, res_num, com_code_insee_depart,
                                 com_code_insee_arrivee, eta_distance, eta_heure)
                               VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:heure, 'hh24:mi'))";
                    $stEta = preparerRequetePDO($conn, $sqlEta);
                    ajouterParamPDO($stEta, ':lig',   $ligNum, 'texte');
                    ajouterParamPDO($stEta, ':cli',   $cliNum, 'nombre');
                    ajouterParamPDO($stEta, ':res',   $resNum, 'nombre');
                    ajouterParamPDO($stEta, ':dep',   $debu, 'texte');
                    ajouterParamPDO($stEta, ':arr',   $term, 'texte');
                    ajouterParamPDO($stEta, ':dist',  $distance, 'texte');
                    ajouterParamPDO($stEta, ':heure', $heureDep, 'texte');
                    $okEta = majDonneesPrepareesPDO($stEta);
                }

                if ($okRes && $okEta) {
                    $conn->commit();
                    $reservation = [
                        'reference' => 'VT-' . date('Y') . '-' . str_pad((string) $resNum, 5, '0', STR_PAD_LEFT),
                        'lig_num'   => $ligNum,
                        'depart'    => $depart,
                        'arrivee'   => $arrivee,
                        'prix'      => $prix,
                    ];
                } else {
                    $conn->rollBack();
                    $erreur = "La réservation n'a pas pu être enregistrée. Veuillez réessayer.";
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $erreur = "Une erreur technique est survenue : " . $e->getMessage();
            }
        }
    }
}

/* ===================== LISTE DES LIGNES POUR LE FORMULAIRE ===================== */
$lignes = [];
$sqlLignes = "
    SELECT TRIM(l.lig_num) AS lig_num, cd.com_nom AS depart, ct.com_nom AS arrivee
    FROM vik_ligne l
    JOIN vik_commune cd ON cd.com_code_insee = l.com_code_insee_debu
    JOIN vik_commune ct ON ct.com_code_insee = l.com_code_insee_term
    ORDER BY LENGTH(TRIM(l.lig_num)), TRIM(l.lig_num)";

// Appel de la fonction globale LireDonneesPDO3 pour remplir notre matrice de lignes
LireDonneesPDO3($conn, $sqlLignes, $lignes);

/* ===================== AFFICHAGE ===================== */
include __DIR__ . '/../includes/header.php';
?>

<main class="container my-4">
    <h1 class="mb-4">Acheter un billet</h1>

    <?php if ($reservation !== null): ?>
        <section class="p-4 p-md-5 text-center" style="background:#f3f3f5; border-radius:1rem;">
            <i class="bi bi-check-square fs-1"></i>
            <h2 class="h4 mt-2">Réservation confirmée</h2>
            <p class="text-muted">Votre réservation a bien été enregistrée.</p>

            <div class="card text-start mx-auto mt-4" style="max-width:420px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Référence</span>
                        <span class="fw-semibold"><?= htmlspecialchars($reservation['reference']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Trajet</span>
                        <span><?= htmlspecialchars($reservation['depart']) ?> &rarr; <?= htmlspecialchars($reservation['arrivee']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Montant payé</span>
                        <span class="fw-semibold"><?= htmlspecialchars((string) $reservation['prix']) ?> &euro;</span>
                    </div>
                </div>
            </div>

            <a href="reserver.php" class="btn btn-light border rounded-pill mt-4 px-4">Nouvelle réservation</a>
        </section>

    <?php else: ?>
        <section class="p-4 p-md-5" style="background:#f3f3f5; border-radius:1rem;">

            <?php if ($erreur !== null): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post" action="reserver.php">
                <div class="mb-4" style="max-width:420px;">
                    <label for="lig_num" class="form-label">Choix de la ligne</label>
                    <select class="form-select" id="lig_num" name="lig_num" required>
                        <option value="" selected disabled>Sélectionnez une ligne</option>
                        <?php foreach ($lignes as $l): 
                            // Gestion adaptative de la casse Oracle PDO
                            $l_num = $l['LIG_NUM'] ?? $l['lig_num'];
                            $l_dep = $l['DEPART'] ?? $l['depart'];
                            $l_arr = $l['ARRIVEE'] ?? $l['arrivee'];
                        ?>
                            <option value="<?= htmlspecialchars($l_num) ?>">
                                Ligne <?= htmlspecialchars($l_num) ?> :
                                <?= htmlspecialchars($l_dep) ?> &rarr; <?= htmlspecialchars($l_arr) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-dark w-100 py-2">Réserver</button>
            </form>
        </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>