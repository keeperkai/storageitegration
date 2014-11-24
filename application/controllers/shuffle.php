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
	public function registerMoveJobCompleted(){
		if (!$this->session->userdata('ACCOUNT')) {
            header('Location: '.base_url().'index.php/pages/view/login');
            return;
        }
		$user = $this->session->userdata('ACCOUNT');
		$move_job_id = $this->input->post('move_job_id');
		$this->shuffleJobModel->registerMoveJobComplete($move_job_id);
	}
	public function runServerSideShuffle(){
		
	}
	
}
