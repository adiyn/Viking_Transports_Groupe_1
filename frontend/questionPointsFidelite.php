<?php
require_once __DIR__ . '/../includes/header.php';

// Récupérer les points depuis la session si disponible, sinon valeur d'exemple
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pointsUtilisateur = isset($_SESSION['points_fidelite']) ? (int) $_SESSION['points_fidelite'] : 1048;
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:700px;">
        <div class="text-center mb-4">
            <h2 class="h5"><i class="bi bi-star"></i> Vous avez <?= htmlspecialchars((string) $pointsUtilisateur) ?> points de fidélité</h2>
            <p class="text-muted">Voulez-vous utiliser ces points pour réduire le prix de votre billet ?</p>
        </div>

        <div class="d-flex justify-content-center gap-3">
            <a href="utiliserPoints.php" class="btn btn-dark px-4 py-2">Oui, utiliser mes points</a>
            <a href="reserver.php" class="btn btn-outline-secondary px-4 py-2">Non, continuer</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
