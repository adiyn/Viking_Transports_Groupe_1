<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['deconnexion']) && $_GET['deconnexion'] == 1) {
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Correction du "vide" sous le footer et passage au Dark Theme neutre */
    html, body {
        background-color: #121212 !important; /* Gris très sombre, presque noir */
        color: #ffffff;
        height: 100%; /* Aide à plaquer le footer en bas */
    }
    body {
        position: relative;
        overflow-x: hidden;
    }
    
    /* Lumière d'ambiance (Rouge très subtil et Gris clair) pour casser le côté plat */
    .bg-circle-top {
        position: absolute;
        width: 600px;
        height: 600px;
        top: -100px;
        left: -200px;
        background: radial-gradient(circle, rgba(186, 24, 27, 0.12) 0%, rgba(18, 18, 18, 0) 65%);
        border-radius: 50%;
        z-index: 0;
        pointer-events: none;
    }
    .bg-circle-bottom {
        position: absolute;
        width: 800px;
        height: 800px;
        bottom: -200px;
        right: -300px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.03) 0%, rgba(18, 18, 18, 0) 60%);
        border-radius: 50%;
        z-index: 0;
        pointer-events: none;
    }

    /* Styles des cartes en mode sombre */
    .viking-card {
        background-color: #2c3e50;
        border: 1px solid #333333; /* Bordure discrète */
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    }
    .viking-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        border-color: #ba181b !important;
    }
    .viking-icon-wrapper {
        background-color: #121212;
        border: 1px solid #333333;
        color: #ba181b;
        transition: all 0.3s ease;
    }
    .viking-card:hover .viking-icon-wrapper {
        background-color: #ba181b;
        color: #ffffff;
        border-color: #ba181b;
        transform: scale(1.1);
    }
</style>

<!-- Éléments de décor en arrière-plan -->
<div class="bg-circle-top"></div>
<div class="bg-circle-bottom"></div>

<main class="container my-5 flex-grow-1 position-relative" style="z-index: 1;">
    
    <!-- Hero Section : Le Navire Amiral -->
    <div class="row align-items-center mb-5 rounded-4 shadow-lg overflow-hidden viking-card">
        <div class="col-lg-7 p-4 p-md-5">
            <span class="badge mb-3 px-3 py-2 rounded-pill shadow-sm" style="background-color: #121212; border: 1px solid #333333; color: #dc3545; font-weight: 600; letter-spacing: 1px;">
                <i class="bi bi-compass-fill me-1"></i> À LA CONQUÊTE DU TERRITOIRE
            </span>
            <h1 class="display-5 fw-bold text-white mb-4">
                Embarquez avec <span style="color: #ba181b;">Viking Transport</span>
            </h1>
            <p class="lead mb-4 fs-5" style="color: #cccccc;">
                Déployez vos voiles sur l'asphalte normand. Notre flotte d'autocars vous garantit un voyage fiable, confortable et digne des plus grandes expéditions, tout en récompensant la fidélité de notre clan.
            </p>
            <div class="d-flex gap-3 flex-wrap mt-2">
                <a href="/frontend/reserver.php" class="btn text-white rounded-pill px-4 py-2 fw-semibold shadow-sm btn-viking-action" style="background-color: #ba181b; border: 1px solid #ba181b;">
                    <i class="bi bi-ticket-perforated-fill me-2"></i>Réserver un siège
                </a>
                <a href="/frontend/lignes.php" class="btn rounded-pill px-4 py-2 fw-semibold text-white shadow-sm transition-hover" style="background-color: #121212; border: 1px solid #333333;">
                    <i class="bi bi-map-fill me-2"></i>Explorer nos routes
                </a>
            </div>
        </div>
        
        <!-- Zone Illustration : Le Blason -->
        <div class="col-lg-5 p-0 d-none d-lg-flex justify-content-center align-items-center position-relative" style="min-height: 400px; background-color: #151515; border-left: 1px solid #333333;">
            <img src="../public/img/logo_blanc.png" alt="Logo Viking Transport" style="max-height: 240px; filter: drop-shadow(0px 10px 20px rgba(0,0,0,0.5)); z-index: 2; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05) rotate(-2deg)'" onmouseout="this.style.transform='scale(1) rotate(0deg)'">
        </div>
    </div>

    <!-- Section Atouts : Les forces du clan -->
    <div class="row g-4 text-center mt-2">
        <div class="col-md-4">
            <div class="card viking-card rounded-4 h-100 p-4">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm viking-icon-wrapper" style="width: 64px; height: 64px;">
                        <i class="bi bi-signpost-split-fill fs-3"></i>
                    </div>
                </div>
                <h3 class="h5 fw-bold text-white">19 Routes Commerciales</h3>
                <p class="small mb-0" style="color: #cccccc;">Un maillage stratégique et optimisé pour vous connecter efficacement aux principaux bastions de la région.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card viking-card rounded-4 h-100 p-4">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm viking-icon-wrapper" style="width: 64px; height: 64px;">
                        <i class="bi bi-geo-alt-fill fs-3"></i>
                    </div>
                </div>
                <h3 class="h5 fw-bold text-white">77 Terres Desservies</h3>
                <p class="small mb-0" style="color: #cccccc;">De Caen à Cherbourg, en passant par Rouen, notre flotte couvre l'ensemble du royaume normand.</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card viking-card rounded-4 h-100 p-4">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle shadow-sm viking-icon-wrapper" style="width: 64px; height: 64px;">
                        <i class="bi bi-shield-shaded fs-3"></i>
                    </div>
                </div>
                <h3 class="h5 fw-bold text-white">Récompenses de Clan</h3>
                <p class="small mb-0" style="color: #cccccc;">Cumulez des points de gloire à chaque expédition et débloquez des réductions exclusives pour vos futurs voyages.</p>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>