<?php
require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../config/param_connexion.php";
require_once __DIR__ . "/../config/pdo_agile.php";

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

// Sécurité anti-crash
if (!$conn) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Erreur :
    impossible de se connecter à la BDD (si vous voyez ça c'est que qqc ne va VRAIMENT pas 
    ou alors vous êtes en local et n'avez pas d'accès à la BDD).</div></div>");
}

$ligne_recherchee = isset($_GET['ligne']) ? $_GET['ligne'] : '1A';

// trim et chr pour rendre la requete plus sécure et éviter les erreurs de formatage surtout xd
$sql = "SELECT 
    nom AS nom_arret, 
    LISTAGG(TO_CHAR(passage, 'HH24:MI'), ', ') WITHIN GROUP (ORDER BY passage ASC) AS liste_horaires
FROM (
    SELECT co.com_nom AS nom, no.noe_heure_passage AS passage
    FROM vik_noeud no
    JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_arret
    WHERE TRIM(no.lig_num) = :ligne
    UNION ALL
    SELECT co.com_nom, no.noe_heure_passage + (no.noe_duree_prochain / 1440) AS passage
    FROM vik_noeud no
    JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_suivant
    WHERE TRIM(no.lig_num) = :ligne
)
GROUP BY nom
ORDER BY MIN(passage) ASC"; 

$curStmt = preparerRequetePDO($conn, $sql);
$curStmt->bindValue(':ligne', trim($ligne_recherchee), PDO::PARAM_STR);
$curStmt->execute(); 
$stmt = $curStmt;
?>

<!-- au lieu d'echo on peut faire du HTML directement, c'est plus lisible et plus facile à maintenir :D  -->
<main class="container my-5 flex-grow-1">
    
    <div class="mb-4">
        <a href="lignes.php?ligne=<?= htmlspecialchars(trim($ligne_recherchee)) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> Retour à la ligne
        </a>
    </div>

    <div class="card p-4 p-md-5 shadow-sm border-0 rounded-4">
        <h2 class="mb-4 fw-bold" style="color: #2b2438;">
            <i class="bi bi-clock-history me-2" style="color: #ba181b;"></i>
            Horaires de la ligne <?= htmlspecialchars(trim($ligne_recherchee)) ?>
        </h2>

        <div class="table-responsive">
            <table class="table table-hover align-middle border rounded overflow-hidden">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="py-3 px-4 w-25 text-uppercase text-muted small fw-semibold">Arrêt</th>
                        <th scope="col" class="py-3 px-4 text-uppercase text-muted small fw-semibold">Horaires de passage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td class="fw-semibold py-3 px-4 text-dark">
                                <?= htmlspecialchars($row['NOM_ARRET'] ?? $row['nom_arret']) ?>
                            </td>
                            
                            <td class="py-3 px-4">
                                <?php 
                                $liste_brute = $row['LISTE_HORAIRES'] ?? $row['liste_horaires'];

                                if (!empty($liste_brute)):
                                    $horaires = explode(',', $liste_brute);
                                    
                                    foreach ($horaires as $heure): 
                                ?>
                                    <span class="badge rounded-pill fw-medium me-1 mb-1 px-3 py-2" 
                                        style="background-color: #f1f3f5; color: #000000; font-size: 0.85rem;">
                                        <i class="bi bi-clock small me-1 opacity-75"></i> 
                                        <?= htmlspecialchars(trim($heure)) ?>
                                    </span>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <em class="text-muted small">Aucun horaire prévu</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php 
$conn = null;
require_once __DIR__ . "/../includes/footer.php"; 
?>