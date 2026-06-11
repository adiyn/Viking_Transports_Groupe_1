<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";

$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: connexion.php');
    exit;
}

if (isset($_GET["id"])){
    $id = $_GET["id"];
        }
    else{
        $_SESSION['utilisateur']['num'];
    }
$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

$infos = [];
LireDonneesPDO1($conn, "SELECT * FROM VIK_CLIENT WHERE cli_num = $id", $infos);
$client = $infos[0] ?? [];

$succes = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = htmlspecialchars(trim($_POST['nom']));
    $prenom  = htmlspecialchars(trim($_POST['prenom']));
    $mail    = htmlspecialchars(trim($_POST['mail']));
    $tel     = htmlspecialchars(trim($_POST['tel']));
    $mdp     = trim($_POST['mdp'] ?? '');
    $mdp2    = trim($_POST['mdp2'] ?? '');

    if (!empty($mdp) && $mdp !== $mdp2) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $sql = "UPDATE VIK_CLIENT SET 
                    CLI_NOM    = '$nom',
                    CLI_PRENOM = '$prenom',
                    CLI_COURRIEL   = '$mail',
                    CLI_TELEPHONE    = '$tel'";
        if (!empty($mdp)) {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $sql .= ", CLI_MDP = '$hash'";
        }
        $sql .= " WHERE CLI_NUM = $id";

        majDonneesPDO($conn, $sql);
        $succes = 'Informations mises à jour avec succès.';

        // Recharger les infos
        $infos = [];
        LireDonneesPDO1($conn, "SELECT * FROM VIK_CLIENT WHERE cli_num = $id", $infos);
        $client = $infos[0] ?? [];
    }
}
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="bg-light p-4 rounded shadow-sm border">

        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
          <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:64px; height:64px; font-size:24px; font-weight:500; color: #bb3e64;">
            <?= strtoupper(substr($client['CLI_PRENOM'] ?? '?', 0, 1) . substr($client['CLI_NOM'] ?? '?', 0, 1)) ?>
          </div>
          <div>
            <h5 class="mb-0 fw-semibold">Modifier mon profil</h5>
            <span class="text-muted small">Les champs modifiés seront mis à jour</span>
          </div>
        </div>

        <?php if ($succes): ?>
          <div class="alert alert-success rounded-3 small py-2"><?= $succes ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
          <div class="alert alert-danger rounded-3 small py-2"><?= $erreur ?></div>
        <?php endif; ?>

        <form method="POST">

          <div class="bg-white rounded p-3 border mb-3">
            <p class="text-muted small text-uppercase fw-semibold mb-3">Informations personnelles</p>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small text-muted">Nom</label>
                <input type="text" name="nom" class="form-control form-control-sm rounded-pill"
                       value="<?= htmlspecialchars($client['CLI_NOM'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Prénom</label>
                <input type="text" name="prenom" class="form-control form-control-sm rounded-pill"
                       value="<?= htmlspecialchars($client['CLI_PRENOM'] ?? '') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Adresse mail</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0 rounded-start-pill">
                    <i class="bi bi-envelope text-secondary"></i>
                  </span>
                  <input type="email" name="mail" class="form-control border-start-0 rounded-end-pill"
                         value="<?= htmlspecialchars($client['CLI_COURRIEL'] ?? '') ?>" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Téléphone</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0 rounded-start-pill">
                    <i class="bi bi-telephone text-secondary"></i>
                  </span>
                  <input type="tel" name="tel" class="form-control border-start-0 rounded-end-pill"
                         value="<?= htmlspecialchars($client['CLI_TELEPHONE'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white rounded p-3 border mb-4">
            <p class="text-muted small text-uppercase fw-semibold mb-1">Mot de passe</p>
            <p class="text-muted small mb-3">Laissez vide pour ne pas modifier</p>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-muted">Nouveau mot de passe</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0 rounded-start-pill">
                    <i class="bi bi-lock text-secondary"></i>
                  </span>
                  <input type="password" name="mdp" class="form-control border-start-0 rounded-end-pill"
                         placeholder="••••••••">
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Confirmer le mot de passe</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0 rounded-start-pill">
                    <i class="bi bi-lock-fill text-secondary"></i>
                  </span>
                  <input type="password" name="mdp2" class="form-control border-start-0 rounded-end-pill"
                         placeholder="••••••••">
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm rounded-pill px-4 fw-semibold"
                    style="background-color: #bb3e6e; color: #ffffff;">
              <i class="bi bi-check2 me-1"></i>Enregistrer
            </button>
            <a href="compte.php?id=<?=$id?>" class="btn btn-sm rounded-pill px-3 fw-semibold"
               style="background-color: #f5e9ec; color: #bb3e78;">
              <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
          </div>

        </form>

      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>