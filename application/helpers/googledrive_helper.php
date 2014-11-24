<?php
function getGoogleAccessTokenFromRefreshToken($refreshToken){
	$client = setupGoogleClient($refreshToken);
	
	$token_struct = json_decode($client->getAccessToken(), true);
	$token = $token_struct['access_token'];
	return $token;
}
function setupGoogleClient($refreshToken){
	$client_id = '214603671512-82lgo7euepvskvpkfret9bsov1aqghvq.apps.googleusercontent.com';
	$client_secret = 'tXYw9OS8gOEOyOxLWn2sFiR0';
	require_once 'Google/Client.php';
	$client = new Google_Client();
	$client->setClientId($client_id);
	$client->setClientSecret($client_secret);
	$client->setAccessType('offline');
	$client->refreshToken($refreshToken);
	return $client;
}
function setupGoogleDriveService($refreshToken){
	require_once 'Google/Client.php';
	require_once 'Google/Service/Drive.php';
	$client = setupGoogleClient($refreshToken);
	$service = new Google_Service_Drive($client);
	return $service;
}
function deleteGoogleDriveStorageFile($refreshToken, $file_id){
    $service = setupGoogleDriveService($refreshToken);
    $service->files->delete($file_id);
}