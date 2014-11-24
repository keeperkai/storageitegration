<?php

class StorageAccountModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
		//$this->load->model('googledrivemodel', 'googleDriveModel');
		//$this->load->model('onedrivemodel', 'oneDriveModel');
		$this->load->model('cloudstoragemodel', 'cloudStorageModel');//die
    }
	public function getApiSingleFileLimit($provider){
		$info = $this->config->item('provider_info');
		return $info[$provider]['api_single_file_limit'];
	}
	public function getQuotaInfo($account_info){
		return $this->cloudStorageModel->getAccountQuotaInfo($account_info);
		/*
		if($account_info['token_type'] == 'googledrive'){
			return $this->googleDriveModel->getAccountQuotaInfo($account_info);
		}else if($account_info['token_type'] == 'onedrive'){
			return $this->oneDriveModel->getAccountQuotaInfo($account_info);
		}else if($account_info['token_type'] == 'dropbox'){
		
		}
		*/
	}
	public function setAccessTokenDataForClient($storage_account_data){
		$storage_account_data['access_token'] = $this->cloudStorageModel->getAccessTokenForClient($storage_account_data);
		/*
		if($storage_account_data['token_type']=='onedrive'){
			$storage_account_data['access_token'] = $this->oneDriveModel->getAccessTokenForClient($storage_account_data);
		}else if($storage_account_data['token_type']=='googledrive'){
			$storage_account_data['access_token'] = $this->googleDriveModel->getAccessTokenForClient($storage_account_data);
		}
		*/
		return $storage_account_data;
	}
	public function getStorageFilesForAccount($storage_account_id){
		$q = $this->db->get_where('storage_file', array('storage_account_id'=>$storage_account_id));
		return $q->result_array();
	}
	public function getStorageAccountWithTestingSchedulingInfo($user){
		$accounts = $this->getStorageAccounts($user);
		
		foreach($accounts as $key=>$acc){
			$storage_files = $this->getStorageFilesForAccount($acc['storage_account_id']);
			$used = 0;
			foreach($storage_files as $sfile){
				$used += $sfile['storage_file_size'];
			}
			$total = 0;
			if($acc['token_type']=='googledrive'){
				$total = 15*1024*1024*1024;
			}else if($acc['token_type']=='onedrive'){
				$total = 15*1024*1024*1024;
			}
			$accounts[$key]['quota_info'] = array(
				'total'=>$total,
				'free'=>$total-$used,
				'used'=>$used
			);
			//var_dump($accounts[$key]['quota_info']);
			$accounts[$key]['api_single_file_limit'] = $this->getApiSingleFileLimit($acc['token_type']);
			$accounts[$key]['min_free_quota_single_file_limit'] = min($accounts[$key]['quota_info']['free'], $accounts[$key]['api_single_file_limit']);
		}
		return $accounts;
	}
	public function getClientInfoForStorageAccountId($storage_account_id){
		$q = $this->db->get_where('storage_account', array('storage_account_id'=>$storage_account_id));
		$r = $q->result_array();
		if(sizeof($r)!=1) return false;
		$acc = $r[0];
		$acc['api_single_file_limit'] = $this->getApiSingleFileLimit($acc['token_type']);
		return $acc;
	}
	public function getSchedulingInfoForStorageAccountId($storage_account_id){
		$q = $this->db->get_where('storage_account', array('storage_account_id'=>$storage_account_id));
		$r = $q->result_array();
		if(sizeof($r)!=1) return false;
		$acc = $r[0];
		return $this->getSchedulingInfoForStorageAccount($acc);
	}
	public function getSchedulingInfoForStorageAccount($storage_account_data){
		$storage_account_data['quota_info'] = $this->getQuotaInfo($storage_account_data);
		$storage_account_data['api_single_file_limit'] = $this->getApiSingleFileLimit($storage_account_data['token_type']);
		$storage_account_data['min_free_quota_single_file_limit'] = min($storage_account_data['quota_info']['free'], $storage_account_data['api_single_file_limit']);
		return $storage_account_data;
	}
	public function getSchedulingInfoForMultipleStorageAccounts($storage_account_arr){
		$quota_info_map = array();
		$output = array();
		foreach($storage_account_arr as $k=>$acc){
			if(array_key_exists($acc['storage_account_id'], $quota_info_map)){
				$acc['quota_info'] = $quota_info_map[$acc['storage_account_id']];
			}else{
				$acc['quota_info'] = $this->getQuotaInfo($acc);
				$quota_info_map[$acc['storage_account_id']] = $acc['quota_info'];
			}
			$acc['api_single_file_limit'] = $this->getApiSingleFileLimit($acc['token_type']);
			$acc['min_free_quota_single_file_limit'] = min($acc['quota_info']['free'], $acc['api_single_file_limit']);
			$output[]=$acc;
		}
		return $output;
	}
	public function getStorageAccountWithSchedulingInfo($user){
		$accounts = $this->getStorageAccounts($user);
		$accounts = $this->getSchedulingInfoForMultipleStorageAccounts($accounts);
		return $accounts;
	}
    public function getStorageAccounts($user){
		$query = $this->db->get_where('storage_account', array('account'=>$user));
        $storageaccounts = $query->result_array();
        return $storageaccounts;
    }
	public function getStorageAccountWithId($storage_account_id){
		$query = $this->db->get_where('storage_account', array('storage_account_id'=>$storage_account_id));
		$r = $query->result_array();
		if(sizeof($r)>0){
			return $r[0];
		}
		return false;
	}
	/*
    public function getStorageAccountBasicInfo($user){
        $this->db->select('storage_account, email, tokentype');
        $query = $this->db->get_where('accounts', array('account'=>$user));
        $storageaccounts = $query->result_array();
        return $storageaccounts;
    }
	*/
    public function hasAccess($user, $storage_account_data_id){
        $q = $this->db->get_where('storage_account', array('storage_account_id'=>$storage_account_id));
        $r = $q->result_array();
        if(sizeof($r)>0){
            $sa = $r[0];
            if($sa['account'] === $user) return true;
        }
        return false;
    }
	/*
	public function getAccessTokenWithStorageAccountId($storage_account_id){
		
	}
	*/
	public function getAccessToken($storage_account_data){
		$provider = $storage_account_data['token_type'];
		$access_token = '';
		if($provider=='googledrive'){
			$access_token = getGoogleAccessTokenFromRefreshToken($storage_account_data['token']);
		}else if($provider=='onedrive'){
			$onedrive_refresh_data = getOnedriveRefreshData($storage_account_data['token']);
			$access_token = $onedrive_refresh_data['access_token'];
			$refresh_token = $onedrive_refresh_data['refresh_token'];
			$this->db->update('storage_account', array('token'=>$refresh_token), array('storage_account_id'=>$storage_account_data['storage_account_id']));
		}
		return $access_token;
	}
	
}