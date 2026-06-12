<?php
// cron_inactifs.php : exécuté en tâche de fond par le navigateur de chacun :3
require_once __DIR__ . '/../config/param_connexion.php';
require_once __DIR__ . '/../config/pdo_agile.php';

try {
    $db_username = $db_usernameOracle;        
    $db_password = $db_passwordOracle;  
    $db = $dbOracle;
    $conn = OuvrirConnexionPDO($db, $db_username, $db_password);
    
    // Tache 2 : Sécurité et cohérence des points (meêm si on ajoute via bdd (j'espere))
    $sqlVerifPoints = "UPDATE vik_client 
                       SET cli_nb_points_tot = cli_nb_points_ec 
                       WHERE cli_nb_points_tot < cli_nb_points_ec";
    $conn->exec($sqlVerifPoints);

} catch (PDOException $e) {
    // NE RIEN METTRE ICI MERCI
}
?>