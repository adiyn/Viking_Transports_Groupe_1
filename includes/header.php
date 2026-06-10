<?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    $current_page = $_SESSION['current_page'] ?? $_GET['current_page'] ?? basename($_SERVER['PHP_SELF']); // Déterminer la page actuelle
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIKING Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../public/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../public/img/logo.ico">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="index.php">
            🚌 VIKING Transport
        </a> <!-- est-ce qu'on vire l'emoji mdr -->

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navPrincipale"
                aria-controls="navPrincipale" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navPrincipale">

            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lignes.php">Lignes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reserver.php">Réserver</a>
                </li>
            </ul>

            <div class="d-flex gap-2 align-items-center">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <span class="text-white me-2">
                        <?= htmlspecialchars($_SESSION['utilisateur']['prenom'] ?? 'Utilisateur') ?>
                    </span>
                    <a class="btn btn-outline-light btn-sm" href="compte.php">Mon Compte</a>
                    <a class="btn btn-danger btn-sm" href="index.php?deconnexion=1">Déconnexion</a>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm" href="connexion.php">Connexion</a>
                    <a class="btn btn-light btn-sm" href="inscription.php">S'inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>