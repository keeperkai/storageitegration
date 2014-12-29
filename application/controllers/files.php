<?php
class Files extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('settingmodel', 'settingModel');
        $this->load->model('cloudstoragemodel', 'cloudStorageModel');
        
    }
	//test section------------------------------------------------------------------------------
    /*
        this function purges all the virtual files/storage files/storage files on cloud storages
        Note that this doesn't purge the priority settings of the files and the accounts linked will still be intact.
    */
    public function purgeAccountFileData(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        
        //delete all the permission/virtual file/storage file data that is owned by this user
        $vfiles = $this->fileModel->getVirtualFileTreeForUser($user);
        foreach($vfiles as $virtual_file){
            $this->fileModel->deleteFileWithUserContext($virtual_file['virtual_file_id'], $user);
        }
        //delete all the storage files on the cloud storage accounts linked to this account
        $accounts = $this->storageAccountModel->getStorageAccounts($user);
        foreach($accounts as $acc){
            $this->cloudStorageModel->purge($acc);
        }
    }
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
    /*
        these parts are the same as googledrive
        permission >= writers can see who has access to the file
        others cannot see/change the permissions
        get's the shared info of a file, output format:
        array(
            'status'=>'success' or 'error', if the user has >= writer access to the file, they can see the file permissions, otherwise output is error
            'info'=>
            array(
                'owner'=>owner account,
                'writers'=>array(
                    account1,
                    account2,
                    ...
                )
                'readers'=>array(
                    reader account1,
                    reader account2,
                    ...
                )
            )
        
        )
    */
    public function getShareInfo(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $virtual_file_id = $this->input->post('virtual_file_id');
        $user = $this->session->userdata('ACCOUNT');
        $user_permit = $this->fileModel->getVirtualFilePermissionForUser($virtual_file_id, $user);
        $output = array();
        if($user_permit == false){
            $output['status'] = 'error';
        }else if($user_permit['role'] == 'reader'){
            $output['status'] = 'error';
        }else{
            //the user has at least writer permission to the file, so the user is allowed to see the permission info
            $output['status'] = 'success';
            $output['info'] = $this->fileModel->getPermissionsForVirtualFileStructured($virtual_file_id);
        }
        header('Content-Type: application/json');
        echo json_encode($output);
    }
    public function modifyShareInfo(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $output = array('status'=>'error');
        $user = $this->session->userdata('ACCOUNT');
        $virtual_file_id = $this->input->post('virtual_file_id');
        $share_change = json_decode($this->input->post('share_change'), true);
        //var_dump($virtual_file_id);
        //var_dump($share_change);
        //check if the user has at least writer access
        $permit = $this->fileModel->getVirtualFilePermissionForUser($virtual_file_id, $user);
        if($permit){
            $role = $permit['role'];
            if($this->fileModel->roleCompare($role, 'writer')>=0){
                //user has at least writer access, do what the user wants.
                $this->fileModel->modifyShareInfo($virtual_file_id, $share_change);
                $output['status'] = 'success';
            }
        }
        header('Content-Type: application/json');
        echo json_encode($output);
    }
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
		
        $schedule_data = $this->schedulerModel->schedule($user, $name, $size, $extension);//0.04 secs
        $upload_plan = $this->dispatcherModel->dispatch($schedule_data, $user);//this line
		
        
        header('Content-Type: application/json');
		echo json_encode($upload_plan);
        
		
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
        $fileData['mime_type'] = $this->input->post('mime_type');
		$fileData['name'] = $this->input->post('name');
		$fileData['extension'] = $this->input->post('extension');
		$fileData['parent_virtual_file_id'] = $this->input->post('parent_virtual_file_id');
		
        $allow_chunk = false;
        
		$storage_file_data = json_decode($this->input->post('storage_file_data'), true);
        if(sizeof($storage_file_data)>1)    $allow_chunk = true;//if the file is already chunked, it doesn't matter what extension it is
        else if($fileData['file_type']=='folder') $allow_chunk = false;
        else{
            $allow_chunk = $this->settingModel->chunkAllowedForExtension($user, $fileData['extension']);
        }
        
        $fileData['allow_chunk'] = $allow_chunk;
        
		/*
		structure for storage_file_data:
        array(
            array(
                storage_account_id
                storage_file_type
                byte_offset_start
                byte_offset_end
                storage_id
            )
        )
		*/
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
				//foreach storage file, register them and propagate the permissions to storage if needed
                foreach($storage_file_data as $sfile){
                    $sfile['virtual_file_id'] = $virtual_file_id;
                   $this->fileModel->registerStorageFileAndSetPermissionsOnStorage($sfile);
                }
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
        $loosefiles = array();
        foreach($files as $file){
            $loosefiles = array_merge($loosefiles, $this->fileModel->deleteFileWithUserContext($file['virtual_file_id'], $user));
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
        this method returns a link to the shared/not shared file on the cloud storage provider it resides on, this only works for files that are
        not allowed to be chunked(configured files).
        we will look at the user's permission to the file on our storage and decide what kind of link(edit or preview) the user will get
        
        output: array(
            status: 'success' or 'error' or 'need_account'
            errorMessage: a message describing why the user can't get a link to the file, such as "You don't have the permissions to access the file"
            type: 'edit' or 'preview'
            link: the url we return to the user agent
        )
    */
    public function getEditViewLink(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $virtual_file_id = $this->input->post('virtual_file_id');
        $result = $this->fileModel->getEditViewLink($user, $virtual_file_id);
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    public function getDownloadLink(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $virtual_file_id = $this->input->post('virtual_file_id');
        $result = $this->fileModel->getDownloadLink($virtual_file_id, $user);
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    public function downloadFromServer($virtual_file_id){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        //check if user has access
        if(!$this->fileModel->hasAccess($user, $virtual_file_id, 'reader')){
            //user doesn't have access to the file, reject call
            header('Content-Type: text/plain');
            echo '你沒有存取該檔案之權利';
            return;
        }
        $fh = tmpfile();
        $vfile = $this->fileModel->getVirtualFileData($virtual_file_id);
        $this->fileModel->getVirtualFileContent($virtual_file_id, $fh);//returns the virtual file meta-data, the file content is written to $fh
        //check if file is actually a folder, if so then reject
        if($vfile['file_type'] == 'folder'){
            header('Content-Type: text/plain');
            echo '無法下載資料夾這種檔案類型';
            return;
        }
        header("Content-Type: ".$vfile['mime_type']); 
        header("Content-Disposition: attachment; filename=\"".$vfile['name']."\"");
        //stream write the file contents
        $chunk_size = 1*1024*1024;//1mb
        //open the output stream
        $stdout = fopen('php://output', 'w');
        while(!feof($fh)){//does not work correctly
        //$file_size = fseek($fh, 0, SEEK_END);
        //rewind($fh);
        //while(ftell($fh)<$file_size){
            $data = fread($fh, $chunk_size);
            fwrite($stdout, $data);
        }
        fclose($fh);
        fclose($stdout);
    }
}