<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] === 'admin';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIKING Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../public/img/logo.ico">
    <style>
        /* Thème global de la navigation */
        .navbar-viking {
            background-color: #1f2c3a; /* Un peu plus clair que bg-dark */
        }
        
        /* Style des logos circulaires */
        .logo-circle {
            border-radius: 50%;
            width: 55px;
            height: 55px;
            object-fit: contain;
            object-position: center;
            background-color: #ffffff; /* Fond blanc pour faire ressortir le logo */
            padding: 8px; /* Espace autour du logo */
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        .logo-circle:hover {
            transform: scale(1.05);
        }

        /* Conteneur du logo */
        .logo-container {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background-color: #ffffff;
            overflow: hidden;
            border: 2px solid #48627a;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        /* L'image avec le zoom interne permanent */
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transform: scale(1.25); /* <-- Augmentez cette valeur (ex: 1.3) pour zoomer plus fort */
            transition: transform 0.3s ease;
        }

        /* L'effet de zoom supplémentaire quand on passe la souris sur le logo ou le titre */
        .navbar-brand:hover .logo-container img {
            transform: scale(1.35);
        }

        /* Liens de navigation */
        .nav-link-viking {
            transition: all 0.25s ease-in-out;
            font-weight: 600;
        }
        .nav-link-viking:hover {
            background-color: #2c3e50 !important; /* Variante plus claire au survol */
            color: #ff6b6b !important;
        }

        /* Boutons d'action */
        .btn-viking-action {
            transition: all 0.3s ease;
            background-color: #2c3e50;
            border-color: #48627a;
        }
        .btn-viking-action:hover {
            background-color: #ba181b !important;
            border-color: #ba181b !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Centrage absolu parfait pour grand écran */
        @media (min-width: 992px) {
            .nav-viking-center {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                margin: 0 !important;
                z-index: 1030;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary py-3 px-4 shadow-sm sticky-top navbar-viking">
    <!-- position-relative est crucial ici pour que le menu au centre s'aligne par rapport à toute la barre -->
    <div class="container-fluid position-relative d-flex justify-content-between align-items-center">

        <a class="navbar-brand d-flex align-items-center gap-3 text-white m-0" href="/index.php">
            <div class="logo-container">
                <img src="../public/img/logo_blanc.png" alt="Viking Logo">
            </div>
            <div class="d-flex flex-column">
                <strong class="fs-5 lh-1 text-uppercase text-white" style="font-family: 'Arial Black', sans-serif; letter-spacing: 0.5px;">Viking</strong>
                <span class="small text-white-50 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 2px;">Transport</span>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipale">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse w-100" id="navPrincipale">
            
            <!-- Le bloc centré avec la classe nav-viking-center -->
            <ul class="navbar-nav nav-viking-center gap-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'index.php' ? 'text-white fw-bold' : 'text-white-50' ?>" 
                        href="/index.php"
                        style="<?= $currentPage == 'index.php' ? 'background-color: #2c3e50; border: 1px solid #48627a;' : '' ?>">
                        <i class="bi <?= $currentPage == 'index.php' ? 'bi-house-door-fill text-danger' : 'bi-house-door' ?> me-2"></i>Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'lignes.php' ? 'text-white fw-bold' : 'text-white-50' ?>" 
                        href="/frontend/lignes.php"
                        style="<?= $currentPage == 'lignes.php' ? 'background-color: #2c3e50; border: 1px solid #48627a;' : '' ?>">
                        <i class="bi <?= $currentPage == 'lignes.php' ? 'bi-signpost-split-fill text-danger' : 'bi-signpost-split' ?> me-2"></i>Lignes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'reserver.php' ? 'text-white fw-bold' : 'text-white-50' ?>" 
                        href="/frontend/reserver.php"
                        style="<?= $currentPage == 'reserver.php' ? 'background-color: #2c3e50; border: 1px solid #48627a;' : '' ?>">
                        <i class="bi <?= $currentPage == 'reserver.php' ? 'bi-ticket-perforated-fill text-danger' : 'bi-ticket-perforated' ?> me-2"></i>Réserver
                    </a>
                </li>
            </ul>

            <!-- Le bloc de droite repoussé à droite par ms-auto -->
            <div class="d-flex gap-2 align-items-center ms-auto mt-3 mt-lg-0">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <span class="small text-white-50 me-3 d-none d-sm-inline">
                        Bonjour <strong class="text-white"><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] ?? 'Guerrier') ?></strong> !
                    </span>

                    <a class="btn text-white rounded-pill btn-sm px-3 py-2 fw-semibold shadow-sm d-flex align-items-center gap-2" 
                       style="background-color: #2c3e50; border: 1px solid #48627a;" href="/frontend/compte.php">
                        <?php if ($isAdmin): ?>
                            <i class="bi bi-shield-lock-fill text-danger"></i> Espace Admin
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i> Mon Espace
                        <?php endif; ?>
                    </a>
                    
                    <a class="btn btn-outline-danger border-0 rounded-pill btn-sm px-2 py-2 d-flex align-items-center" href="/index.php?deconnexion=1" title="Se déconnecter">
                        <i class="bi bi-box-arrow-right fs-5"></i>
                    </a>
                <?php else: ?>
                    <a class="btn rounded-pill fw-bold px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm text-white btn-viking-action" 
                        href="/frontend/connexion.php">
                        <i class="bi bi-shield-lock-fill"></i> Se connecter
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>