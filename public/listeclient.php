<?php
    include_once "../includes/header.php";
    include_once "../config/param_connexion.php";
    include_once "../config/pdo_agile.php";
    $db_username = $db_usernameOracle;		
	$db_password = $db_passwordOracle;	
	$db = $dbOracle;
    $conn = OuvrirConnexionPDO($db,$db_username,$db_password);

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $sql = "SELECT CLI_NUM, CLI_NOM, CLI_ROLE FROM vik_client WHERE CLI_NOM LIKE :search";
    
    $stmt = $conn->prepare($sql);
    // On ajoute les jokers (%) pour chercher n'importe où dans le nom
    $stmt->execute(['search' => '%' . $search . '%']);
?>

<form method="GET" action="">
    <input type="text" name="search" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Rechercher</button>
</form>

<table border='1'>
    <tr><th>Numéro</th><th>Nom</th><th>Rôle</th></tr>
    <?php foreach($stmt as $client): ?>
        <tr>
            <td><?= $client['CLI_NUM'] ?></td>
            <td><?= $client['CLI_NOM'] ?></td>
            <td><?= $client['CLI_ROLE'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>