<?php
	/**
	 * Fichier : pdo_agile.php
	 * 
	 * Auteur : E.Porcq
	 * Projet : Saison 5, Épisode 3 - SAE 2.456
	 * Description : Fonctions permettant d'utiliser PDO
	 * Date : 11/10/2016
	 */

/*  Exemple
	$db_username = "XXX";
	$db_password = "XXX";
	//$db = "oci:dbname=info;charset=AL32UTF8"; // fonctionne si tnsname.ora est complet (base UTF8)
	//$db = "oci:dbname=info;charset=WE8ISO8859P15"; // fonctionne si tnsname.ora est complet
	// $db = "'oci:dbname=kiutoracle18.unicaen.fr:1521/info.kiutoracle18.unicaen.fr;charset=AL32UTF8'; ; // fonctionne si tnsname.ora est complet (base UTF8)
	$db = fabriquerChaineConnex(); // plus général 

	$conn = ConnecterPDO($db,$db_username,$db_password);
*/

/**
 * Ouvre une connexion PDO à la base de données
 * @param string $db Chaîne de connexion (DSN) à la base de données
 * @param string $db_username Nom d'utilisateur pour la connexion
 * @param string $db_password Mot de passe pour la connexion
 * @return PDO|null Objet PDO connecté ou null en cas d'erreur
 */
function OuvrirConnexionPDO($db,$db_username,$db_password)
{
	$conn = null;
	try
	{
		$conn = new PDO($db,$db_username,$db_password);
	}
	catch (PDOException $erreur)
	{
		echo "Erreur de connexion: " . $erreur->getMessage();
		$conn = null;
	}
	return $conn;
}

/**
 * Exécute une requête non préparée (INSERT, UPDATE, DELETE)
 * @param PDO $conn Connexion PDO déjà ouverte
 * @param string $sql Requête SQL de modification
 * @return int|false Nombre de lignes affectées ou false en cas d'erreur
 */
function majDonneesPDO($conn,$sql)
{
	$stmt = $conn->exec($sql);
	return $stmt;
}

/**
 * Prépare une requête SQL pour utilisation avec paramètres liés
 * @param PDO $conn Connexion PDO déjà ouverte
 * @param string $sql Requête SQL avec paramètres nommés ou positionnels
 * @return PDOStatement Objet requête préparée
 */
function preparerRequetePDO($conn,$sql)
{
	$cur = $conn->prepare($sql);
	return $cur;
}

/**
 * Ajoute un paramètre lié à une requête préparée
 * @param PDOStatement $cur Requête préparée
 * @param string $param Nom du paramètre (ex: :email)
 * @param mixed $contenu Valeur du paramètre (passé par référence)
 * @param string $type Type du paramètre ('texte' ou 'nombre')
 * @param int $taille Taille maximale du paramètre
 * @return PDOStatement Requête avec le paramètre ajouté
 */
function ajouterParamPDO($cur,$param,&$contenu,$type='texte',$taille=0)
{

	if ($type == 'nombre')
	{
		$cur->bindParam($param, $contenu, PDO::PARAM_INT|PDO::PARAM_INPUT_OUTPUT, $taille);
	}
	else
	{
		$cur->bindParam($param, $contenu, PDO::PARAM_STR|PDO::PARAM_INPUT_OUTPUT, $taille);
	}
	return $cur;
}

/**
 * Exécute une requête préparée avec paramètres liés
 * @param PDOStatement $cur Requête préparée avec paramètres déjà liés
 * @return bool true si l'exécution a réussi, false sinon
 */
function majDonneesPrepareesPDO($cur)
{
	$res = $cur->execute();
	return $res;
}

/**
 * Exécute une requête préparée avec un tableau de paramètres
 * @param PDOStatement $cur Requête préparée
 * @param array $tab Tableau associatif de paramètres [':param' => valeur]
 * @return bool true si l'exécution a réussi, false sinon
 */
function majDonneesPrepareesTabPDO($cur,$tab)
{
	$res = $cur->execute($tab);
	return $res;
}

/**
 * @param mixed $conn connexion PDO déjà ouverte
 * @param mixed $sql requête SQL de type SELECT (non préparée)
 * @param mixed $tab tableau qui va être rempli avec les résultats de la requete
 * @return int le nombre de lignes retournées par la requête
 */
function LireDonneesPDO1($conn,$sql,&$tab) // requêtes select non préparées
{
	$i=0;
	foreach  ($conn->query($sql,PDO::FETCH_ASSOC) as $ligne)
		$tab[$i++] = $ligne;
	$nbLignes = $i;
	return $nbLignes;
}

/**
 * 
 * @param mixed $conn connexion PDO déjà ouverte
 * @param mixed $sql requête SQL de type SELECT (non préparée)
 * @param mixed $tab tableau qui va être rempli avec les résultats de la requete
 * @return int le nombre de lignes retournées par la requête
 */
function LireDonneesPDO3($conn,$sql,&$tab) // requêtes select non préparées
{
	$cur = $conn->query($sql);
  	//$tab = $cur->fetchall(PDO::FETCH_BOTH); // okazou : nom de colonnne + numéro
  	$tab = $cur->fetchall(PDO::FETCH_ASSOC); // sinon euh : nom de colonnne
	return count($tab);
}

/**
 * 
 * @param mixed $cur requete déjà préparée et avec les paramètres liés
 * @param mixed $tab tableau qui va être rempli avec les résultats de la requete
 * @return int le nombre de lignes retournées par la requête
 */
function LireDonneesPDOPreparee($cur,&$tab) // requêtes select  préparées
{
	$res = $cur->execute();
	$tab = $cur->fetchall(PDO::FETCH_ASSOC);
	return count($tab);
}

/**
 * =============================
 * Fonctions supplémentaires
 * =============================
 */

/**
 * Construit une chaîne de connexion PDO pour Oracle
 * @return string Chaîne de connexion au format DSN Oracle
 */
function fabriquerChaineConnexPDO()
{
	//$hote = '10.103.0.20';
	//$hote = '127.0.0.1';
	$hote = 'kiutoracle18.unicaen.fr';
	$port = '1521'; // port par défaut
	$service = 'info';
	//$service = 'XE';

	$db =
	"oci:dbname=(DESCRIPTION =
	(ADDRESS_LIST =
		(ADDRESS =
			(PROTOCOL = TCP)
			(Host = ".$hote .")
			(Port = ".$port."))
	)
	(CONNECT_DATA =
		(SID = ".$service.")
	)
	)";
	return $db;
}

?>