<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";

$_SESSION['current_page'] = 'lignes.php'; // Stocker la page actuelle pour redirection après connexion


$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;

$selected = isset($_GET['ligne']) ? trim((string) $_GET['ligne']) : '1A';

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

if ($conn === null) {
    die("Erreur: Impossible de se connecter à la base de données.");
}

$lignes = [];
LireDonneesPDO1($conn, "SELECT * FROM VIK_LIGNE", $lignes);

$arrets = [];
$sqlArrets = "
    SELECT com_code_insee, com_nom FROM 
(
    SELECT com_code_insee,com_nom,MIN(ordre) ordre FROM 
    (
        SELECT co.com_code_insee,co.com_nom,no.noe_heure_passage AS ordre
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_arret
        WHERE TRIM(no.lig_num) = :ligne
        UNION ALL
        SELECT co.com_code_insee,co.com_nom,no.noe_heure_passage + (no.noe_duree_prochain/1440)
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_suivant
        WHERE TRIM(no.lig_num) = :ligne
    )
    GROUP BY com_code_insee, com_nom
)
ORDER BY ordre
";
$curArrets = preparerRequetePDO($conn, $sqlArrets);
$curArrets->bindValue(':ligne', trim($selected), PDO::PARAM_STR);
LireDonneesPDOPreparee($curArrets, $arrets);
$nbArrets = count($arrets);
?>

<meta charset="UTF-8">

<div class="container-fluid px-4 mt-4">

  <div class="mb-4">
    <h1 class="fs-3 fw-bold mb-1 text-center" style="color: #111111;">Lignes & arrêts</h1>
    <p class="text-muted mb-0 text-center">Sélectionnez une ligne pour visualiser ses communes desservies.</p>
  </div>

  <div class="row g-3" style="min-height: calc(100vh - 160px);">

    <div class="col-md-3 col-lg-2">
      <div class="card border rounded-4 p-3 bg-white shadow-sm h-100">
        <p class="text-uppercase text-muted small fw-bold mb-3">
          <i class="bi bi-compass me-2 text-danger"></i>Itinéraire
        </p>
        <select class="form-select form-select-sm rounded-3 fw-semibold py-2"
                onchange="window.location.href='lignes.php?ligne=' + this.value;">
          <?php foreach ($lignes as $ligne): ?>
            <option value="<?= htmlspecialchars($ligne['LIG_NUM']) ?>"
              <?= trim($ligne['LIG_NUM']) == trim($selected) ? 'selected' : '' ?>>
              Ligne <?= htmlspecialchars($ligne['LIG_NUM']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
          <span class="text-muted small fw-medium">Arrêts desservis :</span>
          <span class="badge rounded-pill fs-6 px-3 py-1 text-white" style="background-color: #ba181b;"><?= $nbArrets ?></span>
        </div>

        <div class="mt-4 pt-3 border-top">
          <a href="horaire.php?ligne=<?= $selected ?>"
             class="btn btn-outline-dark btn-sm w-100 rounded-pill d-flex align-items-center justify-content-center gap-2 py-2 fw-bold">
            <i class="bi bi-clock-history"></i> Grille Horaires
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-lg-7">
      <div class="card border rounded-4 overflow-hidden p-0 h-100 shadow-sm" style="min-height: 500px;">
        <img class="w-100 h-100 object-fit-cover"
             src="../public/img/carte.png"
             alt="Carte de la ligne <?= htmlspecialchars($selected) ?>">
      </div>
    </div>

    <div class="col-md-3 col-lg-3">
      <div class="card border rounded-4 p-3 bg-white shadow-sm h-100">
        <p class="text-uppercase text-muted small fw-bold mb-2">
          <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Parcours
        </p>
        <ul class="list-unstyled mb-0 overflow-y-auto" style="max-height: 550px;">
          <?php foreach ($arrets as $arret): ?>
            <li class="d-flex align-items-center gap-3 py-2 border-bottom text-dark fw-medium">
              <span style="width:10px; height:10px; border-radius:50%;
                           background:#ba181b; flex-shrink:0;" class="shadow-sm"></span>
              <?= htmlspecialchars($arret['COM_NOM']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>