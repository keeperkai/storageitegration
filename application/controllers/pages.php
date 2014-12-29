<?php
class Pages extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function view($page = 'login')
    {
        if (!file_exists('application/views/pages/'.$page.'.php')) {
            header('Location: '.base_url().'index.php/pages/view/login');
        }
        if ($page === 'login') {
            $this->load->view('pages/login');
        } else {
			$data = array();
			$data['page'] = $page;
            if (!$this->session->userdata('ACCOUNT')) {
                header('Location: '.base_url().'index.php/pages/view/login');
                return;
            }
            $account = $this->session->userdata('ACCOUNT');
            switch ($page) {
                case 'manageaccount':
                    $data['title'] = '連結雲端硬碟帳號';
					$this->load->model('storageaccountmodel', 'storageAccountModel');
                    $result = $this->storageAccountModel->getStorageAccountWithSchedulingInfo($account);
                    $data['storage_account'] = $result;
                    break;
                case 'integration':
                    $data['title'] = '檔案操作介面';
                    break;
				case 'settings':
                    $data['title'] = '系統設定';
                    break;
            }
			if(!isset($data['title'])) $data['title'] = $page;
            $data['username'] = $this->session->userdata('ACCOUNT');
            $this->load->view('templates/header', $data);
            $this->load->view('pages/'.$page, $data);
            $this->load->view('templates/footer', $data);
        }
    }
}
