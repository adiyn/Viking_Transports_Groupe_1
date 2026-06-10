<?php
session_start();
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';
$utilisateurValide = false;
$erreur = "";

if (isset($_POST["BtConnex"])) {
    if (!empty($_POST["courriel"]) && !empty($_POST["mdp"])) {
        $courriel = $_POST['courriel'];
        $mdp      = $_POST['mdp'];
        
        $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        
        // Requête pour récupérer les informations de l'utilisateur
        $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_COURRIEL, CLI_MDP
                FROM VIK_CLIENT
                WHERE CLI_COURRIEL = :courriel";

        // Préparer et exécuter la requête
        $cur = preparerRequetePDO($conn, $sql);
        $cur->bindValue(':courriel', $courriel, PDO::PARAM_STR);
        $nb = LireDonneesPDOPreparee($cur, $resultats);

        // Vérifier les résultats et le mot de passe
        if ($nb === 1 && password_verify($mdp, $resultats[0]['CLI_MDP'])) {
            $_SESSION["connecte"]   = true;
            $_SESSION["cli_num"]    = $resultats[0]["CLI_NUM"];
            $_SESSION["cli_nom"]    = $resultats[0]["CLI_NOM"];
            $_SESSION["cli_prenom"] = $resultats[0]["CLI_PRENOM"];
            // Mettre à jour la date de connexion
            $sqlUpdate = "UPDATE VIK_CLIENT SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = :num";
        if($nb == 1){ // si un utilisateur correspond à l'email
            $_SESSION['utilisateur'] = [
                'num'    => $resultats[0]["CLI_NUM"],
                'nom'    => $resultats[0]["CLI_NOM"],
                'prenom' => $resultats[0]["CLI_PRENOM"]
            ];
            // Mettre à jour la date de connexion
            $sqlUpdate = "UPDATE vik_client
                SET    cli_date_connec = SYSDATE
                WHERE  cli_num = :num
            ";

            // Exécuter la mise à jour de la date de connexion
            $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
            $curUpdate->bindValue(':num', $_SESSION["cli_num"], PDO::PARAM_INT);
            $curUpdate->execute();
            
            // Rediriger vers la page d'accueil après une connexion réussie
            $conn = null;
            header('Location: index.php');
            $utiliszateurValide = true;
            exit;

            } else { // Si l'email n'existe pas ou le mot de passe est incorrect
                $erreur = "Courriel ou mot de passe incorrect.";
            }
            // Fermer la connexion
            $conn = null;

        } else { // Si les champs sont vides
            $erreur = "Veuillez remplir tous les champs.";
        }
    }
}
if ($utilisateurValide) { // Si les informations d'identification sont valides, créer la session
    $_SESSION['user_id'] = $cli_num;
    $_SESSION['user_nom'] = $cli_nom;
    $_SESSION['user_prenom'] = $cli_prenom;
    $_SESSION['user_email'] = $cli_courriel;
    $_SESSION['user_role'] = $cli_role;
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time(); // Enregistrer le timestamp de la dernière activité
    $_SESSION['deconnexion'] = 0; // Initialiser le flag de déconnexion
    header('Location: accueil.php');
    exit();
}
include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – VIKING Transport</title>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

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