<?php
class Files extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('settingmodel', 'settingModel');
    }
	//test section------------------------------------------------------------------------------
	public function downloadWholeFileFromChunks(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
		$formdata = json_decode($this->input->post('formdata'), true);
		//$chunkids = $this->input->post('chunk_ids');
		$chunkids = $formdata['chunk_ids'];
		$this->load->helper('googledrive');
		//$storageaccount= $this->input->post('storage_account_id');
		$storageaccount = $formdata['storage_account_id'];
		//echo var_dump($storageaccount).PHP_EOL;
		$q = $this->db->get_where('storage_account', array('storage_account_id'=>$storageaccount));
		$r = $q->result_array();
		$acc = $r[0];
		$refreshtoken = $acc['token'];
		$this->load->helper('googledrive');
		$this->load->helper('download');
		//get the data of the files
		require_once 'Google/Client.php';
		require_once 'Google/Service/Drive.php';
		//set_include_path('Google/'. PATH_SEPARATOR . get_include_path());
		$client = setupGoogleClient($refreshtoken);
		$drive = new Google_Service_Drive($client);
		$filedata = '';
		foreach($chunkids as $id){
			//get the download url
			$file = $drive->files->get($id);
			$dl = $file->getDownloadUrl();
			//set_include_path('Google/'. PATH_SEPARATOR . get_include_path());
			$request = new Google_Http_Request($dl, 'GET', null, null);
			$httpRequest = $client->getAuth()->authenticatedRequest($request);
			$filedata .= $httpRequest->getResponseBody();
		}
		force_download('testfilename.jpg', $filedata);
	}
	
	//end of test section-----------------------------------------------------------------------
    public function getUploadInstructions(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $mime = $this->input->post('mime');
		$size = $this->input->post('size');
		$extension = $this->input->post('extension');
		$name = $this->input->post('name');
		//this function will schedule and decide how the data will be moved to fit this file, and where the file should be uploaded to
		//if it is just an upload without shuffle, the user agent should just upload according to the instructions
		//if it involves shuffling, then the user agent should prompt and ask there is ? bytes that need to be shuffled for this file, are you willing to
		//wait? if so, notify the server to start running move jobs for the server,then execute the instructions for the client side, once the user agent
		//finishes a job, it notifies the server for file registration and delete of old storage data
		
        //ini_set('max_execution_time', 0);
		$this->load->model('schedulermodel','schedulerModel');
		$this->load->model('dispatchermodel','dispatcherModel');
		//$s = microtime(true);
		$schedule_data = $this->schedulerModel->schedule($user, $name, $size, $extension);//0.04 secs
		/*
		$t = microtime(true);
		var_dump($t-$s);
		$s = microtime(true);
		*/
		$upload_plan = $this->dispatcherModel->dispatch($schedule_data, $user);//3 secs
		/*
		$t = microtime(true);
		var_dump($t-$s);
		*/
		
		/*
		$response = array('status'=>'', 'errorMessage'=>'', 'client_jobs'=>array());//status:need_shuffle, upload, impossible, errorMessage: tell the user what kind of account to link
		if($upload_plan['status'] == 'upload' || $upload_plan['status'] == 'need_shuffle'){
			$client_jobs = $this->dispatcherModel->dispatch($upload_plan);
			$response['status'] = $upload_plan['status'];
			$response['client_jobs'] = $client_jobs;
			if($upload_plan['status'] == 'need_shuffle'){
				$response['shuffle_data_size'] = $upload_plan['shuffle_data_size'];
			}
		}else{
			$error = $upload_plan['errorMessage'];
			$response['status'] = 'impossible';
		}
		*/
		header('Content-Type: application/json');
		//echo json_encode(array('message'=>'schedule problem!'));
		echo json_encode($upload_plan);
		//echo json_encode($response);
		
    }
	
	public function getFileTreeForUser(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
		$user = $this->session->userdata('ACCOUNT');
        $tree = $this->fileModel->getVirtualFileTreeForUser($user);
		header('Content-Type: application/json');
		echo json_encode($tree);
	}
	public function registerFileToSystem(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
		$fileData = array();
		$fileData['account'] = $user;
		$fileData['file_type'] = $this->input->post('file_type');
		$fileData['name'] = $this->input->post('name');
		$fileData['extension'] = $this->input->post('extension');
		$fileData['parent_virtual_file_id'] = $this->input->post('parent_virtual_file_id');
		$fileData['allow_chunk'] = $this->settingModel->chunkAllowedForExtension($user, $filedata['extension']);
		$storage_file_data = json_decode($this->input->post('storage_file_data'), true);
		//var_dump($storage_file_data);
		$resp = array();
		$virtual_file_id = false;
		if($this->fileModel->registerFile($fileData)){
			$resp['status'] = 'success';
			$insertedfile = $this->fileModel->findLatestVirtualFileWithProperties($fileData);
			$virtual_file_id = $insertedfile['virtual_file_id'];
			//$storage_file_data['virtual_file_id'] = $virtual_file_id;
		
			//add permission for the owner
			$this->fileModel->addPermission($virtual_file_id, $user, 'owner');
			//inherit permissions from parent
			$this->fileModel->inheritParentPermission($virtual_file_id);
			if($fileData['file_type']!='folder'){
				$this->fileModel->registerStorageFileData($storage_file_data, $virtual_file_id);
			}
		}else{
			$resp['status'] = 'error';
		}
		
		
		header('Content-Type: application/json');
		echo json_encode($resp);
	}
    public function deleteFiles(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $files = $this->input->post('files');
        $files = $this->fileModel->getIndependentFiles($files);
        foreach($files as $file){
            $loosefiles = $this->fileModel->deleteFileWithUserContext($file['virtual_file_id'], $user);
        }
        $this->fileModel->attachLooseFilesToOwnerTree($loosefiles);
    }
    private function checkFileAccess($user, $file_id){
        if(!($this->fileModel->hasAccess($user, $file_id))){
            header('Content-Type: application/json');
            echo json_encode(array('status'=>'error', 'errorMessage'=> '你沒有存取該檔案之權利'));
            exit(0);
        }
    }
	//new system---------------------------------------------------------
    
    //will return a file tree
    public function getAllFiles(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $rootdir_file_id = -1;
        $filetree = $this->fileModel->getFileTree($user, $rootdir_file_id, true);
        header('Content-Type: application/json');
        echo json_encode($filetree);
    }
    public function getFileTree(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $rootdir_file_id = $this->input->post('dir_id');
        $recurse = $this->input->post('recurse');
        $filetree = $this->fileModel->getFileTree($user, $rootdir_file_id, $recurse);
        header('Content-Type: application/json');
        echo json_encode($filetree);
    }
	public function renderFileTree(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $filetree = $this->fileModel->getFileTree($user, $rootdir_file_id, $recurse);
        header('Content-Type: application/json');
        echo json_encode($filetree);
    }
	/*
    public function registerFileToSystem(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
		$fileData = array();
		$fileData['account'] = $user;
		$fileData['file_type'] = $this->input->post('file_type');
		$fileData['name'] = $this->input->post('name');
		$fileData['extension'] = $this->input->post('extension');
		$fileData['parent_virtual_file_id'] = $this->input->post('parent_virtual_file_id');
		
        $fileData = array();
        $fileData['storage_id'] = $this->input->post('storage_id');
        $fileData['type'] = $this->input->post('type');
        $fileData['account_data_id'] = $this->input->post('account_data_id');
        $fileData['name'] = $this->input->post('name');
        $fileData['ext'] = $this->input->post('ext');
        $fileData['mime'] = $this->input->post('mime');
        $fileData['access'] = $this->input->post('access');
        $fileData['parent_file_id'] = $this->input->post('parent_file_id');
        //check if the user owns the parent_file_id and the storage account
        $this->checkDirAccess($user, $fileData['parent_file_id']);
        $this->checkStorageAccountAccess($user, $fileData['account_data_id']);
        
        $this->fileModel->registerFile($fileData);
		
    }
	*/
    public function moveFileInSystem(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $files = $this->input->post('files');
        $parent_file_id = $this->input->post('parent_file_id');
        
        $this->checkDirAccess($user, $parent_file_id);
        //remove non-indie files
        $files = $this->fileModel->getIndependentFiles($files);
        //check if the target is a descendant of any of the files
        $this->checkTargetDirIsDescendantOfFiles($parent_file_id, $files);
        //check access for each file
        foreach($files as $file){
            $this->checkFileAccess($user, $file['file_id']);
        }
        $this->fileModel->setParentForFileArray($files,$parent_file_id);
    }
    private function checkTargetDirIsDescendantOfFiles($parent_id, $files){
        if($this->fileModel->isDescendantOfFiles($parent_id, $files)){
            header('Content-Type: application/json');
            echo json_encode(array('status'=>'error', 'errorMessage'=> '目標資料夾不可為移動檔案之下層檔案'));
            exit(0);
        }
    }
    private function checkStorageAccountAccess($user, $storage_account_data_id){
        if(!($this->storageAccountModel->hasAccess($user, $storage_account_data_id))){
            header('Content-Type: application/json');
            echo json_encode(array('status'=>'error', 'errorMessage'=> '你沒有該雲端硬碟帳號之存取權限'));
            exit(0);
        }
    }
    
    private function checkDirAccess($user, $dir_id){
        if(!($this->fileModel->hasAccessToDir($user, $dir_id))){
            header('Content-Type: application/json');
            echo json_encode(array('status'=>'error', 'errorMessage'=> '你沒有存取該資料夾之權利'));
            exit(0);
        }
    }
    
    
    /*
    public function testFileTree(){
        $user = 'keeperkai@msn.com';
        $rootdir_file_id = -1;
        $recurse = true;
        $filetree = $this->fileModel->getFileTree($user, $rootdir_file_id, $recurse);
        echo json_encode($filetree);
    }
    */
}