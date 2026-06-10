<?php
session_start();
 
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

$erreur = "";

if(isset($_POST["BtConnex"])){
    if (!empty($_POST["courriel"]) && !empty($_POST["mdp"])){
        $courriel = $_POST['courriel'];

        $conn = OuvrirConnexionPDO($dbOracle, $db_usernameOracle, $db_passwordOracle);
        $sql = "SELECT cli_num, cli_nom, cli_prenom, cli_courriel from vik_client
        where cli_courriel = :courriel";

        $cur = preparerRequetePDO($conn, $sql);
        $cur->bindValue(':courriel', $courriel, PDO::PARAM_STR);
        $nb = LireDonneesPDOPreparee($cur, $resultats);

        if($nb=1){
            $_SESSION["connecte"]   = true;
            $_SESSION["cli_num"] = $resultats[0]["cli_num"];
            $_SESSION["cli_nom"]    = $resultats[0]["CLI_NOM"];
            $_SESSION["cli_prenom"] = $resultats[0]["CLI_PRENOM"];

            $sqlUpdate = "UPDATE vik_client
                SET    cli_date_connec = SYSDATE
                WHERE  cli_num = :num
            ";
            $curUpdate = preparerRequetePDO($conn, $sqlUpdate);
            $curUpdate->bindValue(':num', $_SESSION["cli_num"], PDO::PARAM_INT);
            $curUpdate->execute();
 
                        $conn = null;
            header('Location: index.php');
            exit;
 
        } else {
            $erreur = "Courriel introuvable. Vérifiez votre adresse ou inscrivez-vous.";
        }
 
        $conn = null;
 
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
 
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – VIKING Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../public/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
 
<main class="container my-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-5">
 
            
            <h2 class="text-center mb-4">🚌 VIKING Transport</h2>
 
            <div class="card p-4 shadow-sm">
                <h1 class="card-title text-center mb-4 fs-4">Connexion</h1>
 
                <?php if ($erreur != ""): ?>
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
                               required>
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
