<?php
include '../includes/header.php';
?>

<div class="container mt-5 mb-5">
    <h1 class="text-center mb-4">Politique de Confidentialité</h1>
    <p class="text-muted text-center">En vigueur à partir de Juin 2026 | 
        Document conforme RGPD (Cadre académique SAE)</p>
    
    <hr class="my-4">

    <p class="lead">Chez Viking Transports, nous accordons une grande 
        importance à la protection de vos données personnelles. Cette 
        politique vous explique quelles données nous collectons, comment 
        nous les utilisons et comment nous les sécurisons dans le cadre 
        de notre application de gestion de transports.</p>

    <section class="mt-5 mb-4">
        <h2>1. Les données que nous collectons</h2>
        <p>Dans le cadre de l'utilisation de notre application (création 
            de compte, réservation de trajets), nous pouvons être amenés 
            à collecter et traiter les données suivantes :</p>
        <ul>
            <li><strong>Données d'identification :</strong> Nom, prénom, 
            adresse email.</li>

            <li><strong>Données de sécurité :</strong> Mot de passe (celui-ci 
            est crypté et haché de manière sécurisée en base de données, 
            nous n'y avons pas accès en clair).</li>

            <li><strong>Données d'activité :</strong> Historique de vos 
            réservations, lignes et trajets consultés, horaires sélectionnés.</li>

            <li><strong>Données de fidélité :</strong> Solde et historique 
            d'utilisation de vos points de fidélité.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h2>2. Pourquoi utilisons-nous vos données ?</h2>

        <p>Les informations collectées sont nécessaires pour répondre aux 
            priorités de notre service, et plus précisément pour :</p>

        <ul>
            <li>Permettre la création de votre compte client et votre 
                authentification sécurisée.</li>

            <li>Gérer l'achat, la modification ou l'annulation de vos 
                réservations sur nos lignes de transport.</li>

            <li>Calculer et attribuer vos points de fidélité lors de 
                vos voyages.</li>

            <li>Permettre aux administrateurs du réseau de gérer les 
                comptes clients et de générer des statistiques globales de 
                fréquentation des lignes.</li>

        </ul>
    </section>

    <section class="mb-4">
        <h2>3. Durée de conservation des données</h2>

        <p>Vos données personnelles sont conservées en base de données 
            uniquement le temps nécessaire à la gestion de votre compte 
            et de vos réservations :</p>

        <ul>
            <li><strong>Comptes clients :</strong> Les données sont 
            conservées tant que le compte est actif. Conformément aux 
            règles du réseau, les comptes jugés inactifs sont supprimés 
            ou anonymisés.</li>

            <li><strong>Historique des réservations :</strong> Conservé 
            pendant la durée légale requise pour la comptabilité et la 
            gestion des transports.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h2>4. Sécurité de vos données</h2>

        <p>Nous mettons en œuvre toutes les mesures techniques requises 
            (hachage des mots de passe en PHP, requêtes SQL sécurisées via 
            PDO pour éviter les injections, exclusion du fichier <code>db.php</code> 
            via le fichier <code>.gitignore</code>) pour garantir la confidentialité 
            de vos données et empêcher tout accès non autorisé.</p>
    </section>

    <section class="mb-4">
        <h2>5. Vos Droits (RGPD)</h2>

        <p>Conformément à la réglementation sur les données personnelles, vous 
            disposez d'un droit d'accès, de rectification, de modification et de 
            suppression de vos données. Vous pouvez modifier vos informations 
            personnelles directement depuis votre espace client ("Mon Compte").</p>
    </section>

    <section class="mb-4">
        <h2>6. Contact</h2>

        <p>Pour toute question concernant la gestion de vos données personnelles 
            ou pour exercer vos droits, vous pouvez contacter notre délégué à la 
            protection des données (fictif) à l'adresse : <strong><a href="mailto:viking.transports@proton.me">viking.transports@proton.me</a></strong>.</p>
    </section>
</div>

<?php
include '../includes/footer.php';
?>