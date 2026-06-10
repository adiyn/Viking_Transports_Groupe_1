<?php
	/**
	 * Fichier : util.php
	 * 
	 * Auteur : E.Porcq
	 * Projet : Saison 5, Épisode 3 - SAE 2.456
	 * Description : Fonctions utilitaires pour l'affichage et le débogage
	 * Date : 11/10/2016
	 */

	/**
	 * Affiche le contenu d'un tableau ou d'un objet
	 * @param mixed $obj Objet ou tableau à afficher
	 * @return void
	 */
	function afficherTab($obj)
	{
		echo "<pre>";
		print_r($obj);
		echo "</pre>";
	}
?>
