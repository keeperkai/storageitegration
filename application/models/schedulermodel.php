<?php
class SchedulerModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
		$this->load->model('settingmodel', 'settingModel');
		$this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('filemodel', 'fileModel');
	}
	private function providerPriorityLHAndMinFreeQuotaApiLimitLH($a,$b){
		$confA = $a['config_priority'];
		$confB = $b['config_priority'];
		if($confA>$confB){
			return 1;
		}else if($confA<$confB){
			return -1;
		}else{//equal
			$A = $a['min_free_quota_single_file_limit'];
			$B = $b['min_free_quota_single_file_limit'];
			if($A == $B){
				return 0;
			}
			return ($A > $B)? 1:-1;
		}
		
	}
	private function providerPriorityLHAndFreeQuotaLH($a,$b){
		$confA = $a['config_priority'];
		$confB = $b['config_priority'];
		if($confA>$confB){
			return 1;
		}else if($confA<$confB){
			return -1;
		}else{//equal
			$A = $a['quota_info']['free'];
			$B = $b['quota_info']['free'];
			if($A == $B){
				return 0;
			}
			return ($A > $B)? 1:-1;
		}
		
	}
	private function providerPrioritySortLowToHigh($a,$b){
		$A = $a['config_priority'];
		$B = $b['config_priority'];
		if($A == $B){
			return 0;
		}
		return ($A > $B)? 1:-1;
	}
	private function minfreeQuotaApiLimitCompareHighToLow($a, $b){
		$A = $a['min_free_quota_single_file_limit'];
		$B = $b['min_free_quota_single_file_limit'];
		if($A == $B){
			return 0;
		}
		return ($A > $B)? -1:1;
	}
	private function freeQuotaCompareHighToLow($a, $b){
		$A = $a['quota_info']['free'];
		$B = $b['quota_info']['free'];
		if($A == $B){
			return 0;
		}
		return ($A > $B)? -1:1;
	}
	private function storageFileSizeHL($a,$b){
		$A = $a['storage_file_size'];
		$B = $b['storage_file_size'];
		if($A == $B){
			return 0;
		}
		return ($A > $B)? -1:1;
	}
	private function storageFileSizeLH($a,$b){
		$A = $a['storage_file_size'];
		$B = $b['storage_file_size'];
		if($A == $B){
			return 0;
		}
		return ($A > $B)? 1:-1;
	}
	private function bestFitShufflingAlgorithmBase($suitable_accounts, $source_account, $source_files, $s_target_quota, $zero_cost, $pickBestFitAccountAlgo, $account_sorting){// last two, variable function strings
		/*
		this is called when the source account is chosen
		this function will try to give a shuffle plan that:
        (i)shuffles whole files( in other words, 'chunks' will all be size of 1)
		(ii)if meeting the quota is possible, it will output the plan that has the smallest(as we found with our algo, not optimal) difference between the target quota
		and the total size that have been moved out.
		(iii)if meeting the quota is not possible, it will output a plan that is as close to the target quota as possible(again, not optimal)
        Note that there are two variants of this function, one using the account free quota and another using the min_free_quota_single_file_limit, just by adjusting the
        sort and picking algorithms in this function we can get these two variants, hence the $pickBestFitAccountAlgo and $account_sorting arguments.
		
		idea: maybe we can use another function like this, but use 0-1 knapsack and greedy, starting from low free quota target accounts to high free quota accounts
		but we would need to deal with memory problems, like if there is 300mb of free quota on the target account and 300 source files, we would need a table of
		size 300*2^20*300 = 90000m = 90G, not considering the size of the ints/data structs ..., one way is to find a most suitable amount of bytes like maybe 1kb
		as the base unit of distribution, so for the above the table size can be shrinked to 90M, which is more feasible(but still not okay for one user)
		*/
		/*
		output format: array:
			'schedule_data'=>array(...)//same as in schedule.
			'cost'=>total size in bytes, including the target quota
			'moved_size'=>usually==cost, but for same provider copy and replace, this will be different
			'moved_source_files'=>the new list of source files left after this operation
			'modified_account_state'=>an array of accounts that might have been modified(in quota)
			'quota_met'=> true or false, depending on the target quota being met or not
		*/
		//sort data
		usort($suitable_accounts, $account_sorting);
		usort($source_files, array($this,"storageFileSizeHL"));
		//--
		$target_quota = $s_target_quota;
		$moved_files = array();
		$moved_size = 0;
		$schedule_data = array();
		$cost = 0;
		$min_cost_schedule_data = array();
		$min_cost = PHP_INT_MAX;
		$min_cost_account_states = array();
		$min_cost_moved_files = array();
		for($i=1;$i<sizeof($source_files);$i++){
			$sfile = $source_files[$i];
			$lastfile = $source_files[$i-1];
			if($sfile['storage_file_size']>$target_quota){
				continue;
			}
			if($lastfile['storage_file_size']<=$target_quota){//the last file
				//try to move the last file
				$picked_acc_idx = $pickBestFitAccountAlgo($suitable_accounts, $lastfile['storage_file_size']);
				
				if($picked_acc_idx >= 0){
					if($target_quota==$lastfile['storage_file_size']){//quota is met, this is an exact fit, just update the min_cost datas and break out
						$min_cost = $s_target_quota;
						$min_cost_schedule_data = $schedule_data;
						$min_cost_schedule_data[] = array(
							//'type'=>'whole',
							'source_file'=>$lastfile['storage_file_id'],
							'source_account'=>$source_account['storage_account_id'], 
							'chunks'=>array(
                                array(
                                    'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id'],
                                    'byte_offset_start'=>0,
                                    'byte_offset_end'=>$lastfile['storage_file_size']-1
                                )
                            )
						);
						//update min account and moved file states
						$min_cost_account_states = $suitable_accounts;
						$min_cost_account_states[$picked_acc_idx]['quota_info']['free'] -= $lastfile['storage_file_size'];
						$min_cost_account_states[$picked_acc_idx] = $this->updateStorageAccountMinFreeQuotaApiSingleFileLimit($min_cost_account_states[$picked_acc_idx]);
						$min_cost_moved_files = $moved_files;
						$min_cost_moved_files[]= $lastfile;
						break;
					}else{//quota isn't met yet, update the datas and keep going.
						//update all data, including sorting the suitable accounts
						$target_quota -= $lastfile['storage_file_size'];
						$schedule_data[] = array(
							//'type'=>'whole',
							'source_file'=>$lastfile['storage_file_id'],
							'source_account'=>$source_account['storage_account_id'], 
							//'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id']
							'chunks'=>array(
                                array(
                                    'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id'],
                                    'byte_offset_start'=>0,
                                    'byte_offset_end'=>$lastfile['storage_file_size']-1
                                )
							)
						);
						$moved_files[]=$lastfile;
						$cost += $lastfile['storage_file_size'];
						//sort the suitable accounts again, we can optimize this later by using binary search to insert the account we just updated
						$suitable_accounts[$picked_acc_idx]['quota_info']['free'] -= $lastfile['storage_file_size'];
						$suitable_accounts[$picked_acc_idx] = $this->updateStorageAccountMinFreeQuotaApiSingleFileLimit($suitable_accounts[$picked_acc_idx]);
						usort($suitable_accounts, $account_sorting);
					}
					
				}//else, can't fit the file, move on
				//whether succeed or not, we need to move to the next file, except if we had an exact match
				continue;
			}
			//found a file that is smaller than the target quota
			if(($sfile['storage_file_size']<$target_quota)&&($lastfile['storage_file_size']>=$target_quota)){
				//test if it is possible to move the last file
				$picked_acc_idx = $pickBestFitAccountAlgo($suitable_accounts, $lastfile['storage_file_size']);
				//if yes then test if picking the last file will yield a smaller cost than the previous yields
				if($picked_acc_idx >= 0){
					$expectedcost = $cost+$lastfile['storage_file_size'];
					if($expectedcost<$min_cost){
						//update the min datas
						$min_cost = $expectedcost;
						$min_cost_schedule_data = $schedule_data;
						$min_cost_schedule_data[] = array(
							//'type'=>'whole',
							'source_file'=>$lastfile['storage_file_id'],
							'source_account'=>$source_account['storage_account_id'], 
							//'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id']
							'chunks'=>array(
								array(
                                    'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id'],
                                    'byte_offset_start'=>0,
                                    'byte_offset_end'=>$lastfile['storage_file_size']-1
                                )
							)
						);
						//update min account and moved file states
						$min_cost_account_states = $suitable_accounts;
						$min_cost_account_states[$picked_acc_idx]['quota_info']['free'] -= $lastfile['storage_file_size'];
						$min_cost_account_states[$picked_acc_idx] = $this->updateStorageAccountMinFreeQuotaApiSingleFileLimit($min_cost_account_states[$picked_acc_idx]);
						$min_cost_moved_files = $moved_files;
						$min_cost_moved_files[]= $lastfile;
						//if the quota is exactly met, just break.
						if($target_quota==$lastfile['storage_file_size']) break;
						//move on to the next file, it will automatically be picked if possible to move in the above code
					}
				}
				//no account was picked because this file cannot be fit in any accounts
				//move on to next file that is smaller
			}
			
		}
		if(sizeof($source_files)==1){
			$sfile = $source_files[0];
			$picked_acc_idx = $pickBestFitAccountAlgo($suitable_accounts, $sfile['storage_file_size']);
			if($picked_acc_idx >= 0){
				if($target_quota<=$sfile['storage_file_size']){
					$expectedcost = $cost+$sfile['storage_file_size'];
					//update the min datas
					$min_cost = $expectedcost;
					$min_cost_schedule_data = $schedule_data;
					$min_cost_schedule_data[] = array(
						//'type'=>'whole',
						'source_file'=>$sfile['storage_file_id'],
						'source_account'=>$source_account['storage_account_id'], 
						//'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id']
						'chunks'=>array(
                            array(
                                'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id'],
                                'byte_offset_start'=>0,
                                'byte_offset_end'=>$sfile['storage_file_size']-1
                            )
						)
					);
					//update min account and moved file states
					$min_cost_account_states = $suitable_accounts;
					$min_cost_account_states[$picked_acc_idx]['quota_info']['free'] -= $sfile['storage_file_size'];
					$min_cost_moved_files = $moved_files;
					$min_cost_moved_files[]= $sfile;
				
				}else{
					//simply pick the file and update states, if the file can be fit into one of the accounts
					$target_quota -= $sfile['storage_file_size'];
					$schedule_data[] = array(
						//'type'=>'whole',
						'source_file'=>$sfile['storage_file_id'],
						'source_account'=>$source_account['storage_account_id'], 
						//'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id']
						'chunks'=>array(
                            array(
                                'target_account'=>$suitable_accounts[$picked_acc_idx]['storage_account_id'],
                                'byte_offset_start'=>0,
                                'byte_offset_end'=>$sfile['storage_file_size']-1
                            )
						)
					);
					$moved_files[]=$sfile;
					$cost += $sfile['storage_file_size'];
					//sort the suitable accounts again, we can optimize this later by using binary search to insert the account we just updated
					$suitable_accounts[$picked_acc_idx]['quota_info']['free'] -= $sfile['storage_file_size'];
					$suitable_accounts[$picked_acc_idx] = $this->updateStorageAccountMinFreeQuotaApiSingleFileLimit($suitable_accounts[$picked_acc_idx]);
					usort($suitable_accounts, $account_sorting);
				}
			}
		}
		//generate output
		$moved_size = $cost;
		$cost = $zero_cost ? 0 : $cost;
		if($min_cost != PHP_INT_MAX){
			return array(
				'schedule_data'=>$min_cost_schedule_data,
				'cost'=>$min_cost,
				'moved_size'=>$moved_size,
				'moved_source_files'=>$min_cost_moved_files,
				'modified_account_state'=>$suitable_accounts,
				'quota_met'=> true
			);
		}else{//didn't find a solution to meet the quota, output the closest we could get
			return array(
				'schedule_data'=>$schedule_data,
				'cost'=>$cost,
				'moved_size'=>$moved_size,
				'moved_source_files'=>$moved_files,
				'modified_account_state'=>$suitable_accounts,
				'quota_met'=> false
			);
		}
		
	}
	private function pickBestFitAccountWithMinFreeQuotaApiLimit($suitable_accounts, $file_size){
		//suitable_accounts: H->L min_free_quota_single_file_limit
        $picked = -1;
        for($j=0;$j<sizeof($suitable_accounts);$j++){
            $acc = $suitable_accounts[$j];
            if($acc['min_free_quota_single_file_limit']>=$file_size){
                $picked = $j;
            }else{
                break;
            }
            
        }
        return $picked;
        /*
		$picked_acc_idx = -1;
		if(sizeof($suitable_accounts)>=1){
			for($j=0;$j<sizeof($suitable_accounts)-1;$j++){
				$acc = $suitable_accounts[$j];
				$nextacc = $suitable_accounts[$j+1];
				if($acc['min_free_quota_single_file_limit']<$file_size) break;
				if($acc['min_free_quota_single_file_limit']>=$file_size && $nextacc['min_free_quota_single_file_limit']<$file_size){
					$picked_acc_idx = $j;
				}
			}
			//test if the last account is suitable
			if($suitable_accounts[sizeof($suitable_accounts)-1]['min_free_quota_single_file_limit']>=$lastfile['storage_file_size']){
				$picked_acc_idx = sizeof($suitable_accounts)-1;
			}
		}else{//only 1 suitable account
			if($suitable_accounts[0]['min_free_quota_single_file_limit']>= $lastfile['storage_file_size']){
				$picked_acc_idx = 0;
			}
		}
		return $picked_acc_idx;
        */
	}
	private function bestFitShuffleWithApiLimit($suitable_accounts, $source_account, $source_files, $target_quota){
		//same as below, except this time we need to consider the api limits for whole files.
		return $this->bestFitShufflingAlgorithmBase($suitable_accounts, $source_account, $source_files, $target_quota, false, array($this,"pickBestFitAccountWithMinFreeQuotaApiLimit"),array($this,"minfreeQuotaApiLimitCompareHighToLow"));
	}
	private function pickBestFitAccountWithFreeQuota($suitable_accounts, $file_size){
		//suitable_accounts: H->L free quota order
        $picked = -1;
        for($j=0;$j<sizeof($suitable_accounts);$j++){
            $acc = $suitable_accounts[$j];
            if($acc['quota_info']['free']>=$file_size){
                $picked = $j;
            }else{
                break;
            }
        }
        return $picked;
        /*
		$picked_acc_idx = -1;
		if(sizeof($suitable_accounts)>1){
			for($j=0;$j<sizeof($suitable_accounts)-1;$j++){
				$acc = $suitable_accounts[$j];
				$nextacc = $suitable_accounts[$j+1];
				if($acc['quota_info']['free']<$file_size) break;
				if($acc['quota_info']['free']>=$file_size && $nextacc['quota_info']['free']<$file_size){
					$picked_acc_idx = $j;
				}
			}
            //if no account has been picked, see if the last account is suitable
            if($picked_acc_idx == -1){
                if($suitable_accounts[sizeof($suitable_accounts)-1]['quota_info']['free']>=$file_size){
                    $picked_acc_idx = sizeof($suitable_accounts)-1;
                }
            }
			
			
		}else if(sizeof($suitable_accounts)==1){//only 1 suitable account
			if($suitable_accounts[0]['quota_info']['free']>= $file_size){
				$picked_acc_idx = 0;
			}
		}else{//no suitable accounts
		
		}
        return $picked_acc_idx;
        */
	}
	private function updateStorageAccountMinFreeQuotaApiSingleFileLimit($storage_account){
		$storage_account['min_free_quota_single_file_limit'] = min($storage_account['api_single_file_limit'], $storage_account['quota_info']['free']);
		return $storage_account;
	}
	private function bestFitShuffle($suitable_accounts, $source_account, $source_files, $s_target_quota, $zero_cost){
		return $this->bestFitShufflingAlgorithmBase($suitable_accounts, $source_account, $source_files, $s_target_quota, $zero_cost, array($this, "pickBestFitAccountWithFreeQuota"), array($this, "freeQuotaCompareHighToLow"));
		/*
		this is called when the source account is chosen
		this function will try to give a shuffle plan that:
		(i)if meeting the quota is possible, it will output the plan that has the smallest(as we found with our algo, not optimal) difference between the target quota
		and the total size that have been moved out.
		(ii)if meeting the quota is not possible, it will output a plan that is as close to the target quota as possible(again, not optimal)
		
		idea: maybe we can use another function like this, but use 0-1 knapsack and greedy, starting from low free quota target accounts to high free quota accounts
		but we would need to deal with memory problems, like if there is 300mb of free quota on the target account and 300 source files, we would need a table of
		size 300*2^20*300 = 90000m = 90G, not considering the size of the ints/data structs ..., one way is to find a most suitable amount of bytes like maybe 1kb
		as the base unit of distribution, so for the above the table size can be shrinked to 90M, which is more feasible(but still not okay for one user)
		*/
		/*
		output format: 
        array(
            array(
                'schedule_data'=>array(...)//same as in schedule.
                'cost'=>total size in bytes, including the target quota
                'moved_size'=>usually==cost, but for same provider copy and replace, this will be different
                'moved_source_files'=>the new list of source files left after this operation
                'modified_account_state'=>an array of accounts that might have been modified(in quota)
                'quota_met'=> true or false, depending on the target quota being met or not
            )
        )
		*/
	}
    /*
        this function allocates a single storage file as chunks to the $suitable_accounts, note that this variable is passed by reference, meaning it will change the state
        of the accounts
    */
	private function allocateStorageFileAsChunks($sfile, &$suitable_accounts, $source_account){
        $schedule_data = array();
		if($sfile['storage_file_size']<=$suitable_accounts[0]['quota_info']['free']){
			//enough quota, move the whole storage file there
			$schedule_data[]=array(
				//'type'=>'whole',
				'source_file'=> $sfile['storage_file_id'],
				'source_account'=> $source_account['storage_account_id'],
				//'target_account'=> $suitable_accounts[0]['storage_account_id'],
				'chunks'=>array(
                    array(
                        'target_account'=>$suitable_accounts[0]['storage_account_id'],
                        'byte_offset_start'=>0,
                        'byte_offset_end'=>$sfile['storage_file_size']-1
                    )
				)
			);
			//update suitable account data
			$suitable_accounts[0]['quota_info']['free'] -= $sfile['storage_file_size'];
			//move suitable_account[0] to the right position
			usort($suitable_accounts, array($this,"freeQuotaCompareHighToLow"));
			return $schedule_data;
		}else{//not enough quota, we need to chunk it up!!!
			$new_acc_states = array();
			$start_offset = 0;
			$target_offset = $sfile['storage_file_size']-1;
			$chunk_data = array();
			foreach($suitable_accounts as $k=>$acc){
				$rest_size = $target_offset-$start_offset+1;
				$end_offset = 0;
				if($rest_size>$acc['quota_info']['free']){//not last chunk, the account's free quota will be exhausted after this
					$end_offset = $start_offset+$acc['quota_info']['free']-1;
					$chunk_data[]=array(
						'target_account'=>$acc['storage_account_id'],
						'byte_offset_start'=>$start_offset,
						'byte_offset_end'=>$end_offset
					);
					//record updated account state
					$suitable_accounts[$k]['quota_info']['free'] = 0;
					$new_acc_states []= $suitable_accounts[$k];
				}else{//last chunk, it could exhaust the free quota if the remaining chunk size is the same as the free quota, but that's not always the case.
					$end_offset = $target_offset;
					$chunk_data[]=array(
						'target_account'=>$acc['storage_account_id'],
						'byte_offset_start'=>$start_offset,
						'byte_offset_end'=>$end_offset
					);
					//record updated account state
					$suitable_accounts[$k]['quota_info']['free'] -= $end_offset - $start_offset + 1;
					$new_acc_states []= $suitable_accounts[$k];
					break;
				}
				$start_offset = $end_offset +1;
			}
			$suitable_accounts = $this->updateAccountState($suitable_accounts, $new_acc_states);
			usort($suitable_accounts, array($this,"freeQuotaCompareHighToLow"));
			//insert schedule data
			$schedule_data []= array(
				//'type'=>'chunked',
				'source_file'=> $sfile['storage_file_id'],
				'source_account'=> $source_account['storage_account_id'],
				'chunks'=>$chunk_data
			);
			return $schedule_data;
		}
	}
	private function chunkedShuffle($suitable_accounts, $source_account, $source_files, $s_target_quota){
		/*
		output:
		an array with the following keys:
		'schedule_data':
		array(
			'type'=>'chunked',
			'source_file'=>storage_file_id,
			'source_account'=>storage_account_id,
			'chunks'=>array(
				array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
				...
			)//note that the byte offsets are relative offsets for the storage file itself, not the same value as the database,
			//the database's byte_offset_start, byte_offset_end are the offsets relative to the original(or virutual) file
		)
		'cost'=>total size in bytes, including the target quota
		'moved_size'=>usually==cost, but for same provider copy and replace, this will be different
		'moved_source_files'=>the new list of source files left after this operation
		'modified_account_state'=>an array of accounts that might have been modified(in quota)
		'quota_met'=> true or false, depending on the target quota being met or not
		*/
		//we only need to pick the victim files and find a set that has the lowest additional cost(the part that is bigger than the target quota)
		//we can do this because, these files can be chunked, we can cut them into pieces so as long as the suitable_accounts have enough space
		//we can find a way to fit the victims into them
		//and then we allocate the picked victims to the accounts
        $moved_files = array();
		$schedule_data = array();
		$cost = 0;
		$quota_met = false;
		$target_quota = $s_target_quota;
		//sort data
		usort($source_files, array($this,"storageFileSizeHL"));
		usort($suitable_accounts, array($this,"freeQuotaCompareHighToLow"));
		//note that further chunking will be done in the dispatch phase, because there is an api single file size limit
		
		//first see how much quota is left in the other accounts
		$other_account_total_quota = 0;
		foreach($suitable_accounts as $acc){
			$other_account_total_quota += $acc['quota_info']['free'];
		}
		/*
		$practical_quota = 0;
		if($target_quota<=$other_account_total_quota){
			$practical_quota = $target_quota;
		}else{
			$practical_quota = $other_account_total_quota;
		}
		*/
		//try to pick victims and minimize cost
		$victims = array();
		$min_cost = 0;
		if(sizeof($source_files)>1){
			$local_min_cost = PHP_INT_MAX;
			$local_min_cost_victims = array();
			$current_victims = array();
			$current_cost = 0;
			
			for($i=1;$i<sizeof($source_files);$i++){
				$lastfile = $source_files[$i-1];
				$sfile = $source_files[$i];
				if($lastfile['storage_file_size']<$target_quota){
					//pick the last file
					$current_victims[]=$lastfile;
					$current_cost+=$lastfile['storage_file_size'];
					$target_quota -= $lastfile['storage_file_size'];
				}else if($lastfile['storage_file_size']==$target_quota){//exact fit found, break out and move to the data assigning phase
					$target_quota = 0;
					$local_min_cost += $lastfile['storage_file_size'];
					$local_min_cost_victims = $current_victims;
					$local_min_cost_victims []= $lastfile;
					break;
				}else if(($lastfile['storage_file_size']>$target_quota)&&($sfile['storage_file_size']<$target_quota)){
					//see if last file has lower cost
					$new_cost = $current_cost + $lastfile['storage_file_size'];
					if($new_cost<$local_min_cost){//register new min cost
						$local_min_cost = $new_cost;
						$local_min_cost_victims = $current_victims;
						$local_min_cost_victims[]= $lastfile;
					}
				}
			}
			//for the current set, the quota must not be met yet, add the last file in source_files and see if this yields a lower cost
			$lastfile = $source_files[sizeof($source_files)-1];
            
			if($target_quota>$lastfile['storage_file_size']){//quota can't be met with the current set even with this file
			//see if there is an answer already, if not, then output this one
				if($local_min_cost==PHP_INT_MAX){//no solution yet
					$min_cost = $current_cost + $lastfile['storage_file_size'];
					$victims = $current_victims;
					$victims[]= $lastfile;
				}else{//already a solution exists, output that solution
					$min_cost = $local_min_cost;
					$victims = $local_min_cost_victims;
				}
			}else{//$target_quota<= last file size, this will yield a solution, see if it has lowest cost, if so update min datas
				$new_cost = $current_cost + $lastfile['storage_file_size'];
				if($new_cost<$local_min_cost){//register new min cost
					$local_min_cost = $new_cost;
					$local_min_cost_victims = $current_victims;
					$local_min_cost_victims[]= $lastfile;
				}
				//at this point we will at least have 1 min_cost solution, so we need to output it
				$min_cost = $local_min_cost;
				$victims = $local_min_cost_victims;
			}
		}else if(sizeof($source_files)==1){
			//pick the file as victim
			$min_cost = $source_files[0]['storage_file_size'];
			$victims[]= $source_files[0];
		}else{// no source files, just output no cost and quota not met and 0 bytes moved
			return array(
				'schedule_data'=>array(),
				'cost'=> 0,
				'moved_size'=> 0,
				'moved_source_files'=> array(),
				'modified_account_state'=> array(),
				'quota_met'=> false
			);
		}
		//test if the mininum cost is smaller or equal to the free quotas left in the other accounts
		//if so then move the victim files
		//if not then try to move as much data out as possible
		if($min_cost<=$other_account_total_quota){
			//THIS IS NOT DONE YET, WE NEED TO UPDATE THE ACCOUNT STATES FIRST!!!!!!!!!!!
			/*
			foreach($suitable_accounts as $acc){
				$free_quota = $acc['quota_info']['free'];
				foreach($victims as $sfile){
			
				}
			}
			*/
			foreach($victims as $sfile){
				$schedule_data = array_merge($schedule_data, $this->allocateStorageFileAsChunks($sfile, $suitable_accounts, $source_account));
			}
			//output
			return array(
				'schedule_data'=>$schedule_data,
				'cost'=> $min_cost,
				'moved_size'=> $min_cost,
				'moved_source_files'=> $victims,
				'modified_account_state'=> $suitable_accounts,
				'quota_met'=> ($min_cost>=$s_target_quota)
			);
		}else{//the victims are useless, re-schedule to knapsack files to $other_account_total_quota
			$victims = array();
			$free_quota_left = $other_account_total_quota;
			$cost = 0;
			foreach($source_files as $sfile){
				if($sfile['storage_file_size']<=$free_quota_left){
					$victims[]=$sfile;
					$cost += $sfile['storage_file_size'];
					$free_quota_left -= $sfile['storage_file_size'];
				}
			}
			$schedule_data = array();
			foreach($victims as $sfile){
				$schedule_data = array_merge($schedule_data, $this->allocateStorageFileAsChunks($sfile, $suitable_accounts, $source_account));
			}
			
			return array(
				'schedule_data'=>$schedule_data,
				'cost'=> $cost,
				'moved_size'=> $cost,
				'moved_source_files'=> $victims,
				'modified_account_state'=> $suitable_accounts,
				'quota_met'=> ($cost>=$s_target_quota)
			);
		}
	}
	private function updateStorageFileState($original, $removed){//removes the removed accounts from original and returns it
		$map = array();
		foreach($removed as $removed_file){
			$storage_file_id = $removed_file['storage_file_id'];
			$map[$storage_file_id] = $removed_file;
		}
		foreach($original as $key=>$sfile){
			$storage_file_id = $sfile['storage_file_id'];
			if(array_key_exists($storage_file_id, $map)){
				$original[$key] = $map[$storage_file_id];;
			}
		}
		return $original;
	}
	private function updateAccountState($original, $updated){//function updates the min_free_quota_single_file_limit and free quotas of the storage account array
		//and returns it
		$map = array();
		foreach($updated as $updated_acc){
			$storage_account_id = $updated_acc['storage_account_id'];
			$map[$storage_account_id] = $updated_acc;
		}
		for($i=0;$i<sizeof($original);$i++){
			$storage_account_id = $original[$i]['storage_account_id'];
			if(array_key_exists($storage_account_id, $map)){
				$original[$i] = $map[$storage_account_id];
				$original[$i]['min_free_quota_single_file_limit'] = min($original[$i]['min_free_quota_single_file_limit'], $original[$i]['quota_info']['free']);
			}
		}
		return $original;
	}
	private function updateSimulationStates($shuffle_result, &$schedule_data, &$cost, &$target_quota, &$other_accounts, &$movable_storage_files){
		$schedule_data = array_merge($schedule_data, $shuffle_result['schedule_data']);
        $cost += $shuffle_result['cost'];
		$target_quota -= $shuffle_result['moved_size'];
		$other_accounts = $this->updateAccountState($other_accounts, $shuffle_result['modified_account_state']);
		$movable_storage_files = $this->updateStorageFileState($movable_storage_files, $shuffle_result['moved_source_files']);
	}
	private function smallCostShuffle($other_accounts, $target_account, $size){
		//in this function we will try to shuffle and simulated, take note that the updated data of the accounts will only change the free quota data
		//the storage files will be inconsistent after these operations
		//output vars
        $cost = 0;
		$schedule_data = array();
		//other_accounts: in H->L for free quota
		$movable_storage_files = $target_account['storage_files'];
		//in L->H for extension priority
		//check if there is enough space in total for movable files, if not return false
		$total_movable_bytes = 0;
		foreach($movable_storage_files as $sfile){
			$total_movable_bytes += $sfile['storage_file_size'];
		}
        //echo 'total movable bytes for this account: ';
        //var_dump($total_movable_bytes);
        if($total_movable_bytes+$target_account['quota_info']['free']<$size){
			return false;
		}
		//initialize data
		$target_quota = $size - $target_account['quota_info']['free'];
		$p_info = $this->config->item('provider_info');
		//try to do copy-and-delete on provider side, create scheduling data
		$copy_and_replace_support = $p_info[$target_account['token_type']]['copy_and_replace_support'];
		if($copy_and_replace_support){
            //echo 'supports copy and replace, victim account:';
            //var_dump($target_account);
            //first, copy the accounts that are same type with the target account
			$same_provider_accounts = array();
            foreach($other_accounts as $acc){
				if($acc['token_type'] == $target_account['token_type']){
					$same_provider_accounts[]=$acc;
                    //echo 'other account picked: ';
                    //var_dump($acc);
				}
			}
			$result = $this->bestFitShuffle($same_provider_accounts, $target_account, $movable_storage_files, $target_quota, true);
            //echo 'api copy result: ';
            //var_dump($result);
			$this->updateSimulationStates($result, $schedule_data, $cost, $target_quota, $other_accounts, $movable_storage_files);
			//if completed, output data
			if($result['quota_met']){
				return array('cost'=>0, 'schedule_data'=>$schedule_data);//no cost
			}
            
            //echo 'copy and replace result:  ';
            //var_dump($result);
		}
		//cross provider whole file moving, we should consider moving only the whole storage files first because we cannot
		//chunk them(well we can but we'd prefer not to, this will make our server need to interfere when the user wants to download this file)
		//start from the largest files and find the file that is the smallest that can get enough free quota
		//usort($other_accounts, "minfreeQuotaApiLimitCompareHighToLow");
		
		//target files that are not chunked yet
		$not_chunked = array();
		foreach($movable_storage_files as $sfile){
			if($sfile['storage_file_type'] != 'split_file'){
				$not_chunked[] = $sfile;
			}
		}
		$result = $this->bestFitShuffleWithApiLimit($other_accounts, $target_account, $not_chunked, $target_quota);
		$this->updateSimulationStates($result, $schedule_data, $cost, $target_quota, $other_accounts, $movable_storage_files);
		//if completed output data
		if($result['quota_met']){
			return array('cost'=>$cost, 'schedule_data'=>$schedule_data);
		}
		//try to move out chunked data using chunk
		//only try to move files that are chunked already
		$chunked_files = array();
		foreach($movable_storage_files as $sfile){
			if($sfile['storage_file_type'] == 'split_file'){
				$chunked_files[] = $sfile;
			}
		}
		$result = $this->chunkedShuffle($other_accounts, $target_account, $chunked_files, $target_quota);
        $this->updateSimulationStates($result, $schedule_data, $cost, $target_quota, $other_accounts, $movable_storage_files);
		
		//if completed, output data
		if($result['quota_met']){
			return array('cost'=>$cost, 'schedule_data'=>$schedule_data);
		}
		
		//if still not completed, start chunking any file
		$result = $this->chunkedShuffle($other_accounts, $target_account, $movable_storage_files, $target_quota);
        
        //echo 'chunked shuffle any file result: ';
        //var_dump($result);
        
        $this->updateSimulationStates($result, $schedule_data, $cost, $target_quota, $other_accounts, $movable_storage_files);
		//if completed, output data, if not return false
		if($result['quota_met']){
			return array('cost'=>$cost, 'schedule_data'=>$schedule_data);
		}else return false;
	}
	
	private function scheduleShuffle($suitable_accounts, $all_user_accounts, $size, $user){
		$output = array();
		//sort suitable_accounts and make them in free quota order
		usort($suitable_accounts, array($this,"freeQuotaCompareHighToLow"));
		/*
		expected output format:
		array(
			schedule_data=>array(...),
			target_account=> the account to upload to after the shuffling is done
		)
		it will only output the schedules for the smallest bandwidth cost that we found(not optimal of course, since this is a packing problem, it is NP-Hard)
		note that if no account can be shuffled to fit the needs it will output false;
		*/
		//first gather the storage file data for each account
		foreach($suitable_accounts as $key=>$acc){
			$storage_files = $this->fileModel->getAllMovableStorageFilesFromStorageAccount($user, $acc['storage_account_id']);
			//sort the files with size, from high -> low, since we will try to move the larger files first
			usort($storage_files, array($this,"storageFileSizeHL"));
			$suitable_accounts[$key]['storage_files'] = $storage_files;
		}
		$min_cost = PHP_INT_MAX;
		$min_schedule = false;
		$min_target_account = false;
		for($i=0;$i<sizeof($suitable_accounts);$i++){
			$other_accounts = $all_user_accounts;
			foreach($other_accounts as $k=>$v){
				if($v['storage_account_id']==$suitable_accounts[$i]['storage_account_id']){
					array_splice($other_accounts, $k, 1);
					break;
				}
			}
			$result = $this->smallCostShuffle($other_accounts, $suitable_accounts[$i], $size);
            if($result){
				if($min_cost>$result['cost']){
					$min_cost = $result['cost'];
					$min_schedule = $result['schedule_data'];
					$min_target_account = $suitable_accounts[$i];
				}
			}
		}
		if($min_target_account){
			$output['schedule_data'] = $min_schedule;
			$output['target_account'] = $min_target_account;
			return $output;
		}else{
			return false;
		}
	}
	public function schedule($user, $name, $size, $extension){
		//output format
		/*
		array(
		'status':need_shuffle, upload, impossible
		'errorMessage':tell user what to do and why it can't be uploaded
		'schedule_data':array(//the scheduled data, sourcefile(byte-offset)=>account array
			'upload': array(
				array('type'=>'whole','target_account'=>);//whole
				array('type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end)
			)
			'shuffle':
            array(
                array(
                    'source_file'=>storage_file_id,
                    'source_account'=>storage_account_id,
                    'chunks'=>array(
                        array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
                        array('target_account'=>storage_account_id, byte_offset_start, byte_offset_end),
                        ...
                    )
                )
            )
		)
		)
		*/
		$output = array();
		
        
        
        //get all the accounts and their info of the current user
        
		$accounts = $this->storageAccountModel->getStorageAccountWithSchedulingInfo($user);
        //$accounts = $this->storageAccountModel->getStorageAccountWithTestingSchedulingInfo($user);
        //$accounts = $this->storageAccountModel->getStorageAccountWithTestingSchedulingInfoForceSplit($user, $size);
        //$accounts = $this->storageAccountModel->getStorageAccountWithTestingSchedulingInfoForceShuffle($user, $size, 0.8);//sets all free quota to 0.8 of uploading file
		//$accounts = $this->storageAccountModel->getStorageAccountForceMultiChunked($user, $size);//forces api copy and chunk level assign with both client/server
		
        
        
        //get the setting for this extension
		$setting = $this->settingModel->searchSetting($extension, $user);
		
		if($setting){//need to save this file as whole, we can remove the accounts that have an api single file size that is smaller than this file
			$providers = $setting['provider'];//providers in priority order
			$score_map = array();
			foreach($providers as $p){
				$score_map[$p['provider']] = $p['priority'];
			}
			//loop through all accounts and score them using this priority
			$accounts_in_priority_order = $accounts;
			$all_user_accounts_in_free_quota_order = $accounts;
			usort($all_user_accounts_in_free_quota_order, array($this, "freeQuotaCompareHighToLow"));
			//filter all accounts with providers that have api size limits that are smaller
			$filtered = array();
			foreach($accounts_in_priority_order as $key=>$acc){
				if($acc['api_single_file_limit']>=$size){
					$filtered[]=$acc;
				}
			}
            $accounts_in_priority_order = $filtered;
			$filtered = array();
			foreach($accounts_in_priority_order as $key=>$acc){
				$acc_provider = $acc['token_type'];
				//note: 0 == false
				if(array_key_exists($acc_provider, $score_map)){
					$acc['config_priority'] = $score_map[$acc_provider];
					$filtered [] = $acc;
				}
			}
            $accounts_in_priority_order = $filtered;
            if(sizeof($accounts_in_priority_order) == 0){
				//no accounts suitable
				$output['status'] = 'impossible';
				$output['errorMessage'] = '你所設定的雲端硬碟類型的單一檔案上傳大小限制比此檔案小，所以無法上傳，請設定 '.$extension.'使用其他種的雲端硬碟';
				return $output;
			}
			usort($accounts_in_priority_order, array($this, "providerPriorityLHAndFreeQuotaLH"));
			$whole_acc = false;
			foreach($accounts_in_priority_order as $acc){
				if($acc['min_free_quota_single_file_limit']>=$size){
					$whole_acc = $acc;
					break;
				}
			}
			if($whole_acc){//able to do whole file upload without shuffle, just upload there
				$output['status'] = 'upload';
				$output['schedule_data'] = array(
					'upload'=>array(array('type'=>'whole','target_account'=>$whole_acc['storage_account_id']))
				);
				return $output;
			}
			//can't whole file upload without shuffling, try to schedule shuffles and find a semi-lowest cost shuffle.
			$shuffle_data = $this->scheduleShuffle($accounts_in_priority_order, $all_user_accounts_in_free_quota_order, $size, $user);
			if($shuffle_data){
				$output['status'] = 'need_shuffle';
				$output['schedule_data'] = array(
					'shuffle'=>$shuffle_data['schedule_data'],
					'upload'=>array(array(
							'type'=>'whole',
							'target_account'=>$shuffle_data['target_account']['storage_account_id']
						)
					)
				);
				return $output;
			}else{//can't even shuffle, tell them to link a new account
				$output['status'] = 'impossible';
				$output['errorMessage'] = '針對'.$extension.'此類型檔案，目前設定上傳的雲端硬碟類型之帳號無法容納此檔案，也無法調整出足夠的空間擺放，請連結新的雲端硬碟帳號';
				return $output;
			}
		}else{//this file is a not configured file, we can do what ever the hell we want
			$accounts_in_min_order = $accounts;//copy array
			usort($accounts_in_min_order, array($this,"minfreeQuotaApiLimitCompareHighToLow"));
			//try to whole file upload
			$whole_acc = false;
			foreach($accounts_in_min_order as $acc){
				if($acc['min_free_quota_single_file_limit']>=$size){
					$whole_acc = $acc;
				}else{
					break;
				}
			}
			if($whole_acc){
				//do whole file upload
				$output['status'] = 'upload';
				$output['schedule_data'] = array('upload'=>array(
					array('type'=>'whole', 'target_account'=>$whole_acc['storage_account_id'])
				));
				return $output;
			}
			//can't then try to split the file, check if there is enough quota in total first.
			$total_bytes_left = 0;
			foreach($accounts as $acc){
				$total_bytes_left += $acc['quota_info']['free'];
			}
			if($total_bytes_left>=$size){
				//split
				$output['status'] = 'upload';
				//get split upload schedule data
				$accounts_in_free_order = $accounts;
				usort($accounts_in_free_order, array($this,"freeQuotaCompareHighToLow"));
				$accounts_in_free_order = array_reverse($accounts_in_free_order);
				$upload_data = array();
				$offset = 0;
				foreach($accounts_in_free_order as $acc){
					if($acc['quota_info']['free']>0){
						$freequota = $acc['quota_info']['free'];
						$start_offset = $offset;
						$end_offset = (($offset+$freequota-1)>($size-1))? $size-1:$offset+$freequota-1;
						
						$upload_data[] = array('type'=>'chunked', 'target_account'=>$acc['storage_account_id'], 'byte_offset_start'=>$start_offset,'byte_offset_end'=>$end_offset);
						$offset=$end_offset+1;
						if($offset>$size-1) break;
					}
				}
				$output['status'] = 'upload';
				$output['schedule_data'] = array('upload'=>$upload_data);
				return $output;
			}else{
				//error, not enough space
				$output['status']='impossible';
				$output['errorMessage'] = '雲端硬碟總和不足，請連結新的帳號';
				return $output;
			}
		}
		
	}
}