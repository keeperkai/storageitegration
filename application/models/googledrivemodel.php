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
		$client = $this->setupGoogleClient($storage_account);
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
    //returns a curl handle
    public function getAccountQuotaInfoAsyncRequest($storage_account){
        //GET https://www.googleapis.com/drive/v2/about
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => "https://www.googleapis.com/drive/v2/about",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>array(
                'Authorization: Bearer '.$this->getAccessTokenForClient($storage_account),
                'Connection: close'
            )
        );
        curl_setopt_array($ch, $curlConfig);
        return $ch;
    }
    public function getAccountQuotaInfoAsyncYield($result){
        $result = json_decode($result, true);
        $total = $result['quotaBytesTotal'];
		$used = $result['quotaBytesUsed'];
		$free = $total-$used;
		$output = array('total'=>$total, 'used'=>$used, 'free'=>$free);
		return $output;
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
		$client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
        try{
            $service->files->delete($storage_id);
        }catch(Exception $e){
        
        }
	}
    //for google drive the alternate link is the .../storage_id/edit page
    //it will use the user account to decide whether or not the user can edit/preview the file
    public function getEditLink($storage_account, $storage_id){
        //do a get request for the file and get the alternate link
        $client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
        $google_file = $service->files->get($storage_id);
        return $google_file->getAlternateLink();
    }
    public function getPreviewLink($storage_account, $storage_id){
        return $this->getEditLink($storage_account, $storage_id);
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
			$this->addPermissionForUserIdOnProvider($uid, $storage_id, $storage_account, $role);
        }
	}
    /*
        adds the permissions to a file for an account ON THE STORAGE PROVIDER,
        $storage_account is the account that currently has the owner privileges to the file.
    */
    public function addPermissionForUserIdOnProvider($user_id, $storage_id, $storage_account, $role){
        if($role == 'owner') $role = 'writer';//because for a user that has owner level privilege, the other accounts of his should only have writer access
        //we do not need to worry about the original file uploading account because the owner is already set.
        $client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
		$permissions = $service->permissions->listPermissions($storage_id);
		$permits = $permissions->getItems();
        $existing_perm = false;
        foreach($permits as $fperm){
            if($fperm['id']==$user_id){
                $existing_perm = $fperm;
                break;
            }
        }
        //update existing perm or insert a new one
        if($existing_perm){//update
            if($this->fileModel->roleCompare($role, $existing_perm['role'])>0){
                $permission = $service->permissions->get($storage_id, $user_id);
                $permission->setRole($role);
                $service->permissions->update($storage_id, $user_id, $permission);
            }
        }else{//insert new one
            $newPermission = new Google_Service_Drive_Permission();
            $newPermission->setId($user_id);
            $newPermission->setType('user');
            $newPermission->setRole($role);
            $service->permissions->insert($storage_id, $newPermission);
        }
    }
    public function setPermissionForUser($storage_id, $storage_account, $user, $role){
		//sfile is a join of storage_file and storage_account, so it contains all the necessary data for this single storage file.
		//adds the permissions to the storage files on gdrive
		//setup the client and get the list of permissions on gdrive for the file
		//$storage_account = $this->storageAccountModel->getStorageAccountWithId($storage_account_id);
		$client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
		$permissions = $service->permissions->listPermissions($storage_id);
		$provider_permissions = $permissions->getItems();
        //var_dump($provider_permissions);
        /*
        array of:(these kinds of objects)
        object(Google_Service_Drive_Permission)#41 (17) {
            ["additionalRoles"]=>
            NULL
            ["authKey"]=>
            NULL
            ["domain"]=>
            string(9) "gmail.com"
            ["emailAddress"]=>
            string(21) "keeperkaigg@gmail.com"
            ["etag"]=>
            string(57) ""Lie3Y624-6bAlCGsnUSYyb6P-dU/gjFB-hZaNhsSE3crTGN7r8KcKLE""
            ["id"]=>
            string(20) "09392245092114565377"//this is actually the permission id of the user
            ["kind"]=>
            string(16) "drive#permission"
            ["name"]=>
            string(10) "Kai Keeper"
            ["photoLink"]=>
            NULL
            ["role"]=>
            string(5) "owner"
            ["selfLink"]=>
            string(103) "https://www.googleapis.com/drive/v2/files/0B5RzcMP4KMEsbS1xQjBlYWhTNGM/permissions/09392245092114565377"
            ["type"]=>
            string(4) "user"
            ["value"]=>
            NULL
            ["withLink"]=>
            NULL
            ["collection_key":protected]=>
            string(5) "items"
            ["modelData":protected]=>
            array(0) {
            }
            ["processed":protected]=>
            array(0) {
            }
          }
        */
        //turn the provider_permissions into a map, with the user_id(user permission_id) as key
        $provider_permissions = array_to_map('id', $provider_permissions);
		//find the user's google drive permission id;
		$permission_id_list = $this->getPermissionIds($user);
        $owner_uid = $storage_account['permission_id'];
        //see if the file already has a permission for that user on google
        foreach($permission_id_list as $uid){
			if($uid == $owner_uid) continue;//don't need to set the permission for the owner
            $this->setPermissionForUserIdOnProvider($service, $provider_permissions, $uid, $storage_id, $role);
        }
	}
    public function setPermissionForUserIdOnProvider($service, $provider_permissions, $user_id, $storage_id, $role){
        if($role == 'owner') $role = 'writer';//because for a user that has owner level privilege, the other accounts of his should only have writer access
        //update existing perm or insert a new one
        if(array_key_exists($user_id, $provider_permissions)){//update
            if($provider_permissions[$user_id]['role'] == 'owner') return;//don't set the owner
            $permission = $provider_permissions[$user_id];//$service->permissions->get($storage_id, $user_id);
            $permission->setRole($role);
            $service->permissions->update($storage_id, $user_id, $permission);
        }else{//insert new one
            $newPermission = new Google_Service_Drive_Permission();
            $newPermission->setId($user_id);
            $newPermission->setType('user');
            $newPermission->setRole($role);
            $service->permissions->insert($storage_id, $newPermission);
        }
    }
    public function deletePermissionForUser($storage_id, $storage_account, $user){
		//sfile is a join of storage_file and storage_account, so it contains all the necessary data for this single storage file.
		//adds the permissions to the storage files on gdrive
		//setup the client and get the list of permissions on gdrive for the file
		//$storage_account = $this->storageAccountModel->getStorageAccountWithId($storage_account_id);
		$client = $this->setupGoogleClient($storage_account);
		$service = $this->setupDriveService($client);
		$permissions = $service->permissions->listPermissions($storage_id);
		$provider_permissions = $permissions->getItems();
		//find the user's google drive permission id;
		$permission_id_list = $this->getPermissionIds($user);
		$provider_permissions = array_to_map('id', $provider_permissions);//use user permission id as key
        foreach($permission_id_list as $uid){
			//$perm_to_delete = $service->permissions->get($storage_id, $uid);
            //$service->permissions->delete($perm_to_delete);
            //first check if the user's account exists
            if(array_key_exists($uid, $provider_permissions)){
                $service->permissions->delete($storage_id, $uid);
            }//if the permission for this uid doesn't exist then there is no need to delete it.
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
    //------------------------------------------------------------------------------------------
    private function getFilesUnderFolder($folder_id, $storage_account){
        $pageToken = NULL;
        $client = $this->setupGoogleClient($storage_account);
        $service = $this->setupDriveService($client);
        $output = array();
        do {
            try {
                $parameters = array();
                if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
                }
                $children = $service->children->listChildren($folder_id, $parameters);
        
                foreach ($children->getItems() as $child) {
                //print 'File Id: ' . $child->getId();
                    $output[]=$child->getId();
                }
                $pageToken = $children->getNextPageToken();
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
                $pageToken = NULL;
            }
        } while ($pageToken);
        return $output;
    }
    public function purge($storage_account){
        //get all file ids under root folder
        $files_under_root = $this->getFilesUnderFolder('root', $storage_account);
        foreach($files_under_root as $file_id){
            $this->deleteStorageFile($file_id, $storage_account);
        }
    }
    
    
    //Shuffle related / upload/download/copy files------------
    
    //direct upload without using hard drive
    public function uploadFileData($storage_account, $file_name, $file_mime, $file_size, $data){
        $client = $this->setupGoogleClient($storage_account);
        $service = $this->setupDriveService($client);
        
        
        $google_api_file = new Google_Service_Drive_DriveFile();
        $google_api_file->title = $file_name;
        $chunkSizeBytes = 1 * 1024 * 1024;

        // Call the API with the media upload, defer so it doesn't immediately return.
        $client->setDefer(true);
        $request = $service->files->insert($google_api_file);

        // Create a media file upload to represent our upload process.
        $media = new Google_Http_MediaFileUpload(
          $client,
          $request,
          $file_mime,
          null,
          true,
          $chunkSizeBytes
        );
        $media->setFileSize($file_size);

        $status = false;
        
        
          $status = $media->nextChunk($data);
          
        

        // The final value of $status will be the data from the API for the object
        // that has been uploaded.
        $result = false;
        if($status != false) {
          $result = $status;
        }
        // Reset to the client to execute requests immediately in the future.
        $client->setDefer(false);
        return $result['id'];
    }
    public function uploadFile($storage_account, $file_name, $file_mime, $file_size, $file, $convert = false){
        $client = $this->setupGoogleClient($storage_account);
        $service = $this->setupDriveService($client);
        
        
        $google_api_file = new Google_Service_Drive_DriveFile();
        $google_api_file->title = $file_name;
        $chunkSizeBytes = 1 * 1024 * 1024;

        // Call the API with the media upload, defer so it doesn't immediately return.
        $client->setDefer(true);
        $request = '';
        if($convert){
            $request = $service->files->insert($google_api_file, array('convert'=>true));
        }else{
            $request = $service->files->insert($google_api_file);
        }

        // Create a media file upload to represent our upload process.
        $media = new Google_Http_MediaFileUpload(
          $client,
          $request,
          $file_mime,
          null,
          true,
          $chunkSizeBytes
        );
        $media->setFileSize($file_size);

        // Upload the various chunks. $status will be false until the process is
        // complete.
        $status = false;
        //var_dump('uploading for '.$file_name.' :'.PHP_EOL);
        //var_dump('file pointer is at: '.ftell($file));
        
        /*
        fseek($file, 0, SEEK_END);
        var_dump('file resource size: '.ftell($file));
        rewind($file);
        */
        
        //rewind($file);//for some reason, we need to rewind the file handle even if it's position is at 0
        //var_dump('file pointer is at: '.ftell($file));
        
        
        while (!$status && !feof($file)) {
          $chunk = fread($file, $chunkSizeBytes);
          $status = $media->nextChunk($chunk);
          //var_dump(strlen($chunk));
          //var_dump(feof($file));
          
        }

        // The final value of $status will be the data from the API for the object
        // that has been uploaded.
        $result = false;
        if($status != false) {
          $result = $status;
        }
        // Reset to the client to execute requests immediately in the future.
        $client->setDefer(false);
        rewind($file);
        //var_dump($result);
        return $result['id'];
    }
    //this function directly downloads file data into memory without going into hard drive
    public function downloadFileData($storage_account, $storage_id){
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $google_api_file = $drive->files->get($storage_id);
        $dl_link = $google_api_file->getDownloadUrl();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dl_link);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer '.$this->getAccessToken($storage_account),
                'Connection: close'
            )
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    /*
        download a storage file on the server and stream it to hard drive
        storage_id: the id of the file to download on the storage provider
        file: the files resource to write to, the function will start writing from the current position, so you need to initialize it to the
        right position. By doing it this way, you can download a lot of data from different storage files and append them into the same file
        resource.
        
        Take note that curl rewinds the file pointer after it has finished the operation. If you want to append multiple downloads together, use
        fseek($fp, 0, SEEK_END); to move the position to the current end before calling this function.
        
        returns:
        the handle to the file resource that has been downloaded, with the position set back to 0
    */
    public function downloadFile($storage_account, $storage_id, $file){
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $google_api_file = $drive->files->get($storage_id);
        $dl_link = $google_api_file->getDownloadUrl();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dl_link);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer '.$this->getAccessToken($storage_account),
                'Connection: close'
            )
        );
        curl_exec($ch);
        curl_close($ch);
        rewind($file);//it seems that for w+/r+ files between reading and writing you need to rewind for some reason
        //see http://php.net/manual/en/function.fflush.php
        
        //testing downloaded file resource
        /*
        $testdata = fread($file, 50);
        var_dump('download testing, read 50 bytes: ');
        var_dump($testdata);
        var_dump('after read, position at'.ftell($file));
        rewind($file);
        $testdata = fread($file, 50);
        var_dump('download testing after rewinding, read 50 bytes: ');
        var_dump($testdata);
        var_dump('after read, position at'.ftell($file));
        //the result is the first section will result in a 0 byte read
        //after the rewind the read will succeed with 50 bytes read.
        */
        
    }
    /*
        this function downloads a part of a storage file to our server, the byte offsets are specified by $start_offset and $end_offset(inclusive),
        the function writes to the $file resource and returns a reference to it.
    */
    public function downloadChunk($storage_account, $storage_id, $start_offset, $end_offset, $file){
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $google_api_file = $drive->files->get($storage_id);
        $dl_link = $google_api_file->getDownloadUrl();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dl_link);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer '.$this->getAccessToken($storage_account),
                'Connection: close',
                'Range: bytes='.$start_offset.'-'.$end_offset
            )
        );
        curl_exec($ch);
        curl_close($ch);
        rewind($file);//it seems that for w+/r+ files between reading and writing you need to rewind for some reason
        //see http://php.net/manual/en/function.fflush.php
    }
    /*
        copies a file using $target_account on the storage provider through api calls, so we don't need to actually transfer the data.
        note that this can be called with another account, so we can transfer data between accounts.
        
        Note: make sure the target_account has the permissions to the file before calling this.
    */
    public function apiCopyFile($storage_id, $source_account, $target_account){
        $client = $this->setupGoogleClient($target_account);
        $drive = $this->setupDriveService($client);
        $source_file = $drive->files->get($storage_id);
        
        $copiedFile = new Google_Service_Drive_DriveFile();
        $copiedFile->setTitle($source_file->getTitle());
        $result = array();
        try {
            //$s = microtime(true);
            $result = $drive->files->copy($storage_id, $copiedFile);//result is a Google_Service_Drive_DriveFile
            //$t = microtime(true);
            //echo 'copy request: '.($t-$s).'   '.PHP_EOL;//takes 2s~4s...maybe we should use curl multi exec...
        } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
        }
        if($result){
            return $result->getId();
        }
        return NULL;
    }
    public function getDownloadLink($storage_id, $owner_account, $user){
        //first check if the user has a googledrive account, if not, output need_account
        $gdrive_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'googledrive');
        if(sizeof($gdrive_accounts)==0){
            return array(
                'status'=>'need_account',
                'errorMessage'=>'您需要至少一個google drive帳號才能下載此檔案，請連結一個google drive帳號至本系統'
            );
        }
        //user has at least 1 gdrive account, use this user's account to get the downloadurl of the file, and use the access_token to download it.
        $storage_account = $gdrive_accounts[0];
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $google_api_file = $drive->files->get($storage_id);
        $dl_link = $google_api_file->getDownloadUrl();
        $dl_link .= '&access_token='.$this->getAccessTokenForClient($storage_account);
        return array(
            'status'=>'success',
            'link'=>$dl_link
        );
    }
    //------------------google doc related functions
    /*
        $doc_type = document, spreadsheet, presentation
    */
    public function getExportLink($doc_type, $storage_id, $owner_account, $user){
        $gdrive_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'googledrive');
        if(sizeof($gdrive_accounts)==0){
            return array(
                'status'=>'need_account',
                'errorMessage'=>'您需要至少一個google drive帳號才能下載此檔案，請連結一個google drive帳號至本系統'
            );
        }
        //user has at least 1 gdrive account, use this user's account to get the export link of the file, and use the access_token to download it.
        $storage_account = $gdrive_accounts[0];
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $google_api_file = $drive->files->get($storage_id);
        if($doc_type == 'document'){
            $dl_link = $google_api_file->getExportLinks()['application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        }else if($doc_type == 'spreadsheet'){
            $dl_link = $google_api_file->getExportLinks()['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }else if($doc_type == 'presentation'){
            $dl_link = $google_api_file->getExportLinks()['application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        }
        $dl_link .= '&access_token='.$this->getAccessTokenForClient($storage_account);
        return array(
            'status'=>'success',
            'link'=>$dl_link
        );
    }
    public function createDocument($storage_account, $name){
        $doc_path = $this->config->item('document_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('document_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
    public function createSpreadSheet($storage_account, $name){
        $doc_path = $this->config->item('spreadsheet_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('spreadsheet_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
    public function createPresentation($storage_account, $name){
        $doc_path = $this->config->item('presentation_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('presentation_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
    public function testInsertPermit($storage_account, $file_id, $perm_id){
        $client = $this->setupGoogleClient($storage_account);
        $drive = $this->setupDriveService($client);
        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setId($perm_id);
        $newPermission->setType('user');
        $newPermission->setRole('writer');
        $drive->permissions->insert($file_id, $newPermission);
    }
}