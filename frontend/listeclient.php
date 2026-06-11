<?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";
    $db_username = $db_usernameOracle;        
    $db_password = $db_passwordOracle;  
    $db = $dbOracle;
    $conn = OuvrirConnexionPDO($db,$db_username,$db_password);

    // il faut sauver le soldat ryan ahh
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_sauvetage'])) {
        if ($_POST['action_sauvetage'] === 'sauver_un' && !empty($_POST['cli_num'])) {
            $idSauver = intval($_POST['cli_num']);
            $conn->exec("UPDATE vik_client SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_NUM = $idSauver");
            $_SESSION['flash_success'] = "Le compte #$idSauver a été sauvé de la suppression.";
        }
        elseif ($_POST['action_sauvetage'] === 'sauver_tout') {
            $conn->exec("UPDATE vik_client SET CLI_DATE_CONNEC = SYSDATE WHERE CLI_DATE_CONNEC <= SYSDATE - 730 AND UPPER(CLI_ROLE) != 'ADMIN'");
            $_SESSION['flash_success'] = "Tous les comptes en sursis ont été sauvés !";
        }
        
        // La redirection fonctionne maintenant car AUCUN HTML n'a été affiché !
        header("Location: listeclient.php");
        exit;
    }

    // un commentaire tout entier pour juste inclure header, wow
    include_once "../includes/header.php";

    // assassinat silencieux des comtpes en sursis
    $conn->beginTransaction();
    $stmtGetOld = $conn->query("SELECT CLI_NUM FROM vik_client WHERE CLI_DATE_CONNEC < SYSDATE - 732 AND UPPER(CLI_ROLE) != 'ADMIN'");
    $oldClients = $stmtGetOld->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($oldClients)) {
        // Sécurité : On supprime d'abord les contraintes (étapes, réservations)
        $inQuery = implode(',', array_map('intval', $oldClients));
        $conn->exec("DELETE FROM vik_etape WHERE CLI_NUM IN ($inQuery)");
        $conn->exec("DELETE FROM vik_reservation WHERE CLI_NUM IN ($inQuery)");
        $conn->exec("DELETE FROM vik_client WHERE CLI_NUM IN ($inQuery)");
    }
    $conn->commit();

    // on ficha le soldat ryan et ses copains (jcp ce que je raconte aled)
    $stmtAttente = $conn->query("SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, TO_CHAR(CLI_DATE_CONNEC, 'YYYY-MM-DD') AS DCO 
                                 FROM vik_client 
                                 WHERE CLI_DATE_CONNEC <= SYSDATE - 730 
                                 AND UPPER(CLI_ROLE) != 'ADMIN'");
    $clientsEnAttente = $stmtAttente->fetchAll();
    $nbEnAttente = count($clientsEnAttente);


    // le tablo + rehcerche
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    if (empty($search)) {
        // si vide, on récupère tout sans filtre
        $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_ROLE, 
                TO_CHAR(CLI_DATE_CONNEC, 'YYYY-MM-DD HH24:MI:SS') AS CLI_DATE_CONNEC 
                FROM vik_client";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        // si il y a une recherche, on applique le filtre
        $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_ROLE, 
                TO_CHAR(CLI_DATE_CONNEC, 'YYYY-MM-DD HH24:MI:SS') AS CLI_DATE_CONNEC 
                FROM vik_client 
                WHERE upper(CLI_NOM) LIKE upper(:search) 
                   OR CLI_NUM LIKE :search 
                   OR upper(CLI_ROLE) LIKE upper(:search)
                   OR upper(CLI_PRENOM) LIKE upper(:search)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['search' => '%' . $search . '%']);
    }
?>

<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people-fill text-danger"></i> Liste des Clients</h2>
        
        <?php if ($nbEnAttente > 0): ?>
            <button type="button" class="btn fw-bold text-white shadow-sm" style="background-color: #ba181b;" data-bs-toggle="modal" data-bs-target="#modalSursis">
                <i class="bi bi-hourglass-split"></i> <?= $nbEnAttente ?> compte(s) en sursis !
            </button>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success bg-success-subtle border-success-subtle text-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['flash_success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <form method="GET" action="" class="row g-3 mb-4 align-items-center">
        <div class="col-auto">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" 
                    placeholder="Rechercher (Nom, Num, Rôle)..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-danger" style="background-color: #ba181b; border-color: #ba181b;">Rechercher</button>
            <?php if (!empty($search)): ?>
                <a href="?" class="btn btn-outline-secondary">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive shadow-sm rounded" style="min-height: 600px;">
        <table class="table table-hover align-middle mb-0 text-center">
            <thead class="table-light">
                <tr>
                    <th scope="col">Numéro</th>
                    <th scope="col">Nom</th>
                    <th scope="col">Prénom</th>
                    <th scope="col">Rôle</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col">Action</th>
                    <th scope="col">Suppression</th>
                </tr>
            </thead>
            <tbody>
                
                <?php 
                // DEBUG
                echo "Nombre de clients trouvés : " . $stmt->rowCount(); 
                ?>

                <?php foreach($stmt as $client): ?>
                    <?php 
                        // statuts
                        $estInactif = false;
                        $suppImminente = false;
                        $aSupp = false;
                        
                        $statusText = "Actif";
                        $badgeClass = "bg-success-subtle text-success border-success-subtle";
                        
                        // intervalisation
                        $derniereConnexion = new DateTime($client['CLI_DATE_CONNEC']);
                        $aujourdhui = new DateTime();
                        $nbJours = $aujourdhui->diff($derniereConnexion)->days;

                        if ($nbJours >= 730) {
                            $aSupp = true;
                            $statusText = "En file d'attente (2j)";
                            $badgeClass = "bg-danger text-white";
                        } 
                        elseif ($nbJours >= 700) {
                            $suppImminente = true;
                            $statusText = "Suppression imminente";
                            $badgeClass = "bg-dark text-light border border-secondary";
                        } 
                        elseif ($nbJours >= 365) {
                            $estInactif = true;
                            $statusText = "Inactif";
                            $badgeClass = "bg-warning-subtle text-warning border-warning-subtle";
                        }

                        $texteJours = ($nbJours === 0) ? "Aujourd'hui" : "Il y a " . $nbJours . " jours";
                    ?>

                    <tr>
                        <td class="fw-bold text-danger" style="color: #ba181b !important;">
                            <?= htmlspecialchars($client['CLI_NUM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_NOM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_PRENOM']) ?></td>
                        <td><span class="badge bg-dark text-white fw-medium">
                            <?= htmlspecialchars($client['CLI_ROLE']) ?></span></td>
                        
                        <td>
                            <span class="badge <?= $badgeClass ?> border">
                                <?= $statusText ?>
                            </span>
                        </td>

                        <td class="text-muted small fw-medium">
                            <?= $texteJours ?>
                        </td>

                        <td>
                            <a href='compte.php?id=<?= $client['CLI_NUM'] ?>' class="btn btn-sm btn-outline-danger" 
                                style="color: #ba181b; border-color: #ba181b;">
                                <i class="bi bi-eye"></i> Voir
                            </a>
                        </td>
                        <td>
                            <form method="POST" action="supprimer_client.php" class="d-inline">
                                <input type="hidden" name="cli_num" value="<?= $client['CLI_NUM'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash3-fill"></i> Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalSursis" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="background-color: #121212; border: 1px solid #3d3030;">
      <div class="modal-header border-bottom border-secondary">
        <h5 class="modal-title fw-bold text-white">
            <i class="bi bi-exclamation-triangle-fill" style="color: #ba181b;"></i> Suppressions Automatiques</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-white-50">Ces comptes ont dépassé les 730 jours d'inactivité. 
            Ils sont dans le délai de grâce (2 jours) avant purge définitive de la base.</p>
        
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle border-secondary mb-0">
                <tbody>
                    <?php foreach($clientsEnAttente as $c): ?>
                        <tr>
                            <td class="fw-bold" style="color: #ba181b;">#<?= $c['CLI_NUM'] ?></td>
                            <td><?= htmlspecialchars($c['CLI_PRENOM'] . ' ' . $c['CLI_NOM']) ?></td>
                            <td class="text-white-50 small">Inactif depuis : <?= $c['DCO'] ?></td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action_sauvetage" value="sauver_un">
                                    <input type="hidden" name="cli_num" value="<?= $c['CLI_NUM'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill fw-semibold">
                                        <i class="bi bi-shield-check"></i> Annuler la supp.
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer border-top border-secondary d-flex justify-content-between">
        <button type="button" class="btn btn-outline-light rounded-pill" data-bs-dismiss="modal">Fermer</button>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action_sauvetage" value="sauver_tout">
            <button type="submit" class="btn btn-success rounded-pill fw-bold">
                <i class="bi bi-check2-all"></i> Tout annuler & Sauvegarder
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div id="nuh-uh-overlay" style="position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); 
        z-index:9999; display:flex; flex-direction:column; align-items:center; justify-content:center;">
        <img src="https://www.gif-vif.com/trending/nuh-uh-scared-cat-face-vajrzvf5vbivuxek.gif" style="width: 300px; 
            border-radius: 15px; box-shadow: 0 0 20px rgba(186, 24, 27, 0.5);">
        <h2 style="color: #ba181b; margin-top: 20px; font-weight: bold; text-transform: uppercase;">Nuh uh !</h2>
        <p style="color: white; font-size: 1.2rem;"><?= $_SESSION['flash_error'] ?></p>
    </div>

    <script>
        setTimeout(function() {
            document.getElementById('nuh-uh-overlay').style.display = 'none';
        }, 3000);
    </script>
    
    <?php unset($_SESSION['flash_error']);?>
<?php endif; ?>

<?php
    if (isset($stmt)) $stmt->closeCursor();
    $conn = null;
    include_once "../includes/footer.php";
?>