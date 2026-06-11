<?php
session_start();
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";
?>

<?php
$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;
$_SESSION['current_page'] = 'compte.php'; 
if (isset($_SESSION["user_id"])){
    if (isset($_SESSION["user_role"]) && $_SESSION["user_role"]=="admin"){
        if (isset($_GET["id"])){
            $id = $_GET["id"];
        }
        else{
            $id = $_SESSION["user_id"];
        }
    }
    else{
        $id = $_SESSION["user_id"];
    }
    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);
    $infos = [];
    LireDonneesPDO1($conn, "SELECT * FROM VIK_CLIENT WHERE cli_num=$id", $infos);
    echo "<pre>";
		print_r($infos);
		echo "</pre>";
}
else{
    echo"Vous n'êtes pas connecté";
}?>

<?php include_once __DIR__ . "/../includes/footer.php";?>
