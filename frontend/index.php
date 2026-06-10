<?php
require_once __DIR__ . '/../includes/header.php';
if (isset($_GET['deconnexion']) && $_GET['deconnexion'] == 1) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

<main class="container my-5 flex-grow-1">
    <div class="row g-4 align-items-stretch">
        
        <div class="col-md-7">
            <div class="card border-0 p-5 rounded-5 h-100 d-flex flex-column justify-content-center shadow-sm"
            style="<?= 'background-color: #ede9f5; color: #5d3ebb !important;' ?>">
                <h2 class="fw-bold display-6 mb-4">Bienvenue sur VIKING Transport</h2>
                <p class="lh-lg fs-5 opacity-75 mb-0">
                    Découvrez la Normandie grâce à notre service de transport de qualité. 
                    Nous sommes fiers de vous offrir une expérience de voyage exceptionnelle, 
                    alliant confort, sécurité et ponctualité. Que vous soyez un voyageur 
                    d'affaires ou un touriste, nous avons des solutions adaptées à vos besoins. 
                    Rejoignez-nous pour une aventure inoubliable à travers les paysages 
                    magnifiques de la Normandie.
                </p>
            </div>
        </div>

        <div class="col-md-5 d-flex flex-column gap-4">
            
            <div class="card border-0 bg-body-tertiary p-4 rounded-4 text-center shadow-sm">
                <p class="text-muted small fw-medium mb-3">Logo entreprise</p>
                <div class="d-flex justify-content-center align-items-center gap-3 text-secondary display-5 my-2">
                    <img src="../public/img/logo_blanc.png" alt="Logo entreprise" class="img-fluid">
                </div>
            </div>
            
            <div class="card border-0 bg-body-tertiary p-4 rounded-4 flex-grow-1 d-flex flex-column justify-content-center shadow-sm position-relative overflow-hidden">
                <h3 class="h5 fw-bold text-dark mb-3">Merci à Tantu</h3>
                <p class="text-muted small lh-lg mb-0">
                    Nous tenons à exprimer notre profonde gratitude à Tantu pour leur soutien 
                    indéfectible et leur contribution précieuse à notre projet. Leur expertise, 
                    leur patience et leur engagement ont été essentiels pour nous aider à surmonter 
                    les défis et à atteindre nos objectifs.
                </p>
                <div class="position-absolute bottom-0 end-0 m-3 text-secondary opacity-25 fs-6">
                    <i class="bi bi-triangle-fill me-1"></i>
                    <i class="bi bi-square-fill"></i>
                </div>
            </div>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>