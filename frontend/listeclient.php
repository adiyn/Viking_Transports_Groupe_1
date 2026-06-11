<?php
    include_once "../includes/header.php";
    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";
    $db_username = $db_usernameOracle;        
    $db_password = $db_passwordOracle;  
    $db = $dbOracle;
    $conn = OuvrirConnexionPDO($db,$db_username,$db_password);

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_ROLE, 
               TO_CHAR(CLI_DATE_CONNEC, 'YYYY-MM-DD HH24:MI:SS') AS CLI_DATE_CONNEC 
        FROM vik_client 
        WHERE upper(CLI_NOM) LIKE upper(:search) 
           OR CLI_NUM LIKE :search 
           OR upper(CLI_ROLE) LIKE upper(:search)
           OR upper(CLI_PRENOM) LIKE upper(:search)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['search' => '%' . $search . '%']);
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-people-fill text-danger"></i> Liste des Clients</h2>

    <form method="GET" action="" class="row g-3 mb-4 align-items-center">
        <div class="col-auto">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Rechercher (Nom, Num, Rôle)..." value="<?= htmlspecialchars($search) ?>">
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
                <?php foreach($stmt as $client): ?>
                    <?php 
                        $estInactif = true;
                        $texteJours = "Jamais connecté"; 
                        
                        if (!empty($client['CLI_DATE_CONNEC'])) {
                            $derniereConnexion = new DateTime($client['CLI_DATE_CONNEC']);
                            $aujourdhui = new DateTime();
                            $intervalle = $aujourdhui->diff($derniereConnexion);
                            $nbJours = $intervalle->days;

                            if ($nbJours < 365) {
                                $estInactif = false;
                            }

                            if ($nbJours === 0) {
                                $texteJours = "Aujourd'hui";
                            } elseif ($nbJours === 1) {
                                $texteJours = "Hier";
                            } else {
                                $texteJours = "Il y a " . $nbJours . " jours";
                            }
                        }
                    ?>
                    <tr>
                        <td class="fw-bold text-danger" style="color: #ba181b !important;"><?= htmlspecialchars($client['CLI_NUM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_NOM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_PRENOM']) ?></td>
                        <td><span class="badge bg-dark text-white fw-medium"><?= htmlspecialchars($client['CLI_ROLE']) ?></span></td>
                        
                        <td>
                            <?php if ($estInactif): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-dash-circle-fill"></i> Inactif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill"></i> Actif
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="text-muted small fw-medium">
                            <?= $texteJours ?>
                        </td>

                        <td>
                            <a href='compte.php?id=<?= $client['CLI_NUM'] ?>' class="btn btn-sm btn-outline-danger" style="color: #ba181b; border-color: #ba181b;">
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

<?php
    if (isset($stmt)) $stmt->closeCursor();
    $conn = null;
    include_once "../includes/footer.php";
?>
