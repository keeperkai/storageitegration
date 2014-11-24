<?php
class StorageAccounts extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
		$this->load->model('storageaccountmodel', 'storageAccountModel');//die
		$this->load->model('cloudstoragemodel', 'cloudStorageModel');
	}
    public function getStorageAccounts()
	{
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
		$storageaccounts = $this->storageAccountModel->getStorageAccounts($user);
        //refresh all the google tokens, because the ones stored are refresh tokens
        foreach($storageaccounts as $key=>$acc){
            $acc['token'] = $this->cloudStorageModel->getAccessTokenForClient($acc);
			$storageaccounts[$key] = $acc;
        }
        header('Content-Type: application/json');
        echo json_encode($storageaccounts);
    }
}