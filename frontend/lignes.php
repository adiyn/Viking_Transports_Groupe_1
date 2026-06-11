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
    SELECT DISTINCT com_nom FROM VIK_NOEUD
    JOIN VIK_COMMUNE ON VIK_COMMUNE.com_code_insee = VIK_NOEUD.com_code_insee_arret
    WHERE TRIM(VIK_NOEUD.lig_num) = :ligne
    UNION
    SELECT DISTINCT com_nom FROM VIK_NOEUD
    JOIN VIK_COMMUNE ON VIK_COMMUNE.com_code_insee = VIK_NOEUD.com_code_insee_suivant
    WHERE TRIM(VIK_NOEUD.lig_num) = :ligne
    order by com_nom ASC
";
$curArrets = preparerRequetePDO($conn, $sqlArrets);
$curArrets->bindValue(':ligne', trim($selected), PDO::PARAM_STR);
LireDonneesPDOPreparee($curArrets, $arrets);
$nbArrets = count($arrets);
?>

<meta charset="UTF-8">

<div class="container-fluid px-4 mt-4">

  <div class="mb-4">
    <h1 class="fs-3 fw-medium mb-1">Lignes & arrêts</h1>
    <p class="text-muted mb-0">Sélectionnez une ligne pour visualiser ses communes desservies.</p>
  </div>

  <div class="row g-3" style="min-height: calc(100vh - 160px);">

    <div class="col-md-2">
      <div class="card border rounded-3 p-3 h-100">
        <p class="text-uppercase text-muted small fw-semibold mb-2">
          <i class="ti ti-route me-1"></i>Ligne
        </p>
        <select class="form-select form-select-sm"
                onchange="window.location.href='lignes.php?ligne=' + this.value;">
          <?php foreach ($lignes as $ligne): ?>
            <option value="<?= htmlspecialchars($ligne['LIG_NUM']) ?>"
              <?= trim($ligne['LIG_NUM']) == trim($selected) ? 'selected' : '' ?>>
              Ligne <?= htmlspecialchars($ligne['LIG_NUM']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div class="mt-3 pt-3 border-top">
          <p class="text-muted small mb-1">Communes desservies</p>
          <span class="badge rounded-pill bg-primary fs-6"><?= $nbArrets ?></span>
        </div>

        <div class="mt-3 pt-3 border-top">
          <a href="horaire.php?ligne=<?= $selected ?>"
             class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="ti ti-clock"></i> Horaires
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card border rounded-3 overflow-hidden p-0 h-100" style="min-height: 500px;">
        <img class="w-100 h-100 object-fit-cover"
             src="../public/img/carte.png"
             alt="Carte de la ligne <?= htmlspecialchars($selected) ?>">
      </div>
    </div>

    <div class="col-md-2">
      <div class="card border rounded-3 p-3 h-100">
        <p class="text-uppercase text-muted small fw-semibold mb-2">
          <i class="ti ti-map-pin me-1"></i>Arrêts
        </p>
        <ul class="list-unstyled mb-0">
          <?php foreach ($arrets as $arret): ?>
            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
              <span style="width:9px; height:9px; border-radius:50%;
                           background:#0d6efd; flex-shrink:0;"></span>
              <?= htmlspecialchars($arret['COM_NOM']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>