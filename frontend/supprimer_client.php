<?php
if (session_status() === PHP_SESSION_NONE) session_start();


$isAdmin = isset($_SESSION['utilisateur']['role']) && strtoupper($_SESSION['utilisateur']['role']) === 'ADMIN';

if (!$isAdmin) {
    
    header('Location: index.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cli_num'])) {
    
    
    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";
    
    $db_username = $db_usernameOracle;      
    $db_password = $db_passwordOracle;  
    $db = $dbOracle;
    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);

	$clientId = $_POST['cli_num'];

    // pour éviter que les admins se supp entre eux mdr
    $stmtCheck = $conn->prepare("SELECT CLI_ROLE FROM vik_client WHERE CLI_NUM = :id");
    $stmtCheck->execute(['id' => $clientId]);
    $cible = $stmtCheck->fetch();

    if ($cible && strtoupper($cible['CLI_ROLE']) === 'ADMIN') {
        $_SESSION['flash_error'] = "Nuh uh! toi pas pouvoir supprimer admin, camarade.";
        header('Location: listeclient.php');
        exit;
    }

    $conn->beginTransaction();

    $sqlEtape = "DELETE FROM vik_etape WHERE CLI_NUM = :id";
    $stmtEtape = $conn->prepare($sqlEtape);
    $stmtEtape->execute(['id' => $clientId]);

    $sqlRes = "DELETE FROM vik_reservation WHERE CLI_NUM = :id";
    $stmtRes = $conn->prepare($sqlRes);
    $stmtRes->execute(['id' => $clientId]);

    $sqlCli = "DELETE FROM vik_client WHERE CLI_NUM = :id";
    $stmtCli = $conn->prepare($sqlCli);
    $stmtCli->execute(['id' => $clientId]);
    $conn->commit();
}


header('Location: listeclient.php'); 
exit;
?>