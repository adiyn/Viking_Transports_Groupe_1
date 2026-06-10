<?php
include_once "../config/param_connexion.php";
include_once "../config/pdo_agile.php";
include('../includes/header.php');

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email   = $_POST['email'] ?? '';
    $fname   = $_POST['fname'] ?? '';
    $lname   = $_POST['lname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone   = $_POST['phone'] ?? '';
    $psw     = $_POST['psw'] ?? '';

    $psw_hache = password_hash($psw, PASSWORD_BCRYPT);
    
    define("MOD_BDD", "ORACLE"); 

    if (MOD_BDD == "MYSQL") {
        $db_username = $db_usernameMySQL;        
        $db_password = $db_passwordMySQL;
        $db = $dbMySQL;
    } else {
        $db_username = $db_usernameOracle;        
        $db_password = $db_passwordOracle;    
        $db = $dbOracle;
    }

    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);

    if ($conn != null) {
        $sql = "INSERT INTO users (email, fname, lname, address, phone, psw) 
                VALUES (:email, :fname, :lname, :address, :phone, :psw)";
        
        $stmt = $conn->prepare($sql);
        
        $result = $stmt->execute([
            ':email'   => $email,
            ':fname'   => $fname,
            ':lname'   => $lname,
            ':address' => $address,
            ':phone'   => $phone,
            ':psw'     => $psw_hache
        ]);

        if ($result) {
            $message = "<div class='alert alert-success text-center'>Compte créé avec succès ! 🎉</div>";
        } else {
            $message = "<div class='alert alert-danger text-center'>Erreur lors de l'inscription.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger text-center'>Erreur : Impossible de se connecter à la base de données.</div>";
    }
}
?>

<div class="container mt-5">
    <?php echo $message; ?>

    <form action="" method="POST" class="bg-light p-4 rounded shadow-sm border">
        <h1>Inscription</h1>
        <p class="text-muted">Veuillez remplir ce formulaire pour créer un compte.</p>
        <hr>

        <div class="mb-3">
            <label for="email" class="form-label"><b>Email</b></label>
            <input type="email" class="form-control" placeholder="exemple@domaine.com" name="email" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="fname" class="form-label"><b>Nom</b></label>
                <input type="text" class="form-control" placeholder="Tom" name="fname" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="lname" class="form-label"><b>Prénom</b></label>
                <input type="text" class="form-control" placeholder="Jedusor" name="lname" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="address" class="form-label"><b>Ville</b></label>
            <input type="text" class="form-control" placeholder="Caen" name="address" required>
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label"><b>Numéro de téléphone</b></label>
            <input type="text" class="form-control" placeholder="06 12 34 56 78" name="phone" required>
        </div>

        <div class="mb-3">
            <label for="psw" class="form-label"><b>Mot de passe</b></label>
            <input type="password" class="form-control" placeholder="Password123" name="psw" required>
        </div>

        <div class="mb-3">
            <label for="psw-repeat" class="form-label"><b>Confirmer le mot de passe</b></label>
            <input type="password" class="form-control" placeholder="Password123" name="psw-repeat" required>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" checked="checked" name="remember" id="remember">
            <label class="form-check-label" for="remember">Se souvenir de moi</label>
        </div>

        <p>En créant un compte, vous acceptez nos <a href="CGU.php">Conditions d'Utilisation</a> et notre 
        <a href="PDC.php">Politique de Confidentialité</a>.</p>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50">Inscription</button>
        </div>
    </form>
</div>

<?php
include('../includes/footer.php');
?>