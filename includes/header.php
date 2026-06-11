<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
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
        .nav-link-viking {
            transition: all 0.25s ease-in-out;
            font-weight: 600;
        }
        .nav-link-viking:hover {
            background-color: #f1f3f5 !important;
            color: #ba181b !important;
        }
        .btn-viking-action {
            transition: all 0.3s ease;
        }
        .btn-viking-action:hover {
            background-color: #ba181b !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom py-3 px-4 shadow-sm">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center gap-3 text-dark" href="../index.php">
            <div class="d-inline-flex align-items-center justify-content-center bg-white border rounded shadow-sm overflow-hidden 
            flex-shrink-0" style="width: 50px; height: 50px;">
                <img src="../public/img/logo_blanc.png" alt="Viking Logo" class="w-100 h-100 p-1" style="object-fit: contain;">
            </div>
            <div class="d-flex flex-column">
                <strong class="fs-5 lh-1 text-uppercase" style="font-family: 'Arial Black', sans-serif; letter-spacing: 0.5px;">Viking</strong>
                <span class="small text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 2px;">Transport</span>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipale">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navPrincipale">
            
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-2">
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'index.php' ? 'text-dark fw-bold' : 'text-secondary' ?>" 
                        href="/index.php"
                        style="<?= $currentPage == 'index.php' ? 'background-color: #f1f3f5; border: 1px solid #dee2e6;' : '' ?>">
                        <i class="bi <?= $currentPage == 'index.php' ? 'bi-house-door-fill text-danger' : 'bi-house-door' ?> me-2"></i>Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'lignes.php' ? 'text-dark fw-bold' : 'text-secondary' ?>" 
                        href="/frontend/lignes.php"
                        style="<?= $currentPage == 'lignes.php' ? 'background-color: #f1f3f5; border: 1px solid #dee2e6;' : '' ?>">
                        <i class="bi <?= $currentPage == 'lignes.php' ? 'bi-signpost-split-fill text-danger' : 'bi-signpost-split' ?> me-2"></i>Lignes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                    <?= $currentPage == 'reserver.php' ? 'text-dark fw-bold' : 'text-secondary' ?>" 
                        href="/frontend/reserver.php"
                        style="<?= $currentPage == 'reserver.php' ? 'background-color: #f1f3f5; border: 1px solid #dee2e6;' : '' ?>">
                        <i class="bi <?= $currentPage == 'reserver.php' ? 'bi-ticket-perforated-fill text-danger' : 'bi-ticket-perforated' ?> me-2"></i>Réserver
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                        <?= $currentPage == 'listeclient.php' ? 'text-dark fw-bold' : 'text-secondary' ?>" 
                            href="/frontend/listeclient.php"

                            style="<?= $currentPage == 'listeclient.php' ? 'background-color: #f1f3f5; border: 1px solid #dee2e6;' : '' ?>">
                            <i class="bi <?= $currentPage == 'listeclient.php' ? 'bi-shield-lock-fill text-danger' : 'bi-shield-lock' ?> me-2"></i>Admin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-viking d-flex align-items-center px-4 py-2 rounded-pill 
                        <?= $currentPage == 'statistiques.php' ? 'text-dark fw-bold' : 'text-secondary' ?>" 
                            href="/frontend/statistique.php"
                            style="<?= $currentPage == 'statistiques.php' ? 'background-color: #f1f3f5; border: 1px solid #dee2e6;' : '' ?>">
                            <i class="bi <?= $currentPage == 'statistiques.php' ? 'bi-bar-chart-fill text-danger' : 'bi-bar-chart' ?> me-2"></i>Statistiques
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex gap-2 align-items-center">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <span class="small text-muted me-2">
                        Bonjour <strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] ?? 'Guerrier') ?></strong> !
                    </span>

                    <a class="btn bg-dark text-white border rounded-pill btn-sm px-3 fw-semibold shadow-sm" href="/frontend/compte.php">Mon Espace</a>
                    <a class="btn btn-outline-danger border-0 rounded-pill btn-sm px-2" href="/index.php?deconnexion=1"><i class="bi bi-box-arrow-right"></i></a>
                <?php else: ?>
                    <a class="btn rounded-pill fw-bold px-4 py-2 d-inline-flex align-items-center gap-2 border-0 shadow-sm text-white btn-viking-action" 
                        href="/frontend/connexion.php"
                        style="background-color: #111111;">
                        <i class="bi bi-shield-lock-fill"></i> Se connecter
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
