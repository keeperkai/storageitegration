<?php
/*
	this model is a generic cloud storage model that does various operations on the cloud storages
	it will create a model according to the storage account and then call the responding methods
*/
class CloudStorageModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
		$this->load->model('googledrivemodel', 'googleDriveModel');
		$this->load->model('onedrivemodel', 'oneDriveModel');
    }
	public function getCloudStorageModel($provider){
		if($provider == 'googledrive'){
			return $this->googleDriveModel;
		}else if($provider == 'onedrive'){
			return $this->oneDriveModel;
		}
	}
    public function getAccountQuotaInfo($storage_account){
	    //gets the quota info of an account
	    //output: array(
	    //'free': the free quota left in bytes
	    //'used': the used quota in bytes
	    //'total': total quota for this account
		$cs_model = $this->getCloudStorageModel($storage_account['token_type']);
		return $cs_model->getAccountQuotaInfo($storage_account);
    }
	/*
		gets the access token for the account, if the current access token in the database is expired, it will refresh
		the access token.
		Note that this is not just the access token itself, but can possibly be a datastructor with a lot more data.
		Take a look at the google drive access token, it is a json object that is used by the googledrive api client.
		Not just the access token by itself
	*/
    public function getAccessToken($storage_account){
		$cs_model = $this->getCloudStorageModel($storage_account['token_type']);
		return $cs_model->getAccessToken($storage_account);
    }
	/*
		the browser(client) needs the access token itself, so this function is for extracting the access token itself.
	*/
	public function getAccessTokenForClient($storage_account){
		$cs_model = $this->getCloudStorageModel($storage_account['token_type']);
		return $cs_model->getAccessTokenForClient($storage_account);
	}
	/*
		delete a storage file on the cloud storage, note that this function does not delete the storage file data in our database.
	*/
	public function deleteStorageFile($storage_id, $storage_account){
		//$storage_account = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
		$cs_model = $this->getCloudStorageModel($storage_account['token_type']);
		$cs_model->deleteStorageFile($storage_id, $storage_account);
	}
	/*
		adds the permissions for a user to access a storage file on the cloud storage if needed.
		(googledrive files needs this, but for storages like onedrive that share through a link, we don't need to do anything)
	*/
	public function addPermissionForUser($storage_id, $storage_account, $user, $role){
		$cs_model = $this->getCloudStorageModel($storage_account['token_type']);
		if(method_exists ( $cs_model , 'addPermissionForUser')){
			$cs_model->addPermissionForUser($storage_id, $storage_account, $user, $role);
		}
	}
    /*
        NOT SUPPORTED YET
        list all files on that cloud storage account(even if the file is not registered on our system)
        output format:
        array(
            array(
                name
                id
                size
                
            )
        
        )
    */
    public function listAllFiles($storage_account){
        $cs_model = $this->getCloudStorageModel($storage_account['token_type']);
        return $cs_model->listAllFiles($storage_account);
    }
	/*
        delete all files that are on the storage account
    */
    public function purge($storage_account){
        $cs_model = $this->getCloudStorageModel($storage_account['token_type']);
        $cs_model->purge($storage_account);
    }
    /*
        upload a file from our server hard drive to a cloud storage provider
        
        file: the file pointer to the file, note that this fp will NOT be closed after this operation. But for unification's sake,
        we should rewind it.(because the curl's curlopt_infile does this)
        
        returns:
        the id of the file on the storage provider
    */
    public function uploadFile($storage_account, $file_name, $file_mime, $file_size, $file){
        $cs_model = $this->getCloudStorageModel($storage_account['token_type']);
        return $cs_model->uploadFile($storage_account, $file_name, $file_mime, $file_size, $file);
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
        $cs_model = $this->getCloudStorageModel($storage_account['token_type']);
        return $cs_model->downloadFile($storage_account, $storage_id, $file);
    }
    /*
        this function downloads a part of a storage file to our server, the byte offsets are specified by $start_offset and $end_offset(inclusive),
        the function writes to the $file resource and returns a reference to it.
    */
    public function downloadChunk($storage_account, $storage_id, $start_offset, $end_offset, $file){
        $cs_model = $this->getCloudStorageModel($storage_account['token_type']);
        if(method_exists ( $cs_model , 'downloadChunk')){
            return $cs_model->downloadChunk($storage_account, $storage_id, $start_offset, $end_offset, $file);
        }else{
            throw new Exception('trying to call downloadChunk on a cloud storage model that does not support range header');
        }
        return false;
    }
    /*
        copies a file from $source_account to $target_account on the storage provider, so we don't need to actually transfer the data.
    */
    public function apiCopyFileBetweenAccounts($source_account, $storage_id, $target_account){
        $cs_model = $this->getCloudStorageModel($source_account['token_type']);
		if(method_exists ( $cs_model , 'apiCopyFileBetweenAccounts')){
			$cs_model->apiCopyFileBetweenAccounts($source_account, $storage_id, $target_account);
		}else{
            throw new Exception('Trying to copy between accounts:'.PHP_EOL.var_export($source_account, true).PHP_EOL.PHP_EOL.var_export($target_account, true).PHP_EOL.'But the method is not supported in the cloud storage model');
        }
        return false;
    }
}
