<?php
	include_once "pdo_agile.php";
	include_once "param_connexion.php";
	echo '<meta charset="utf-8"> ';
	// décommenter en fonction du serveur de BDD utilisé
	//define ("MOD_BDD","MYSQL");
	define ("MOD_BDD","ORACLE");

	if (MOD_BDD == "MYSQL")
	{
		$db_username = $db_usernameMySQL;		
		$db_password = $db_passwordMySQL;
		$db = $dbMySQL;
	}
	else
	{
		$db_username = $db_usernameOracle;		
		$db_password = $db_passwordOracle;	
		$db = $dbOracle;
	}
	
	$conn = OuvrirConnexionPDO($db,$db_username,$db_password);

	afficherObj($conn);
	echo "aaaaaaaaaa";
	if ($conn)
	{
		echo ("<hr/> Connexion réussie à la base de données <br/>");
		insererDonnee($conn);
		corrigerDonnees($conn);
		$matrice = lireDonnees($conn);
		//afficherObj($matrice);
		foreach($matrice as $cle1 => $ligne)
		{
			foreach($ligne as $cle2 => $contenu)
				echo $cle2 . " " . $contenu . " ";
			echo "<br/>";
		}
	}
	else
		echo ("<hr/> Connexion impossible à la base de données <br/>");
	
	function insererDonnee($c)
	{
		$sql = "INSERT INTO bidon VALUES (25,'Valise','jaune')";
		afficherObj($sql);
		$res = majDonneesPDO($c,$sql);
		echo "Résultats de la requête " ,$res . "<br/>";
		$sql = "INSERT INTO bidon VALUES (28,'Valise','rouge')";
		afficherObj($sql);
		$res = majDonneesPDO($c,$sql);
		echo "Résultats de la requête ",$res . "<br/>";
	}
	
	function corrigerDonnees($c)
	{
		$sql = "update bidon set type='trousse' where type='Valise'";
		afficherObj($sql);
		$res = majDonneesPDO($c,$sql);
		echo "Résultats de la requête " . $res . "<br/>";
	}

	function lireDonnees($c)
	{
		$sql = "select * from bidon";
		LireDonneesPDO1($c,$sql,$donnee);  
		return $donnee;
	}
	
	function afficherObj($obj)
	{
		echo "<PRE>";
		print_r($obj);
		echo "</PRE>";
	}
	
 ?>

