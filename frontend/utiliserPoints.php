<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données
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

$erreur = '';
$pointsUtilisateur = 0;

// On récupère le prix défini dans reserver.php
$prixInitial = isset($_SESSION['prix_billet']) ? (float) $_SESSION['prix_billet'] : 12.80;

// 1. Récupération des points réels du client
if (isset($_SESSION['cli_num'])) {
    $sqlPoints = "SELECT cli_nb_points_ec FROM vik_client WHERE cli_num = :cli";
    $stPoints = preparerRequetePDO($conn, $sqlPoints);
    ajouterParamPDO($stPoints, ':cli', $_SESSION['cli_num'], 'nombre');
    $tabPoints = [];
    LireDonneesPDOPreparee($stPoints, $tabPoints);
    
    if (!empty($tabPoints)) {
        $pointsUtilisateur = (int) ($tabPoints[0]['CLI_NB_POINTS_EC'] ?? $tabPoints[0]['cli_nb_points_ec']);
    }
} else {
    $pointsUtilisateur = 1048; 
}

// 2. Récupération des pourcentages de réduction depuis VIK_REDUCTION
$sqlReduc = "SELECT red_nb_points, red_valeur FROM vik_reduction ORDER BY red_nb_points ASC";
$reductions = [];
LireDonneesPDO3($conn, $sqlReduc, $reductions);

// 3. Traitement de la validation du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['points_choice'])) {
        $pointsChoisis = (int) $_POST['points_choice'];
        $pourcentageReduction = 0;

        // Vérification dans le tableau des réductions récupéré
        foreach ($reductions as $red) {
            $nbPts = (int) ($red['RED_NB_POINTS'] ?? $red['red_nb_points']);
            $val = (float) ($red['RED_VALEUR'] ?? $red['red_valeur']);
            
            if ($nbPts === $pointsChoisis) {
                $pourcentageReduction = $val;
                break;
            }
        }

        if ($pourcentageReduction > 0 && $pointsChoisis <= $pointsUtilisateur) {
            // Calcul de la valeur en euros de la réduction
            $valeurEnEuros = $prixInitial * ($pourcentageReduction / 100);

            // On stocke les infos en session pour l'insertion dans reserver.php
            $_SESSION['reduction_points'] = $pointsChoisis;
            $_SESSION['reduction_valeur'] = $valeurEnEuros; // Montant exact à soustraire au total
            $_SESSION['points_fidelite_temp'] = $pointsUtilisateur - $pointsChoisis;

            header('Location: reserver.php');
            exit;
        } else {
            $erreur = "Vous n'avez pas assez de points pour cette réduction.";
        }
    } else {
        $erreur = "Veuillez sélectionner une option de réduction.";
    }
}
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:650px;">
        <div class="row">
            <div class="col-md-12">
                
                <div class="mb-3">
                    <a href="questionPointsFidelite.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                </div>

                <?php if ($erreur !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <div class="mb-4 p-3 rounded" style="background:#ffffff; border-radius:8px;">
                    <div class="p-3 rounded" style="background:#f9f9fb; border:1px solid #e0e0e0;">
                        <h2 class="h5 text-center">Vous avez <?= htmlspecialchars((string) $pointsUtilisateur) ?> points en cours !</h2>
                    </div>
                </div>

                <form method="post" action="utiliserPoints.php">
                    <div class="card mb-4" style="border-radius:15px;">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <?php 
                                    foreach ($reductions as $red): 
                                        $nbPts = (int) ($red['RED_NB_POINTS'] ?? $red['red_nb_points']);
                                        $pourcentage = (int) ($red['RED_VALEUR'] ?? $red['red_valeur']); // Cast en int pour un affichage propre (-1%, pas -1.00%)
                                        $isDisabled = ($pointsUtilisateur < $nbPts) ? 'disabled' : '';
                                    ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="points_choice" id="pts<?= $nbPts ?>" value="<?= $nbPts ?>" <?= $isDisabled ?>>
                                            <label class="form-check-label" for="pts<?= $nbPts ?>">
                                                Utiliser <?= $nbPts ?> points pour -<?= $pourcentage ?>%
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="col-4 text-end">
                                    <?php 
                                    foreach ($reductions as $red): 
                                        $pourcentage = (float) ($red['RED_VALEUR'] ?? $red['red_valeur']);
                                        $nouveauPrix = max(0, $prixInitial - $pourcentage);                                    ?>
                                        <div class="mb-3">
                                            <?= number_format($prixInitial, 2) ?> &rarr; <strong><?= number_format($nouveauPrix, 2) ?> €</strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-dark px-5 py-2">Appliquer la réduction</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>