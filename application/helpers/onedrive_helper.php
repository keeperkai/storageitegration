<?php
function getOnedriveRefreshData($refresh_token){
	$onedrive_client_id = '0000000048127A68';
	$onedrive_client_secret = 'MTuhJU2dIGjqndPvzc8PZwKztnuLQ6d6';
	$onedrive_redirect_uri = 'http://storageintegration.twbbs.org/index.php/ciauthorization/onedrivecode';
	$body = array(
			'client_id'=>$onedrive_client_id,
			'redirect_uri'=>$onedrive_redirect_uri,
			'client_secret'=>$onedrive_client_secret,
			'refresh_token'=>$refresh_token,
			'grant_type'=>'refresh_token'
	);
	$opt = array(
		'http' => array(
			'method' => 'POST',
			'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
			'content' => http_build_query($body),
			'protocol_version'=> 1.1,
			"follow_location"=>0
		)
	);
	$context = stream_context_create($opt);
	$fp = fopen('https://login.live.com/oauth20_token.srf', 'r', false, $context);
	//var_dump($http_response_header);
	$response = stream_get_contents($fp);
	fclose($fp);
	$resp_obj = json_decode($response, true);
	return $resp_obj;
	//$access_token = $resp_obj['access_token'];
	//$refresh_token = $resp_obj['refresh_token'];
}
