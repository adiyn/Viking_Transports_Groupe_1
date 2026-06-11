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

}
else{
    die("Vous n'êtes pas connecté");
}?>



<div class="container mt-5 flex-grow-1">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="bg-white p-4 p-md-5 rounded-4 shadow border-0">
<?php if (!empty($infos)): $client = $infos[0]; ?>

    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
      <div class="rounded-circle border d-flex align-items-center justify-content-center flex-shrink-0 shadow-smfw-bold text-white"
           style="width:64px; height:64px; font-size:22px; font-family:'Arial Black',sans-serif; background-color:#111111;">
        <?= strtoupper(substr($client['CLI_PRENOM'] ?? '?', 0, 1) . substr($client['CLI_NOM'] ?? '?', 0, 1)) ?>
      </div>
      <div>
        <h4 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($client['CLI_PRENOM'] ?? '') ?> <?= htmlspecialchars($client['CLI_NOM'] ?? '') ?></h4>
        <span class="badge rounded-pill px-3 py-1 mt-1 text-uppercase fw-bold small" style="background-color: #ba181b; color: white;">
          <?= htmlspecialchars($client['CLI_ROLE'] ?? 'Client') ?>
        </span>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="bg-light rounded-3 p-3 border border-light">
          <p class="text-muted small text-uppercase fw-bold mb-3"><i class="bi bi-shield-person me-2 text-danger"></i>Coordonnées du compte</p>
          <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fw-medium text-dark">
            <li class="d-flex align-items-center gap-2">
<<<<<<< HEAD
              <i class="bi bi-envelope text-muted"></i>
              <span><?= htmlspecialchars($client['CLI_MAIL'] ?? '—') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-telephone text-muted"></i>
              <span><?= htmlspecialchars($client['CLI_TEL'] ?? '—') ?></span>
=======
              <i class="bi bi-envelope text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_COURRIEL'] ?? '—') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-telephone text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_TELEPHONE'] ?? '—') ?></span>
>>>>>>> fdb5ffa0cc5d8450e1d252bf82818b185c951c18
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-geo-alt text-muted"></i>
              <span><?= htmlspecialchars($client['CLI_VILLE'] ?? '—') ?></span>
            </li>
          </ul>
        </div>
      </div>

      <?php if ($client['CLI_ROLE'] === 'client_fid'): ?>
      <div class="col-12">
        <div class="bg-light rounded-3 p-3 border border-light">
          <p class="text-muted small text-uppercase fw-bold mb-3"><i class="bi bi-award-fill me-2 text-danger"></i>Points Fidélité</p>
          <?php
            $pointsActuels = $client['CLI_POINTS_ACTUELS'] ?? null;
            $pointsTotaux  = $client['CLI_POINTS_TOTAUX']  ?? null;
            $pct = ($pointsTotaux > 0) ? min(100, round($pointsActuels / $pointsTotaux * 100)) : 0;
          ?>
          <?php if ($pointsActuels !== null): ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="small text-muted fw-semibold">Points disponible</span>
              <span class="fw-bold text-danger"><?= $pointsActuels ?> pts</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="small text-muted fw-semibold">Total historique accumulé</span>
              <span class="fw-semibold text-dark"><?= $pointsTotaux ?> pts</span>
            </div>
            <div class="progress bg-secondary-subtle" style="height:10px; border-radius:999px;">
              <div class="progress-bar rounded-pill" role="progressbar"
                   style="width:<?= $pct ?>%; background-color:#ba181b;"
                   aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
              </div>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i><?= $pct ?>% des points totaux encore disponibles.</p>
          <?php else: ?>
            <p class="text-muted small mb-0">Aucune donnée de points disponible.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

<<<<<<< HEAD
    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
      <a href="modifier_compte.php" class="btn text-white rounded-pill px-4 py-2 fw-bold shadow-sm w-100 text-center"
         style="background-color: #111111;">
        <i class="bi bi-pencil-square me-2"></i>Modifier mes infos
      </a>
      <a href="reservations.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold w-100 text-center">
        <i class="bi bi-ticket-perforated me-2"></i>Mes réservations
=======
    <div class="d-flex gap-2">
      <a href="modifier_compte.php?$id" class="btn btn-sm rounded-pill px-3 fw-semibold"
         style="background-color:#ede9f5; color:#5d3ebb;">
        <i class="bi bi-pencil me-1"></i>Modifier mes informations
      </a>
      <a href="reservations.php?$id" class="btn btn-sm rounded-pill px-3 fw-semibold btn-outline-secondary">
        <i class="bi bi-ticket-perforated me-1"></i>Voir mes réservations
>>>>>>> fdb5ffa0cc5d8450e1d252bf82818b185c951c18
      </a>
    </div>

<?php else: ?>
  <div class="alert alert-warning text-center rounded-3">Impossible de charger les informations du client.</div>
<?php endif; ?>
</div>
</div>
</div>
</div>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>