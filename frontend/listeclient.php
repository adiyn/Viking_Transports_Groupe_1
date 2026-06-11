<?php
    include_once "../includes/header.php";
    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";
    $db_username = $db_usernameOracle;		
	$db_password = $db_passwordOracle;	
	$db = $dbOracle;
    $conn = OuvrirConnexionPDO($db,$db_username,$db_password);

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $sql = "SELECT CLI_NUM, CLI_NOM, CLI_PRENOM, CLI_ROLE FROM vik_client WHERE upper(CLI_NOM) LIKE upper(:search) OR CLI_NUM LIKE :search OR upper(CLI_ROLE) LIKE upper(:search) OR upper(CLI_NOM) LIKE upper(:search)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['search' => '%' . $search . '%']);
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-people-fill"></i> Liste des Clients</h2>

    <form method="GET" action="" class="row g-3 mb-4 align-items-center">
        <div class="col-auto">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Rechercher (Nom, Num, Rôle)..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Rechercher</button>
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
			<th scope="col">Action</th>
			<th scope="col">Suppression</th>
		</tr>
	</thead>
            <tbody>
                <?php foreach($stmt as $client): ?>
                    <tr>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($client['CLI_NUM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_NOM']) ?></td>
                        <td><?= htmlspecialchars($client['CLI_PRENOM']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($client['CLI_ROLE']) ?></span></td>
                        <td class="text-center">
                            <a href='compte.php?id=<?= $client['CLI_NUM'] ?>' class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Voir
                            </a>
                        </td>
                        <td>
							<form method="POST" action="supprimer_client.php" class="d-inline">
								<input type="hidden" name="cli_num" value="<?= $client['CLI_NUM'] ?>">
								<button type="submit" class="btn btn-sm btn-outline-danger">
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
    include_once "../includes/footer.php";

?>
