<?php
// cron_inactifs.php : cript exécuté en tâche de fond par le navigateur de chacun :3
require_once __DIR__ . '/../config/param_connexion.php';
require_once __DIR__ . '/../config/pdo_agile.php';

try {
    $db_username = $db_usernameOracle;        
    $db_password = $db_passwordOracle;  
    $db = $dbOracle;
    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);
    
    $sqlInactif = "UPDATE vik_client 
               SET CLI_ROLE = 'inactif' 
               WHERE CLI_DATE_CONNEC < SYSDATE - 30 
               AND UPPER(CLI_ROLE) NOT IN ('ADMIN', 'INACTIF')";
    $conn->exec($sqlInactif);

} catch (PDOException $e) {
    // NE RIEN METTRE ICI MERCI
}
?>