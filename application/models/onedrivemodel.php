<?php
class OneDriveModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
	private function refreshAccessToken(&$storage_account){
		$refresh_token = $storage_account['token'];
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
		$access_token = $resp_obj['access_token'];
		$refresh_token = $resp_obj['refresh_token'];
		$duration = $resp_obj['expires_in'];
		//update the columns in database
		$expire_date = new DateTime("now");
		$expire_date->add(new DateInterval('PT'.$duration.'S'));
		$this->db->update('storage_account', array('token'=>$refresh_token,'current_token'=>$access_token,'current_token_expire'=>$expire_date->format('Y-m-d H:i:s')),array('storage_account_id'=>$storage_account['storage_account_id']));
		//update the account info
		$storage_account['token'] = $refresh_token;
		$storage_account['current_token'] = $access_token;
		$storage_account['current_token_expire'] = $expire_date;
		return $resp_obj;
	}
	public function getAccessToken($storage_account){
		$expire_date = new DateTime($storage_account['current_token_expire']);
		$now = new DateTime("now");
		$access_token = $storage_account['current_token'];
		//var_dump($now);
		//var_dump($expire_date);
		if(($storage_account['current_token']=='')||$expire_date<$now){
			//refresh the token
			$obj = $this->refreshAccessToken($storage_account);
			$access_token = $obj['access_token'];
		}
		return $access_token;
	}
	public function getAccountQuotaInfo($storage_account){
		//gets the quota info of an account
		//output: array(
		//'free': the free quota left in bytes
		//'used': the used quota in bytes
		//'total': total quota for this account
		$access_token = $this->getAccessToken($storage_account);
		$opt = array(
			'http' => array(
				'method' => 'GET',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen('https://apis.live.net/v5.0/me/skydrive/quota?access_token='.$access_token, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
		$resp_obj = json_decode($response, true);
		$total = $resp_obj['quota'];
		$free = $resp_obj['available'];
		$used = $total-$free;
		$output = array('total'=>$total, 'used'=>$used, 'free'=>$free);
		return $output;
	}
	public function getAccessTokenForClient($storage_account_data){
		return $this->getAccessToken($storage_account_data);
	}
	public function deleteStorageFile($storage_id, $storage_account){
		//DELETE https://apis.live.net/v5.0/file.b7c3b8f9g3616f6f.B7CB8F9G3626F6!225?access_token=ACCESS_TOKEN
		$access_token = $this->getAccessToken($storage_account);
		$delete_url = 'https://apis.live.net/v5.0/'.$storage_id.'?access_token='.access_token;
		
		$opt = array(
			'http' => array(
				'method' => 'DELETE',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen($delete_url, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
		//$resp_obj = json_decode($response, true);
		
	}
}