<?php
class DispatcherModel extends CI_Model
{
    public function __construct()
    {
		$this->load->model('settingmodel', 'settingModel');
		$this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('filemodel', 'fileModel');
		$this->load->model('shufflejobmodel', 'shuffleJobModel');
		parent::__construct();
	}
	private function chunkShuffleDataToFitSingleFileApiLimit($shuffle_data){
		$output = array();
		foreach($shuffle_data as $k=>$movejob){
			$new_movejob = array();
			$new_movejob['source_file'] = $movejob['source_file'];
			$new_movejob['source_account'] = $movejob['source_account'];
			$new_movejob['chunks'] = array();
			foreach($movejob['chunks'] as $s=>$chunkjob){
				$job = $chunkjob;
				$all_start = $job['byte_offset_start'];
				$all_end = $job['byte_offset_end'];
				$chunksize = $all_end-$all_start+1;
				$single_file_limit = $job['target_account']['api_single_file_limit'];
				if($chunksize>$single_file_limit){
					$current_start = $all_start;
					while($current_start<=$all_end){
						$current_end = min($current_start+$single_file_limit-1,$all_end);
						//insert the job in between
						$new_movejob['chunks'][]= array('target_account'=>$job['target_account'], 'byte_offset_start'=>$current_start, 'byte_offset_end'=>$current_end);
						$current_start = $current_end+1;
					}
				}else{//just push the element to output
					$new_movejob['chunks'][]= $job;
				}
			}
			$output[]=$new_movejob;
		}
		return $output;
	}
	private function organizeShuffleStorageFileData($shuffle_data){
		foreach($shuffle_data as $k=>$movejob){
			$shuffle_data[$k]['source_file'] = $this->fileModel->getStorageFile($movejob['source_file']);
		}
		return $shuffle_data;
	}
	private function organizeShuffleStorageAccountData($shuffle_data){
		foreach($shuffle_data as $k=>$movejob){
			$shuffle_data[$k]['source_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($movejob['source_account']);
			foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
				$shuffle_data[$k]['chunks'][$s]['target_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($chunkjob['target_account']);
			}
		}
		return $shuffle_data;
	}
	private function organizeShuffleData($shuffle_data){
		//this function replaces the source_file, target_account, source_account id's with real data
		$shuffle_data = $this->organizeShuffleStorageAccountData($shuffle_data);
		$shuffle_data = $this->organizeShuffleStorageFileData($shuffle_data);
		$shuffle_data = $this->chunkShuffleDataToFitSingleFileApiLimit($shuffle_data);
		return $shuffle_data;
	}
	private function determineJobExecuters($shuffle_data){
		/*
		this function will determine the job executers(server or client), the unit for assigning is a chunk job
		that means if we wanted to move out a file test.mp4 that is 3gb large, and the scheduler chooses to move
		1.5gb to a google drive account A, and 1.5gb to one drive account B(which is further chunked up into 15 chunks),
		the assignment for moving each chunk can differ between chunks.
		
		At this stage we simply assign the chunks to either client or server without modifying the chunks, but in the future
		we can further chunk the file which enables jobs to be executed by the client that previously couldn't be assigned to
		the client due to memory issues, the dispatcher can do load balancing and decide whether or not to further chunk a larger
		job depending on the jobs/the server load/the client context(i.e is the user using a mobile phone? a pc?).
		
		
		the shuffling data will be transformed from:
		array(
			'source_file'=>storage file data,
			'source_account'=>storage account data,
			'chunks'=>array(
				array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				...
			)
		)
		
		To:
		
		array(
			'job_type'=>enum('download_whole_file_and_distribute_on_server','api_copy_and_replace','chunk_level_assign'),
			'source_file'=>storage file data,
			'source_account'=>storage account data,
			'chunks'=>array(
				array('executor'=>'client','target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				array('executor'=>'server','target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				...
			)
		)
		//for all job types, this is the format, for some types some data is pretty redundant, but this gives
		//a more unified data structure for the jobs.
		Note:
		the javascript(client) side executors will try to download/upload(for shuffle) by looking up the chunk size and
		deciding to chunk download->chunk upload loop, or do a 1 shot download->upload... when the provider doesn't support
		it, just simply throw an alert stating so.
		In other words, the scheduler/dispatcher needs to make sure that no job executors will bite more than they can eat.
		*/
		$provider_info = $this->config->item('provider_info');
		$client_memory_limit = $this->config->item('client_memory_limit');
		foreach($shuffle_data as $k=>$movejob){
			//check if the move only has one whole chunk(meaning moving the storage file as a whole)
			//and is moved to another account of the same provider and
			//said provider is capable of copy and replace
			$source_account_provider_info = $provider_info[$movejob['source_account']['token_type']];
			if(
				(sizeof($movejob['chunks'])==1)&&
				($movejob['chunks'][0]['target_account']['token_type']==$movejob['source_account']['token_type'])&&
				($source_account_provider_info['copy_and_replace_support'])
			){
				$shuffle_data[$k]['job_type'] = 'api_copy_and_replace';
				$shuffle_data[$k]['chunks'][0]['executor'] = 'server';
				continue;
			}
			//check if the source account supports cors download at all
			if($source_account_provider_info['cors_download_support']){
			
				//check if the source account can support resumable download(http range header) through cors
				if($source_account_provider_info['cors_download_range_header_support']){
					//we can possibly move some of the chunk jobs to the client
					//however, if we find out that we can't do so for any of the chunk jobs
					//we should just use download_whole_file_and_distribute_on_server to save request overhead
					//currently if the target_account doesn't support resumable upload, then I just do it on the server
					//but I think we should look at the chunk size and see if it exceeds the user agent's memory size
					//if not then we can just upload it without resumable upload support.
					foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
						$start_offset = $chunkjob['byte_offset_start'];
						$end_offset = $chunkjob['byte_offset_end'];
						$chunk_size = $end_offset-$start_offset+1;
						$target_account_provider_info = $provider_info[$chunkjob['target_account']['token_type']];
						if(($chunk_size<$client_memory_limit)||($target_account_provider_info['cors_resumable_upload_support'])){
							//just do it in the client, one shot download/upload shuffling
							//or we can do chunk by chunk download/upload shuffling
							$shuffle_data[$k]['chunks'][$s]['executor'] = 'client';
						}else{//no resumable upload support, and the chunk is too large, so we can only choose to do this on the server
							$shuffle_data[$k]['chunks'][$s]['executor'] = 'server';
						}
					}
					$client_job_exists = false;
					foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
						if($chunkjob['executor'] == 'client'){
							$client_job_exists = true;
							break;
						}
					}
					if($client_job_exists){//at least one client chunk job exists, output a chunk_level_assign move job
						$shuffle_data[$k]['job_type'] = 'chunk_level_assign';
					}else{//no client chunk job exists, all done on server
						$shuffle_data[$k]['job_type'] = 'download_whole_file_and_distribute_on_server';
					}
				}else{//no range header support for downloading, depending on the source file size
					//we: 1. if the source file size is larger than the client memory limit we can only choose to do this on the server
					//in this case we do a download_whole_file_and_distribute_on_server job
					//2. if the source file size is smaller than the client memory limit we can let the client do this, we do a
					//download_whole_file_and_distribute_on_client
					if($client_memory_limit<$shuffle_data[$k]['source_file']['storage_file_size']){
					//issue download_whole_file_and_distribute_on_server
						$shuffle_data[$k]['job_type'] = 'download_whole_file_and_distribute_on_server';
						foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
							$shuffle_data[$k]['chunks'][$s]['executor'] = 'server';
						}
					}else{//possible to download whole file and upload chunks via the client
						$shuffle_data[$k]['job_type'] = 'download_whole_file_and_distribute_on_client';
						foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
							$shuffle_data[$k]['chunks'][$s]['executor'] = 'client';
						}
					}
					
				}
			}else{//source account doesn't support cors download(whole download) into javascript for transferring on client side
			//unable to download the source file into javascript on the client side at all, so we have no choice.
			//just do download_whole_file_and_distribute_on_server
				$shuffle_data[$k]['job_type'] = 'download_whole_file_and_distribute_on_server';
				foreach($shuffle_data[$k]['chunks'] as $s=>$chunkjob){
					$shuffle_data[$k]['chunks'][$s]['executor'] = 'server';
				}
			}
		}
		return $shuffle_data;
	}
	private function dispatchShuffle($shuffle_data, $user){
		//first prepare the data for the accounts, and further chunk data if needed
		//also prepare the data for the whole files, we need their file size to know if we can move them in the client
		$shuffle_data = $this->organizeShuffleData($shuffle_data);//chunk wrong
		//second determine who will execute the job
		//$s = microtime(true);
		$dispatched_jobs = $this->determineJobExecuters($shuffle_data);
		//$t = microtime(true);
		//var_dump($t-$s);
		//6 secs at first, very little afterwards(sql optimization?)
		//three write the jobs to the database
		$shuffle_id = $this->shuffleJobModel->writeShuffleJobToDatabase($dispatched_jobs, $user);
		//0.003xx secs
		//$s = microtime(true);
		//four filter the client jobs, and then return the client jobs in the format that the client needs.
		$shuffle_job = $this->shuffleJobModel->getShuffleJobData($shuffle_id);
		//$t = microtime(true);
		//var_dump($t-$s);
		
		//6.xx secs
		
		return $shuffle_job;
	}
	private function organizeUploadStorageAccountData($upload_data){
		foreach($upload_data as $k=>$job){
			$upload_data[$k]['target_account'] = $this->storageAccountModel->getClientInfoForStorageAccountId($job['target_account']);
			//need to update the access_token for the client
			$upload_data[$k]['target_account'] = $this->storageAccountModel->setAccessTokenDataForClient($upload_data[$k]['target_account']);
		}
		return $upload_data;
	}
	private function chunkUploadDataToFitSingleFileApiLimit($upload_data){
		$output = array();
		foreach($upload_data as $k=>$job){
			$all_start = $job['byte_offset_start'];
			$all_end = $job['byte_offset_end'];
			$chunksize = $all_end-$all_start+1;
			$single_file_limit = $job['target_account']['api_single_file_limit'];
			if($chunksize>$single_file_limit){
				$current_start = $all_start;
				while($current_start<=$all_end){
					$current_end = min($current_start+$single_file_limit-1,$all_end);
					//insert the job in between
					$output[]= array('target_account'=>$job['target_account'],'type'=>'chunked', 'byte_offset_start'=>$current_start, 'byte_offset_end'=>$current_end);
					$current_start = $current_end+1;
				}
			}else{//just push the element to output
				$output[]= $job;
			}
		}
		return $output;
	}
	private function dispatchUpload($upload_data){
		if(sizeof($upload_data) == 0) return false;
		$output = array();
		//fill all account data, since the accounts in the scheduling phase will only contain the storage_account_id
		$output = $this->organizeUploadStorageAccountData($upload_data);
		if($upload_data[0]['type']=='chunked'){
			$output = $this->chunkUploadDataToFitSingleFileApiLimit($output);
		}
		return $output;
	}
	public function dispatch($schedule_data, $user){
		/*
		output:
		array(
			status: impossible, need_shuffle, upload
			schedule_data: array(
				shuffle:array(
					'shuffle_job_id'=> ...,
					'timestamp'=>...,
					'total_shuffle_size'=>...,
					'client_shuffle_size'=>...,
					'server_shuffle_size'=>...,
					'move_job'=>array(
						'move_job_id'=>...,
						'job_type'=>enum(download_whole_file_and_distribute_on_server, download_whole_file_and_distribute_on_client, api_copy_and_replace, chunk_level_assign)
						'source_file'=>source storage file data /w virtual file data,
						'source_account'=>source storage account data /w client side usable access_token,
						'shuffle_job_id'=>the parent shuffle job id
						'status'=>enum('waiting','complete')
						'chunk_job'=>array(
							'chunk_job_id'=>...,
							'executor'=>enum(server,client),
							'target_account'=>target account data with client side usable access_token,
							'byte_offset_start'=>...,
							'byte_offset_end'=>...,
							'status'=>enum('waiting','complete'),
							'move_job_id'=>the parent move job id
						)
					)
				)
				upload:array(
					array('type'=>'whole','target_account'=>);//whole
					array('type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end)
				)
			)
		)
		This function will determine which jobs will be executed on the client/server, and save shuffling jobs to the database,
		it will return the whole shuffle job tree including the server's jobs
		
		After the user confirms they want to shuffle, the browser calls an service that will query the database for the server-side jobs
		note that the client jobs are also written to the database, and will be updated after the client finishes it.
		note that upload jobs are not written to the database
		*/
		$output = array('status'=>$schedule_data['status']);
		if($schedule_data['status']=='impossible'){
			$output['errorMessage'] = $schedule_data['errorMessage'];
			return $output;//just output the needed error info
		}
		$output['schedule_data'] =  array();
		$upload_data = $schedule_data['schedule_data']['upload'];
		$upload_part = $this->dispatchUpload($upload_data);
		$shuffle_part = array();
		if($schedule_data['status']=='need_shuffle'){
			$shuffle_data = $schedule_data['schedule_data']['shuffle'];
			$shuffle_part = $this->dispatchShuffle($shuffle_data, $user);
			$output['schedule_data']['shuffle']=$shuffle_part;
			$output['schedule_data']['upload']=$upload_part;
		}else{//status == upload
			$output['schedule_data']['upload']=$upload_part;
		}
		return $output;
	}
}