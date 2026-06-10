<?php
// frontend/index.php — Page d'accueil (Accueil)
// Page statique : aucune dépendance à la base de données.
// On inclut simplement le layout d'Aurélien et le contenu de bienvenue.
$_SESSION['current_page'] = 'index.php'; // Stocker la page actuelle pour redirection après connexion
require_once __DIR__ . '/../includes/header.php';
if (isset($_GET['deconnexion']) && $_GET['deconnexion'] == 1) {
    session_destroy();
    header('Location: index.php');
    $_SESSION['deconnexion'] = 0;
    exit;
}


?>

<main class="container my-4 flex-grow-1">

    <h1 class="mb-4">Accueil</h1>

    <div class="row">
        <!-- Colonne principale : message de bienvenue -->
        <div class="col-md-7">
            <div class="card p-4 mb-4">
                <h2 class="card-title">Bienvenue sur VIKING Transport</h2>
                <p class="card-text">
                    Decouvrez la Normandie grace à notre service de transport de qualité. 
                    Nous sommes fiers de vous offrir une expérience de voyage exceptionnelle, 
                    alliant confort, sécurité et ponctualité. Que vous soyez un voyageur 
                    d'affaires ou un touriste, nous avons des solutions adaptées à vos besoins. 
                    Rejoignez-nous pour une aventure inoubliable à travers les paysages 
                    magnifiques de la Normandie.
                </p>
            </div>
        </div>

        <!-- Colonne secondaire : logo + remerciements -->
        <div class="col-md-5">
            <div class="card p-4 mb-4 text-center">
                <p class="text-muted">Logo entreprise</p>
                <img src="../public/img/logo_blanc.png" alt="Logo VIKING Transport">
            </div>
            <div class="card p-4">
                <h2 class="card-title">Merci à Tantu</h2>
                <p class="card-text">
                    Nous tenons à exprimer notre profonde gratitude à Tantu pour leur soutien 
                    indéfectible et leur contribution précieuse à notre projet. Leur expertise, 
                    leur patience et leur engagement ont été essentiels pour nous aider à surmonter 
                    les défis et à atteindre nos objectifs. Merci, Tantu, pour votre aide 
                    précieuse et votre encouragement tout au long de cette aventure.
                </p>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>