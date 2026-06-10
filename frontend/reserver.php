<?php
declare(strict_types=1);

/**
 * Page : reservation d'un voyage sur une ligne complete (priorite 3, client non inscrit).
 * Rendu serveur : affiche le formulaire (GET), le récapitulatif avec prix, puis la confirmation (POST).
 */

/* Inclusion des configurations et des fonctions PDO de l'application */
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

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

$erreur         = null;
$reservation    = null;
$afficher_recap = false;

/* Requete commune : distance, tarif, communes d'une ligne. */
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
    
    $resNom    = isset($_POST['res_nom']) ? trim((string) $_POST['res_nom']) : '';
    $resPrenom = isset($_POST['res_prenom']) ? trim((string) $_POST['res_prenom']) : '';
    $resTel    = isset($_POST['res_tel']) ? trim((string) $_POST['res_tel']) : '';
    $resMail   = isset($_POST['res_mail']) ? trim((string) $_POST['res_mail']) : '';

    if ($ligNum === '' || $resDate === '' || $resHeure === '' || $resNom === '' || $resPrenom === '' || $resTel === '' || $resMail === '') {
        $erreur = 'Veuillez remplir tous les champs (ligne, date, horaire et coordonnées).';
    } else {
        // Validation de la date et de l'heure : on ne peut pas réserver dans le passé
        date_default_timezone_set('Europe/Paris');
        if (strtotime($resDate . ' ' . $resHeure) < time()) {
            $erreur = "La date et l'heure de réservation ne peuvent pas être dans le passé.";
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

            // VERIFICATION : L'utilisateur a-t-il validé le récapitulatif ?
            if (isset($_POST['confirmation_finale'])) {
                
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

                    /* 3. Prochain RES_NUM pour ce client */
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
            } else {
                // S'il n'y a pas de confirmation finale, on passe à l'étape du récapitulatif
                $afficher_recap = true;
            }
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

/* ===================== LISTE DES HORAIRES PAR LIGNE ===================== */
$sqlHoraires = "
    SELECT DISTINCT TRIM(n.lig_num) AS lig_num, TO_CHAR(n.noe_heure_passage, 'HH24:MI') AS heure
    FROM vik_noeud n
    JOIN vik_ligne l ON TRIM(l.lig_num) = TRIM(n.lig_num)
    WHERE n.com_code_insee_arret = l.com_code_insee_debu
    ORDER BY heure";

$horairesRaw = [];
LireDonneesPDO3($conn, $sqlHoraires, $horairesRaw);

$horairesParLigne = [];
foreach ($horairesRaw as $h) {
    $l_id = $h['LIG_NUM'] ?? $h['lig_num'];
    $h_val = $h['HEURE'] ?? $h['heure'];
    $horairesParLigne[$l_id][] = $h_val;
}

/* ===================== AFFICHAGE ===================== */
date_default_timezone_set('Europe/Paris');
$aujourdhui = date('Y-m-d');
$heureActuelle = date('H:i');

include __DIR__ . '/../includes/header.php';
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>
<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <?php if ($reservation !== null): ?>

        <section class="p-4 p-md-5 text-center mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <i class="bi bi-check-square fs-1 text-success"></i>

        <section class="p-4 p-md-5 text-center mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <i class="bi bi-check-square fs-1 text-success"></i>
            <h2 class="h4 mt-2">Réservation confirmée</h2>
            <p class="text-muted">Votre réservation a bien été enregistrée.</p>

            <div class="card text-start mx-auto mt-4">
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

    <?php elseif ($afficher_recap === true): ?>

        <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <h2 class="h4 mb-4 text-center text-secondary"><i class="bi bi-wallet2"></i> Vérifiez votre trajet</h2>
            
            <div class="card text-start mx-auto mb-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Passager</span>
                        <span class="fw-semibold"><?= htmlspecialchars($resPrenom . ' ' . $resNom) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Trajet</span>
                        <span class="text-end"><?= htmlspecialchars($depart) ?> <br>&rarr; <?= htmlspecialchars($arrivee) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Date & Horaire</span>
                        <span>Le <?= htmlspecialchars(date('d/m/Y', strtotime($resDate))) ?> à <?= htmlspecialchars($resHeure) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center fs-5">
                        <span class="text-muted">Prix total</span>
                        <span class="fw-bold fs-3"><?= htmlspecialchars((string) $prix) ?> &euro;</span>
                    </div>
                </div>
            </div>

            <form method="post" action="reserver.php" class="d-flex justify-content-between mt-4">
                <input type="hidden" name="lig_num" value="<?= htmlspecialchars($ligNum) ?>">
                <input type="hidden" name="res_date" value="<?= htmlspecialchars($resDate) ?>">
                <input type="hidden" name="res_heure" value="<?= htmlspecialchars($resHeure) ?>">
                <input type="hidden" name="res_nom" value="<?= htmlspecialchars($resNom) ?>">
                <input type="hidden" name="res_prenom" value="<?= htmlspecialchars($resPrenom) ?>">
                <input type="hidden" name="res_tel" value="<?= htmlspecialchars($resTel) ?>">
                <input type="hidden" name="res_mail" value="<?= htmlspecialchars($resMail) ?>">

                <a href="reserver.php" class="btn btn-outline-secondary px-4 py-2">Annuler</a>
                <button type="submit" name="confirmation_finale" value="1" class="btn btn-dark px-4 py-2">Confirmer et Payer</button>
            </form>
        </section>

    <?php else: ?>

        <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:900px;">

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
                                <input type="date" class="form-control" id="res_date" name="res_date" min="<?= $aujourdhui ?>" required>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label for="res_heure" class="form-label">Horaire de départ</label>
                                <select class="form-select" id="res_heure" name="res_heure" required disabled>
                                    <option value="" selected disabled>Choisissez d'abord une ligne</option>
                                </select>
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

                </div> 
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-dark py-2 px-5 fs-5">Calculer le prix</button>
                </div>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const dateInput = document.getElementById('res_date');
                    const timeSelect = document.getElementById('res_heure');
                    const lineSelect = document.getElementById('lig_num');
                    const today = '<?= $aujourdhui ?>';
                    const nowTime = '<?= $heureActuelle ?>';
                    const horairesParLigne = <?= json_encode($horairesParLigne) ?>;

                    function updateHours() {
                        const selectedLine = lineSelect.value;
                        const selectedDate = dateInput.value;
                        
                        // Vidage du select
                        timeSelect.innerHTML = '<option value="" selected disabled>Choisissez un horaire</option>';
                        
                        if (selectedLine && horairesParLigne[selectedLine]) {
                            const horaires = horairesParLigne[selectedLine];
                            let count = 0;

                            horaires.forEach(heure => {
                                // Si c'est aujourd'hui, on ne montre que les horaires futurs
                                if (selectedDate === today && heure < nowTime) {
                                    return;
                                }
                                
                                const option = document.createElement('option');
                                option.value = heure;
                                option.textContent = heure;
                                timeSelect.appendChild(option);
                                count++;
                            });

                            if (count > 0) {
                                timeSelect.disabled = false;
                            } else {
                                timeSelect.innerHTML = '<option value="" selected disabled>Aucun horaire disponible pour ce jour</option>';
                                timeSelect.disabled = true;
                            }
                        } else {
                            timeSelect.innerHTML = '<option value="" selected disabled>Choisissez d\'abord une ligne</option>';
                            timeSelect.disabled = true;
                        }
                    }

                    lineSelect.addEventListener('change', updateHours);
                    dateInput.addEventListener('change', updateHours);
                    
                    // Initialisation si besoin (ex: retour arrière)
                    if (lineSelect.value) {
                        updateHours();
                    }
                });
            </script>
        </section>


    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
