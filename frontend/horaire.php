<?php
require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../config/param_connexion.php";
require_once __DIR__ . "/../config/pdo_agile.php";

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

// Sécurité anti-crash
if (!$conn) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Erreur :
    impossible de se connecter à la BDD (si vous voyez ça c'est que qqc ne va VRAIMENT pas 
    ou alors vous êtes en local et n'avez pas d'accès à la BDD).</div></div>");
}

$ligne_recherchee = isset($_GET['ligne']) ? $_GET['ligne'] : '1A';

// trim et chr pour rendre la requete plus sécure et éviter les erreurs de formatage surtout xd
$sql = "SELECT c.COM_NOM AS nom_arret, 
    LISTAGG(TO_CHAR(n.NOE_HEURE_PASSAGE, 'HH24:MI'), ',') WITHIN GROUP (ORDER BY n.NOE_HEURE_PASSAGE ASC) AS liste_horaires
FROM VIK_COMMUNE c
INNER JOIN VIK_NOEUD n ON c.COM_CODE_INSEE = n.COM_CODE_INSEE_ARRET
WHERE TRIM(n.LIG_NUM) = :ligne
GROUP BY c.COM_CODE_INSEE, c.COM_NOM"; 

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
            <i class="bi bi-clock-history me-2" style="color: #5d3ebb;"></i>
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
                                        style="background-color: #ede9f5; color: #5d3ebb; font-size: 0.85rem;">
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