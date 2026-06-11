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

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);
$_SESSION['current_page'] = 'compte.php'; // Stocker la page actuelle pour redirection après connexion
// A finir ?



?>