<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";
$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;

if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] && isset($_SESSION["utilisateur"]) && isset($_SESSION["utilisateur"]['num'])){
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
}
?>

<div class="container mt-5 flex-grow-1">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="bg-white p-4 p-md-5 rounded-4 shadow border-0">
<?php if (!empty($infos)): $client = $infos[0]; ?>

    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
      <div class="rounded-circle border d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm fw-bold text-white"
           style="width:64px; height:64px; font-size:22px; font-family:'Arial Black',sans-serif; background-color:#111111;">
        <?= strtoupper(substr($client['CLI_PRENOM'] ?? '?', 0, 1) . substr($client['CLI_NOM'] ?? '?', 0, 1)) ?>
      </div>
      <div>
        <h4 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($client['CLI_PRENOM'] ?? '') ?> <?= htmlspecialchars($client['CLI_NOM'] ?? '') ?></h4>
        <span class="badge rounded-pill px-3 py-1 mt-1 text-light fw-bold small" style="background-color: #86808b;">
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
              <i class="bi bi-envelope text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_COURRIEL'] ?? '-') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-telephone text-secondary"></i>
              <span><?= htmlspecialchars($client['CLI_TELEPHONE'] ?? '-') ?></span>
            </li>
            <li class="d-flex align-items-center gap-2">
              <i class="bi bi-geo-alt text-muted"></i>
              <span><?= htmlspecialchars($client['CLI_VILLE'] ?? '-') ?></span>
            </li>
          </ul>
        </div>
      </div>
      <div class="col-12">
        <div class="bg-light rounded-3 p-3 border border-light">
          <p class="text-muted small text-uppercase fw-bold mb-3"><i class="bi bi-award-fill me-2 text-danger"></i>Points Fidélité</p>
          <?php
            $pointsActuels = (!is_null($client['CLI_NB_POINTS_EC']) ? $client['CLI_NB_POINTS_EC'] : 0);
            $pointsTotaux  = (!is_null($client['CLI_NB_POINTS_TOT']) ? $client['CLI_NB_POINTS_TOT'] : 0);
          ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="small text-muted fw-semibold">Points disponible</span>
              <span class="fw-bold text-danger"><?= $pointsActuels ?> pts</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="small text-muted fw-semibold">Total accumulé</span>
              <span class="fw-semibold text-dark"><?= $pointsTotaux ?> pts</span>
            </div>
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4 justify-content-center">
      <a href="modifier_compte.php?id=<?=$id;?>" class="btn btn-sm rounded-pill px-3 fw-semibold text-warning d-flex align-items-center"
         style="background-color: #111111;">
        <i class="bi bi-pencil me-2 text-warning"></i>Modifier les informations
      </a>
      <a href="reservations.php?id=<?=$id;?>" class="btn btn-sm rounded-pill px-3 fw-semibold btn-outline-secondary text-success d-flex align-items-center">
        <i class="bi bi-ticket-perforated me-2"></i>Voir les réservations
      </a>

      <?php if ($isAdmin): ?>
        <div class="col-12 mt-4 pt-3 border-top w-100">
            <p class="text-muted small text-uppercase fw-bold text-center mb-3">
                <i class="bi bi-shield-lock-fill text-danger me-2"></i>Console d'administration
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                
                <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill shadow-sm" 
                   href="/frontend/listeclient.php"
                   style="background-color: #502c2c; border: 1px solid #7a4848;">
                    <i class="bi bi-people text-danger me-2"></i>Liste Clients
                </a>
                
                <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill shadow-sm" 
                   href="/frontend/statistique.php"
                   style="background-color: #502c2c; border: 1px solid #7a4848;">
                    <i class="bi bi-bar-chart-fill text-danger me-2"></i>Statistiques
                </a>
                
                <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill shadow-sm" 
                   href="/frontend/modifierLignesHoraire.php"
                   style="background-color: #502c2c; border: 1px solid #7a4848;">
                    <i class="bi bi-clock-fill text-danger me-2"></i>Horaires Lignes
                </a>
                
                <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill shadow-sm" 
                   href="/frontend/testsql.php"
                   style="background-color: #502c2c; border: 1px solid #7a4848;">
                    <i class="bi bi-filetype-sql text-danger me-2"></i>Executer du SQL
                </a>
                
            </div>
        </div>
      <?php endif; ?>
    </div>
<?php endif; ?>
</div>
</div>
</div>
</div>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>