<?php
require_once __DIR__ . '/../includes/header.php';

// Exemple simple : remplacer par la valeur réelle depuis la session / BDD si disponible
$pointsUtilisateur = 1048;
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:900px;">
        <div class="row">
            <div class="col-md-9">
                <div class="mb-4 p-3 rounded" style="background:#ffffff; border-radius:8px;">
                    <div class="p-3 rounded" style="background:#f9f9fb; border:1px solid #e0e0e0;">
                        <h2 class="h5 text-center">Vous avez <?= htmlspecialchars((string) $pointsUtilisateur) ?> points! Voulez-vous les utiliser?</h2>
                    </div>
                </div>

                <form method="get" action="reserver.php">
                    <div class="card mb-4" style="border-radius:8px;">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="points_choice" id="pts100" value="100">
                                        <label class="form-check-label" for="pts100">Utiliser 100 points pour -1%</label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="points_choice" id="pts200" value="200">
                                        <label class="form-check-label" for="pts200">Utiliser 500 points pour -7%</label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="points_choice" id="pts500" value="500">
                                        <label class="form-check-label" for="pts500">Utiliser 1000 points pour -15%</label>
                                    </div>
                                </div>

                                <div class="col-4 text-end">
                                    <div class="mb-3">12.80 &rarr;<strong>12.7 €</strong></div>
                                    <div class="mb-3">12.80 &rarr;<strong>11.9 €</strong></div>
                                    <div class="mb-3">12.80 &rarr;<strong>10.9 €</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-dark px-5 py-2">Procéder au paiement</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
