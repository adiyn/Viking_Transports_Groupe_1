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
            background-color: #1f2c3a;
            /* Légèrement plus épais pour accompagner le logo agrandi */
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
        }
        
        /* Conteneur du logo – maintenant bien visible */
        .logo-container {
            width: 90px;   /* agrandi significativement */
            height: 90px;
            border-radius: 50%;
            background-color: #ffffff;
            overflow: hidden;
            border: 2px solid #48627a;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        /* Image zoomée pour remplir le cercle */
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transform: scale(1.4); /* zoom plus fort pour que le logo remplisse bien */
            transition: transform 0.3s ease;
        }

        /* Survol : encore un peu plus */
        .navbar-brand:hover .logo-container img {
            transform: scale(1.5);
        }

        /* Texte de la marque */
        .navbar-brand .brand-text {
            font-size: 1.5rem; /* agrandi */
            letter-spacing: 0.5px;
        }
        .navbar-brand .brand-sub {
            font-size: 0.75rem; /* légèrement plus grand */
            letter-spacing: 2px;
        }

        /* Liens de navigation */
        .nav-link-viking {
            transition: all 0.25s ease-in-out;
            font-weight: 600;
        }
        .nav-link-viking:hover {
            background-color: #2c3e50 !important;
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

        /* Centrage absolu pour le menu central */
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

<nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary shadow-sm sticky-top navbar-viking">
    <div class="container-fluid position-relative d-flex justify-content-between align-items-center">

        <a class="navbar-brand d-flex align-items-center gap-3 text-white m-0" href="/index.php">
            <div class="d-flex flex-column">
                <strong class="brand-text lh-1 text-uppercase text-white" style="font-family: 'Arial Black', sans-serif;">Viking</strong>
                <span class="brand-sub text-white-50 text-uppercase fw-bold">Transport</span>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipale">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse w-100" id="navPrincipale">
            
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