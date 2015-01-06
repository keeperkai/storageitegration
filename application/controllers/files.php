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
        $this->load->model('googledrivemodel', 'googleDriveModel');
        $this->load->model('onedrivemodel', 'oneDriveModel');
        
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
    /*
        output:
        array(
            status:'error','success','need_account'
            errorMessage: ...
        )
    */
    public function createGoogleDoc(){
         if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $doc_type = $this->input->post('doc_type');
        $name = $this->input->post('name');
        $parent_virtual_file_id= $this->input->post('parent_virtual_file_id');
        $storage_id = '';
        $picked_account = '';
        //get an account that can fit the file, if no account exists, output error
        if($doc_type == 'document'){
            $filesize = filesize($this->config->item('document_file_path'));
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'googledrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->googleDriveModel->createDocument($picked_account, $name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的google drive帳號來容納此文件'
                ));
                return;
            }
        }else if($doc_type == 'spreadsheet'){
            $filesize = filesize($this->config->item('spreadsheet_file_path'));
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'googledrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->googleDriveModel->createSpreadSheet($picked_account, $name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的google drive帳號來容納此文件'
                ));
                return;
            }
        }else if($doc_type == 'presentation'){
            $filesize = filesize($this->config->item('spreadsheet_file_path'));
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'googledrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->googleDriveModel->createPresentation($picked_account, $name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的google drive帳號來容納此文件'
                ));
                return;
            }
        }
        //register the file to our system
        //note that google documents take 0 bytes of space(according to google), so the storage_file_size is 0 no matter the original file size.
        $storage_file_data = array(
            array(
                'storage_account_id'=>$picked_account['storage_account_id'],
                'storage_file_type'=>'file',
                'byte_offset_start'=>0,
                'byte_offset_end'=>0,
                'storage_file_size'=>0,
                'storage_id'=>$storage_id
            )
        );
        $mime_type = $this->config->item($doc_type.'_file_mime');
        $this->fileModel->registerFileToSystem($user, 'google_doc', $mime_type, $name, 'google_'.$doc_type, $parent_virtual_file_id, $storage_file_data, true);
        //respond to browser
        header('Content-Type: application/json');
        echo json_encode(array(
            'status'=>'success',
        ));
    }
    /*
        different from google docs, the onedrive doc is just .xlsx .docx .pptx files uploaded, no special treatment needed for downloading or
        the virtual file type...etc.
    */
    public function createOnedriveDoc(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $doc_type = $this->input->post('doc_type');
        $name = $this->input->post('name');
        
        $parent_virtual_file_id= $this->input->post('parent_virtual_file_id');
        $storage_id = '';
        $picked_account = '';
        $filesize = 0;
        $ext = '';
        $complete_file_name = '';
        //get an account that can fit the file, if no account exists, output error
        if($doc_type == 'document'){
            $filesize = filesize($this->config->item('document_file_path'));
            $ext = 'docx';
            $complete_file_name = $name.'.'.$ext;
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'onedrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->oneDriveModel->createDocument($picked_account, $complete_file_name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的onedrive帳號來容納此文件'
                ));
                return;
            }
        }else if($doc_type == 'spreadsheet'){
            $filesize = filesize($this->config->item('spreadsheet_file_path'));
            $ext = 'xlsx';
            $complete_file_name = $name.'.'.$ext;
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'onedrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->oneDriveModel->createSpreadSheet($picked_account, $complete_file_name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的onedrive帳號來容納此文件'
                ));
                return;
            }
        }else if($doc_type == 'presentation'){
            $filesize = filesize($this->config->item('spreadsheet_file_path'));
            $ext = 'pptx';
            $complete_file_name = $name.'.'.$ext;
            $accounts = $this->storageAccountModel->getStorageAccountsOfProviderAndQuotaLE($user, 'onedrive', $filesize);
            if(sizeof($accounts)>0){
                $picked_account = $accounts[0];
                $storage_id = $this->oneDriveModel->createPresentation($picked_account, $complete_file_name);
            }else{
                header('Content-Type: application/json');
                echo json_encode(array(
                    'status'=>'need_account',
                    'errorMessage'=>'您需要一個新的onedrive帳號來容納此文件'
                ));
                return;
            }
        }
        //register the file to our system
        $storage_file_data = array(
            array(
                'storage_account_id'=>$picked_account['storage_account_id'],
                'storage_file_type'=>'file',
                'byte_offset_start'=>0,
                'byte_offset_end'=>$filesize-1,
                'storage_file_size'=>$filesize,
                'storage_id'=>$storage_id
            )
        );
        $mime_type = $this->config->item($doc_type.'_file_mime');
        $this->fileModel->registerFileToSystem($user, 'file', $mime_type, $complete_file_name, $ext, $parent_virtual_file_id, $storage_file_data, true);
        //respond to browser
        header('Content-Type: application/json');
        echo json_encode(array(
            'status'=>'success',
        ));
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
    gets the file info of virtual_file_id from server
    server response format:
        {
            'status':'error' or 'success',
            'errorMessage':the error,
            'file_info': the file info structure written for createInfoDialog in integration.js
        }
    */
	public function getVirtualFileInfo(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $virtual_file_id = $this->input->post('virtual_file_id');
        //check if the file exists
        $vfile = $this->fileModel->getVirtualFileData($virtual_file_id);
        if(!$vfile){
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'=>'error',
                'errorMessage'=>'該檔案不存在!'
            ));
            return;
        }
        //check if the file_type is correct
        if($vfile['file_type'] == 'folder'){
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'=>'error',
                'errorMessage'=>'無法查看資料夾之詳細資訊!'
            ));
            return;
        }
        //check if user has access to the file
        if(!$this->fileModel->hasAccess($user, $virtual_file_id, 'reader')){
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'=>'error',
                'errorMessage'=>'您沒有足夠的權限查看該檔案之詳細資訊!'
            ));
            return;
        }
        //get the info
        $file_info = $this->fileModel->getVirtualFileInfo($virtual_file_id);
        header('Content-Type: application/json');
        echo json_encode(
            array(
                'status'=>'success',
                'file_info'=>$file_info
            )
        );
    }
    public function moveFileInSystem(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $user = $this->session->userdata('ACCOUNT');
        $files = $this->input->post('virtual_files');
        $parent_virtual_file_id = $this->input->post('parent_virtual_file_id');
        /*
        $this->checkDirAccess($user, $parent_file_id);
        //remove non-indie files
        $files = $this->fileModel->getIndependentFiles($files);
        //check if the target is a descendant of any of the files
        $this->checkTargetDirIsDescendantOfFiles($parent_file_id, $files);
        //check access for each file
        foreach($files as $file){
            $this->checkFileAccess($user, $file['virtual_file_id']);
        }
        $this->fileModel->setParentForFileArray($files,$parent_file_id);
        */
        //check if the user has at least writer access to the target directory, and if it is in fact a folder
        $parent_vfile = $this->fileModel->getVirtualFileData($parent_virtual_file_id);
        if(!$this->fileModel->hasAccess($user, $parent_virtual_file_id, 'writer')){
            header('Content-Type: application/json');
            echo json_encode(
                array(
                    'status'=>'error',
                    'errorMessage'=>'你沒有存取移動位置目標的權限，您至少要有編輯的權利才能移動至該資料夾'
                )
            );
            return;
        }
        if($parent_vfile != false){
            if($parent_vfile['file_type']!='folder'){
                header('Content-Type: application/json');
                echo json_encode(
                    array(
                        'status'=>'error',
                        'errorMessage'=>'你所移動的目標位置並不是資料夾'
                    )
                );
                return;
            }
        }else{//no virtual file, depending on if parent_virtual_file_id = -1 we reply
            if($parent_virtual_file_id != -1){
                header('Content-Type: application/json');
                echo json_encode(
                    array(
                        'status'=>'error',
                        'errorMessage'=>'你所移動的目標位置不存在'
                    )
                );
                return;
            }
        }
        //all illegal target folders excluded, now do checking on the source files and get independent files
        $files = $this->fileModel->getIndependentFiles($files);
        //check if the user has access to each file
        foreach($files as $vfile){
            if(!$this->fileModel->hasAccess($user, $vfile['virtual_file_id'], 'writer')){
                header('Content-Type: application/json');
                echo json_encode(
                    array(
                        'status'=>'error',
                        'errorMessage'=>'您沒有足夠的權限去移動這些檔案，請確認您至少有編輯權限'
                    )
                );
                return;
            }
        }
        //check if the target folder is an descendant of one of the source files
        if($this->fileModel->isDescendantOfFiles($parent_virtual_file_id, $files)){
            header('Content-Type: application/json');
            echo json_encode(
                array(
                    'status'=>'error',
                    'errorMessage'=>'不可將上層資料夾移動到下層資料夾中'
                )
            );
            return;
        }
        //all systems go!
        foreach($files as $vfile){
            $this->fileModel->moveVirtualFile($vfile['virtual_file_id'], $parent_virtual_file_id);
        }
        header('Content-Type: application/json');
        echo json_encode(
            array(
                'status'=>'success'
            )
        );
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
    
}