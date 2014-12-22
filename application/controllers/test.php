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
        $this->load->model('dropboxmodel', 'dropboxModel');
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
        
        $testfilepath = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilepath = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilepath);
        $img = fopen(APPPATH . $testfilepath, 'r');
        //$data = stream_get_contents($img);
        //upload the file first
        
        //upload to each account
        foreach($storage_accounts as $acc){
            //if($acc['token_type'] != 'googledrive') continue;
            $s = microtime(true);
            $id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilepath, $testfilemime, $testfilesize, $img);
            //var_dump($id);
            //$id = $this->googleDriveModel->uploadFileData($acc, $testfilepath, $testfilemime, $testfilesize, $data);
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
            $output[]=$this->cloudStorageModel->uploadFile($acc, $testfilepath, $testfilemime, $testfilesize, $tmph);
            //$output[]=$this->googleDriveModel->uploadFileData($acc, $testfilepath, $testfilemime, $testfilesize, $dl_data);
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
        
        $testfilepath = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilepath = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilepath);
        $img = fopen(APPPATH . $testfilepath, 'r');
        
        //upload a file to each account, download parts of the file and append them together, then upload back to the same account and see if the file works
        foreach($storage_accounts as $acc){
            //upload a file to the provider
            $base_id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilepath, $testfilemime, $testfilesize, $img);
            //download the file in three divisions
            $tmp = tmpfile();
            $div_1_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, 0, floor($testfilesize/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_2_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize/3)+1, floor($testfilesize*2/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_3_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize*2/3)+1, $testfilesize-1,  $tmp);
            //upload the merged file
            $output[]=$this->cloudStorageModel->uploadFile($acc, $testfilepath, $testfilemime, $testfilesize, $img);
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
        
        $testfilepath = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilepath = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilepath);
        $img = fopen(APPPATH . $testfilepath, 'r');
        
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
                if(sizeof($filtered_accounts)==0) continue;//continue if this account does not have at least one account of this provider
                //upload a file to the first account
                $base_id = $this->cloudStorageModel->uploadFile($filtered_accounts[0], 'base_'.$testfilepath, $testfilemime, $testfilesize, $img);
                //share the file with all the other accounts
                foreach($filtered_accounts as $target_account){
                    if($target_account['storage_account_id']!=$filtered_accounts[0]['storage_account_id']){
                        $this->cloudStorageModel->addPermissionForUserIdOnProvider($target_account['permission_id'], $base_id, $filtered_accounts[0], 'writer');
                    }
                }
                //make a copy of the file in each account
                $s =  microtime(true);
                foreach($filtered_accounts as $target_account){
                    $output_data[]=$this->cloudStorageModel->apiCopyFile($base_id, $filtered_accounts[0], $target_account);
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
    public function testdropboxmakedir(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $path = $this->input->post('path');
        $dropbox_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        
        $output = array();
        $ch = curl_init();
        foreach($dropbox_accounts as $k=>$acc){
            $output[]=$this->dropboxModel->makeDir($acc, $path, $ch);
        }
        
        header('Content-Type: application/json');
        echo json_encode($output);
    }
    public function testdropboxupload(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $dropbox_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        
        //$testfilepath = APPPATH .'testimage.png';
        $testfilename = 'powerline2.zip';
        $testmime = 'application/octet-stream';
        /*
        $testfilename = 'testimage.png';
        $testmime = 'image/png';
        */
        $testfilepath = APPPATH . $testfilename;
        $file = fopen($testfilepath, 'r');
        $filesize = filesize($testfilepath);
        $output = array();
        foreach($dropbox_accounts as $k=>$acc){
            $output [] = $this->dropboxModel->uploadFile($acc, $testfilepath, $testmime, $filesize, $file);
            //$output []= $this->dropboxModel->uploadSmallFile($acc, $testfilepath, 'image/png', $filesize, $file);
        }
        header('Content-Type: application/json');
        echo json_encode($output);
    }
    /*
        uploads a file on our server and then downloads it and then uploads it again to the storage provider
    */
    public function testdropboxuploadthendownloadthenupload(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $output = array();
        $storage_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        
        $testfilepath = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilepath = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilepath);
        $img = fopen(APPPATH . $testfilepath, 'r');
        //$data = stream_get_contents($img);
        //upload the file first
        
        //upload to each account
        foreach($storage_accounts as $acc){
            $id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilepath, $testfilemime, $testfilesize, $img);
            //download the file
            $tmph = tmpfile();
            $this->cloudStorageModel->downloadFile($acc, $id, $tmph);
            //fseek($tmph, 0, SEEK_END);
            //var_dump(ftell($tmph));
            //rewind($tmph);
            $output[]=$this->cloudStorageModel->uploadFile($acc, 'sec_upload_'.$testfilepath, $testfilemime, $testfilesize, $tmph);
            fclose($tmph);
            //delete the first uploaded file
            $this->cloudStorageModel->deleteStorageFile($id, $acc);
        }
        
        fclose($img);
        echo json_encode($output);
    }
    /*
        same as above, except it downloads in chunks and combines them
    */
    public function testdropboxuploadthendownloadchunked(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $output = array();
        $storage_accounts = $this->storageAccountModel->getStorageAccountsOfProvider($user, 'dropbox');
        
        $testfilepath = 'testimage.png';
        $testfilemime = 'image/png';
        
        /*
        $testfilepath = 'powerline2.zip';
        $testfilemime = 'application/octet-stream';
        */
        $testfilesize = filesize(APPPATH . $testfilepath);
        $img = fopen(APPPATH . $testfilepath, 'r');
        
        //upload a file to each account, download parts of the file and append them together, then upload back to the same account and see if the file works
        foreach($storage_accounts as $acc){
            //upload a file to the provider
            $base_id = $this->cloudStorageModel->uploadFile($acc, 'base_'.$testfilepath, $testfilemime, $testfilesize, $img);
            //download the file in three divisions
            $tmp = tmpfile();
            $div_1_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, 0, floor($testfilesize/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_2_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize/3)+1, floor($testfilesize*2/3),  $tmp);
            fseek($tmp, 0, SEEK_END);
            $div_3_id = $this->cloudStorageModel->downloadChunk($acc, $base_id, floor($testfilesize*2/3)+1, $testfilesize-1,  $tmp);
            //upload the merged file
            $output[]=$this->cloudStorageModel->uploadFile($acc, 'sec_upload_'.$testfilepath, $testfilemime, $testfilesize, $img);
            //delete first file
            $this->cloudStorageModel->deleteStorageFile($base_id, $acc);
            fclose($tmp);
        }
        
        fclose($img);
        echo json_encode($output);
    }
}