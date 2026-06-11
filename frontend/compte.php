<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";
$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;
$_SESSION['current_page'] = 'compte.php'; 

if (isset($_SESSION["logged_in"])&&$_SESSION["logged_in"] && isset($_SESSION["utilisateur"]) && isset($_SESSION["utilisateur"]['num'])){
    $user = $_SESSION["utilisateur"];
    $role = [];
    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);
    LireDonneesPDO1($conn, "SELECT cli_role FROM VIK_CLIENT WHERE cli_num={$user['num']}", $role);
    if (isset($role[0]) && $role[0]["CLI_ROLE"] == 'admin'){
        if (isset($_GET["id"])){
            $id = $_GET["id"];
        }
        else{
            $id = $user["num"];
        }
    }
    else{
        $id = $user["num"];
    }
    $infos = [];
    LireDonneesPDO1($conn, "SELECT * FROM VIK_CLIENT WHERE cli_num=$id", $infos);
    echo "<pre>";
	print_r($infos);
	echo "</pre>";
    echo"kfjze";
}
else{
    die("Vous n'êtes pas connecté");
    //include_once __DIR__ . "/../includes/footer.php";



}?>




<div class="container mt-5">
  <div class="bg-light p-4 rounded shadow-sm border">

    <?php if (!empty($infos)): $client = $infos[0]; ?>

    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
      <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0"
           style="width:64px; height:64px; font-size:24px; font-weight:500; color:#5d3ebb;">
        <?= strtoupper(substr($client['CLI_PRENOM'] ?? '?', 0, 1) . substr($client['CLI_NOM'] ?? '?', 0, 1)) ?>
      </div>
      <div>
        <h5 class="mb-0 fw-semibold"><?= htmlspecialchars($client['CLI_PRENOM'] ?? '') ?> <?= htmlspecialchars($client['CLI_NOM'] ?? '') ?></h5>
        <span class="badge rounded-pill" style="background-color:#ede9f5; color:#5d3ebb;">
          <?= htmlspecialchars($client['CLI_ROLE'] ?? 'Client') ?>
        </span>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="bg-white rounded p-3 border h-100">
          <p class="text-muted small text-uppercase fw-semibold mb-3">Coordonnées</p>
          <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-envelope text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_COURRIEL'] ?? '—') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-telephone text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_TELEPHONE'] ?? '—') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-geo-alt text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_VILLE'] ?? '—') ?></span>
            </li>
          </ul>
        </div>
      </div>

      <div class="col-md-6">
        <div class="bg-white rounded p-3 border h-100">
          <p class="text-muted small text-uppercase fw-semibold mb-3">Points fidélité</p>
          <?php
            $pointsActuels = $client['CLI_NB_POINTS_EC'] ?? null;
            $pointsTotaux  = $client['CLI_NB_POINTS_TOT']  ?? null;
            $pct = ($pointsTotaux > 0) ? min(100, round($pointsActuels / $pointsTotaux * 100)) : 0;
          ?>
          <?php if ($pointsActuels !== null): ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="small text-muted">Points actuels</span>
              <span class="fw-semibold" style="color:#5d3ebb;"><?= $pointsActuels ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="small text-muted">Points totaux cumulés</span>
              <span class="fw-semibold"><?= $pointsTotaux ?></span>
            </div>
            <div class="progress" style="height:8px; border-radius:999px;">
              <div class="progress-bar" role="progressbar"
                   style="width:<?= $pct ?>%; background-color:#5d3ebb; border-radius:999px;"
                   aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
              </div>
            </div>
            <p class="text-muted small mt-1 mb-0"><?= $pct ?>% des points totaux encore disponibles</p>
          <?php else: ?>
            <p class="text-muted small mb-0">Aucune donnée de points disponible.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="modifier_compte.php" class="btn btn-sm rounded-pill px-3 fw-semibold"
         style="background-color:#ede9f5; color:#5d3ebb;">
        <i class="bi bi-pencil me-1"></i>Modifier mes informations
      </a>
      <a href="index.php?deconnexion=1" class="btn btn-sm btn-outline-danger rounded-pill px-3">
        <i class="bi bi-box-arrow-right me-1"></i>Se déconnecter
      </a>
    </div>

    <?php else: ?>
      <p class="text-muted">Impossible de charger les informations du compte.</p>
    <?php endif; ?>

  </div>
</div>


<?php include_once __DIR__ . "/../includes/footer.php";?>
