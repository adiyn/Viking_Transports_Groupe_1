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
    /* ---------- FOND DU BODY : Falaises d'Étretat ---------- */
    body {
        background: linear-gradient(rgba(13, 17, 23, 0.55), rgba(13, 17, 23, 0.9)),
                    url('https://www.fond-ecran-hd.net/Public/uploads/2019-01-14/falaises-etretat-normandie-wallpaper-1920x1080.jpg') 
                    center / cover no-repeat fixed;
        color: #f8fafc;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        position: relative;
    }

    /* Header & Footer gardent leur fond solide */
    header, footer {
        background-color: #0d1117 !important;
        position: relative;
        z-index: 2;
    }

    /* ---------- HERO : mise en avant du slogan Viking ---------- */
    .hero-section {
        padding: 5rem 0 3rem;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    /* Bloc slogan agrandi façon “hero page” */
    .hero-slogan {
        display: inline-block;
        background: rgba(13, 17, 23, 0.75);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(186, 24, 27, 0.5);
        color: #fff;
        border-radius: 60px;
        padding: 1rem 3rem;
        font-weight: 700;
        font-size: 1.5rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 2rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }

    .hero-section h1 {
        font-size: 3.5rem;
        font-weight: 800;
        letter-spacing: -1.5px;
        color: #ffffff;
        text-shadow: 0 4px 20px rgba(0,0,0,0.7);
        line-height: 1.2;
        margin-top: 0;
    }

    .hero-section .lead {
        color: rgba(255,255,255,0.85);
        font-size: 1.3rem;
        max-width: 650px;
        margin: 1.5rem auto 0;
        text-shadow: 0 2px 8px rgba(0,0,0,0.5);
    }

    .hero-section .btn-hero {
        background-color: #ba181b;
        border: none;
        color: white;
        border-radius: 50px;
        padding: 1rem 3rem;
        font-weight: 700;
        font-size: 1.2rem;
        transition: all 0.3s;
        box-shadow: 0 10px 25px rgba(186, 24, 27, 0.3);
    }
    .hero-section .btn-hero:hover {
        background-color: #9a1315;
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(186, 24, 27, 0.5);
    }

    /* ---------- SECTIONS ---------- */
    .section-title {
        font-weight: 700;
        font-size: 2.2rem;
        letter-spacing: -0.5px;
        margin-bottom: 1rem;
        position: relative;
    }
    .section-title::after {
        content: '';
        display: block;
        width: 60px;
        height: 4px;
        background: #ba181b;
        margin-top: 0.75rem;
        border-radius: 2px;
    }

    /* ---------- CARTES DE VALEURS ---------- */
    .feature-card {
        background-color: #161b22;
        border-radius: 24px;
        border: 1px solid #30363d;
        padding: 2.5rem 2rem;
        height: 100%;
        transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s;
        text-align: center;
    }
    .feature-card:hover {
        transform: translateY(-8px);
        border-color: #48627a;
        box-shadow: 0 20px 35px rgba(0,0,0,0.6);
    }
    .feature-icon {
        width: 60px;
        height: 60px;
        background-color: rgba(186, 24, 27, 0.12);
        color: #ba181b;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin: 0 auto 1.5rem;
    }

    /* ---------- AMÉLIORATION DE LA LISIBILITÉ ---------- */
    /* Remplace text-white-50 par une version plus lisible */
    .text-light-muted {
        color: rgba(255, 255, 255, 0.75) !important;
    }

    .text-light-soft {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    /* Texte des paragraphes dans la présentation */
    .presentation-text {
        font-size: 1.1rem;
        line-height: 1.7;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Badges de statistiques */
    .stat-badge {
        background-color: rgba(186, 24, 27, 0.1);
        border: 1px solid rgba(186, 24, 27, 0.2);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 30px;
        font-size: 0.9rem;
    }

    /* ---------- IMAGE D'ILLUSTRATION ---------- */
    .illustration-img {
        border-radius: 28px;
        border: 2px solid #30363d;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8);
    }

    /* ---------- BANNIÈRE ESPACE CLIENT ---------- */
    .account-banner {
        background-color: #161b22;
        border: 1px solid #30363d;
        border-radius: 28px;
        transition: border-color 0.3s;
    }
    .account-banner:hover {
        border-color: #48627a;
    }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 768px) {
        .hero-section h1 {
            font-size: 2.5rem;
        }
        .hero-slogan {
            font-size: 1.2rem;
            padding: 0.8rem 2rem;
        }
        .section-title {
            font-size: 1.8rem;
        }
    }
</style>

<!-- ==================== HERO ==================== -->
<section class="hero-section">
    <div class="container">
        <!-- Slogan Viking agrandi comme une véritable accroche -->
        <div class="hero-slogan">
            Viking Transports – L’esprit normand en mouvement
        </div>
        <h1>La Normandie<br>comme vous l’aimez</h1>
        <p class="lead">
            Des falaises d’Étretat aux méandres de la Suisse normande,<br>
            chaque trajet est une promesse d’évasion et de fierté régionale.
        </p>
    </div>
</section>

<!-- ==================== PRÉSENTATION VIKING TRANSPORTS ==================== -->
<section class="container py-5">
    <div class="row align-items-center g-5">
        <div class="col-lg-6">
            <h2 class="section-title text-white">Qui sommes-nous ?</h2>
            <p class="presentation-text">
                <strong class="text-white">Viking Transports</strong>, c’est avant tout une histoire
                d’amour avec notre région. Depuis 1998, nous sillonnons les routes de Normandie pour
                relier les communes, des plus rurales aux plus touristiques, avec la passion du
                service et l’authenticité d’une entreprise familiale.
            </p>
            <p class="presentation-text">
                Fiers de notre héritage viking, nous avons bâti un réseau qui incarne la force,
                la fiabilité et l’esprit d’aventure. Chaque jour, nos conducteurs vous font traverser
                le bocage, longer les côtes et découvrir les trésors cachés de nos cinq départements.
            </p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <div class="stat-badge">
                    <i class="bi bi-check-circle-fill text-danger me-2"></i>19 lignes régulières
                </div>
                <div class="stat-badge">
                    <i class="bi bi-check-circle-fill text-danger me-2"></i>77 communes desservies
                </div>
                <div class="stat-badge">
                    <i class="bi bi-check-circle-fill text-danger me-2"></i>Élu “Service de l’année” 2025
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <!-- Moulin à eau normand -->
            <img src="https://s1.best-wallpaper.net/wallpaper/m/1809/France-Normandy-water-mill-houses_m.jpg" 
                 alt="Moulin à eau en Normandie – Campagne viking" 
                 class="img-fluid illustration-img">
        </div>
    </div>
</section>

<!-- ==================== SERVICES (cartes) ==================== -->
<section class="container py-5">
    <div class="text-center mb-5">
        <h2 class="section-title text-white d-inline-block">Voyager avec Viking</h2>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-signpost-split-fill"></i>
                </div>
                <h3 class="h5 fw-bold text-white mb-3">Un maillage unique</h3>
                <p class="text-light-muted mb-0" style="font-size: 0.95rem;">
                    De Granville à Gisors, du Havre à Avranches, nos lignes dessinent la carte de
                    vos envies. Vous n’êtes jamais loin d’un car Viking.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-tree-fill"></i>
                </div>
                <h3 class="h5 fw-bold text-white mb-3">La Normandie grandeur nature</h3>
                <p class="text-light-muted mb-0" style="font-size: 0.95rem;">
                    Nos itinéraires privilégient les paysages : valleuses, plages du Débarquement,
                    vergers et haras. Ouvrez les yeux, c’est gratuit.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-star-fill"></i>
                </div>
                <h3 class="h5 fw-bold text-white mb-3">Programme Fidélité</h3>
                <p class="text-light-muted mb-0" style="font-size: 0.95rem;">
                    Cumulez des points « drakkars » à chaque voyage et offrez-vous des réductions,
                    des week‑ends découverte ou des invitations exclusives.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>