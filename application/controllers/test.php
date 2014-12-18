<?php
class Test extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
        $this->load->model('cloudstroragemodel', 'cloudStorageModel');
        //$this->load->model('googledrivemodel', 'googleDriveModel');
        
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
            //if($acc['token_type'] ==  'onedrive')
                $output[]=$this->cloudStorageModel->uploadFile($acc, 'testimage.png', 'image/png', filesize(APPPATH . 'testimage.png'), $img);
        }
        
        fclose($img);
        echo json_encode($output);
    }
    /*
        this function will first upload a file to each of the accounts, and then download it, and upload the downloaded file to simulate shuffling.
        note that it will delete the first uploaded file.
    */
    public function downloadThenUpload(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $output = array();
        $storage_accounts = $this->storageAccountModel->getStorageAccounts($user);
        
        $testfilename = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilename = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilename);
        $img = fopen(APPPATH . $testfilename, 'r');
        //$data = stream_get_contents($img);
        //upload the file first
        
        //upload to each account
        foreach($storage_accounts as $acc){
            //if($acc['token_type'] != 'googledrive') continue;
            $s = microtime(true);
            $id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilename, $testfilemime, $testfilesize, $img);
            //var_dump($id);
            //$id = $this->googleDriveModel->uploadFileData($acc, $testfilename, $testfilemime, $testfilesize, $data);
            $t = microtime(true);
            echo 'first upload: '.($t-$s);
            //download the file
            $tmph = tmpfile();
            $s = microtime(true);
            $this->cloudStorageModel->downloadFile($acc, $id, $tmph);
            //$dl_data = $this->googleDriveModel->downloadFileData($acc, $id);
            $t = microtime(true);
            echo 'download: '.($t-$s);
            $s = microtime(true);
            $output[]=$this->cloudStorageModel->uploadFile($acc, $testfilename, $testfilemime, $testfilesize, $tmph);
            //$output[]=$this->googleDriveModel->uploadFileData($acc, $testfilename, $testfilemime, $testfilesize, $dl_data);
            $t = microtime(true);
            echo 'second upload: '.($t-$s);
            
            fclose($tmph);
            //delete the first uploaded file
            $s = microtime(true);
            $this->cloudStorageModel->deleteStorageFile($id, $acc);
            $t = microtime(true);
            echo 'delete file: '.($t-$s);
        }
        
        fclose($img);
        echo json_encode($output);
    }
    
    public function testpartialdownload(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $output = array();
        $storage_accounts = $this->storageAccountModel->getStorageAccounts($user);
        
        $testfilename = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilename = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilename);
        $img = fopen(APPPATH . $testfilename, 'r');
        
        //upload a file to each account, download parts of the file and append them together, then upload back to the same account and see if the file works
        foreach($storage_accounts as $acc){
            //upload a file to the provider
            $base_id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilename, $testfilemime, $testfilesize, $img);
            //download the file in three divisions
            $tmp = tmpfile();
            $div_1_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, 0, floor($testfilesize/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_2_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize/3)+1, floor($testfilesize*2/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_3_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize*2/3)+1, $testfilesize-1,  $tmp);
            //upload the merged file
            $output[]=$this->cloudStorageModel->uploadFile($acc, $testfilename, $testfilemime, $testfilesize, $img);
            //delete first file
            $this->cloudStorageModel->deleteStorageFile($base_id, $acc);
            fclose($tmp);
        }
        
        fclose($img);
        echo json_encode($output);
    }
    
    public function testcopyandreplace(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $output = array();
        $storage_accounts = $this->storageAccountModel->getStorageAccounts($user);
        
        $testfilename = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilename = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilename);
        $img = fopen(APPPATH . $testfilename, 'r');
        
        $output = array();
        $provider_info = $this->config->item('provider_info');
        foreach($provider_info as $provider_name=>$provider_data){
            if($provider_data['copy_and_replace_support']){
                $output_data = array();
                //get all accounts that are on this provider
                $filtered_accounts = array();
                foreach($storage_accounts as $acc){
                    if($acc['token_type']==$provider_name){
                        $filtered_accounts[]=$acc;
                    }
                }
                //upload a file to the first account
                $base_id = $this->cloudStorageModel->uploadFile($filtered_accounts[0], 'base_'.$testfilename, $testfilemime, $testfilesize, $img);
                //share the file with all the other accounts
                foreach($filtered_accounts as $target_account){
                    if($target_account['storage_account_id']!=$filtered_accounts[0]['storage_account_id']){
                        $this->cloudStorageModel->addPermissionForUserIdOnProvider($target_account['permission_id'], $base_id, $filtered_accounts[0], 'writer');
                    }
                }
                //make a copy of the file in each account
                $s =  microtime(true);
                foreach($filtered_accounts as $target_account){
                    $output_data[]=$this->cloudStorageModel->apiCopyFile($base_id, $target_account);
                }
                $t = microtime(true);
                $time_for_api_copy = $t-$s;
                $output_data['total_copy_time'] = $time_for_api_copy.' seconds.';
                //delete original uploaded file
                $this->cloudStorageModel->deleteStorageFile($base_id, $filtered_accounts[0]);
                $output[$provider_name] = $output_data;
            }
        }
        
        fclose($img);
        echo json_encode($output);
    }
    public function testDropboxGetQuotaInfo(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        
        $dropbox_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        foreach($dropbox_accounts as $k=>$acc){
            $dropbox_accounts[$k]['quota_info'] = $this->storageAccountModel->getQuotaInfo($acc);
        }
        
        header('Content-Type: application/json');
        echo json_encode($dropbox_accounts);
    }
    public function testdropboxdeletestoragefile(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $path = $this->input->post('path');
        $dropbox_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        
        foreach($dropbox_accounts as $k=>$acc){
            $this->cloudStorageModel->deleteStorageFile($path, $acc);
        }
        
        header('Content-Type: application/json');
        echo json_encode(array('status'=>'done'));
    }
}