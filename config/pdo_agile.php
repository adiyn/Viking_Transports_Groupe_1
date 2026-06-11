<?php
	// E.Porcq : Saison 5 , Épisode 3 
	// préparation SAE 2.456 : Fonctions permettant d'utiliser PDO
	// pdo_agile.php 11/10/2016


/*  Exemple
	$db_username = "XXX";
	$db_password = "XXX";
	//$db = "oci:dbname=info;charset=AL32UTF8"; // fonctionne si tnsname.ora est complet (base UTF8)
	//$db = "oci:dbname=info;charset=WE8ISO8859P15"; // fonctionne si tnsname.ora est complet
	// $db = "'oci:dbname=kiutoracle18.unicaen.fr:1521/info.kiutoracle18.unicaen.fr;charset=AL32UTF8'; ; // fonctionne si tnsname.ora est complet (base UTF8)
	$db = fabriquerChaineConnex(); // plus général 

	$conn = ConnecterPDO($db,$db_username,$db_password);
*/

//---------------------------------------------------------------------------------------------
function OuvrirConnexionPDO($db,$db_username,$db_password)
{
	try
	{
		$conn = new PDO($db,$db_username,$db_password);
		$res = true;
	}
	catch (PDOException $erreur)
	{
		echo $erreur->getMessage();
	}
	return $conn;
}
//---------------------------------------------------------------------------------------------
function majDonneesPDO($conn,$sql) // requêtes insert, update, delete non préparées
{
	$stmt = $conn->exec($sql);
	return $stmt;
}
//---------------------------------------------------------------------------------------------
function preparerRequetePDO($conn,$sql) // pour les requêtes préparées
{
	$cur = $conn->prepare($sql);
	return $cur;
}
//---------------------------------------------------------------------------------------------
function ajouterParamPDO($cur,$param,&$contenu,$type='texte',$taille=0) // fonctionne avec preparerRequetePDO
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
//---------------------------------------------------------------------------------------------
function majDonneesPrepareesPDO($cur) // fonctionne avec ajouterParamPDO
{
	$res = $cur->execute();
	return $res;
}
//---------------------------------------------------------------------------------------------
function majDonneesPrepareesTabPDO($cur,$tab) // fonctionne directement après preparerRequetePDO
{
	$res = $cur->execute($tab);
	return $res;
}
//---------------------------------------------------------------------------------------------
function LireDonneesPDO1($conn,$sql,&$tab) // requêtes select non préparées
{
	$i=0;
	foreach  ($conn->query($sql,PDO::FETCH_ASSOC) as $ligne)     
		$tab[$i++] = $ligne;
	$nbLignes = $i;
	return $nbLignes;
}
//---------------------------------------------------------------------------------------------
function LireDonneesPDO2($conn,$sql,&$tab) // requêtes select non préparées
{
	$i=0;
	foreach  ($conn->query($sql,PDO::FETCH_ASSOC) as $ligne)     
		$tab[$i++] = $ligne;
	$nbLignes = $i;
	return $nbLignes;
}
//---------------------------------------------------------------------------------------------
function LireDonneesPDO3($conn,$sql,&$tab) // requêtes select non préparées
{
  $cur = $conn->query($sql);
  //$tab = $cur->fetchall(PDO::FETCH_BOTH); // nom de colonnne + numéro
  $tab = $cur->fetchall(PDO::FETCH_ASSOC); // nom de colonnne
  return count($tab);
}
//---------------------------------------------------------------------------------------------
function LireDonneesPDOPreparee($cur,&$tab) // requêtes select  préparées
{
  $res = $cur->execute();
  $tab = $cur->fetchall(PDO::FETCH_ASSOC);
  return count($tab);
}
//---------------------------------------------------------------------------------------------
// fonctions supplementaires
//---------------------------------------------------------------------------------------------
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