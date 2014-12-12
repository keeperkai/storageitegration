<?php
class Test extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
        $this->load->model('cloudstroragemodel', 'cloudStorageModel');
        
    }
    public function echoPostBody(){
        $body = file_get_contents('php://input');
        header('Content-Type: text/plain');
        var_dump($_FILES['file']);
        echo $body;
    }
    public function justEchoOK(){
        header('Content-Type: text/plain');
        echo 'OK!'.PHP_EOL;
    }
    public function uploadToAllAccounts(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        //header('Content-Type: text/plain');
		$user = $this->session->userdata('ACCOUNT');
        //get all storage accounts
        $storage_accounts = $this->storageAccountModel->getStorageAccounts($user);
        //generate some data for the file to upload
        $img = fopen(APPPATH . 'testimage.png', 'r');
        //fseek($img, 0, SEEK_END);
        //var_dump(ftell($img));
        //flush();
        //rewind($img);
        
        $output = array();
        foreach($storage_accounts as $acc){
            if($acc['token_type'] ==  'onedrive')
                $output[]=$this->cloudStorageModel->uploadFile($acc, 'testimage.png', 'image/png', filesize(APPPATH . 'testimage.png'), $img);
        }
        
        fclose($img);
        echo json_encode($output);
    }
}