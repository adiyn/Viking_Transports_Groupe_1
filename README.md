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
.
viking-transport/
│
├── config/                      # CONFIGURATION & CONNEXION DB
│   └── db.php                   # Connexion PDO à Oracle ou MySQL (uniquement le code de connexion)
│
├── includes/                    # COMPOSANTS DE MISE EN PAGE (LAYOUT)
│   ├── header.php               # Balise <head>, CSS, début du <body> et barre de navigation
│   └── footer.php               # Pied de page, scripts JS, fermetures des balises </body> et </html>
│
├── public/                      # ASSETS STATIQUES (Accessibles par le navigateur)
│   ├── css/
│   │   └── style.css            # TOUT le design du site (un seul fichier pour l'équipe)
│   ├── js/
│   │   └── main.js              # Vos scripts de dynamisme (tri de tableaux, alertes, etc.)
│   └── images/                  # Vos logos, icônes, cartes du réseau
│
├── src/                         # LOGIQUE MÉTIER & REQUÊTES SQL (Pas de HTML ici !)
│   ├── lignes_fonctions.php     # Fonctions : getLignes(), getHoraires($ligne), etc.
│   └── reservation_fonctions.php# Fonctions : creerReservation(), annulerReservation(), etc.
│
├── frontend/    # Bah là on va créer autant de pages qu'on a besoin
│   ├── index.php                # Accueil : Visualisation des lignes (Priorité 1)
│   ├── cgu.php                  # Mentions légales / CGU (Priorité 2)
│   ├── reserver.php             # Formulaire de réservation (Priorités 3, 4, 5)
│   └── connexion.php            # Formulaire de connexion / inscription (Priorité 7)
│
│              ETC on en fais autant qu'on veut dans frontend
│
└── .gitignore                   # Fichier pour exclure config/db.php (sécurité des mots de passe) 
```

---
*Projet réalisé dans le cadre du BUT Informatique à l'IUT Caen site d'Ifs (Semestre 2).*
