<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIKING Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../public/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg"> 
    <div class="container-fluid">

        <a class="navbar-brand" href="index.php">
            <!-- image -->
            VIKING Transport
        </a>

        <!-- Bouton burger visible uniquement quand la barre se replie -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navPrincipale"
                aria-controls="navPrincipale" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Zone repliable : les liens + le bouton de connexion -->
        <div class="collapse navbar-collapse" id="navPrincipale">

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lignes.php">Ligne</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../frontend/reserver.php">Réserver</a>
                </li>
            </ul>

            // TODO : Afficher "Mon Compte" si connecté, sinon "Se connecter" (là ça marche po)
            <?php if (isset($_SESSION['utilisateur'])): ?>
                <a class="btn btn-secondary ms-auto" href="mon_compte.php">Mon Compte</a>
            <?php else: ?>
                <a class="btn btn-primary ms-auto" href="connexion.php">Se connecter</a>
            <?php endif; ?>

        </div>
    </div>
</nav>