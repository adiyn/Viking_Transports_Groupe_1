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

/* Requete commune : distance, tarif, communes d'une ligne. */
$sqlInfo = "
    SELECT l.lig_num,
           l.com_code_insee_debu,
           l.com_code_insee_term,
           cd.com_nom AS depart,
           ct.com_nom AS arrivee,
           d.distance_totale,
           t.tar_num_tranche,
           t.tar_prix
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

    $ligNum    = isset($_POST['lig_num']) ? trim((string) $_POST['lig_num']) : '';
    $resDate   = isset($_POST['res_date']) ? trim((string) $_POST['res_date']) : '';
    $resHeure  = isset($_POST['res_heure']) ? trim((string) $_POST['res_heure']) : '';
    
    // Récupération des coordonnées de la maquette
    $resNom    = isset($_POST['res_nom']) ? trim((string) $_POST['res_nom']) : '';
    $resPrenom = isset($_POST['res_prenom']) ? trim((string) $_POST['res_prenom']) : '';
    $resTel    = isset($_POST['res_tel']) ? trim((string) $_POST['res_tel']) : '';
    $resMail   = isset($_POST['res_mail']) ? trim((string) $_POST['res_mail']) : '';

    if ($ligNum === '' || $resDate === '' || $resHeure === '' || $resNom === '' || $resPrenom === '' || $resTel === '' || $resMail === '') {
        $erreur = 'Veuillez remplir tous les champs (ligne, date, horaire et coordonnées).';
    } else {
        $stInfo = preparerRequetePDO($conn, $sqlInfo);
        ajouterParamPDO($stInfo, ':lig_num', $ligNum, 'texte');
        
        $tabInfo = [];
        LireDonneesPDOPreparee($stInfo, $tabInfo);
        $info = !empty($tabInfo) ? $tabInfo[0] : false;

        if ($info === false) {
            $erreur = 'Ligne inconnue ou tarif introuvable pour cette distance.';
        } else {
            // Gestion adaptative de la casse pour Oracle PDO
            $debu     = $info['COM_CODE_INSEE_DEBU'] ?? $info['com_code_insee_debu'];
            $term     = $info['COM_CODE_INSEE_TERM'] ?? $info['com_code_insee_term'];
            $distance = (float) ($info['DISTANCE_TOTALE'] ?? $info['distance_totale']);
            $tranche  = (int) ($info['TAR_NUM_TRANCHE'] ?? $info['tar_num_tranche']);
            $prix     = (int) ($info['TAR_PRIX'] ?? $info['tar_prix']);
            $depart   = $info['DEPART'] ?? $info['depart'];
            $arrivee  = $info['ARRIVEE'] ?? $info['arrivee'];
            $points   = 0; 

            $conn->beginTransaction();

            try {
                /* 1. Génération du prochain CLI_NUM unique */
                $sqlCliNum = "SELECT NVL(MAX(cli_num), 0) + 1 AS next_cli FROM VIK_CLIENT";
                $stCliNum = preparerRequetePDO($conn, $sqlCliNum);
                $tabCliNum = [];
                LireDonneesPDOPreparee($stCliNum, $tabCliNum);
                $cliNum = (int) ($tabCliNum[0]['NEXT_CLI'] ?? $tabCliNum[0]['next_cli'] ?? 1);

                /* 2. Insertion du nouveau client dans VIK_CLIENT */
                $sqlNewCli = "INSERT INTO VIK_CLIENT 
                                (cli_num, typ_num, cli_nom, cli_prenom, cli_telephone, cli_courriel)
                              VALUES (:cli, 3, :nom, :prenom, :tel, :mail)";
                $stNewCli = preparerRequetePDO($conn, $sqlNewCli);
                ajouterParamPDO($stNewCli, ':cli', $cliNum, 'nombre');
                ajouterParamPDO($stNewCli, ':nom', $resNom, 'texte');
                ajouterParamPDO($stNewCli, ':prenom', $resPrenom, 'texte');
                ajouterParamPDO($stNewCli, ':tel', $resTel, 'texte');
                ajouterParamPDO($stNewCli, ':mail', $resMail, 'texte');
                $okCli = majDonneesPrepareesPDO($stNewCli);

                /* 3. Prochain RES_NUM pour ce client (sera logiquement 1) */
                $okRes = false;
                $resNum = 1;
                if ($okCli) {
                    $sqlNum = "SELECT NVL(MAX(res_num), 0) + 1 AS next_num FROM vik_reservation WHERE cli_num = :cli";
                    $stNum = preparerRequetePDO($conn, $sqlNum);
                    ajouterParamPDO($stNum, ':cli', $cliNum, 'nombre');
                    
                    $tabNum = [];
                    LireDonneesPDOPreparee($stNum, $tabNum);
                    $resNum = (int) ($tabNum[0]['NEXT_NUM'] ?? $tabNum[0]['next_num'] ?? 1);

                    /* 4. Insertion de la Réservation */
                    $sqlRes = "INSERT INTO vik_reservation
                                (cli_num, res_num, tar_num_tranche, res_date, res_nb_points, res_prix_tot)
                               VALUES (:cli, :res, :tar, TO_DATE(:res_date, 'YYYY-MM-DD'), :pts, :prix)";
                    $stRes = preparerRequetePDO($conn, $sqlRes);
                    ajouterParamPDO($stRes, ':cli',  $cliNum, 'nombre');
                    ajouterParamPDO($stRes, ':res',  $resNum, 'nombre');
                    ajouterParamPDO($stRes, ':tar',  $tranche, 'nombre');
                    ajouterParamPDO($stRes, ':res_date', $resDate, 'texte');
                    ajouterParamPDO($stRes, ':pts',  $points, 'nombre');
                    ajouterParamPDO($stRes, ':prix', $prix, 'nombre');
                    $okRes = majDonneesPrepareesPDO($stRes);
                }

                /* 5. Insertion de l'Étape */
                $okEta = false;
                if ($okCli && $okRes) {
                    $sqlEta = "INSERT INTO VIK_ETAPE
                                (lig_num, cli_num, res_num, com_code_insee_depart,
                                 com_code_insee_arrivee, eta_distance, eta_heure)
                               VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:heure, 'HH24:MI'))";
                    $stEta = preparerRequetePDO($conn, $sqlEta);
                    ajouterParamPDO($stEta, ':lig',   $ligNum, 'texte');
                    ajouterParamPDO($stEta, ':cli',   $cliNum, 'nombre');
                    ajouterParamPDO($stEta, ':res',   $resNum, 'nombre');
                    ajouterParamPDO($stEta, ':dep',   $debu, 'texte');
                    ajouterParamPDO($stEta, ':arr',   $term, 'texte');
                    ajouterParamPDO($stEta, ':dist',  $distance, 'texte');
                    ajouterParamPDO($stEta, ':heure', $resHeure, 'texte');
                    $okEta = majDonneesPrepareesPDO($stEta);
                }

                if ($okCli && $okRes && $okEta) {
                    $conn->commit();
                    $reservation = [
                        'reference' => 'VT-' . date('Y') . '-' . str_pad((string) $resNum, 5, '0', STR_PAD_LEFT),
                        'lig_num'   => $ligNum,
                        'depart'    => $depart,
                        'arrivee'   => $arrivee,
                        'prix'      => $prix,
                        'date'      => date('d/m/Y', strtotime($resDate)),
                        'heure'     => $resHeure
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

LireDonneesPDO3($conn, $sqlLignes, $lignes);

/* ===================== AFFICHAGE ===================== */
include __DIR__ . '/../includes/header.php';
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <?php if ($reservation !== null): ?>

        <section class="p-4 p-md-5 text-center mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <i class="bi bi-check-square fs-1 text-success"></i>
            <h2 class="h4 mt-2">Réservation confirmée</h2>
            <p class="text-muted">Votre réservation a bien été enregistrée.</p>

            <div class="card text-start mx-auto mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Référence</span>
                        <span class="fw-semibold"><?= htmlspecialchars($reservation['reference']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Trajet</span>
                        <span><?= htmlspecialchars($reservation['depart']) ?> &rarr; <?= htmlspecialchars($reservation['arrivee']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Date & Horaire</span>
                        <span class="fw-semibold">Le <?= htmlspecialchars($reservation['date']) ?> à <?= htmlspecialchars($reservation['heure']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Montant payé</span>
                        <span class="fw-semibold"><?= htmlspecialchars((string) $reservation['prix']) ?> &euro;</span>
                    </div>
                </div>
            </div>

            <a href="reserver.php" class="btn btn-dark mt-4 px-4">Nouvelle réservation</a>
        </section>

    <?php else: ?>

        <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:900px;">

            <?php if ($erreur !== null): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post" action="reserver.php">
                <div class="row g-4">
                    
                    <div class="col-md-6 border-end">
                        <h3 class="h5 mb-4 text-secondary"><i class="bi bi-bus-front"></i> Votre trajet</h3>
                        
                        <div class="mb-3">
                            <label for="lig_num" class="form-label">Choix de la ligne</label>
                            <select class="form-select" id="lig_num" name="lig_num" required>
                                <option value="" selected disabled>Sélectionnez une ligne</option>
                                <?php foreach ($lignes as $l): 
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

                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label for="res_date" class="form-label">Date du voyage</label>
                                <input type="date" class="form-control" id="res_date" name="res_date" required>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label for="res_heure" class="form-label">Horaire de départ</label>
                                <input type="time" class="form-control" id="res_heure" name="res_heure" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h5 mb-4 text-secondary"><i class="bi bi-person"></i> Vos coordonnées</h3>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label for="res_nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="res_nom" name="res_nom" placeholder="Pork" required>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label for="res_prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="res_prenom" name="res_prenom" placeholder="John" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="res_tel" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="res_tel" name="res_tel" placeholder="07 67 67 67 67" pattern="[0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}" required>
                        </div>

                        <div class="mb-4">
                            <label for="res_mail" class="form-label">Adresse mail</label>
                            <input type="email" class="form-control" id="res_mail" name="res_mail" placeholder="johnpork@gmail.com" required>
                        </div>
                    </div>

                </div> <div class="text-center mt-4">
                    <button type="submit" class="btn btn-dark py-2 px-5 fs-5">Continuer</button>
                </div>
            </form>
        </section>

    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>