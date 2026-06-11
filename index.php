<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['deconnexion']) && $_GET['deconnexion'] == 1) {
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="container my-5 flex-grow-1">
    <div class="row g-4 align-items-stretch">
        
        <div class="col-md-7">
            <div class="card border-0 p-5 rounded-5 h-100 d-flex flex-column justify-content-center shadow"
                 style="background-color: #111111; color: #ffffff;">
                <h2 class="fw-bold display-6 mb-4" style="font-family: 'Arial Black', sans-serif;">
                    <span style="color: #ba181b;"></span> Bienvenue sur VIKING Transport
                </h2>
                <p class="lh-lg fs-5 mb-0" style="font-weight: 300;">
                    Découvrez la Normandie grâce à notre service de transport de qualité. 
                    Nous sommes fiers de vous offrir une expérience de voyage exceptionnelle, 
                    alliant confort, sécurité et ponctualité. Que vous soyez un voyageur 
                    d'affaires ou un clan en quête de nouvelles escales, nous avons des solutions adaptées à vos besoins. 
                    Rejoignez-nous pour une traversée inoubliable à travers les paysages 
                    magnifiques de notre région.
                </p>
            </div>
        </div>

        <div class="col-md-5 d-flex flex-column gap-4">
            
            <div class="card border-0 p-3 rounded-4 text-center shadow" style="background-color: #000000;">
                <div class="d-flex justify-content-center align-items-center rounded-3 overflow-hidden" style="height: 180px;">
                    <img src="public/img/logo_noir.png" alt="Grand Blason Viking" class="w-100 h-100" style="object-fit: contain;">
                </div>
            </div>
            
            <div class="card border-0 bg-white p-4 rounded-4 flex-grow-1 d-flex flex-column justify-content-center shadow-sm border-start border-4"
                 style="border-color: #ba181b !important;">
                <h3 class="h5 fw-bold text-dark mb-3"><i class="bi bi-people-fill me-2 text-muted"></i>Merci à Tantu</h3>
                <p class="text-muted small lh-lg mb-0">
                    Nous tenons à exprimer notre profonde gratitude à Tantu pour leur soutien 
                    indéfectible et leur contribution précieuse à notre projet. Leur expertise, 
                    leur patience et leur engagement ont été essentiels pour nous aider à surmonter 
                    les défis et à atteindre nos objectifs.
                </p>
                <div class="position-absolute bottom-0 end-0 m-3 text-secondary opacity-25 fs-6">
                    <i class="bi bi-shield small"></i>
                </div>
            </div>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>