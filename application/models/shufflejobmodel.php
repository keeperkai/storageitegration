<?php
class ShuffleJobModel extends CI_Model
{
    public function __construct()
    {
		$this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('filemodel', 'fileModel');
		$this->load->model('cloudstoragemodel', 'cloudStorageModel');
		parent::__construct();
	}
	public function getChunkJobsWithMoveJobId($move_job_id){
		$q = $this->db->get_where('chunk_job',array('move_job_id'=>$move_job_id));
		$r = $q->result_array();
		return $r;
	}
	public function registerChunkJobComplete($chunk_job_id, $uploaded_storage_id){
		//update database data
		$this->db->update('chunk_job', array('status'=>'complete','uploaded_storage_id'=>$uploaded_storage_id),array('chunk_job_id'=>$chunk_job_id));
	}
	/*
		this function checks if the movejob is completed, if so it will register the move job to be completed.
		we need to do this because some of the movejobs are chunk level assigned and we don't know if the client
		or the server will finish the jobs last, so both sides need to check when they finish the last chunkjob
	*/

	public function checkMoveJobComplete($move_job_id){
		$q = $this->db->get_where('chunk_job', array('move_job_id'=>$move_job_id, 'status'=>'waiting'));
		$unfinished = $q->result_array();
		if(sizeof($unfinished)>0){
			header('Content-Type: application/json');
			echo json_encode(array('completed'=>false));
		}else{
			$this->registerMoveJobComplete($move_job_id);
			header('Content-Type: application/json');
			echo json_encode(array('completed'=>true));
		}
	}
	public function registerMoveJobComplete($move_job_id){
		$this->db->update('move_job', array('status'=>'complete'), array('move_job_id'=>$move_job_id));
		$q = $this->db->get_where('move_job', array('move_job_id'=>$move_job_id));
		$r = $q->result_array();
		$movejob = $r[0];
		//create new storage_files in our system, propagate old shared permissions in the cloud storage if needed
		//delete old storage files in our system
		//delete old file from cloud storage
		
		//get the old storage file
		$old_sfile = $this->fileModel->getStorageFile($movejob['source_file']);
		//get the chunk job data
		$chunk_jobs = $this->getChunkJobsWithMoveJobId($move_job_id);
		//register each storage file data and set permissions if needed
		foreach($chunk_jobs as $chunkjob){
			//determine the storage file type
			$sfile_type = $old_sfile['storage_file_type'];
			if(($old_sfile['storage_file_type'] == 'file')&&(sizeof($chunk_jobs)>1)){
				//this means the file was originally whole, but now it is going to be split up
				$sfile_type = 'split_file';
			}
			//calculate the new byte offsets, note that the chunk job offsets are not for the whole virtual file
			//but counting from the original storage file
			$bstart = $old_sfile['byte_offset_start']+$chunkjob['byte_offset_start'];
			$bend = $old_sfile['byte_offset_end']+$chunkjob['byte_offset_end'];
			//generate the new storage file data
			$storage_file_data = array(
				'storage_account_id'=>$old_sfile['storage_account_id'],
				'virtual_file_id'=>$old_sfile['virtual_file_id'],
				'storage_file_type'=>$sfile_type,
				'byte_offset_start'=>$bstart,
				'byte_offset_end'=>$bend,
				'storage_file_size'=>$bend-$bstart+1,
				'storage_id'=>$chunkjob['uploaded_storage_id']
			);
			$this->fileModel->registerStorageFileAndSetPermissionsOnStorage($storage_file_data);
		}
		//delete old file from cloud storage and delete old storage file data in our system
		$this->fileModel->deleteStorageFile($old_sfile['storage_file_id']);
	}
	/*
		this function writes the shuffle job data from the dispatcher(after it has determined the executors and move
		job types) to the database.
		it returns the resulting shuffle job id in the database(the primary key)
		input format:
		array(//a set of move jobs
			array(
				'job_type'=>enum('download_whole_file_and_distribute_on_server','download_whole_file_and_distribute_on_server','api_copy_and_replace','chunk_level_assign'),
				'source_file'=>storage file data,
				'source_account'=>storage account data,
				'chunks'=>array(
					array('executor'=>'client','target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
					array('executor'=>'server','target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
					...
				)
			)
		)
	*/
	public function writeShuffleJobToDatabase($dispatched_jobs, $user)
	{
		$this->db->insert('shuffle_job', array('account'=>$user));
		$shuffle_id = $this->db->insert_id();
		foreach($dispatched_jobs as $k=>$movejob){
			//insert the movejob
			$this->db->insert('move_job', array(
					'job_type'=>$movejob['job_type'],
					'source_file'=>$movejob['source_file']['storage_file_id'],
					'source_account'=>$movejob['source_account']['storage_account_id'],
					'status'=>'waiting',
					'shuffle_job_id'=>$shuffle_id
				)
			);
			$move_id = $this->db->insert_id();
			//insert the child chunk jobs
			foreach($movejob['chunks'] as $s=>$chunkjob){
				$this->db->insert('chunk_job', array(
						'executor'=>$chunkjob['executor'],
						'target_account'=>$chunkjob['target_account']['storage_account_id'],
						'byte_offset_start'=>$chunkjob['byte_offset_start'],
						'byte_offset_end'=>$chunkjob['byte_offset_end'],
						'status'=>'waiting',
						'move_job_id'=>$move_id
					)
				);
			}
		}
		return $shuffle_id;
	}
	public function getShuffleJobData($shuffle_id)
	{
		$q = $this->db->get_where('shuffle_job', array('shuffle_job_id'=>$shuffle_id));
		$r = $q->result_array();
		if(sizeof($r)!=1) return false;
		$shufflejob = $r[0];
		$q2 = $this->db->get_where('move_job', array('shuffle_job_id'=>$shuffle_id));
		$movejobs = $q2->result_array();
		$total_shuffle_size = 0;
		$client_shuffle_size = 0;
		//replace the fields of storage file id and storage account id with the actual file/account data for client
		foreach($movejobs as $k=>$movejob){
			$movejobs[$k]['source_file'] = $this->fileModel->getStorageFile($movejobs[$k]['source_file']);
			//var_dump('get scheduling info for account: '+$movejob['source_account']);
			//$s = microtime(true);
			$movejobs[$k]['source_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($movejob['source_account']);
			//set access token data for client
			$movejobs[$k]['source_account'] = $this->storageAccountModel->setAccessTokenDataForClient($movejobs[$k]['source_account']);
			//$t = microtime(true);
			//var_dump($t-$s);
			$movejob_id = $movejob['move_job_id'];
			//organize chunks for each movejob
			$q3 = $this->db->get_where('chunk_job', array('move_job_id'=>$movejob_id));
			$chunkjobs = $q3->result_array();
			foreach($chunkjobs as $s=>$chunkjob){
				$chunkjobs[$s]['target_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($chunkjobs[$s]['target_account']);
				//set access token data for client
				$chunkjobs[$s]['target_account'] = $this->storageAccountModel->setAccessTokenDataForClient($chunkjobs[$s]['target_account']);
				$total_shuffle_size += $chunkjob['byte_offset_end']-$chunkjob['byte_offset_start']+1;
				if($chunkjob['executor'] == 'client'){
					$client_shuffle_size += $chunkjob['byte_offset_end']-$chunkjob['byte_offset_start']+1;
				}
			}
			//link chunks to movejob
			$movejobs[$k]['chunk_job'] = $chunkjobs;
		}
		//link movejobs to shuffle_job
		$shufflejob['move_job'] = $movejobs;
		$shufflejob['total_shuffle_size'] = $total_shuffle_size;
		$shufflejob['client_shuffle_size'] = $client_shuffle_size;
		$shufflejob['server_shuffle_size'] = $total_shuffle_size-$client_shuffle_size;
		return $shufflejob;
	}
}