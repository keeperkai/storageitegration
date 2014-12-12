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
    public function uploadFile($storage_account, $file_name, $file_mime, $file_size, $file){
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

        // Upload the various chunks. $status will be false until the process is
        // complete.
        $status = false;
        while (!$status && !feof($file)) {
          $chunk = fread($file, $chunkSizeBytes);
          $status = $media->nextChunk($chunk);
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
        return $result['id'];
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
    }
    /*
        this function downloads a part of a storage file to our server, the byte offsets are specified by $start_offset and $end_offset(inclusive),
        the function writes to the $file resource and returns a reference to it.
    */
    public function downloadChunk($storage_account, $storage_id, $start_offset, $end_offset, $file){
        
    }
    /*
        copies a file from $source_account to $target_account on the storage provider through api calls, so we don't need to actually transfer the data.
    */
    public function apiCopyFileBetweenAccounts($source_account, $storage_id, $target_account){
        
    }
}