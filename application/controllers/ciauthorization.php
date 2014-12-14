<?php
class CIAuthorization extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel','fileModel');
        $this->load->model('storageaccountmodel','storageAccountModel');
        $this->load->model('cloudstoragemodel','storageAccountModel');
    }
    public function connectSkydriveAccount(){
        $client_id = '0000000048127A68';
        $client_secret = 'MTuhJU2dIGjqndPvzc8PZwKztnuLQ6d6';
    }
    public function connectGoogleAccount(){
        require_once 'Google/Client.php';
        $client_id = '214603671512-82lgo7euepvskvpkfret9bsov1aqghvq.apps.googleusercontent.com';
        $client_secret = 'tXYw9OS8gOEOyOxLWn2sFiR0';
        //$client_id = '214603671512-7a11eenevrthg7uc7tkq9jg66eoijiu6.apps.googleusercontent.com';
        //$client_secret = '3c5TrhJCINdvftrROW-yrR-V';
        //$redirect_uri = 'urn:ietf:wg:oauth:2.0:oob';
        $redirect_uri = base_url().'index.php/ciauthorization/code';
        
        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirect_uri);
        $client->addScope("https://www.googleapis.com/auth/drive");
        $client->addScope("email");
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $authUrl = $client->createAuthUrl();
        header('Location: '.$authUrl);
    }
	public function oneDriveCode(){
		//header('Content-Type: text/plain');
		$onedrive_client_id = '0000000048127A68';
		$onedrive_client_secret = 'MTuhJU2dIGjqndPvzc8PZwKztnuLQ6d6';
		$onedrive_redirect_uri = 'http://storageintegration.twbbs.org/index.php/ciauthorization/onedrivecode';
		$code = $this->input->get('code');
		$body = array(
			'client_id'=>$onedrive_client_id,
			'redirect_uri'=>$onedrive_redirect_uri,
			'client_secret'=>$onedrive_client_secret,
			'code'=>$code,
			'grant_type'=>'authorization_code'
		);
		//do the post request
		$opt = array(
			'http' => array(
				'method' => 'POST',
				'header' => ["Content-Type: application/x-www-form-urlencoded\r\n"],
				'content' => http_build_query($body),
				'protocol_version'=> 1.1,
				"follow_location"=>0
			)
		    //'ssl' => array("verify_peer" => true ,"cafile" => dirname(__FILE__) . '/google-api-php-client-master/src/Google/IO/cacerts.pem')
		);
		//var_dump($opt);
		$context = stream_context_create($opt);
		
		$fp = fopen('https://login.live.com/oauth20_token.srf', 'r', false, $context);
		//var_dump($http_response_header);
		$response = stream_get_contents($fp);
		fclose($fp);
		$resp_obj = json_decode($response, true);
		$access_token = $resp_obj['access_token'];
		$refresh_token = $resp_obj['refresh_token'];
		//gather user data
		$opt2 = array(
			'http' => array(
				'method' => 'GET',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context2 = stream_context_create($opt2);
		
		$fp2 = fopen('https://apis.live.net/v5.0/me?access_token='.$access_token, 'r', false, $context2);
		//var_dump($http_response_header);
		//var_dump($response);
		//var_dump($context2);
		//get the content length, we need it because onedrive is being stupid and not closing the stream.
		/*
		$r_length = 0;
		foreach($http_response_header as $head){
			if(strncasecmp ($head, 'content-length', 14) === 0){
				$r_length = intval(substr($head, strpos($head, ': ')+2));
			}
		}
		*/
		//$response2 = stream_get_contents($fp2, $r_length);
		
		$response2 = stream_get_contents($fp2);
		//var_dump($r_length);
		//var_dump($response2);
		fclose($fp2);
		$resp_obj2 = json_decode($response2, true);
		$id_on_storage = $resp_obj2['id'];
		//end of gathering
		$curr_timestamp = new DateTime();
		$dbdata = array(
            'account'=>$this->session->userdata('ACCOUNT'),
            'id_on_storage'=>$id_on_storage,
            'token_type'=>'onedrive',
            'token'=>$refresh_token,
			'time_stamp'=>$curr_timestamp->format('Y-m-d H:i:s')
		);
		
		$this->db->insert('storage_account', $dbdata);
		//echo "access_token length: ".strlen($access_token).", refresh_token length:".strlen($refresh_token).PHP_EOL;
		header("Location: ".base_url()."index.php/pages/view/manageaccount");
	}
    public function code(){//code for google drive accounts
        $code = $this->input->get('code');
        header("Content-type: text/plain");
        require_once 'Google/Client.php';
		require_once 'Google/Service/Drive.php';
		$client = new Google_Client();
        $client_id = '214603671512-82lgo7euepvskvpkfret9bsov1aqghvq.apps.googleusercontent.com';
        $client_secret = 'tXYw9OS8gOEOyOxLWn2sFiR0';
        $redirect_uri = base_url().'index.php/ciauthorization/code';
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirect_uri);
        $client->authenticate($code);
        $accesstoken = json_decode($client->getAccessToken(), true);
        $drive_service = new Google_Service_Drive($client);
		
        $id_token = $client->verifyIdToken()->getAttributes();
        $email = $id_token['payload']['email'];
        $platform = 'googledrive';
        $id_on_storage = $id_token['payload']['sub'];
		$permission_id = $drive_service->permissions->getIdForEmail($email);
		//var_dump($id_token);
        //var_dump($accesstoken);
        //check if an account with the same sub is already in the db, if so refuse and output error.
        $query = $this->db->get_where('storage_account', array('id_on_storage'=>$id_on_storage, 'token_type'=>'googledrive'));
        $result = $query->result_array();
        if(sizeof($result)>0){
            //this means the storage account is already linked to someone's account in our system
            header('Content-Type: text/html');
            $this->load->view('pages/accountalreadylinked', array('email'=>$email, 'platform'=>$platform));
            return;
        }
        //save the account information to db.
		$curr_timestamp = new DateTime();
        $dbdata = array(
            'account'=>$this->session->userdata('ACCOUNT'),
            'id_on_storage'=>$id_on_storage,
            'token_type'=>'googledrive',
            'token'=>$accesstoken['refresh_token'],
			'permission_id'=>$permission_id->getId(),
			'time_stamp'=>$curr_timestamp->format('Y-m-d H:i:s')
        );
        $this->db->insert('storage_account', $dbdata);
        
        
        //Propagate the permissions for the files that this account has access to on our platform
        //1. files on the same provider owned by this user
        //2. files on the same provider shared with this user
        
        //or find all permissions and get all files this user has access to.
        //for each virtual file, find the storage files that need to be permitted(according to storage provider of storage account)
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $this->addPermissionsForNewAccountOnProvider('googledrive', $user, $permission_id->getId());
        header("Location: ".base_url()."index.php/pages/view/manageaccount");
    }
    private function addPermissionsForNewAccountOnProvider($provider, $user, $new_account_id){
        //or find all permissions and get all files this user has access to.
        //for each virtual file, find the storage files that need to be permitted(according to storage provider of storage account)
        
        $vfiles = $this->fileModel->getVirtualFilesForUserWithAccessGreaterEqualThan($user, 'reader');
        //echo 'virtual file count: ';
        //var_dump(sizeof($vfiles));
        foreach($vfiles as $vfile){
            //echo 'processing vfile: ';
            //var_dump($vfile);
            if($vfile['file_type'] == 'file'){
                $sfiles = $this->fileModel->getStorageFilesForVirtualFileId($vfile['virtual_file_id']);
                //echo 'got storage files for virtual files, size: ';
                //var_dump(sizeof($sfiles));
                foreach($sfiles as $sfile){
                    //echo 'processing sfile: ';
                    //var_dump($sfile);
                    $storage_account = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);//this is the storage account of the user.
                    if($storage_account['token_type'] == $provider){
                        //echo 'calling add permission';
                        $this->cloudStorageModel->addPermissionForUser($sfile['storage_id'], $storage_account, $user, $vfile['role']);
                        //$this->cloudStorageModel->addPermissionForUserIdOnProvider($new_account_id, $sfile['storage_id'], $storage_account, $vfile['role']);
                    }
                }
            }
        }
    }
}