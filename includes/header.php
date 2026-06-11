<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Logique déco
if (isset($_GET['deconnexion']) && $_GET['deconnexion'] == 1) {
    session_destroy();
    header('Location: index.php');
    exit;
}
// On récupère le rôle une seule fois ici pour simplifier les conditions en dessous
$isAdmin = isset($_SESSION['utilisateur']['role']) && $_SESSION['utilisateur']['role'] === 'admin';
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
        .btn-connect-main {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .btn-connect-main:hover {
            background-color: #5d3ebb !important;
            color: #ffffff !important;
            transform: scale(1.05);
        }
        .btn-connect-bordered {
            border: 2px solid #5d3ebb !important;
        }
        .nav-link {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .nav-link:hover {
            background-color: #5d3ebb !important;
            color: #ffffff !important;
            transform: scale(1.05);
        }   
        .nav-link.disabled {
            pointer-events: none;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .nav-link i {
            transition: color 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-body">

<nav class="navbar navbar-expand-lg border-bottom py-3 px-4 shadow-sm" style="background-color: #f3edf7;">
    <div class="container-fluid">

        <!-- Logo gauche -->
        <a class="navbar-brand d-flex align-items-center gap-2 text-dark" href="index.php">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle 
            bg-light border overflow-hidden flex-shrink-0" style="width: 42px; height: 42px;">
                <img src="../public/img/logo_noir.png" alt="Logo entreprise" class="w-100 h-100" style="object-fit: cover;">
            </div>
            <strong class="fs-6">VIKING Transport</strong>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipale">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navPrincipale">
            
            <!-- Onglets du milieu -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-3">
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= $currentPage == 'index.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= $currentPage == 'index.php' ? '#' : 'index.php' ?>"
                        style="<?= $currentPage == 'index.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= $currentPage == 'index.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Accueil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= $currentPage == 'lignes.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= $currentPage == 'lignes.php' ? '#' : 'lignes.php' ?>"
                        style="<?= $currentPage == 'lignes.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= $currentPage == 'lignes.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Lignes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= $currentPage == 'reserver.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= $currentPage == 'reserver.php' ? '#' : 'reserver.php' ?>"
                        style="<?= $currentPage == 'reserver.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= $currentPage == 'reserver.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Réserver</span>
                    </a>
                </li>

                <!-- Onglets admin : visibles uniquement si rôle = admin -->
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= $currentPage == 'listeclient.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= $currentPage == 'listeclient.php' ? '#' : 'listeclient.php' ?>"
                        style="<?= $currentPage == 'listeclient.php' ? 'background-color: #ede9f5; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= $currentPage == 'listeclient.php' ? 'bi-people-fill' : 'bi-people' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Clients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= $currentPage == 'statistiques.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= $currentPage == 'statistiques.php' ? '#' : 'statistiques.php' ?>"
                        style="<?= $currentPage == 'statistiques.php' ? 'background-color: #ede9f5; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= $currentPage == 'statistiques.php' ? 'bi-bar-chart-fill' : 'bi-bar-chart' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Statistiques</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Zone droite : boutons connexion / compte -->
            <div class="d-flex gap-2 align-items-center">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>

                    <!-- Badge admin discret à côté du prénom -->
                    <?php if ($isAdmin): ?>
                        <span class="badge rounded-pill px-2 py-1 me-1"
                              style="background-color: #5d3ebb; color: #fff; font-size: 0.7rem;">
                            <i class="bi bi-shield-fill-check me-1"></i>Admin
                        </span>
                    <?php endif; ?>

                    <span class="small text-muted me-1">
                        Bienvenue, <strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] ?? 'Utilisateur') ?></strong>
                    </span>
                    <a class="btn bg-light text-dark border rounded-pill btn-sm px-3 fw-semibold" href="compte.php">Mon Compte</a>
                    <a class="btn bg-danger-subtle text-danger border-0 rounded-pill btn-sm px-2" href="index.php?deconnexion=1">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>

                <?php elseif (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/connexion.php'): ?>
                    <a class="btn rounded-pill fw-bold px-4 py-2 d-inline-flex align-items-center gap-2 border-0 btn-connect-main" 
                        href="connexion.php"
                        style="background-color: #ede9f5; color: #5d3ebb;">
                        <i class="bi bi-star-fill"></i> Se connecter
                    </a>
                <?php else: ?>
                    <a class="btn rounded-pill fw-bold px-4 py-2 d-inline-flex align-items-center gap-2 btn-connect-main btn-connect-bordered" 
                        href="connexion.php"
                        style="background-color: #ffffff; color: #5d3ebb; border: 2px solid #5d3ebb;">
                        <i class="bi bi-star-fill"></i> Se connecter
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>