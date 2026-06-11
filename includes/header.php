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
    <style>
        .btn-connect-main {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            background-color: #ede9f5;
            color: #5d3ebb;
            border: none;
        }
        .btn-connect-main:hover {
            background-color: #5d3ebb !important;
            color: #ffffff !important;
            transform: scale(1.05);
        }
        .btn-connect-bordered {
            border: 2px solid #5d3ebb !important;
            background-color: #ffffff;
            color: #5d3ebb;
        }
        .btn-connect-bordered:hover {
            background-color: #5d3ebb !important;
            color: #ffffff !important;
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
            
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-3">
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '#' : 'index.php' ?>"
                        style="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Accueil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'lignes.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= basename($_SERVER['PHP_SELF']) == 'lignes.php' ? '#' : 'lignes.php' ?>"
                        style="<?= basename($_SERVER['PHP_SELF']) == 'lignes.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= basename($_SERVER['PHP_SELF']) == 'lignes.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Lignes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center px-3 py-1 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'reserver.php' ? 'fw-bold disabled' : 'text-secondary' ?>" 
                        href="<?= basename($_SERVER['PHP_SELF']) == 'reserver.php' ? '#' : 'reserver.php' ?>"
                        style="<?= basename($_SERVER['PHP_SELF']) == 'reserver.php' ? 'background-color: #d6cddf; color: #5d3ebb !important;' : '' ?>">
                        <i class="bi <?= basename($_SERVER['PHP_SELF']) == 'reserver.php' ? 'bi-star-fill' : 'bi-star' ?> fs-5"></i>
                        <span class="small" style="font-size: 0.75rem;">Réserver</span>
                    </a>
                </li>
            </ul>

            <div class="d-flex gap-2 align-items-center">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <span class="small text-muted me-2">
                        Bienvenue, <strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] ?? 'Utilisateur') ?></strong>
                    </span>
                    <a class="btn bg-light text-dark border rounded-pill btn-sm px-3 fw-semibold" href="compte.php">Mon Compte</a>
                    <a class="btn bg-danger-subtle text-danger border-0 rounded-pill btn-sm px-2" href="index.php?deconnexion=1">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a class="btn rounded-pill fw-bold px-4 py-2 d-inline-flex align-items-center gap-2 btn-connect-bordered" 
                        href="connexion.php">
                        <i class="bi bi-star-fill"></i> Se connecter
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>