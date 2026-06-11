<?php
session_start(); // On démarre la session en tout premier pour pouvoir la remplir
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

$erreur = "";

if (isset($_POST["BtConnex"])) {
    if (!empty($_POST["courriel"]) && !empty($_POST["mdp"])) {
        $courriel = $_POST['courriel'];
        $mdp      = $_POST['mdp'];
        
        $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        
        if ($conn != null) {
            $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_COURRIEL, CLI_MDP, CLI_ROLE
                    FROM VIK_CLIENT WHERE CLI_COURRIEL = :courriel";

            $cur = preparerRequetePDO($conn, $sql);
            $cur->bindValue(':courriel', $courriel, PDO::PARAM_STR);
            $resultats = [];
            $nb = LireDonneesPDOPreparee($cur, $resultats);

            if ($nb === 1 && password_verify($mdp, $resultats[0]['CLI_MDP'])) {
                $_SESSION['utilisateur'] = [
                    'num'    => $resultats[0]["CLI_NUM"],
                    'nom'    => $resultats[0]["CLI_NOM"],
                    'prenom' => $resultats[0]["CLI_PRENOM"]
                ];
                $_SESSION['logged_in'] = true;
                
                $sqlUpdate = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
                $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
                $curUpdate->bindValue(':num', $resultats[0]["CLI_NUM"], PDO::PARAM_INT);
                $curUpdate->execute();
                
                $conn = null;
                header('Location: index.php'); // La redirection marchera car aucun HTML n'a été généré !
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

                <?php if ($erreur != ""): ?> <!-- Afficher les erreurs de connexion -->
                    <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="mb-3">
                        <label for="courriel" class="form-label">Adresse e-mail</label>
                        <input type="email"
                                class="form-control"
                                id="courriel"
                                name="courriel"
                                placeholder="ex : jean.dupont@mail.fr"
                                value="<?= isset($_POST['courriel']) ? htmlspecialchars($_POST['courriel']) : '' ?>"
                                required> <!-- Conserver l'email saisi en cas d'erreur -->
                    </div>

                    <div class="mb-3">
                        <label for="mdp" class="form-label">Mot de passe</label>
                        <input type="password"
                                class="form-control"
                                id="mdp"
                                name="mdp"
                                autocomplete="off"
                                required>
                    </div>

                    <div class="d-grid mb-2">
                        <button type="submit" name="BtConnex" class="btn btn-primary">
                            Se connecter
                        </button>
                    </div>

                    <div class="d-grid">
                        <a href="inscription.php" class="btn btn-outline-primary">
                            S'inscrire
                        </a>
                    </div>

                </form>

            </div>

        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>