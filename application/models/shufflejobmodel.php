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
    public function registerShuffleJobCompleted($shuffle_job_id){
        $shuffle_job = $this->getShuffleJobData($shuffle_job_id);
        $move_jobs = $shuffle_job['move_job'];
        $not_finished = false;
        foreach($move_jobs as $movejob){
            if(!$this->checkMoveJobCompleted($movejob['move_job_id'])){
                $not_finished = true;
            }
        }
        if($not_finished) return false;
        else return true;
    }
	public function getChunkJobsWithMoveJobId($move_job_id){
		$q = $this->db->get_where('chunk_job',array('move_job_id'=>$move_job_id));
		$r = $q->result_array();
		return $r;
	}
	public function registerChunkJobCompleted($chunk_job_id, $uploaded_storage_id){
		//update database data
		$this->db->update('chunk_job', array('status'=>'complete','uploaded_storage_id'=>$uploaded_storage_id),array('chunk_job_id'=>$chunk_job_id));
	}
	/*
		this function checks if the movejob is completed, if so it will register the move job to be completed.
		we need to do this because some of the movejobs are chunk level assigned and we don't know if the client
		or the server will finish the jobs last, so both sides need to check when they finish the last chunkjob
	*/
	public function checkMoveJobCompleted($move_job_id){
        $q = $this->db->get_where('move_job', array('move_job_id'=>$move_job_id));
        $m = $q->result_array();
        if(sizeof($m)==0)   return false;
        if($m[0]['status'] == 'complete') return true;
		$q = $this->db->get_where('chunk_job', array('move_job_id'=>$move_job_id, 'status'=>'waiting'));
		$unfinished = $q->result_array();
		if(sizeof($unfinished)>0){
            return false;
		}else{
            $this->registerMoveJobCompleted($move_job_id);
			return true;
		}
	}
    /*
        registers that the move job is completed and updates the storage file data in our database accordingly.
        also deletes the old files stored on cloud storages.
    */
	public function registerMoveJobCompleted($move_job_id){
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
			$bend = $old_sfile['byte_offset_start']+$chunkjob['byte_offset_end'];
			//generate the new storage file data
			$storage_file_data = array(
				'storage_account_id'=>$chunkjob['target_account'],
				'virtual_file_id'=>$old_sfile['virtual_file_id'],
				'storage_file_type'=>$sfile_type,
				'byte_offset_start'=>$bstart,
				'byte_offset_end'=>$bend,
				'storage_file_size'=>$bend-$bstart+1,
				'storage_id'=>$chunkjob['uploaded_storage_id']
			);
            //$this->fileModel->registerStorageFileAndSetPermissionsOnStorage($storage_file_data);
            $this->fileModel->registerStoragefile($storage_file_data);
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
			$movejobs[$k]['source_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($movejob['source_account']);
			//set access token data for client
			$movejobs[$k]['source_account'] = $this->storageAccountModel->setAccessTokenDataForClient($movejobs[$k]['source_account']);
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
    /*
        takes the output of getShuffleJobData and executes it
    */
    public function executeShuffleJob($shuffle_job_data){
        //var_dump('executing         ');
        //echo 'executing shuffle job: '.$shuffle_job_data['shuffle_job_id'];
        $times = array(
            'googledrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'onedrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'dropbox'=>array(
                'upload'=>0,
                'download'=>0
            )
        );
        $api_call_times = array(
            'googledrive'=>array(),
            'onedrive'=>array(),
            'dropbox'=>array()
        );
        foreach($shuffle_job_data['move_job'] as $movejob){
            $performance_data = $this->executeMoveJob($movejob);
            //add the upload/download times
            $times = $this->addProviderTimes($times, $performance_data['times']);
            $api_call_times = $this->mergeApiCallTimes($api_call_times, $performance_data['api_copy_and_replace_times']);
        }
        //no need to check shuffle job completed here, since the client will do that for us.
        //return the performance info
        return array('times'=>$times, 'api_copy_and_replace_times'=>$api_call_times);
    }
    /*
        returns:
        array('times'=>$times, 'api_copy_and_replace_times'=>$api_call_times);
        just look at the first two variables
    */
    public function executeMoveJob($movejob){
        $times = array(
            'googledrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'onedrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'dropbox'=>array(
                'upload'=>0,
                'download'=>0
            )
        );
        $api_call_times = array(
            'googledrive'=>array(),
            'onedrive'=>array(),
            'dropbox'=>array()
            
        );
        //for each type of move job, do something different
        //'download_whole_file_and_distribute_on_server','download_whole_file_and_distribute_on_client','api_copy_and_replace','chunk_level_assign'
        if($movejob['job_type'] == 'download_whole_file_and_distribute_on_server'){
            $provider_times = $this->executeDownloadWholeMoveJob($movejob);
            $times = $this->addProviderTimes($times, $provider_times);
        }else if($movejob['job_type'] == 'api_copy_and_replace'){
            $s = microtime(true)*1000;
            $this->executeApiCopyAndReplace($movejob);
            $api_time = microtime(true)*1000 - $s;
            $api_call_times [$movejob['source_account']['token_type']][] = $api_time;
        }else if($movejob['job_type'] == 'chunk_level_assign'){
            $provider_times = $this->executeChunkLevelMoveJob($movejob);
            //var_dump($times);
            //var_dump($provider_times);
            $times = $this->addProviderTimes($times, $provider_times);
            //var_dump($times);
        }else{
            throw new Exception("job_type of move job is unrecognisable on server : "+$movejob['job_type']);
            return;
        }
        return array('times'=>$times, 'api_copy_and_replace_times'=>$api_call_times);
        
    }
    public function executeDownloadWholeMoveJob($movejob){
        $times = array(
            'googledrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'onedrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'dropbox'=>array(
                'upload'=>0,
                'download'=>0
            )
        );
        $copysize = 1*1024*1024;//1MB for copying files into chunks
        //stream download file and stream upload to chunk targets
        $s = microtime(true)*1000;
        $source_fp = tmpfile();
        $source_file = $movejob['source_file'];
        $this->cloudStorageModel->downloadFile($movejob['source_account'], $movejob['source_file']['storage_id'], $source_fp);
        $times[$movejob['source_account']['token_type']]['download'] += microtime(true)*1000 - $s;
        //upload each chunk
        foreach($movejob['chunk_job'] as $chunkjob){
            //setup upload chunk file
            $target_fp = tmpfile();
            //set the position of the source to the start offset and then copy until end offset
            $start_offset = $chunkjob['byte_offset_start'];
            $end_offset = $chunkjob['byte_offset_end'];
            $total_length = $end_offset - $start_offset + 1;
            $current_offset = $start_offset;
            fseek($source_fp, $start_offset);
            do{
                $len = $copysize;
                if($current_offset+$copysize>$end_offset) $len = $end_offset-$current_offset+1;
                $bytes = fread($source_fp, $len);
                fwrite($target_fp, $bytes);
                $current_offset += $len;
            }while($current_offset<=$end_offset);
            //upload the target file
            rewind($target_fp);
            $s2 = microtime(true)*1000;
            $storage_id = $this->cloudStorageModel->uploadFile($chunkjob['container_storage_id'], $chunkjob['target_account'], $source_file['name'], $source_file['mime_type'], $total_length, $target_fp);
            $times[$chunkjob['target_account']['token_type']]['upload'] += microtime(true)*1000 - $s2;
            //register chunk job completed and update the uploaded storage id
            $this->registerChunkJobCompleted($chunkjob['chunk_job_id'], $storage_id);
            //close the target file
            fclose($target_fp);
        }
        
        //no need to check if move job is completed because this is a whole move job executor
        //just register completed
        //new flow: don't register move job
        //$this->registerMoveJobCompleted($movejob['move_job_id']);
        
        //close source file
        fclose($source_fp);
        return $times;
    }
    public function executeChunkLevelMoveJob($movejob){
        $times = array(
            'googledrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'onedrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'dropbox'=>array(
                'upload'=>0,
                'download'=>0
            )
        );
        //for these kinds of jobs, the source account must support partial download.
        $source_account = $movejob['source_account'];
        $source_file = $movejob['source_file'];
        foreach($movejob['chunk_job'] as $chunkjob){
            if($chunkjob['executor'] == 'server'){
                $target_account = $chunkjob['target_account'];
                $source = '';
                //download the chunk of the file from the source file
                $start_offset = $chunkjob['byte_offset_start'];
                $end_offset = $chunkjob['byte_offset_end'];
                $size_of_chunk = $end_offset - $start_offset + 1;
                //echo 'downloading source    ';
                $dl_count = 0;
                while($dl_count<3){
                    $source=tmpfile();
                    $s = microtime(true)*1000;
                    $this->cloudStorageModel->downloadChunk($source_account, $source_file['storage_id'], $start_offset, $end_offset, $source);
                    $times[$movejob['source_account']['token_type']]['download'] += microtime(true)*1000 - $s;
                    //check if the downloaded chunk matches the size to see if download is success
                    fseek($source, 0, SEEK_END);
                    if(ftell($source)!=$size_of_chunk){
                        fclose($source);
                        $dl_count++;
                        continue;
                    }else{
                        break;
                    }
                }
                if($dl_count>=3){
                    var_dump($chunkjob);
                    throw new Exception('unable to download chunk from the above chunkjob');
                    exit(0);
                }
                rewind($source);
                //echo 'source downloaded, uploading file    ';
                //upload the file as a new storage file
                $s = microtime(true)*1000;
                $storage_id = $this->cloudStorageModel->uploadFile($chunkjob['$container_storage_id'], $target_account, $source_file['name'], $source_file['mime_type'], $size_of_chunk, $source);
                $times[$target_account['token_type']]['upload'] += microtime(true)*1000 - $s;
                //echo 'uploaded    ';
                //register chunk job completed
                $this->registerChunkJobCompleted($chunkjob['chunk_job_id'], $storage_id);
                //close fp
                fclose($source);
            }
        }
        //check move job completed
        //new flow don't check move job completed, wait till end
        //$this->checkMoveJobCompleted($movejob['move_job_id']);
        return $times;
    }
    public function executeApiCopyAndReplace($movejob){
        //echo 'executing api copy and replace';
        //$s = microtime(true);
        
        $source_account = $movejob['source_account'];
        $source_file = $movejob['source_file'];
        $target_account = $movejob['chunk_job'][0]['target_account'];
        $storage_id = $this->cloudStorageModel->apiCopyFile($movejob['chunk_job'][0]['container_storage_id'], $source_file['storage_id'], $source_account, $target_account);
        $this->registerChunkJobCompleted($movejob['chunk_job'][0]['chunk_job_id'], $storage_id);
        //new flow don't register move job
        //$this->registerMoveJobCompleted($movejob['move_job_id']);
        
        //$t = microtime(true);
        //echo 'time = '.($t-$s).PHP_EOL;
    }
    private function addProviderTimes($time1, $time2){
        $output = array(
            'googledrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'onedrive'=>array(
                'upload'=>0,
                'download'=>0
            ),
            'dropbox'=>array(
                'upload'=>0,
                'download'=>0
            )
        );
        foreach($time1 as $provider=>$times){
            $output[$provider]['upload'] = $time1[$provider]['upload'] + $time2[$provider]['upload'];
            $output[$provider]['download'] = $time1[$provider]['download'] + $time2[$provider]['download'];
        }
        return $output;
    }
    private function mergeApiCallTimes($api_time1, $api_time2){
        $output = array();
        $output['googledrive'] = array_merge($api_time1['googledrive'], $api_time2['googledrive']);
        $output['onedrive'] = array_merge($api_time1['onedrive'], $api_time2['onedrive']);
        $output['dropbox'] = array_merge($api_time1['dropbox'], $api_time2['dropbox']);
        return $output;
    }
}