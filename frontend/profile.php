<?php
require_once '/../config/param_connexion.php';
require_once '/../config/pdo_agile.php';       
require_once '/../config/util.php';            

include 'includes/header.php'; 

if (!$is_connected) {
    echo "<script>window.location.href='connexion.php';</script>";
    exit();
}

$num_client = $_SESSION['num_client'];
$message_success = "";
$message_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_modifier'])) {
    $nom = htmlspecialchars(trim($_POST['nom_client']));
    $prenom = htmlspecialchars(trim($_POST['prenom_client']));
    $ville = htmlspecialchars(trim($_POST['ville_client']));
    $tel = htmlspecialchars(trim($_POST['tel_client']));
    $courriel = filter_var($_POST['courriel_client'], FILTER_VALIDATE_EMAIL);

    $conn->beginTransaction();
    if ($nom && $prenom && $ville && $tel && $courriel) {
        try {
            $sql_update = "UPDATE client 
                            SET nom_client = :nom, prenom_client = :prenom, 
                                ville_client = :ville, tel_client = :tel, courriel_client = :courriel 
                            WHERE num_client = :id";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':ville' => $ville,
                ':tel' => $tel,
                ':courriel' => $courriel,
                ':id' => $num_client
            ]);
            $conn->commit();
            $message_success = "Vos informations ont été mises à jour avec succès !";
        } catch (PDOException $e) {
            $conn->rollback();
            $message_error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $message_error = "Veuillez remplir tous les champs correctement.";
    }
}

try {
    $sql_client = "SELECT * FROM client WHERE num_client = :id";
    $stmt_client = $pdo->prepare($sql_client);
    $stmt_client->execute([':id' => $num_client]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='container'>Erreur de base de données : " . $e->getMessage() . "</div>");
}

try {
    $sql_trajets = "SELECT r.res_num, r.res_date, r.res_prix_total
                    FROM reservation r 
                    WHERE r.num_client = :id
                    ORDER BY r.res_date DESC";
    $stmt_trajets = $pdo->prepare($sql_trajets);
    $stmt_trajets->execute([':id' => $num_client]);
    $historique_trajets = $stmt_trajets->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historique_trajets = []; 
}
?>

<div class="container-profile" style="max-width: 1000px; margin: 40px auto; padding: 20px; font-family: Arial, sans-serif;">
    
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #eef2f7; padding-bottom: 15px;">
        <i class="fa-solid fa-id-card" style="font-size: 32px; color: #1d3557;"></i>
        <h1 style="margin: 0; color: #1d3557;">Mon Espace Viking</h1>
    </div>

    <?php if ($message_success): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 12px; margin-bottom: 20px; border-radius: 4px; border-left: 5px solid #28a745;">
            <i class="fa-solid fa-circle-check"></i> <?= $message_success ?>
        </div>
    <?php endif; ?>
    <?php if ($message_error): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 12px; margin-bottom: 20px; border-radius: 4px; border-left: 5px solid #dc3545;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $message_error ?>
        </div>
    <?php endif; ?>

    <div class="card-points" style="background: #f8f9fa; padding: 20px; border-left: 5px solid #a8dadc; margin-bottom: 40px; 
    border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0 0 10px 0; color: #1d3557;"><i class="fa-solid fa-star" style="color: #ffb703;"></i> Mes Points Fidélité</h3>
            <p style="margin: 5px 0;">Points disponibles pour votre prochaine réduction : <strong>
                <?= htmlspecialchars($client['nb_points_en_cours'] ?? '0') ?></strong> points</p>
            <small style="color: #6c757d;">Cumul total historique : <?= htmlspecialchars($client['nb_points_total'] ?? '0') ?> points</small>
        </div>
        <div style="font-size: 45px; color: #a8dadc; padding-right: 15px;">
            <i class="fa-solid fa-award"></i>
        </div>
    </div>

    <div style="display: flex; gap: 50px; flex-wrap: wrap;">
        
        <div style="flex: 1; min-width: 320px;">
            <h3 style="color: #1d3557; margin-bottom: 20px;"><i class="fa-solid fa-user-gear"></i> Modifier mes informations</h3>
            
            <form action="profile.php" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label style="font-weight: bold; color: #495057;">Prénom</label>
                    <input type="text" name="prenom_client" value="<?= htmlspecialchars($client['prenom_client'] ?? '') ?>"
                    required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div>
                    <label style="font-weight: bold; color: #495057;">Nom</label>
                    <input type="text" name="nom_client" value="<?= htmlspecialchars($client['nom_client'] ?? '') ?>" 
                    required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div>
                    <label style="font-weight: bold; color: #495057;">Adresse Courriel</label>
                    <input type="email" name="courriel_client" value="<?= htmlspecialchars($client['courriel_client'] ?? '') ?>" 
                    required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div>
                    <label style="font-weight: bold; color: #495057;">Téléphone</label>
                    <input type="text" name="tel_client" value="<?= htmlspecialchars($client['tel_client'] ?? '') ?>" 
                    required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div>
                    <label style="font-weight: bold; color: #495057;">Ville de résidence</label>
                    <input type="text" name="ville_client" value="<?= htmlspecialchars($client['ville_client'] ?? '') ?>" 
                    required style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                
                <button type="submit" name="btn_modifier" style="background-color: #1d3557; color: white; padding: 12px; border: none; 
                cursor: pointer; border-radius: 4px; font-weight: bold; margin-top: 10px; transition: background 0.2s;">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
                </button>
            </form>
        </div>

        <div style="flex: 1; min-width: 320px;">
            <h3 style="color: #1d3557; margin-bottom: 20px;"><i class="fa-solid fa-clock-history"></i> Historique de mes trajets</h3>
            
            <?php if (empty($historique_trajets)): ?>
                <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 4px; color: #6c757d; font-style: italic;">
                    <i class="fa-solid fa-compass" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                    Aucun trajet enregistré pour le moment.
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <thead>
                        <tr style="background-color: #1d3557; color: white; text-align: left;">
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">N° Résa</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Date d'achat</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6; text-align: right;">Tarif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique_trajets as $trajet): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px; font-weight: bold; color: #495057;">#<?= htmlspecialchars($trajet['res_num']) ?></td>
                                <td style="padding: 12px; color: #6c757d;"><?= htmlspecialchars($trajet['res_date']) ?></td>
                                <td style="padding: 12px; text-align: right; font-weight: bold; color: #1d3557;">
                                    <?= htmlspecialchars($trajet['res_prix_total']) ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php 
include 'includes/footer.php'; 
?>