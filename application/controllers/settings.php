<?php
class Settings extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
		$this->load->model('settingmodel', 'settingModel');
    }
	public function deleteSetting(){
		$user = $this->session->userdata('ACCOUNT');
		$extension = $this->input->post('extension');
		$this->settingModel->deleteSetting($extension, $user);
		header('Content-Type: application/json');
		$resp = array('status'=>'success');
		echo json_encode($resp);
	}
	public function getAllSettings(){
		$user = $this->session->userdata('ACCOUNT');
		header('Content-Type: application/json');
		echo json_encode($this->settingModel->getAllSettings($user));
	}
	public function getProviderInfo(){
		header('Content-Type: application/json');
		echo json_encode($this->config->item('provider_info'));
	}
	public function setSetting(){
		$user = $this->session->userdata('ACCOUNT');
		$extension = $this->input->post('extension');
		$providers = $this->input->post('providers');
		$setting = array(
			'extension'=>$extension,
			'save_type'=>'whole',
			'provider'=>$providers
		);
		$this->settingModel->setSetting($setting, $user);
		header('Content-Type: application/json');
		$resp = array('status'=>'success');
		echo json_encode($resp);
	}
}
