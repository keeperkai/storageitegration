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
		$cs_model = $this->getCloudStorageModel($storage_id);
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
	
}
