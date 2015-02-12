var CLIENT_MEMORY_LIMIT = 100*1024*1024;
var CHUNK_SIZE = 4*1024*1024;

//----------------------
/*
	server register operation static class, this class provides the static methods to register shuffle job completion
*/
function ShuffleJobServerRegistrator(){

}
ShuffleJobServerRegistrator.registerShuffleJobCompleted = function(shuffle_job_id, success_callback){
  $.ajax({
		url: '../../shuffle/registershufflejobcompleted',
		type: 'POST',
		data: {
			'shuffle_job_id':shuffle_job_id
		},
		async: true,
		success: function(data, textstatus, request) {
					success_callback(data);
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
}
ShuffleJobServerRegistrator.registerChunkJobCompleted = function(chunk_job_id, uploaded_storage_id, success_callback){
	$.ajax({
		url: '../../shuffle/registerchunkjobcompleted',
		type: 'POST',
		data: {
			'chunk_job_id':chunk_job_id,
			'uploaded_storage_id':uploaded_storage_id
		},
		async: true,
		success: function(data, textstatus, request) {
					success_callback();
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
}
ShuffleJobServerRegistrator.registerMoveJobCompleted = function(move_job_id, success_callback){
	$.ajax({
		url: '../../shuffle/registermovejobcompleted',
		type: 'POST',
		data: {
			'move_job_id':move_job_id,
		},
		async: true,
		success: function(data, textstatus, request) {
			//all chunks done and move job registered to be done
			//call the callback function
			success_callback();
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
}
/*
	this function registers that an upload function has been finished, with the file_id's/target accounts...etc data:
	array('type'=>'whole','target_account'=>, 'uploaded_storage_id'=>);//whole
	or
	array('type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end, 'uploaded_storage_id'=>)
	array('type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end, 'uploaded_storage_id'=>)
	...
	the server then registers the storage file data
*/
ShuffleJobServerRegistrator.registerUploadFinished = function(upload_instructions_with_storage_id){

}
/*
	this function checks if the movejob is completed, if so it will register the move job to be completed.
	we need to do this because some of the movejobs are chunk level assigned and we don't know if the client
	or the server will finish the jobs last, so both sides need to check when they finish the last chunkjob
*/
ShuffleJobServerRegistrator.checkMoveJobCompleted = function(move_job_id, success_callback){
	$.ajax({
		url: '../../shuffle/checkmovejobcompleted',
		type: 'POST',
		data: {
			'move_job_id':move_job_id,
		},
		async: true,
		success: function(data, textstatus, request) {
			//all chunks done and move job registered to be done
			//call the callback function
			success_callback(data);
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
}

ShuffleJobServerRegistrator.initiateServerShuffleExecution = function(shuffle_job_id, callback){
	$.ajax({
		url: '../../shuffle/executeServerSideShuffle',
		type: 'POST',
		data: {
			'shuffle_job_id':shuffle_job_id,
		},
		async: true,
    /*
    xhr: function(){
        // get the native XmlHttpRequest object
        var xhr = $.ajaxSettings.xhr() ;
        // set the onprogress event handler
        xhr.onprogress = function(e){
          console.log(xhr.responseText);
        };
    },
    */
    success: function(data, textstatus, request) {
			//all chunks done and move job registered to be done
			//call the callback function
			callback(data);
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
}
/*
	the whole upload executor, it will execute both the upload and shuffle parts of the instructions, 
	THIS IS NOT a uploader for a single file.
	THIS IS AN EXECUTOR FOR THE getUploadInstructions operation,
	it takes the schedule_data of the dispatcher which consists of an shuffle part(optional) and an upload part
	
	it also initiates the server side to start executing shuffle jobs
*/
function WholeUploadExecutor(schedule_data, file, parent_file_id){
	this.file = file;
	this.parentFileId = parent_file_id;
	this.uploadPart = schedule_data.upload;
	this.shufflePart = schedule_data.shuffle;
	this.serverSideShuffleCompleted = false;
	this.clientSideShuffleCompleted = false;
	this.complete = function(){
	
	};
}
/*
returns times in the callback
format for times:
{
  server_side_shuffle: ooxx ms
  client_side_shuffle: 00xx
  upload_time: 00xx ms
  total_time: 00xx ms
  'client_shuffle_performance': 
    the client_provider_times below
  'server_shuffle_performance': 
    {
      'times':{
        'googledrive':{upload: xx(in ms), download: xx},
        'onedrive'
        ...
      }
      'api_copy_and_replace_times':{
        'googledrive':[1802ms,1102ms,...]
        'dropbox':[...]
      }
    }
}
*/
WholeUploadExecutor.prototype.execute = function(){
	var executor = this;
  var times = {
    'server_side_shuffle': 0,
    'client_side_shuffle': 0,
    'upload_time': 0,
    'total_time': 0,
    'client_shuffle_performance': {},
    /*
      the client_provider_times below
    */
    'server_shuffle_performance': {},
    /*
      {
        'times':{
          'googledrive':{upload: xx(in ms), download: xx},
          'onedrive'
          ...
        }
        'api_copy_and_replace_times':{
          'googledrive':[1802ms,1102ms,...]
          'dropbox':[...]
        }
      }
    */
  };
  var client_provider_times = {
    'googledrive':{
      'download': 0,
      'upload': 0
    },
    'onedrive':{
      'download': 0,
      'upload': 0
    },
    'dropbox':{
      'download': 0,
      'upload': 0
    }
  };
	function checkShuffleComplete(){
    //console.log("check shuffle complete called");
    //console.log("executor serverSideShuffleCompleted: "+executor.serverSideShuffleCompleted+", executor clientSideShuffleCompleted"+ executor.clientSideShuffleCompleted);
		if(executor.serverSideShuffleCompleted&&executor.clientSideShuffleCompleted){
			return true;
		}
		return false;
	}
	var start_upload_executor_time = {};
  var start_server_side_shuffle_time = {};
  var start_client_side_shuffle_time = {};
  var start_total_time = new Date().getTime();
  this.uploadJobExecutor = new UploadJobExecutor(this.uploadPart, this.file, this.parentFileId);
	this.uploadJobExecutor.complete = function(){
    var end_upload_executor_time = new Date().getTime();
    times.upload_time = end_upload_executor_time - start_upload_executor_time;
    times.total_time = end_upload_executor_time - start_total_time;
		executor.complete(times);
	};
	if(typeof this.shufflePart === 'undefined'){
		//just upload and call complete
    start_upload_executor_time = new Date().getTime();
		this.uploadJobExecutor.execute();
	}else{
		//initialize the shuffle job executor, initiate the server side shuffling, after shuffling is done then upload...
		this.shuffleJobExecutor = new ShuffleJobExecutor(this.shufflePart);
		
		//whenever the client or server shuffle jobs are finished, check if both sides are finished,
		//if so then execute file uploader
    start_server_side_shuffle_time = new Date().getTime();
		ShuffleJobServerRegistrator.initiateServerShuffleExecution(executor.shufflePart.shuffle_job_id, function(resp){
      times['server_shuffle_performance']= resp.performance_data;
      var end_server_side_shuffle_time = new Date().getTime();
      times.server_side_shuffle = end_server_side_shuffle_time - start_server_side_shuffle_time;
			executor.serverSideShuffleCompleted = true;
			if(checkShuffleComplete()){
        ShuffleJobServerRegistrator.registerShuffleJobCompleted(executor.shufflePart.shuffle_job_id, function(resp){
          if(resp.status == 'success'){
            start_upload_executor_time = new Date().getTime();
            executor.uploadJobExecutor.execute();
          }
        });
        
			}
		});
    start_client_side_shuffle_time = new Date().getTime();
		this.shuffleJobExecutor.execute(
      function(shuffle_times){
        client_provider_times = shuffle_times;
        times['client_shuffle_performance'] = client_provider_times;
        var end_client_side_shuffle_time = new Date().getTime();
        times.client_side_shuffle = end_client_side_shuffle_time - start_client_side_shuffle_time;
        executor.clientSideShuffleCompleted = true;
        if(checkShuffleComplete()){
          ShuffleJobServerRegistrator.registerShuffleJobCompleted(executor.shufflePart.shuffle_job_id, function(resp){
            if(resp.status == 'success'){
              start_upload_executor_time = new Date().getTime();
              executor.uploadJobExecutor.execute();
            }
          });
          
        }
      }
    );
	}
}
/*
	this executor executes the upload part of the instructions,
	upload part is the upload section of the schedule_data after dispatch, it should look like:
	[
		{'type'=>'whole','target_account'=>};//whole
		or
		{'type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end},
		{'type'=>'chunked','target_account'=>, byte_offset_start, byte_offset_end},
		...
	]
*/
function UploadJobExecutor(uploadpart, file, parent_file_id){
	this.uploadInstructions = uploadpart;
	this.file = file;
  this.parentFileId = parent_file_id;
	this.complete = function(instructions_with_storage_file_data){};
}
UploadJobExecutor.prototype.execute = function(){
	var instructions = this.uploadInstructions;
	var executor = this;
	var current_idx = -1;
  function executeNextUploadInstruction(){
		current_idx++;
		if(current_idx<instructions.length){
			//has next ins to execute
			executeUploadInstruction(instructions[current_idx]);
		}else{//all upload jobs are done
			//ShuffleJobServerRegistrator.registerUploadFinished(executor.uploadInstructions);//this function is not implemented, we will
			//register the file directly for now
			//generate storage file data
			var storage_file_data = [];
			var instructs = executor.uploadInstructions;
			var sfile_type = 'file';
			if(instructs.length>1){//file is split
				sfile_type = 'split_file';
				for(var i=0;i<instructs.length;++i){
					var ins = instructs[i];
					storage_file_data.push(
						{
							'storage_account_id':ins.target_account.storage_account_id,
							'storage_file_type':sfile_type,
							'byte_offset_start':ins.byte_offset_start,
							'byte_offset_end':ins.byte_offset_end,
              'storage_file_size':ins.byte_offset_end-ins.byte_offset_start+1,
							'storage_id':ins.uploaded_storage_id
						}
					);
				}
			}else{//whole upload
				sfile_type = 'file';
				var ins = instructs[0];
				storage_file_data.push(
					{
						'storage_account_id':ins.target_account.storage_account_id,
							'storage_file_type':sfile_type,
							'byte_offset_start':0,
							'byte_offset_end':executor.file.size-1,
              'storage_file_size':executor.file.size,
              'storage_id':ins.uploaded_storage_id
					}
				);
			}
			//register virtual file and storage file metadata to our server
			FileController.registerVirtualFileToSystem(
				executor.file.type,
				'file',
				executor.file.name, 
				executor.parentFileId, 
				storage_file_data, 
				function(){
					executor.complete(executor.uploadInstructions);
				}
			);
			return;
		}
	}
	function executeUploadInstruction(ins){
		var uploader = new FileUploader(executor.file, ins.target_account);
		uploader.complete = function(id){
			//save the storage_id of the uploaded file to the instruction field 'uploaded_storage_id'
			executor.uploadInstructions[current_idx].uploaded_storage_id = id;
			executeNextUploadInstruction();
		};
		if(ins.type == 'whole'){
			uploader.uploadWhole();
		}else if(ins.type == 'chunked'){
			uploader.uploadPart(ins.byte_offset_start, ins.byte_offset_end);
		}
	}
	executeNextUploadInstruction();
	
}
function FileUploader(file, target_account){
	this.file = file;
	this.storageAccount = target_account;
	var complete = function(file_id){};
}
FileUploader.prototype.uploadWhole = function(){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, file.name, file.type || 'application/octet-stream', file.size);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file);
}
FileUploader.prototype.uploadPart = function(bstart, bend){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, 'chunk_data_'+file.name, file.type || 'application/octet-stream', bend-bstart+1);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file.slice(bstart, bend+1));
}
/*
function FileUploaderFactory(){

}
FileUploaderFactory.createFileUploader(file, target_account){
	if(target_account.token_type == 'googledrive'){
		return new GoogleDriveFileUploader(file, target_account);
	}else if(target_account.token_type == 'onedrive'){
		return new OneDriveFileUploader(file, target_account);
	}
}
function GoogleDriveFileUploader(file, target_account){
	this.file = file;
	this.storageAccount = target_account;
	var complete = function(file_id){};
}
GoogleDriveFileUploader.prototype.uploadWhole(){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, file.name, file.type || 'application/octet-stream', file.size);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file);
}
GoogleDriveFileUploader.prototype.uploadPart(bstart, bend){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, file.name, file.type || 'application/octet-stream', file.size);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file.slice(bstart, bend+1));
}
function OneDriveFileUploader(file, target_account){
	this.file = file;
	this.storageAccount = target_account;
	var complete = function(file_id){};
}
OneDriveFileUploader.prototype.uploadWhole(file){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, file.name, file.type || 'application/octet-stream', file.size);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file);
}
OneDriveFileUploader.prototype.uploadPart(bstart, bend){
	var executor = this;
	var file = this.file;
	var uploader = DataUploadExecutorFactory.createDataUploadExecutor(this.storageAccount, file.name, file.type || 'application/octet-stream', file.size);
	uploader.complete = function(id){
		executor.complete(id);
	}
	uploader.uploadWhole(file.slice(bstart, bend+1));
}
*/

/*
	this executor executes the shuffle part of the instructions for the client side,
	THIS DOES NOT INCLUDE THE SERVER SIDE SHUFFLEJOB INSTRUCTION EXECUTION(that part is
	written as part of the whole upload job executor)
*/
function ShuffleJobExecutor(shufflejob){
	this.shuffleJob = shufflejob;
}
ShuffleJobExecutor.prototype.execute = function(callback){
  callback = callback || function(){};
	//execute each movejob sequentially, but only execute the ones that are executable on the client
    var times = {
    'googledrive':{
      'download': 0,
      'upload': 0
    },
    'onedrive':{
      'download': 0,
      'upload': 0
    },
    'dropbox':{
      'download': 0,
      'upload': 0
    }
  };
	var current_movejob_idx = -1;
	var movejobs = this.shuffleJob.move_job;
	executeNextMoveJob();
	function executeNextMoveJob(){
		//find the next movejob that is executable on the client
		current_movejob_idx++;
		var movejobexecutor = null;
		while(current_movejob_idx<movejobs.length){
			movejobexecutor = MoveJobExecutorFactory.createMoveJobExecutor(movejobs[current_movejob_idx]);
			if(movejobexecutor != null) break;
			current_movejob_idx++;
		}
		if(current_movejob_idx>=movejobs.length){
			//all movejobs completed for the client
			//just call the callback, the wholeuploadexecutor will then check if the shufflejob is completed
			callback(times);
		}else{//found next client executable movejob
			movejobexecutor.execute(function(move_job_times){
        times = addUpTimes(times, move_job_times);
        executeNextMoveJob();
      });
		}
	}
}
function MoveJobExecutorFactory(){

}
MoveJobExecutorFactory.createMoveJobExecutor = function(movejob){
	if(movejob.job_type == 'download_whole_file_and_distribute_on_client'){
		return new WholeFileDownloadMoveJobExecutor(movejob);
	}else if(movejob.job_type == 'chunk_level_assign'){
		return new ChunkLevelMoveJobExecutor(movejob);
	}else{// non-client jobs
		console.log('this job is not meant to be executed by the client');
		return null;
	}
}
/*
function MoveJobExecutor(movejob){
	if(movejob.job_type == 'download_whole_file_and_distribute_on_client'){
		return new WholeFileDownloadMoveJobExecutor(movejob);
	}else if(movejob.job_type == 'chunk_level_assign'){
		return new ChunkLevelMoveJobExecutor(movejob);
	}else{// non-client jobs
		console.log('this job is not meant to be executed by the client');
		return null;
	}
}
*/
function WholeFileDownloadMoveJobExecutor(movejob){
	this.moveJob = movejob;
}
WholeFileDownloadMoveJobExecutor.prototype.execute = function(callback){
	//Note: 1.the chunks would all be smaller than the whole file, the whole file fits in the client memory limit
	//so all the chunks should be able to fit too, so we don't need chunk uploading.
	//2.all the chunks will be uploaded through the client, no need to see if the chunk jobs are in fact client jobs
  
  //callback(download_and_upload_times)
  //download_and_upload_times:
  //{
  //  googledrive: {download: xx ms, upload: xx ms}
  //  ...
  //}
  callback = callback || function(){};
	var movejobexecutor = this;
	var sourcefile = this.moveJob.source_file;
	var sourceacc = this.moveJob.source_account;
  var times = {
    'googledrive':{
      'download': 0,
      'upload': 0
    },
    'onedrive':{
      'download': 0,
      'upload': 0
    },
    'dropbox':{
      'download': 0,
      'upload': 0
    }
  };
	var source_dler = DataDownloadExecutorFactory.createDataDownloadExecutor(sourceacc, sourcefile.storage_id);
	//get the whole file data first
	var source_blob = {};
  var download_timer = new StopWatch(true);
	source_dler.downloadWhole(function(data){
    var dl_time = download_timer.pause();
    //see which provider this source file is from and update times
      times[sourceacc.token_type].download += dl_time;
    //--------------------
    source_blob = data;
		//start uploading chunks(chunk jobs)
		//upload the chunks to individual accounts
		var chunkjobs = movejobexecutor.moveJob.chunk_job;
		var chunkjobs_idx = -1;
		function upload_next_chunkjob(){
			chunkjobs_idx++;
			if(chunkjobs_idx<chunkjobs.length){
				upload_chunkjob(chunkjobs[chunkjobs_idx]);
			}else{//all chunks done
				//register move job complete
        //new flow, don't register move job
        /*
				ShuffleJobServerRegistrator.registerMoveJobCompleted(chunkjob['move_job_id'], function(){
          callback(times);
        });
        */
        callback(times);
			}
		}
		function upload_chunkjob(chunkjob){
			var uploadfilename = sourcefile.name;
			//see if the source file is chunked currently, or will be chunked after we do this shuffle
			if((sourcefile.storage_file_type == 'split_file')||(chunkjobs.length>1)){
				//will be chunked, so we need to change the name of the file
				uploadfilename = 'chunked_data_'+uploadfilename;
			}
			var upload_timer = new StopWatch(true);
      var chunk_uploader = DataUploadExecutorFactory.createDataUploadExecutor(chunkjob.target_account, sourcefile.name, sourcefile.mime_type, sourcefile.storage_file_size);
			chunk_uploader.complete =  function(file_id){
        //update times
        var ul_time = upload_timer.pause();
        times[chunkjob.target_account.token_type].upload += ul_time;
        //------------------------
				//register to the server that this chunkjob is completed
				ShuffleJobServerRegistrator.registerChunkJobCompleted(chunkjob['chunk_job_id'],file_id, upload_next_chunkjob);
			}
			chunk_uploader.uploadWhole(source_blob.slice(chunkjob.byte_offset_start, chunkjob.byte_offset_end+1));
		}
		upload_next_chunkjob();
		
	});
	
	
}
function ChunkLevelMoveJobExecutor(movejob){
	this.moveJob = movejob;
}
ChunkLevelMoveJobExecutor.prototype.execute = function(callback){
	//This executor will do partial download/resumable upload loops to move the file data,
	//this enables the client(web browser) to shuffle huge files without needing to worry about memory issues
	//Note that this is not only downloading each chunkjob chunk as whole and uploading, we need to further chunk
	//the data according to CHUNK_SIZE and send them via multiple requests
	
	//Note that some chunks might be assigned to the server, so we need to ignore them when choosing the next chunk to upload
	callback = callback || function(){};
  var executor = this;
	var current_chunk_idx = -1;
	var chunk_jobs = this.moveJob.chunk_job;
	var source_account = this.moveJob.source_account;
	var source_file = this.moveJob.source_file;
	var downloader = {};
  var times = {
    'googledrive':{
      'download': 0,
      'upload': 0
    },
    'onedrive':{
      'download': 0,
      'upload': 0
    },
    'dropbox':{
      'download': 0,
      'upload': 0
    }
  };
  
	function executenextchunkjob(){
		//find the next chunk that has executor set to client
		do{
			current_chunk_idx++;
			if(current_chunk_idx>=chunk_jobs.length) break;
		}while(chunk_jobs[current_chunk_idx].executor == 'server');
		if(current_chunk_idx<chunk_jobs.length){
			//next chunk job found
			//init downloader, we will be using the same downloader for this chunkjob
			downloader = DataDownloadExecutorFactory.createDataDownloadExecutor(source_account, source_file['storage_id']);
			//uploadchunk(chunk_jobs[current_chunk_idx]);//this line is wrong, there is no upload chunk
      executechunkjob(chunk_jobs[current_chunk_idx]);
		}else{//all client chunk jobs completed
			//check move job completed, we don't know if the server side chunk jobs are completed so we check
			//new flow, no need to check, just wait till the end and send a request to check for all.
      /*
      ShuffleJobServerRegistrator.checkMoveJobCompleted(executor.moveJob.move_job_id, function(){
        callback(times);
      });
      */
      callback(times);
		}
	}
	function executechunkjob(chunkjob){
		//create download/upload executors
		var target_account = chunkjob['target_account'];
		var filename = source_file.name;
		//see if the source file is chunked currently, or will be chunked after we do this shuffle
		if((source_file.storage_file_type == 'split_file')||(chunk_jobs.length>1)){
			//will be chunked, so we need to change the name of the file
			filename = 'chunked_data_'+filename;
		}
		var filetype = source_file.mime_type;
		var filesize = source_file.storage_file_size;
    var chunkjob_size = chunkjob.byte_offset_end - chunkjob.byte_offset_start + 1;
		var uploader = DataUploadExecutorFactory.createDataUploadExecutor(chunkjob.target_account, source_file.name, source_file.mime_type, chunkjob_size);
    //here we need to determine where we can upload the whole data once or the memory isn't enough, then we need to upload the data using chunk dl->chunk upload, which is not supported by all
    //uploaders, but the client executor shouldn't have to worry about this because the scheduler will take care of this, all we need to do is check the chunk size
    var ul_timer = new StopWatch();
    var dl_timer = new StopWatch();
    function chunkjobcomplete(id){
      var ul_time = ul_timer.pause();
      times[chunkjob.target_account.token_type].upload += ul_time;
      ShuffleJobServerRegistrator.registerChunkJobCompleted(chunkjob.chunk_job_id, id, executenextchunkjob);
    }
    if(chunkjob_size >= CLIENT_MEMORY_LIMIT){//the client memory is not big enough to move the chunkjob in one shot, so do partial dl->resumable upload
      uploader.chunkUploaded = uploadnextchunk;
      uploader.complete = chunkjobcomplete;
      var bstart = chunkjob.byte_offset_start;
      var bend = chunkjob.byte_offset_end;
      var current_bstart = bstart;
      uploadnextchunk();
      
      function uploadnextchunk(){
        //uploads the next chunk of data for the current chunkjob
        //calculate the byte offsets
        var current_bend = (current_bstart+CHUNK_SIZE-1>bend)?bend:current_bstart+CHUNK_SIZE-1;
        //update bstart for the next loops
        var last_bstart = current_bstart;
        current_bstart = current_bend+1;
        dl_timer.reset();
        dl_timer.start();
        downloader.downloadChunk(last_bstart, current_bend, function(dl_data){
          var dl_time = dl_timer.pause();
          times[source_account.token_type].download += dl_time;
          ul_timer.start();
          uploader.uploadChunk(dl_data);
        });
      }
		}else{//client memory is sufficient to move the chunkjob in one shot
      uploader.complete = chunkjobcomplete;
      dl_timer.reset();
      dl_timer.start();
      downloader.downloadChunk(chunkjob.byte_offset_start, chunkjob.byte_offset_end, function(dl_data){
        //must be partial, because if it were to download the whole source file, the job would be assigned to a WholeFileDownloadMoveJobExecutor
        var dl_time = dl_timer.pause();
        times[source_account.token_type].download += dl_time;
        ul_timer.start();
        uploader.uploadWhole(dl_data);
      });
      
    }
	}
	executenextchunkjob();
}
function ChunkJobExecutor(chunkjob, source_account_data, source_file_data){
	//look at the chunk job, create a download executor/upload executor, chain up
	//when done, call callback
	/*
	var downloader = new GoogleDriveDataDownloadExecutor();
	var uploader = new GoogleDriveDataUploadExecutor();
	uploader.chunkUploaded() = function(){
		//check/gen byte offsets, if it is done then just return
		downloader.downloadChunk(byteoffsets, function(){
			uploader.uploadChunk(data);
		});
	}
	uploader.complete = function(file_id){
		//register the file to our system
		
		//call the complete function of the chunkjobexecutor itself
	}
	var first_data = downloader.downloadChunk(byteoffsets);
	uploader.uploadChunk(first_data);
	*/
	this.chunkJob = chunkjob;
	this.sourceFile = source_file_data;
	this.sourceAccount = source_account_data;
}
ChunkJobExecutor.prototype.execute = function(callback){
	
}
function DataUploadExecutorFactory(){
	
}
DataUploadExecutorFactory.createDataUploadExecutor = function(storage_account, filename, filetype, filesize){
	if(storage_account.token_type == 'onedrive'){
		return new OneDriveDataUploadExecutor(filename, filetype, storage_account.access_token, filesize);
	}else if(storage_account.token_type == 'googledrive'){
		return new GoogleDriveDataUploadExecutor(filename, filetype, storage_account.access_token, filesize);
	}else if(storage_account.token_type == 'dropbox'){
    return new DropboxDataUploadExecutor(filename, filetype, storage_account.access_token, filesize);
  }
}
/*
Data upload/download executors load/write binary data to/from javascript from/to cloud storages
not actual file downloaders and file(blob) upload/downloaders.
*/
function DataUploadExecutor(filename, filetype, access_token, filesize){
	//var current_byte_offset
	//var fileName
	//var fileSize
	
	//function uploadChunk(data);
	//var chunkUploaded:callback(response);
	
	//function uploadWhole(data)
	
	//var complete:callback(file_id)
	
}
//concrete classes for each cloud provider
function DropboxDataUploadExecutor(filename, filetype, access_token, filesize){
  this.fileName = filename;
	this.fileType = filetype || 'application/octet-stream';;
	this.accessToken = access_token;
	this.fileSize = filesize;
  this.chunkUploaded = function(){};
  this.complete = function(){};
  this.chunkUploadUrl = '';
  this.currentByteOffset = 0;
  this.uploadId = '';
}
/*
  commits chunk upload, returns the path of the file in the callback function argument
*/
DropboxDataUploadExecutor.prototype._commitUploadChunk = function(callback){
  var executor = this;
  if(executor.uploadId == ''){
    console.log('trying to commit an upload on a dropbox account, but there is no upload_id to commit! stopping');
    return;
  }
  function makeDir(dname, dir_callback){
    $.ajax({
      url: 'https://api.dropbox.com/1/fileops/create_folder',
      type: 'POST',
      dataType:'json',
      headers: {
        'Authorization': 'Bearer '+executor.accessToken,
      },
      data: {
        'root':'auto',
        'path':'/'+dname
      },
      async: true,
      success: function(resp) {
        dir_callback(resp.path);
      },
      error: function(xhr, status, error) {
        //probably due to name collision
        //do this function again
        makeDir(dname+'_n', dir_callback);
      }
    });
  }
  //create a dir using the current timestamp
  var dir_name = new Date().getTime()+'_SI_gen_dir';
  //make directory
  makeDir(dir_name, function(dirpath){
    //commit the upload
    $.ajax({
      url: 'https://api-content.dropbox.com/1/commit_chunked_upload/auto'+dirpath+'/'+executor.fileName,
      type: 'POST',
      dataType:'json',
      headers: {
        'Authorization': 'Bearer '+executor.accessToken,
      },
      data: {
        'overwrite':false,
        'autorename': true,
        'upload_id': executor.uploadId
      },
      async: true,
      success: function(resp) {
        //console.log(resp);
        //console.log(resp.path);
        callback(resp.path);
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });
    
  });
  
}
DropboxDataUploadExecutor.prototype._uploadChunk = function(upload_id, data, callback){
  var executor = this;
  var url = 'https://api-content.dropbox.com/1/chunked_upload?';
  if(upload_id == ''){
    url = url+'offset=0';
  }else{
    url = url+'upload_id='+upload_id+'&offset='+executor.currentByteOffset;
  }
  $.ajax({
    url: url,
    type: 'PUT',
    processData: false,
    dataType: 'json',
    headers: {
      'Authorization': 'Bearer '+executor.accessToken,
      //'Content-Type': executor.fileType,
    },
    data: data,
    async: true,
    success: function(resp) {
      callback(resp);
    },
    error: function(xhr, status, error) {
      var err = xhr.responseText;
      console.log(err);
      console.log(error);
    }
  });
}
  
DropboxDataUploadExecutor.prototype.uploadChunk = function(data){
  var executor = this;
  function chunkuploadedinternal(resp){
    if(executor.uploadId == '') executor.uploadId = resp.upload_id;
    executor.currentByteOffset = resp.offset;
    //check if the whole file is finished by checking the response offset
    //if the whole file is finished call both chunk uploaded and complete
    //if not then only call chunk uploaded
    executor.chunkUploaded(resp);
    if(resp.offset>=executor.fileSize){// whole thing done, we need to commit the chunk upload and then get the file path and pass as argument in complete
      executor._commitUploadChunk(function(commit_resp){
        executor.complete(commit_resp);
      });
    }
  }
	executor._uploadChunk(executor.uploadId, data, chunkuploadedinternal);
}
DropboxDataUploadExecutor.prototype.uploadWhole = function(data){
  var chunksize = 100*1024*1024;
  var executor = this;
  executor.chunkUploaded = function(){
    uploadnextchunk();  
  };
  function uploadnextchunk(){
    //console.log('upload next chunk called, current offset='+executor.currentByteOffset+', filesize='+executor.fileSize);
    if(executor.currentByteOffset>=executor.fileSize) return;
    var offset_start = executor.currentByteOffset;
    var offset_end = (offset_start+chunksize>executor.fileSize)?executor.fileSize-1:offset_start+chunksize-1;//inclusive offset end, but when we blob.slice, we need to add 1 when slicing
    var chunk_data = data.slice(offset_start, offset_end+1);
    
    executor.uploadChunk(chunk_data);
    
  }
  uploadnextchunk();
  
}
function GoogleDriveDataUploadExecutor(filename, filetype, access_token, filesize){
	this.currentByteOffset = 0;
	this.fileName = filename;
	this.fileType = filetype || 'application/octet-stream';;
	this.accessToken = access_token;
	this.fileSize = filesize;
	this.chunkUploadUrl = '';
  this.chunkUploaded = function(){};
  this.complete = function(){};
}
GoogleDriveDataUploadExecutor.prototype.getUploadUrl = function(callback){
	var executor = this;
	var uploadlocation = '';
	var contentType = this.fileType;
	var metadata = {
		'title': this.fileName,
		'mimeType': contentType
	};
	var requesturlwithquery = 'https://www.googleapis.com/upload/drive/v2/files?uploadType=resumable';
	metadata = JSON.stringify(metadata);
	if(this.chunkUploadUrl == ''){
		$.ajax({
			url: requesturlwithquery,
			type: 'POST',
			processData: false,
			headers: {
				'Authorization': 'Bearer '+executor.accessToken,
				'Content-Type': 'application/json; charset=UTF-8',
				'X-Upload-Content-Type': executor.fileType,
				'X-Upload-Content-Length': ''+executor.fileSize
			},
			data: metadata,
			async: true,
			success: function(data, textstatus, request) {
						uploadlocation = request.getResponseHeader('location');
						executor.chunkUploadUrl = uploadlocation;
						//console.log('upload loc: '+uploadlocation);
						//console.log('exe:  '+executor.chunkUploadUrl);
						callback();
			},
			error: function(xhr, status, error) {
				var err = xhr.responseText;
				console.log(err);
				alert(err);
				alert(error);
			}
		});
	}else{
		callback();
	}
}
GoogleDriveDataUploadExecutor.prototype.uploadChunk = function(data){
	var uploadexecutor = this;
	this.getUploadUrl(function(){
		var byteOffsetEnd = uploadexecutor.currentByteOffset+data.size-1;
		var contentType = uploadexecutor.fileType;
		//console.log(uploadexecutor.chunkUploadUrl);
		$.ajax({
			url: uploadexecutor.chunkUploadUrl,
			type: 'PUT',
			processData: false,
			headers: {
				'Authorization': 'Bearer '+uploadexecutor.accessToken,
				'Content-Type': contentType,
				//'Content-Range': 'bytes '+byteOffsetStart+'-'+(byteOffsetStart+fileData.size-1)+'/'+totalSize
				'Content-Range': 'bytes '+uploadexecutor.currentByteOffset+'-'+byteOffsetEnd+'/'+uploadexecutor.fileSize
			},
			data: data,
			async: true,
			statusCode: {
			308: function(resp) {
					uploadexecutor.currentByteOffset = byteOffsetEnd + 1;
					uploadexecutor.chunkUploaded(resp);
				}
			},
			success: function(resp) {
				uploadexecutor.chunkUploaded(resp);
				uploadexecutor.complete(resp.id);
			},
			error: function(xhr, status, error) {
				
			}
		});
	});
}
GoogleDriveDataUploadExecutor.prototype.uploadWhole = function(data){
	var executor = this;
	
  this.getUploadUrl(function(){
    /*
    var uploadexecutor = this;
    var xhr = new XMLHttpRequest();
    xhr.onload = function(e){
      uploadexecutor.complete(e.data.id);
    };
    xhr.open('PUT', executor.chunkUploadUrl, true);
    xhr.send();
    }
    reader.readAsArrayBuffer(data);
    */
    executor.uploadChunk(data);
    /*
    executor.chunkUploaded = function(response){
      //get the offsets
      var end_offset = (executor.currentByteOffset+CHUNK_SIZE-1>executor.fileSize-1)?executor.fileSize-1:executor.currentByteOffset+CHUNK_SIZE-1;
      //create the sub array buffer
      executor.uploadChunk(data.slice(executor.currentByteOffset, end_offset));
    };
    var first_end_offset = (executor.currentByteOffset+CHUNK_SIZE-1>executor.fileSize-1)?executor.fileSize-1:executor.currentByteOffset+CHUNK_SIZE-1;
    executor.uploadChunk(data.slice(executor.currentByteOffset, first_end_offset+1));
    */
  });
	
}
function OneDriveDataUploadExecutor(filename, filetype, access_token, filesize){
	this.currentByteOffset = 0;
	this.fileName = filename;
	this.fileType = filetype || 'application/octet-stream';;
	this.accessToken = access_token;
	this.fileSize = filesize;
	this.uploadUrl = '';
}
/*
  for providers like onedrive, they cannot allow files with the same filename under the same folder,
  so we should create a timestamp folder before we upload to the folder
*/
OneDriveDataUploadExecutor.prototype.getUploadUrl = function(callback){
  var executor = this;
  if(this.uploadUrl == ''){
    //create a folder:
    //POST https://apis.live.net/v5.0/me/skydrive

    //Authorization: Bearer ACCESS_TOKEN
    //Content-Type: application/json

    //{
      //  "name": "My example folder"
    //}
  
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'https://apis.live.net/v5.0/me/skydrive?access_token='+executor.accessToken+'&overwrite=ChooseNewName', true);
    xhr.responseType = 'json';
    //xhr.setRequestHeader('Authorization', 'Bearer ' + executor.accessToken);this line doesn't work in cors, doesn't allow this header, use query parameter instead
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function(){
      var metadata = xhr.response;
      executor.uploadUrl = metadata.upload_location+'?access_token='+executor.accessToken+'&downsize_photo_uploads=false&overwrite=ChooseNewName';
      callback();
    };
    xhr.send(JSON.stringify(
      {
        'name': new Date().getTime()+'_SI_gen_dir'
      }
    ));
  }else{
    callback();
  }
  
}
OneDriveDataUploadExecutor.prototype.uploadChunk = function(data){
	console.log('chunk upload for onedrive is not supported');
}
OneDriveDataUploadExecutor.prototype.uploadWhole = function(data){
	var uploadexecutor = this;
  var formdata = new FormData();
	formdata.append('file', data);
  uploadexecutor.getUploadUrl(
    function(){
      var xhr = new XMLHttpRequest();
      //xhr.open('POST', 'https://apis.live.net/v5.0/me/skydrive/files?access_token='+uploadexecutor.accessToken+'&downsize_photo_uploads=false&overwrite=ChooseNewName', true);
      xhr.open('POST', uploadexecutor.uploadUrl, true);
      xhr.responseType = 'json';
      xhr.onload = function(){
        var id = xhr.response.id;
        //rename the file, since it is called 'blob' right now
        /*
        PUT https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!126

        Authorization: Bearer ACCESS_TOKEN
        Content-Type: application/json

        {
            "name": "MyNewFileName.doc"
        }
        */

        var xhr2 = new XMLHttpRequest();
        xhr2.open('PUT', 'https://apis.live.net/v5.0/'+id+'?access_token='+uploadexecutor.accessToken+'&downsize_photo_uploads=false&overwrite=ChooseNewName', true);
        xhr2.setRequestHeader('Content-Type', 'application/json');
        xhr2.onload = function(){
          //alert('still fired, id= '+ id);//onload is still gonna be fired when it is a 400 bad request(because the file name already exists) so this implementation is somewhat fine
          uploadexecutor.complete(id);
        };
        xhr2.send(JSON.stringify({'name':uploadexecutor.fileName}));
      }
      //xhr.open('POST', '../../test/justechook', true);
      xhr.send(formdata);
      
      /*
      //read first and send binary data, not memory friendly
      var reader = new FileReader();
      reader.onload = function(e){
        var bindata = reader.result;
        var xhr = new XMLHttpRequest();
        xhr.onload = function(){
          uploadexecutor.complete(xhr.response.id);
        };
        xhr.open('PUT', 'https://apis.live.net/v5.0/me/skydrive/files/'+uploadexecutor.fileName+'?access_token='+uploadexecutor.accessToken+'&downsize_photo_uploads=false&overwrite=ChooseNewName', true);
        xhr.responseType = 'json';
        xhr.send(bindata);
      }
      reader.readAsArrayBuffer(data);
      */
    });
}
function DataDownloadExecutorFactory(){
	
}
DataDownloadExecutorFactory.createDataDownloadExecutor = function(storage_account, file_id){
	if(storage_account.token_type == 'onedrive'){
		return new OneDriveDataDownloadExecutor(storage_account.access_token, file_id);
	}else if(storage_account.token_type == 'googledrive'){
		return new GoogleDriveDataDownloadExecutor(storage_account.access_token, file_id);
	}else if(storage_account.token_type == 'dropbox'){
    return new DropboxDataDownloadExecutor(storage_account.access_token, file_id);
  }
}
function DataDownloadExecutor(access_token, file_id){//interface
	//function downloadChunk(byte_offset_start, byte_offset_end, callback)//callback(data:blob)
	//function downloadWhole(callback)//callback(data:blob)
}
//concrete classes for each cloud provider
function DropboxDataDownloadExecutor(access_token, file_id){//file_id is actually the path to the file, including the '/'
  this.accessToken = access_token;
	this.fileId = file_id;
}
DropboxDataDownloadExecutor.prototype.downloadChunk = function(byte_offset_start, byte_offset_end, callback){
  var executor = this;
  var oReq = new XMLHttpRequest();
  oReq.open("GET", 'https://api-content.dropbox.com/1/files/auto'+executor.fileId, true);
  oReq.responseType = "blob";
  oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
  oReq.setRequestHeader('Range', 'bytes='+byte_offset_start+'-'+byte_offset_end);
  oReq.onload = function(oEvent) {
    //var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
    var blob = oReq.response;
    //console.log('response is: '+blob);
    callback(blob);
    //delete oReq;
  };
  oReq.send();
}
DropboxDataDownloadExecutor.prototype.downloadWhole = function(callback){
  var executor = this;
  var oReq = new XMLHttpRequest();
  oReq.open("GET", 'https://api-content.dropbox.com/1/files/auto'+executor.fileId, true);
  oReq.responseType = "blob";
  oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
  oReq.onload = function(oEvent) {
    //var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
    var blob = oReq.response;
    //console.log('response is: '+blob);
    //console.log(blob);
    callback(blob);
    //delete oReq;
  };
  oReq.send();
}

function GoogleDriveDataDownloadExecutor(access_token, file_id){
	this.accessToken = access_token;
	this.fileId = file_id;
	this.downloadUrl = '';
}
GoogleDriveDataDownloadExecutor.prototype.getDownloadUrl = function(callback){
	//get the file meta data, this includes the download link
	var executor = this;
	var downloadurl = '';
	if(this.downloadUrl == ''){
		$.ajax({
			url: 'https://www.googleapis.com/drive/v2/files/'+executor.fileId,
			type: 'GET',
			processData: true,
			headers: {
				'Authorization': 'Bearer '+ executor.accessToken,
			},
			async: false,
			success: function(data) {
				downloadurl = data.downloadUrl;
				executor.downloadUrl = downloadurl;
				callback();
			},
			error: function(xhr, status, error) {
				var err = xhr.responseText;
				console.log(err);
				alert(err);
				alert(error);
			}
		});
	}else{
		callback();
	}
}
GoogleDriveDataDownloadExecutor.prototype.downloadChunk = function(byte_offset_start, byte_offset_end, callback){
	var executor = this;
	this.getDownloadUrl(
		function(){
			var oReq = new XMLHttpRequest();
			oReq.open("GET", executor.downloadUrl+'&forcenocache='+new Date().getTime(), true);
			oReq.responseType = "arraybuffer";
			oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
			oReq.setRequestHeader('Range', 'bytes='+byte_offset_start+'-'+byte_offset_end);
			oReq.onload = function(oEvent) {
				var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
				callback(blob);
        //delete oReq;
      };
			oReq.send();
		}
	);
		
}
GoogleDriveDataDownloadExecutor.prototype.downloadWhole = function(callback){
	var executor = this;
	this.getDownloadUrl(
		function(){
			var oReq = new XMLHttpRequest();
			oReq.open("GET", executor.downloadUrl, true);
			oReq.responseType = "arraybuffer";
			oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
			oReq.onload = function(oEvent) {
				var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
				callback(blob);
        delete oReq;
			};
			oReq.send();
		}
	);
}
function OneDriveDataDownloadExecutor(access_token, file_id){
	this.accessToken = access_token;
	this.fileId = file_id;
	this.downloadUrl = '';
}
OneDriveDataDownloadExecutor.prototype.getDownloadUrl = function(callback){
	//var url = 'https://apis.live.net/v5.0/'+this.fileId+'/content?access_token='+this.accessToken;
	var executor = this;
	if(this.downloadUrl == ''){
		/*
		var location_url = 'https://apis.live.net/v5.0/'+this.fileId+'/content?suppress_redirects=true&access_token='+this.accessToken;
		var req = new XMLHttpRequest();
		req.open("GET", location_url, true);
		req.onload = function(e){
			executor.downloadUrl = (JSON.parse(req.response)).location;
			//console.log(req.response);
			console.log(executor.downloadUrl);
			callback();
		};
		req.send();
		*/
		
		var location_url = 'https://apis.live.net/v5.0/'+this.fileId+'?access_token='+this.accessToken;
		var req = new XMLHttpRequest();
		req.open("GET", location_url, true);
		req.onload = function(e){
			executor.downloadUrl = (JSON.parse(req.response)).source;
			//console.log(req.response);
			console.log(executor.downloadUrl);
			callback();
		};
		req.send();
		
	}else{
		callback();
	}
}
OneDriveDataDownloadExecutor.prototype.downloadChunk = function(byte_offset_start, byte_offset_end, callback){
	console.log('unable to run downloadChunk for OneDriveDataDownloadExecutor because onedrive does not support partial download');
	/*
	var executor = this;
	var oReq = new XMLHttpRequest();
	oReq.open("GET", executor.downloadUrl, true);
	oReq.responseType = "arraybuffer";
	//oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
	oReq.setRequestHeader('Range', 'bytes='+byte_offset_start+'-'+byte_offset_end);
	oReq.onload = function(oEvent) {
		var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
		callback(blob);
	};
	oReq.send();
	*/
}
OneDriveDataDownloadExecutor.prototype.downloadWhole = function(callback){
	console('unable to run downloadWhole for OneDriveDataDownloadExecutor because M$ sucks!');
	/*
	var executor = this;
	this.getDownloadUrl(function(){
		var win = window.open(executor.downloadUrl);
		var oReq = new XMLHttpRequest();
		oReq.open("GET", executor.downloadUrl, true);
		oReq.responseType = "arraybuffer";
		oReq.onload = function(oEvent) {
			var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
			callback(blob);
		};
		oReq.send();
	});
	*/
	/*
	var executor = this;
	var oReq = new XMLHttpRequest();
	oReq.open("GET", 'https://apis.live.net/v5.0/'+this.fileId+'/content?access_token='+this.accessToken, true);
	oReq.responseType = "arraybuffer";
	oReq.onload = function(oEvent) {
		var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
		callback(blob);
	};
	oReq.send();
	*/
}
//times functions
function addUpTimes(times1, times2){
  var output  = {
    'googledrive':{
      'download': 0,
      'upload': 0
    },
    'onedrive':{
      'download': 0,
      'upload': 0
    },
    'dropbox':{
      'download': 0,
      'upload': 0
    }
  };
  for(var provider in times1){
    if(times1.hasOwnProperty(provider)){
      output[provider].upload = times1[provider].upload + times2[provider].upload;
      output[provider].download = times1[provider].download + times2[provider].download;
    }
  }
  return output;
}

//stopwatch class
function StopWatch(auto_start){
  auto_start = auto_start || false;
  this.elapsedTimeMilliSeconds = 0;
  if(auto_start){
    this.start();
  }
}
StopWatch.prototype.start = function(){
  this.startTime = new Date().getTime();
}
StopWatch.prototype.pause = function(){
  this.elapsedTimeMilliSeconds += new Date().getTime() - this.startTime;
  return this.elapsedTimeMilliSeconds;
}
StopWatch.prototype.reset = function(){
  var elapsed = this.elapsedTimeMilliSeconds;
  this.elapsedTimeMilliSeconds = 0;
  return elapsed;
}