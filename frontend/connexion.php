<?php
<<<<<<< HEAD
session_start();
$_SESSION['current_page'] = 'connexion.php'; // Stocker la page actuelle pour redirection après connexion
=======
require_once __DIR__ . '/../includes/header.php';
>>>>>>> 009dcca75e17450c1e9d795d8213538f4aaab015
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

$erreur = "";

if (isset($_POST["BtConnex"])) {
    if (!empty($_POST["courriel"]) && !empty($_POST["mdp"])) {
        $courriel = $_POST['courriel'];
        $mdp      = $_POST['mdp'];
        
        $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        
        if ($conn != null) {
            // Requête pour récupérer les informations de l'utilisateur
            $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_COURRIEL, CLI_MDP, CLI_ROLE
                    FROM VIK_CLIENT
                    WHERE CLI_COURRIEL = :courriel";

            // Préparer et exécuter la requête
            $cur = preparerRequetePDO($conn, $sql);
            $cur->bindValue(':courriel', $courriel, PDO::PARAM_STR);
            $resultats = [];
            $nb = LireDonneesPDOPreparee($cur, $resultats);

<<<<<<< HEAD
            // Vérifier les résultats et le mot de passe
            if ($nb === 1 && password_verify($mdp, $resultats[0]['CLI_MDP'])) {
                
                // UNE SEULE CRÉATION DE SESSION
                $_SESSION['user_id'] = $resultats[0]["CLI_NUM"];
                $_SESSION['user_nom'] = $resultats[0]["CLI_NOM"];
                $_SESSION['user_prenom'] = $resultats[0]["CLI_PRENOM"];
                $_SESSION['user_email'] = $resultats[0]["CLI_COURRIEL"];
                $_SESSION['user_role'] = $resultats[0]["CLI_ROLE"] ?? 'client';
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                // Mettre à jour la date de connexion
                $sqlUpdate = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
                $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
                $curUpdate->bindValue(':num', $resultats[0]["CLI_NUM"], PDO::PARAM_INT);
                $curUpdate->execute();
                
                // Fermer la connexion
                $conn = null;
                
                // Redirection vers l'accueil
                header('Location: index.php');
                exit();
                
            } else {
                $erreur = "Courriel ou mot de passe incorrect.";
            }
            $conn = null;
        } else {
            $erreur = "Erreur de connexion à la base de données.";
        }
=======
        // Vérifier les résultats et le mot de passe
        if ($nb === 1 && password_verify($mdp, $resultats[0]['CLI_MDP'])) {
            $_SESSION['utilisateur'] = [
                'num'    => $resultats[0]["CLI_NUM"],
                'nom'    => $resultats[0]["CLI_NOM"],
                'prenom' => $resultats[0]["CLI_PRENOM"]
            ];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            
            // Mettre à jour la date de connexion
            $sqlUpdate = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
            $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
            $curUpdate->bindValue(':num', $resultats[0]["CLI_NUM"], PDO::PARAM_INT);
            $curUpdate->execute();
            
            $conn = null;
            header('Location: index.php');
            exit;
        } else {
            $erreur = "Courriel ou mot de passe incorrect.";
        }
        
        $conn = null;
>>>>>>> 009dcca75e17450c1e9d795d8213538f4aaab015
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
<<<<<<< HEAD
=======
?>
>>>>>>> 009dcca75e17450c1e9d795d8213538f4aaab015

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