<?php
class Shuffle extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('shufflejobmodel', 'shuffleJobModel');
	}
	public function registerChunkJobCompleted(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
		$user = $this->session->userdata('ACCOUNT');
		$chunk_job_id = $this->input->post('chunk_job_id');
		$uploaded_storage_id = $this->input->post('uploaded_storage_id');
		$this->shuffleJobModel->registerChunkJobCompleted($chunk_job_id, $uploaded_storage_id);
		//check if the move job is completed
		
		//check if the shuffle job is completed
		
	}
    public function checkMoveJobCompleted(){
        if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        $move_job_id = $this->input->post('move_job_id');
        $result = $this->shuffleJobModel->checkMoveJobCompleted($move_job_id);
        if($result){
            header('Content-Type: application/json');
			echo json_encode(array('completed'=>true));
		}else{
            header('Content-Type: application/json');
			echo json_encode(array('completed'=>false));
		}
        
    }
	public function registerMoveJobCompleted(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
		$user = $this->session->userdata('ACCOUNT');
		$move_job_id = $this->input->post('move_job_id');
		$this->shuffleJobModel->registerMoveJobCompleted($move_job_id);
	}
	public function executeServerSideShuffle(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
        set_time_limit(0);
		$user = $this->session->userdata('ACCOUNT');
		$shuffle_job_id = $this->input->post('shuffle_job_id');
		//get the shuffle data
		$shufflejob = $this->shuffleJobModel->getShuffleJobData($shuffle_job_id);
		//check if this is the owner of the shuffle job
        if($shufflejob['account'] != $user){
            header('Content-Type: application/json');
            echo json_encode(array('status'=>'error', errorMessage=>'你沒有執行該工作的權利!'));
            return;
        }
        //execute the shuffle data for server side
		$performance_data = $this->shuffleJobModel->executeShuffleJob($shufflejob);
		//respond to the browser
        
        header('Content-Type: applicaton/json');
        echo json_encode(array('status'=>'success','performance_data'=>$performance_data));
	}
	
}
