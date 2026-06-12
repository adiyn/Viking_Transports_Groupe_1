<?php
include_once "../config/param_connexion.php";
include_once "../config/pdo_agile.php";
include('../includes/header.php');

$_SESSION['current_page'] = 'inscription.php'; // Stocker la page actuelle pour redirection après connexion

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email   = $_POST['email'] ?? '';
    $fname   = $_POST['fname'] ?? '';
    $lname   = $_POST['lname'] ?? '';
    $address = $_POST['address'] ?? ''; // Représente la ville dans le formulaire
    $phone   = $_POST['phone'] ?? '';
    $psw     = $_POST['psw'] ?? '';

    // Validation basique des champs
    if (empty($email) || empty($fname) || empty($lname) || empty($address) || empty($phone) || empty($psw)) {
        $message = "<div class='alert alert-danger text-center'>Veuillez remplir tous les champs.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger text-center'>Adresse email invalide.</div>";
    } else {
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
            try {
                $conn->beginTransaction();
                // Récupérer le MAX actuel
                $sqlMax = "SELECT MAX(cli_num) AS max_id FROM vik_client";
                $tabResult = [];
                LireDonneesPDO3($conn, $sqlMax, $tabResult);
                $maxId = $tabResult[0]['MAX_ID'] ?? 0;
                $nextId = $maxId + 1;
                
                // Vérifier que le mail ou telephone n'existent pas déjà
                $checkSql = "SELECT COUNT(*) AS NB FROM vik_client WHERE cli_courriel = :email OR cli_telephone = :phone";
                $cur = preparerRequetePDO($conn, $checkSql);
                $cur->execute([':email' => $email, ':phone' => $phone]);
                $tabCheck = $cur->fetchAll(PDO::FETCH_ASSOC);
                $exists = $tabCheck[0]['NB'] ?? 0;
                $psw_repeat = $_POST['psw-repeat'] ?? '';
                
                if ($psw !== $psw_repeat) {
                    $message = "<div class='alert alert-danger text-center'>Les mots de passe ne correspondent pas.</div>";
                } elseif ($exists != 0) {
                    $message = "<div class='alert alert-danger text-center'>Cette adresse email ou ce numéro de téléphone est déjà utilisé.</div>";
                } elseif (!preg_match('/^\d{2} \d{2} \d{2} \d{2} \d{2}$/', $phone)) {
                    $message = "<div class='alert alert-danger text-center'>Le numéro de téléphone doit être au format 00 00 00 00 00 (ex. 06 12 34 56 78).</div>";
                } else {
                    // Insertion du nouveau client
                    $sql = "INSERT INTO vik_client (cli_num, cli_courriel, cli_nom, cli_prenom, cli_ville, cli_telephone, 
                    cli_mdp, cli_role, typ_num, cli_nb_points_ec, cli_nb_points_tot) 
                            VALUES (:cli_num, :email, :fname, :lname, :address, :phone, :psw, 'client_fid', 1, 0, 0)";

                    //bindValue pour éviter les injections
                    $curInsert = preparerRequetePDO($conn, $sql);
                    $result = majDonneesPrepareesTabPDO($curInsert, [
                        ':cli_num' => $nextId,
                        ':email'   => $email,
                        ':fname'   => $fname,
                        ':lname'   => $lname,
                        ':address' => $address,
                        ':phone'   => $phone,
                        ':psw'     => $psw_hache
                    ]);
                    
                    // oupsi 
                    $conn->commit();

                    if ($result) {
                        $message = "<div class='alert alert-success text-center'>Compte créé avec succès ! Votre numéro client 
                        est le <strong>$nextId</strong></div>";
                    } else {
                        $message = "<div class='alert alert-danger text-center'>Erreur lors de l'inscription.</div>";
                    }
                }
                
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger text-center'>Erreur SQL : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger text-center'>Erreur : Impossible de se connecter à la base de données.</div>";
        }
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
            <input type="text" class="form-control" placeholder="06 12 34 56 78" name="phone" id="phone"
                pattern="\d{2} \d{2} \d{2} \d{2} \d{2}"
                title="Format : 00 00 00 00 00"
                inputmode="numeric"
                required>
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

        <div class="d-flex gap-3 mt-4">
            <button type="button" class="btn btn-outline-secondary rounded-pill w-50 fw-bold py-2" onclick="window.location.href='/index.php'">Annuler</button>
            <button type="submit" class="btn rounded-pill text-white w-50 fw-bold py-2 shadow-sm btn-viking-action" style="background-color: #ba181b;">
                <i class="bi bi-person-plus-fill me-2"></i>S'inscrire
            </button>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone');
        if (!phoneInput) return;

        phoneInput.addEventListener('input', function(e) {
            let value = this.value.replace(/\s/g, '');
            value = value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            let formatted = '';
            for (let i = 0; i < value.length; i += 2) {
                if (i > 0) formatted += ' ';
                formatted += value.substring(i, i + 2);
            }
            this.value = formatted;
        });
    });
    </script>
<?php
include('../includes/footer.php');
?>