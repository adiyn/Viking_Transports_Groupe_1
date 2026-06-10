<?php
    include('../includes/header.php');
?>

<form action="" style="">
    <div class="container">
        <h1>Sign Up</h1>
        <p>Please fill in this form to create an account.</p>
        <hr>

        <label for="email"><b>Email</b>
            <input type="text" placeholder="exemple@domaine.com" name="email" required>
        </label>

        <label for="fname"><b>Nom</b>
            <input type="text" placeholder="Tom" name="fname" required>
        </label>

        <label for="lname"><b>Prénom</b>
            <input type="text" placeholder="Jedusor" name="lname" required>
        </label>

        <label for="address"><b> Ville </b>
            <input type="text" placeholder="Caen" name="address" required>
        </label>

        <label for="phone"><b>Numéro de téléphone</b>
            <input type="text" placeholder="06 12 34 56 78" name="phone" required>
        </label>

        <label for="psw"><b>Mot de passe</b>
            <input type="password" placeholder="Password123" name="psw" required>
        </label>

        <label for="psw-repeat"><b>Confirmer le mot de passe</b>
            <input type="password" placeholder="Password123" name="psw-repeat" required>
        </label>

        <label>
            <input type="checkbox" checked="checked" name="remember" style="margin-bottom:15px"> se souvenir de moi
        </label>

        <p>En créant un compte, vous acceptez nos <a href="CGU.php" style="">Conditions d'Utilisation</a> 
        et notre <a href="PDC.php" style="">Politique de Confidentialité</a>.</p>

        <div class="clearfix">
            <button type="button" class="cancelbtn">Cancel</button>
            <button type="submit" class="signupbtn">Sign Up</button>
        </div>
    </div>
</form>

<?php
    include('../includes/footer.php');
    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";

    $email = $_POST['email'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $psw = $_POST['psw'];
    
    
    $sql = "INSERT INTO users (email, fname, lname, address, phone, psw)
            VALUES ('$email', '$fname', '$lname', '$address', '$phone', '$psw')";

    $conn = OuvrirConnexionPDO($db,$db_username,$db_password);

    $result = $conn->exec($sql);

    if ($result) {
        echo "Compte crée :D";
    } else {
        echo "Erreur: " . $sql . "<br>" . $conn->errorInfo()[2];
    }
?>