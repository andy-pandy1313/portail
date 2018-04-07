<?php

/*
 * Liste des scopes en fonction des routes
 *   - Définition des scopes:
 *   	portée + "-" + verbe + "-" + categorie + (pour chaque sous-catégorie: '-' + sous-catégorie)
 *   	ex: user-get-user user-get-user-assos user-get-user-assos-followed
 *
 *   - Définition de la portée des scopes:
 *     + user :    user_credential => nécessite que l'application soit connecté à un utilisateur
 *     + client :  client_credential => nécessite que l'application est les droits d'application indépendante d'un utilisateur
 *
 *   - Définition du verbe:
 *     + manage:  gestion de la ressource entière
 *       + get :  récupération des informations en lecture seule
 *       + set :  posibilité d'écrire et modifier les données
 *         + create:  créer une donnée associée
 *         + edit:    modifier une donnée
 *         + remove:  supprimer une donnée
 */

// Toutes les routes commencant par client-{verbe}-users-
return [
	'description' => 'Utilisateurs',
	'verbs' => [
		'manage' => [
			'description' => 'Gérer totalement tous les utilisateurs',
		],
		'get' => [
			'description' => 'Récupérer la liste des utilisateurs',
		],
		'set' => [
			'description' => "Gérer la création et la modification d'utilisateurs",
		],
		'create' => [
			'description' => "Gérer la création d'utilisateurs",
		],
		'edit' => [
			'description' => "Gérer la modification d'utilisateurs",
		],
	]
];
