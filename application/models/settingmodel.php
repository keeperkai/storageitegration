<?php
class SettingModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
	public function getUnmovableFileExtensions($user){
		//returns as a boolean map
		$output = array();
		$q = $this->db->get_where('setting', array('account'=>$user));
		$r = $q->result_array();
		foreach($r as $setting){
			$ext = $setting['extension'];
			$output[$ext] = true;
		}
		return $output;
	}
	public function chunkAllowedForExtension($user, $ext){
        $q = $this->db->get_where('setting', array('account'=>$user, 'extension'=>$ext));
		$r = $q->result_array();
        if(sizeof($r)>0) return false;
        else return true;
	}
	public function getAllSettings($user){
		$output = array();
		$q = $this->db->get_where('setting', array('account'=>$user));
		$r = $q->result_array();
		foreach($r as $setting){
			$id = $setting['setting_id'];
			$providers = $this->getSettingProviders($id);
			$setting['provider'] = $providers;
			$output[]=$setting;
		}
		return $output;
	}
	public function searchSetting($extension, $user){
		$q = $this->db->get_where('setting', array('extension'=>$extension, 'account'=>$user));
		$r = $q->result_array();
		if(sizeof($r)>0){
			$setting = $r[0];
			$setting['provider'] = $this->getSettingProviders($setting['setting_id']);
			return $setting;
		}
		return false;
	}
	public function deleteSetting($extension, $user){
		$q = $this->db->get_where('setting', array('extension'=>$extension, 'account'=>$user));
		$r = $q->result_array();
		if(sizeof($r)>0){
			$setting = $r[0];
			$this->db->delete('setting_whole_file_provider', array('setting_id'=>$setting['setting_id']));
		}
		$this->db->delete('setting', array('setting_id'=>$setting['setting_id']));
	}
	public function getSettingProviders($setting_id){
		$this->db->order_by('priority','asc');
		$q = $this->db->get_where('setting_whole_file_provider', array('setting_id'=>$setting_id));
		$r = $q->result_array();
		if(sizeof($r)>0){
			return $r;
		}
		return false;
	}
	public function setSetting($setting, $user){
		$q = $this->db->get_where('setting', array('account'=>$user, 'extension'=>$setting['extension']));
		$r = $q->result_array();
		//split the data into two parts for the two tables
		$setting_data = $setting;
		$setting_data['account'] = $user;
		unset($setting_data['provider']);
		$id = '';
		if(sizeof($r)>0){
			//remove all data related to the query if any in the provider table, and insert a new one.
			$id = $r[0]['setting_id'];
			$this->db->delete('setting_whole_file_provider', array('setting_id'=>$id));
			//update
			$this->db->update('setting', $setting_data, array('setting_id'=>$id));
		}else{
			//insert
			$this->db->insert('setting', $setting_data);
			
			$q = $this->db->get_where('setting', $setting_data);
			$r = $q->result_array();
			$id = $r[0]['setting_id'];
		}
		//set provider data if needed
		if($setting['save_type'] == 'whole'){
			$providers = $setting['provider'];
			for($i=0;$i<sizeof($providers);$i++){
				$this->db->insert('setting_whole_file_provider', array('setting_id'=>$id, 'priority'=>$i, 'provider'=>$providers[$i]));
			}
		}
	}
	public function initializeSettingsForUser($user){
		$setting_map = array(
			array(
				'extension'=>'xls',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'xlsx',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'ppt',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'pptx',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'doc',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'docx',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'pdf',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'ods',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'odt',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'odp',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'jpg',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'png',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'bmp',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'tiff',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'psd',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'png',
				'provider'=>array(
					'googledrive',
					'onedrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'mp3',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'aiff',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'wav',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'wma',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'bun',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'mp4',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'mov',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'avi',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'wmv',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'flv',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
			array(
				'extension'=>'mts',
				'provider'=>array(
					'onedrive',
					'googledrive',
                    'dropbox'
				),
				'save_type'=>'whole'
			),
		);
		foreach($setting_map as $setting){
			$this->setSetting($setting, $user);
		}
	}
}