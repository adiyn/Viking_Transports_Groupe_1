<?php
    include('../includes/header.php');
?>

<form action="action_page.php" style="">
    <div class="container">
        <h1>Sign Up</h1>
        <p>Please fill in this form to create an account.</p>
        <hr>

        <label for="email"><b>Email</b>
            <input type="text" placeholder="Entrez une adresse email" name="email" required>
        </label>

        <label for="fname"><b>Nom complet</b>
            <input type="text" placeholder="Entrez votre nom complet" name="fname" required>
        </label>

        <label for="phone"><b>Numéro de téléphone</b>
            <input type="text" placeholder="Entrez votre numéro de téléphone" name="phone" required>
        </label>

        <label for="psw"><b>Mot de passe</b>
            <input type="password" placeholder="Entrez un mot de passe" name="psw" required>
        </label>

        <label for="psw-repeat"><b>Confirmer le mot de passe</b>
            <input type="password" placeholder="Entrez à nouveau votre mot de passe" name="psw-repeat" required>
        </label>

        <label>
            <input type="checkbox" checked="checked" name="remember" style="margin-bottom:15px"> se souvenir de moi
        </label>

        <p>En créant un compte, vous acceptez nos <a href="#" style="">Conditions d'Utilisation</a> et notre <a href="#" style="">Politique de Confidentialité</a>.</p>

        <div class="clearfix">
            <button type="button" class="cancelbtn">Cancel</button>
            <button type="submit" class="signupbtn">Sign Up</button>
        </div>
    </div>
</form>

<?php
    include('../includes/footer.php');
?>