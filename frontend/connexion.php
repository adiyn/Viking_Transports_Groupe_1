<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

$erreur = "";

if (isset($_POST["BtConnex"])) {
    if (!empty($_POST["courriel"]) && !empty($_POST["mdp"])) {
        $courriel = $_POST['courriel'];
        $mdp = $_POST['mdp'];
        
        $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        
        if ($conn != null) {
            $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_COURRIEL, CLI_MDP, CLI_ROLE
                    FROM VIK_CLIENT WHERE CLI_COURRIEL = :courriel";

            $cur = preparerRequetePDO($conn, $sql);
            $cur->bindValue(':courriel', $courriel, PDO::PARAM_STR);
            $resultats = [];
            $nb = LireDonneesPDOPreparee($cur, $resultats);

            if ($nb === 1 && password_verify($mdp, $resultats[0]['CLI_MDP'])) {
                
                // On crée une variable locale propre pour éviter les répétitions
                $userRow = $resultats[0];
                $userRole = $userRow['CLI_ROLE'];

                // Si le compte était inactif, il repasse 'client'
                if (strtoupper($userRole) === 'INACTIF') {
                    $userRole = 'client'; // On met à jour la variable pour la session
                    $sqlReact = "UPDATE VIK_CLIENT SET CLI_ROLE = 'client' WHERE CLI_NUM = :num";
                    $stmtReact = $conn->prepare($sqlReact);
                    $stmtReact->execute(['num' => $userRow['CLI_NUM']]);
                }

                // Enregistrement des informations de l'utilisateur en session
                $_SESSION['utilisateur'] = [
                    'num'    => $userRow["CLI_NUM"],
                    'nom'    => $userRow["CLI_NOM"],
                    'prenom' => $userRow["CLI_PRENOM"],
                    'role'   => $userRole // Contiendra 'admin', 'client', ou 'client' réactivé
                ];
                $_SESSION['logged_in'] = true;
                
                // MISE À JOUR DE LA DATE : Faite proprement par requête préparée pour tout le monde
                $sqlUpdate = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
                $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
                $curUpdate->bindValue(':num', $userRow["CLI_NUM"], PDO::PARAM_INT);
                $curUpdate->execute();
                
                $conn = null;
                header('Location: /index.php');
                exit;
            } else {
                $erreur = "Courriel ou mot de passe incorrect.";
            }
            $conn = null;
        } else {
            $erreur = "Erreur de connexion à la base.";
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-5">
            
            <h2 class="text-center mb-4">VIKING Transport</h2>

            <div class="card p-4 shadow-sm">
                <h1 class="card-title text-center mb-4 fs-4">Connexion</h1>

                <?php if ($erreur != ""): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="mb-3">
                        <label for="courriel" class="form-label fw-semibold text-muted small">Adresse e-mail</label>
                        <input type="email"
                                class="form-control rounded-3"
                                id="courriel"
                                name="courriel"
                                placeholder="ex : jean.dupont@mail.fr"
                                value="<?= isset($_POST['courriel']) ? htmlspecialchars($_POST['courriel']) : '' ?>"
                                required>
                    </div>

                    <div class="mb-3">
                        <label for="mdp" class="form-label fw-semibold text-muted small">Mot de passe</label>
                        <input type="password"
                                class="form-control rounded-3"
                                id="mdp"
                                name="mdp"
                                autocomplete="off"
                                required>
                    </div>

                    <div class="d-grid mb-3 mt-4">
                        <button type="submit" name="BtConnex" class="btn rounded-pill text-white fw-bold py-2 shadow-sm btn-viking-action"
                        style="background-color: #111111;">
                            <i class="bi bi-shield-lock-fill me-2"></i>Se connecter
                        </button>
                    </div>

                    <div class="d-grid">
                        <a href="inscription.php" class="btn btn-outline-dark rounded-pill fw-bold py-2">
                            S'inscrire
                        </a>
                    </div>

                </form>

            </div>

        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>