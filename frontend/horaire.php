<?php
require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../config/param_connexion.php";
require_once __DIR__ . "/../config/pdo_agile.php";

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

// Sécurité anti-crash
if (!$conn) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Erreur : impossible de se connecter à la BDD.</div></div>");
}

$ligne_recherchee = isset($_GET['ligne']) ? trim($_GET['ligne']) : '1A';

// --------------------------------------------------------------------------------
// 1. Récupération de l'ordre exact des arrêts de la ligne
// --------------------------------------------------------------------------------
$sqlArrets = "
    SELECT com_nom FROM (
        SELECT com_nom, MIN(ordre) as ordre FROM (
            SELECT co.com_nom, no.noe_heure_passage AS ordre
            FROM vik_noeud no
            JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_arret
            WHERE TRIM(no.lig_num) = :ligne
            UNION ALL
            SELECT co.com_nom, no.noe_heure_passage + (no.noe_duree_prochain / 1440)
            FROM vik_noeud no
            JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_suivant
            WHERE TRIM(no.lig_num) = :ligne
        )
        GROUP BY com_nom
    ) ORDER BY ordre ASC
";

$stArrets = preparerRequetePDO($conn, $sqlArrets);
ajouterParamPDO($stArrets, ':ligne', $ligne_recherchee, 'texte');
$tabArrets = [];
LireDonneesPDOPreparee($stArrets, $tabArrets);

$arrets_ordonnes = [];
foreach ($tabArrets as $a) {
    $arrets_ordonnes[] = $a['COM_NOM'] ?? $a['com_nom'];
}

// --------------------------------------------------------------------------------
// 2. Récupération de tous les horaires et construction de la grille 2D
// --------------------------------------------------------------------------------
$sqlHoraires = "
    SELECT com_nom, passage FROM (
        SELECT co.com_nom, TO_CHAR(no.noe_heure_passage, 'HH24:MI') AS passage
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_arret
        WHERE TRIM(no.lig_num) = :ligne
        UNION
        SELECT co.com_nom, TO_CHAR(no.noe_heure_passage + (no.noe_duree_prochain / 1440), 'HH24:MI') AS passage
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_suivant
        WHERE TRIM(no.lig_num) = :ligne
    )
    ORDER BY passage ASC
";

$stHoraires = preparerRequetePDO($conn, $sqlHoraires);
ajouterParamPDO($stHoraires, ':ligne', $ligne_recherchee, 'texte');
$tabHoraires = [];
LireDonneesPDOPreparee($stHoraires, $tabHoraires);

// Initialisation de la grille
$grille = [];
$max_courses = 0;

foreach ($arrets_ordonnes as $arret) {
    $grille[$arret] = [];
}

// Remplissage des données
foreach ($tabHoraires as $h) {
    $nom = $h['COM_NOM'] ?? $h['com_nom'];
    $passage = $h['PASSAGE'] ?? $h['passage'];
    
    if (isset($grille[$nom])) {
        $grille[$nom][] = $passage;
    }
}

// Calcul du nombre maximum de colonnes (trajets) nécessaires
foreach ($grille as $horaires) {
    if (count($horaires) > $max_courses) {
        $max_courses = count($horaires);
    }
}
?>

<style>
    /* Styles spécifiques pour reproduire l'effet de grille du screenshot */
    .table-horaires {
        font-size: 0.85rem;
        white-space: nowrap;
        border-collapse: collapse;
    }
    /* Garde la première colonne fixe lors du scroll horizontal */
    .table-horaires th:first-child,
    .table-horaires td:first-child {
        position: sticky;
        left: 0;
        background-color: #f1f3f5; 
        font-weight: 600;
        z-index: 1;
        box-shadow: inset -1px 0 0 #dee2e6; /* Ligne de séparation droite */
        min-width: 200px;
    }
    .table-horaires td {
        text-align: center;
        padding: 0.4rem 0.6rem !important;
        vertical-align: middle;
        color: #444;
    }
    /* Survol de la ligne pour faciliter la lecture */
    .table-horaires tbody tr:hover td {
        background-color: #e9ecef;
    }
</style>

<main class="container-fluid px-4 my-4 flex-grow-1">
    
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h2 class="mb-0 fw-bold" style="color: #382424;">
            <i class="bi bi-clock-history me-2" style="color: #ba181b;"></i>
            Horaires - Ligne <?= htmlspecialchars($ligne_recherchee) ?>
        </h2>
        <a href="lignes.php?ligne=<?= urlencode($ligne_recherchee) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <div class="card p-0 shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-horaires mb-0">
                <tbody>
                    <?php if (empty($arrets_ordonnes)): ?>
                        <tr>
                            <td class="text-center py-4 text-muted fst-italic">Aucun horaire disponible pour cette ligne.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($arrets_ordonnes as $arret): ?>
                            <tr>
                                <td><?= htmlspecialchars($arret) ?></td>
                                
                                <?php for ($i = 0; $i < $max_courses; $i++): ?>
                                    <td>
                                        <?php if (isset($grille[$arret][$i])): ?>
                                            <?= htmlspecialchars($grille[$arret][$i]) ?>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php 
$conn = null;
require_once __DIR__ . "/../includes/footer.php"; 
?>