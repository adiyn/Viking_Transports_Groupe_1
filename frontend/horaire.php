<?php
require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../config/param_connexion.php";
require_once __DIR__ . "/../config/pdo_agile.php";

$db_username = $db_usernameOracle;		
$db_password = $db_passwordOracle;	
$db = $dbOracle;
$conn = OuvrirConnexionPDO($db,$db_username,$db_password);

$ligne_recherchee = isset($_GET['ligne']) ? $_GET['ligne'] : '1A';

$sql = "SELECT c.COM_NOM AS nom_arret, 
    LISTAGG(TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI'), ',') WITHIN GROUP (ORDER BY n.NOE_HEURE_PASSAGE ASC) AS liste_horaires
FROM VIK_COMMUNE c
INNER JOIN VIK_NOEUD n ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
WHERE n.LIG_NUM = :ligne
GROUP BY c.COM_CODE_INSEE, c.COM_NOM"; 

$curStmt = preparerRequetePDO($conn, $sql);
// injection SQL impossible grâce au bindValue (il devient une valeur et pas du code lisible par la BDD)
// l'utilisateur aurait pu faire "1A' OR '1'='1" en gros xd 
$curStmt->bindValue(':ligne', $ligne_recherchee, PDO::PARAM_STR);
$curStmt->execute(); // là on peu éxécuter :D
$stmt = $curStmt;

    echo "<table style='border-collapse: collapse; width: 100%; border: 1px solid #ccc; font-family: sans-serif;'>";
    echo "  <thead>";
    echo "    <tr style='background-color: #f4f4f4; border-bottom: 2px solid #ccc;'>";
    echo "      <th style='padding: 10px; text-align: left; border: 1px solid #ccc; width: 25%;'>Arrêt</th>";
    echo "      <th style='padding: 10px; text-align: left; border: 1px solid #ccc;'>Horaires de passage</th>";
    echo "    </tr>";
    echo "  </thead>";
    echo "  <tbody>";

    // 3. Boucle d'affichage des lignes
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "    <tr style='border-bottom: 1px solid #eee;'>";
        
        // Colonne 1 : Nom de l'arrêt
        echo "      <td style='padding: 10px; font-weight: bold; border: 1px solid #ccc;'>";
        echo htmlspecialchars($row['NOM_ARRET'] ?? $row['nom_arret']);
        echo "      </td>";
        
        // Colonne 2 : Les cases d'horaires
        echo "      <td style='padding: 10px; border: 1px solid #ccc;'>";
        
        $liste_brute = $row['LISTE_HORAIRES'] ?? $row['liste_horaires'];

        if (!empty($liste_brute)) {
            // PHP découpe la chaîne à chaque virgule et crée un tableau
            $horaires = explode(',', $liste_brute);
            
            // On boucle sur chaque horaire pour générer un joli bloc HTML style "case"
            foreach ($horaires as $heure) {
                echo "<span style='display: inline-block; 
                                    background-color: #e3f2fd; 
                                    color: #0d47a1; 
                                    padding: 6px 10px; 
                                    margin: 4px; 
                                    border: 1px solid #bbdefb; 
                                    border-radius: 4px; 
                                    font-weight: bold;
                                    font-size: 0.9em;'>";
                echo htmlspecialchars(trim($heure));
                echo "</span>";
            }
        } else {
            echo "<em style='color: #999;'>Aucun horaire prévu</em>";
        }
        
        echo "      </td>";
        echo "    </tr>";
    }

    echo "  </tbody>";
    echo "</table>";
    $conn = null; // Fermer la connexion à la base de données
?>
        
