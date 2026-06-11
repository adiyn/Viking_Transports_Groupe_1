<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$isAdmin = isset($_SESSION['utilisateur']['role']) && strtoupper($_SESSION['utilisateur']['role']) === 'ADMIN';
if (!$isAdmin) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>Accès refusé. Vous n'avez pas l'autorisation d'accéder à la Forge.</h1>");
}

require_once __DIR__ . '/../includes/header.php'; // Adapte le chemin si besoin
require_once __DIR__ . '/../config/param_connexion.php';
require_once __DIR__ . '/../config/pdo_agile.php';

$query = $_POST['sql_query'] ?? '';
$mode = $_POST['mode'] ?? 'commit'; // 'commit' ou 'rollback'
$resultats = null;
$colonnes = [];
$message_success = '';
$message_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($query))) {
    try {
        $db_username = $db_usernameOracle;
        $db_password = $db_passwordOracle;
        $db = $dbOracle;
        
        // Connexion directe via PDO pour gérer les transactions finement
        $pdo = new PDO($db, $db_username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Si le mode Test est activé, on ouvre une transaction
        if ($mode === 'rollback') {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        // Détecter si c'est une requête de lecture (SELECT, DESCRIBE...)
        if (preg_match('/^\s*(SELECT|WITH|DESCRIBE)\b/i', $query)) {
            $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($resultats && count($resultats) > 0) {
                $colonnes = array_keys($resultats[0]);
            }
            $message_success = "Exécution réussie. " . count($resultats) . " ligne(s) retournée(s).";
        } else {
            // Pour INSERT, UPDATE, DELETE, CREATE, DROP...
            $lignesAffectees = $stmt->rowCount();
            $message_success = "Exécution réussie. " . $lignesAffectees . " ligne(s) affectée(s).";
        }

        // gestion de la fin de transaction
        if ($mode === 'rollback') {
            $pdo->rollBack();
            $message_success .= " <br><strong>Mode Test :</strong> Un ROLLBACK a été effectué. Aucune donnée n'a été altérée.";
        } else {
            // cacaracle nécessite un commit explicite parfois sur PDO (débilos)
            if ($pdo->inTransaction()) {
                $pdo->commit(); 
            }
        }

    } catch (PDOException $e) {
        // En cas d'erreur, on annule tout si on était en transaction
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message_error = "Erreur Oracle : " . $e->getMessage();
    }
}
?>

<style>
    html, body { background-color: #0d1117 !important; color: #f8fafc; }
    .console-card { background-color: #161b22; border: 1px solid #30363d; border-radius: 16px; }
    .sql-input { background-color: #0d1117; color: #58a6ff; font-family: monospace; border: 1px solid #30363d; }
    .sql-input:focus { background-color: #0d1117; color: #58a6ff; border-color: #ba181b; box-shadow: none; }
    .table-dark-custom { background-color: #0d1117; color: #c9d1d9; font-family: monospace; font-size: 0.9rem; }
    .table-dark-custom th { background-color: #21262d; color: #f0f6fc; border-color: #30363d; }
    .table-dark-custom td { border-color: #30363d; }
</style>

<main class="container my-5 flex-grow-1">
    
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:50px; height:50px; background-color: #ba181b; color: white;">
            <i class="bi bi-terminal-fill fs-4"></i>
        </div>
        <div>
            <h2 class="h3 fw-bold mb-0">Console SQL Viking</h2>
            <p class="text-white-50 mb-0 small">Connecté à la base 0racle de l'IUT</p>
        </div>
    </div>

    <?php if ($message_success): ?>
        <div class="alert alert-success bg-success-subtle border-success-subtle text-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $message_success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($message_error): ?>
        <div class="alert alert-danger bg-danger-subtle border-danger-subtle text-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Erreur SQL :</strong><br><?= $message_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="console-card p-4 mb-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label text-white-50 fw-semibold text-uppercase small" style="letter-spacing: 1px;">Requête SQL</label>
                <textarea name="sql_query" class="form-control sql-input p-3" rows="5" placeholder="SELECT * FROM vik_client WHERE ROWNUM <= 10..." spellcheck="false" required><?= htmlspecialchars($query) ?></textarea>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="modeTest" name="mode" value="rollback" <?= ($mode === 'rollback') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white-50 ms-2" for="modeTest">
                        Mode Test <span class="badge bg-warning text-dark ms-1">Rollback Auto</span>
                    </label>
                </div>

                <button type="submit" class="btn text-white fw-bold px-5 py-2 rounded-pill shadow-sm" style="background-color: #ba181b;">
                    <i class="bi bi-lightning-fill me-2"></i> Exécuter
                </button>
            </div>
        </form>
    </div>

    <?php if ($resultats !== null): ?>
        <div class="console-card p-4">
            <h5 class="fw-bold mb-3">Résultats de la requête</h5>
            <div class="table-responsive">
                <?php if (count($resultats) > 0): ?>
                    <table class="table table-dark-custom table-hover table-bordered mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($colonnes as $colonne): ?>
                                    <th><?= htmlspecialchars($colonne) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultats as $ligne): ?>
                                <tr>
                                    <?php foreach ($ligne as $valeur): ?>
                                        <td><?= htmlspecialchars((string)$valeur) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-white-50 mb-0 font-monospace">Aucune donnée trouvée.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>