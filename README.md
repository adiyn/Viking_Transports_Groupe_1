# Projet Viking Transport

L'objectif de ce Projet est de concevoir l'identité visuelle, la stratégie de communication et le site web complet de l'entreprise (fictive) **Viking Transport**.

---

## L'Équipe (Groupe 1)

* **BAZIN** Clément
* **CHAMPEIL** Robin
* **COLOGON** Etienne
* **COSSÉ** Damien
* **GIGUERRE** Aurélien
* **MARÉCHAL** Nils
* **POIRIER** Nolan
* **SCHERRER** Ricky 

---

## Technologies Utilisées

```text
- Frontend : HTML5, CSS3, JavaScript
- Backend : PHP
- Base de données : Oracle
- Outils : Git, GitHub, VSCode, Discord, SQL Developer, Eclipse IDE
```

---
## Structure du Projet

Le projet est organisé de manière à séparer les sources de la configuration :

```text
viking-transport/
│
├── config/                      # CONFIGURATION & CONNEXION DB
│   └── db.php                   # Connexion PDO à Oracle ou MySQL (uniquement le code de connexion)
│
├── includes/                    # COMPOSANTS DE MISE EN PAGE (LAYOUT)
│   ├── header.php               
│   └── footer.php               
│
├── public/                      # ASSETS STATIQUES (Accessibles par le navigateur)
│   ├── gif/
│   │   └── ...                  # TOUT le design du site (un seul fichier pour l'équipe)
│   ├── js/
│   │   └── ...                  # scripts js
│   └── img/
|       └── ...                  # logos, icônes, cartes du réseau...
│
├── src/                         # LOGIQUE MÉTIER & REQUÊTES SQL (Pas de HTML ici !)
│   ├── lignes_fonctions.php     # Fonctions : getLignes(), getHoraires($ligne), etc.
│   ├── reservation_fonctions.php# Fonctions : creerReservation(), annulerReservation(), etc.
│   └── auth_fonctions.php       # Fonctions : verifierIdentifiants(), inscrireClient()
│
├── frontend/    # Là on va créer autant de pages qu'on a besoin
│   ├── index.php                # page d'accueil
│   ├── cgu.php                  # mentions légales
│   ├── reserver.php             # formulaire de réservation
│   └── connexion.php            # formulaire de connexion
|   └── ...
│
└── .gitignore                   # Fichier pour exclure config/db.php (sécurité des mots de passe)
```

---
*Projet réalisé dans le cadre du BUT Informatique à l'IUT Caen site d'Ifs (Semestre 2).*
