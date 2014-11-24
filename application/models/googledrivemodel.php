<?php
class GoogleDriveModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
		
		//$this->load->model('filemodel', 'fileModel');
		//$this->load->model('storageaccountmodel', 'storageAccountModel');
		
    }
	private function refreshAccessToken(&$client, &$storage_account){
		$client->refreshToken($storage_account['token']);
		//write the new token to database
		$this->db->update('storage_account', array('current_token'=>$client->getAccessToken()),array('storage_account_id'=>$storage_account['storage_account_id']));
		$storage_account['current_token'] = $client->getAccessToken();
	}
	private function setupGoogleClient($storage_account){
		$client_id = '214603671512-82lgo7euepvskvpkfret9bsov1aqghvq.apps.googleusercontent.com';
		$client_secret = 'tXYw9OS8gOEOyOxLWn2sFiR0';
		require_once 'Google/Client.php';
		$client = new Google_Client();
		$client->setClientId($client_id);
		$client->setClientSecret($client_secret);
		$client->setAccessType('offline');
		//first time
		if($storage_account['current_token']==''){
			$this->refreshAccessToken($client, $storage_account);
		}
		//check current token expire date
		
		$client->setAccessToken($storage_account['current_token']);
		if($client->isAccessTokenExpired()){//refresh the token
			$this->refreshAccessToken($client, $storage_account);
		}
		return $client;
	}
	public function getAccessToken($storage_account){
		//get the access token's string, not the whole datastruct
		$client = $this->setupGoogleClient($refreshToken);
		$token_struct = json_decode($client->getAccessToken(), true);
		$token = $token_struct['access_token'];
		return $token;
	}
	private function setupDriveService($client){
		require_once 'Google/Client.php';
		require_once 'Google/Service/Drive.php';
		$drive = new Google_Service_drive($client);
		return $drive;
	}
	public function getAccountQuotaInfo($storage_account){
		//gets the quota info of an account
		//output: array(
		//'free': the free quota left in bytes
		//'used': the used quota in bytes
		//'total': total quota for this account
		$client = $this->setupGoogleClient($storage_account);
		$drive = $this->setupDriveService($client);
		
		$about = $drive->about->get();
		$total = $about->getQuotaBytesTotal();
		$used = $about->getQuotaBytesUsed();
		$free = $total-$used;
		$output = array('total'=>$total, 'used'=>$used, 'free'=>$free);
		return $output;
	}
	public function getAccessTokenForClient($storage_account_data){
		$client = $this->setupGoogleClient($storage_account_data);
		$token_struct = json_decode($client->getAccessToken(), true);
		$token = $token_struct['access_token'];
		return $token;
	}
	public function deleteStorageFile($storage_id, $storage_account){
		$client = $this->setupGoogleClient($storage_account_data);
		$service = $this->setupDriveService($client);
		$service->files->delete($storage_id);
	}
	//permission functions
	/*
		this function adds the permission for a file, which enables a user on our platform to access it.
		Note that in this case all accounts of type(googledrive) owned by the user will have access to it.
	*/
	public function addPermissionForUser($storage_id, $storage_account, $user, $role){
		//sfile is a join of storage_file and storage_account, so it contains all the necessary data for this single storage file.
		//adds the permissions to the storage files on gdrive
		//setup the client and get the list of permissions on gdrive for the file
		//$storage_account = $this->storageAccountModel->getStorageAccountWithId($storage_account_id);
		$client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
		$permissions = $service->permissions->listPermissions($storage_id);
		$permits = $permissions->getItems();
		//find the user's google drive permission id;
		$permission_id_list = $this->getPermissionIds($user);
		//see if the file already has a permission for that user on google
		foreach($permission_id_list as $uid){
			$existing_perm = false;
			foreach($permits as $fperm){
				if($fperm['id']==$uid){
					$existing_perm = $fperm;
					break;
				}
			}
			//update existing perm or insert a new one
			if($existing_perm){//update
				if($this->fileModel->roleCompare($role, $existing_perm['role'])>0){
					$permission = $service->permissions->get($sfile['storage_id'], $uid);
					$permission->setRole($role);
					$service->permissions->update($sfile['storage_id'], $uid, $permission);
				}
			}else{//insert new one
				$newPermission = new Google_Permission();
				$newPermission->setId($uid);
				$newPermission->setType('user');
				$newPermission->setRole($role);
				$service->permissions->insert($sfile['storage_id'], $newPermission);
			}
		}
	}
	private function getPermissionIds($user){
		$q = $this->db->get_where('storage_account', array('account'=>$user, 'token_type'=>'googledrive'));
		$r = $q->result_array();
		if(sizeof($r)>0){
			$output = array();
			foreach($r as $storageaccount){
				$output[]=$storageaccount['permission_id'];
			}
			return $output;
		}
		return false;
	}
}