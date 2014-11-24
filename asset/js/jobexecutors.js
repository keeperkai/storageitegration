var CLIENT_MEMORY_LIMIT = 100*1024*1024;
var CHUNK_SIZE = 4*1024*1024;
/*
	server register operation static class, this class provides the static methods to register shuffle job completion
*/
function ShuffleJobServerRegistrator(){

}
ShuffleJobServerRegistrator.registerChunkJobCompleted = function(chunk_job_id, uploaded_file_id, success_callback){
	$.ajax({
		url: '../../shuffle/registerchunkjobcomplete',
		type: 'POST',
		data: {
			'chunk_job_id':chunk_job_id,
			'uploaded_file_id':uploaded_file_id
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
		url: '../../shuffle/registermovejobcomplete',
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
	this function checks if the movejob is completed, if so it will register the move job to be completed.
	we need to do this because some of the movejobs are chunk level assigned and we don't know if the client
	or the server will finish the jobs last, so both sides need to check when they finish the last chunkjob
*/
ShuffleJobServerRegistrator.checkMoveJobCompleted = function(move_job_id, success_callback){
	$.ajax({
		url: '../../shuffle/checkmovejobcomplete',
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
/*
	the whole upload executor, it will execute both the upload and shuffle parts of the instructions, 
	THIS IS NOT a uploader for a single file.
	THIS IS AN EXECUTOR FOR THE getUploadInstructions operation
*/
function WholeUploadExecutor(){

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
function UploadJobExecutor(uploadpart){
	this.uploadInstructions = uploadpart;
}
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
/*
	this executor executes the shuffle part of the instructions for the client side,
	THIS DOES NOT INCLUDE THE SERVER SIDE SHUFFLEJOB INSTRUCTION EXECUTION(that part is
	written as part of the whole upload job executor)
*/
function ShuffleJobExecutor(shufflejob){
	this.shuffleJob = shufflejob;
)
ShuffleJobExecutor.prototype.execute(callback){
	//execute each movejob sequentially, but only execute the ones that are executable on the client
	var current_movejob_idx = -1;
	var movejobs = this.shuffleJob.move_job;
	executeNextMoveJob();
	function executeNextMoveJob(){
		//find the next movejob that is executable on the client
		current_movejob_idx++;
		var movejobexecutor = null;
		while(current_movejob_idx<movejobs.length){
			movejobexecutor = MoveJobExecutorFactory.createMoveJobExecutor(movejobs['current_movejob_idx']);
			if(movejobexecutor != null) break;
			current_movejob_idx++;
		}
		if(current_movejob_idx>=movejobs.length){
			//all movejobs completed for the client
			//just call the callback, the wholeuploadexecutor will then check if the shufflejob is completed
			callback();
		}else{//found next client executable movejob
			movejobexecutor.execute(executeNextMoveJob);
		}
	}
}
function MoveJobExecutorFactory(){

}
MoveJobExecutorFactory.createMoveJobExecutor(movejob){
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
	var movejobexecutor = this;
	var sourcefile = this.moveJob.source_file;
	var sourceacc = this.moveJob.source_account;
	var source_dler = DataDownloadExecutorFactory.createDataDownloadExecutor(sourceacc, sourcefile.storage_id);
	//get the whole file data first
	var source_blob = {};
	source_dler.downloadWhole(function(data){
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
				ShuffleJobServerRegistrator.registerMoveJobCompleted(chunkjob['move_job_id'], callback);
				/*
				$.ajax({
					url: '../../shuffle/registermovejobcompleted',
					type: 'POST',
					data: {
						'move_job_id':chunkjob['move_job_id'],
					},
					async: true,
					success: function(data, textstatus, request) {
						//all chunks done and move job registered to be done
						//call the callback function
						callback();
					},
					error: function(xhr, status, error) {
						var err = xhr.responseText;
						console.log(err);
						alert(err);
						alert(error);
					}
				});
			*/
				
			}
		}
		function upload_chunkjob(chunkjob){
			var uploadfilename = sourcefile.name;
			//see if the source file is chunked currently, or will be chunked after we do this shuffle
			if((sourcefile.storage_file_type == 'split_file')||(chunkjobs.length>1)){
				//will be chunked, so we need to change the name of the file
				uploadfilename = 'chunked_data_'+uploadfilename;
			}
			
			var chunk_uploader = DataUploadExecutorFactory.createDataUploadExecutor(chunkjob.target_account, sourcefile.name, sourcefile.mime_type, sourcefile.storage_file_size);
			chunk_uploader.complete =  function(file_id){
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
	var executor = this;
	var current_chunk_idx = -1;
	var chunk_jobs = this.moveJob.chunk_job;
	var source_account = moveJob.source_account;
	var source_file = moveJob.source_file;
	var downloader = {};
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
			uploadchunk(chunk_jobs[current_chunk_idx]);
		}else{//all client chunk jobs completed
			//check move job completed, we don't know if the server side chunk jobs are completed so we check
			ShuffleJobServerRegistrator.checkMoveJobCompleted(executor.moveJob.move_job_id, callback);
			/*
			$.ajax({
				url: '../../shuffle/registermovejobcompleted',
				type: 'POST',
				data: {
					'move_job_id':executor.moveJob.move_job_id,
				},
				async: true,
				success: function(data, textstatus, request) {
					//all chunks done and move job registered to be done
					//call the callback function
					callback();
				},
				error: function(xhr, status, error) {
					var err = xhr.responseText;
					console.log(err);
					alert(err);
					alert(error);
				}
			});
			*/
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
		var uploader = DataUploadExecutorFactory.createDataUploadExecutor(storage_account, filename, filetype, filesize);
		uploader.chunkUploaded = uploadnextchunk;
		uploader.complete = chunkjobcomplete;
		var bstart = chunkjob.byte_offset_start;
		var bend = chunkjob.byte_offset_end;
		var current_bstart = bstart;
		uploadnextchunk();
		function chunkjobcomplete(id){
			ShuffleJobServerRegistrator.registerChunkJobCompleted(chunkjob.chunk_job_id, id, executenextchunkjob);
		}
		function uploadnextchunk(){
			//uploads the next chunk of data for the current chunkjob
			//check boundary for current_bstart
			/*
			if(current_bstart>bend){
				//this chunkjob is uploaded.
				//register chunk job complete, then execute the next chunkjob
				ShuffleJobServerRegistrator.registerChunkJobCompleted();
				executenextchunkjob();
				return;
			}
			*/
			//calculate the byte offsets
			var current_bend = (current_bstart+CHUNK_SIZE-1>bend)?bend:current_bstart+CHUNK_SIZE-1;
			//update bstart for the next loops
			var last_bstart = current_bstart;
			current_bstart = current_bend+1;
			downloader.downloadChunk(last_bstart, current_bend, function(dl_data){
				uploader.uploadChunk(dl_data);
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
DataUploadExecutorFactory.createDataUploadExecutor(storage_account, filename, filetype, filesize){
	if(storage_account.token_type == 'onedrive'){
		return new OneDriveDataUploadExecutor(storage_account, filename, filetype, filesize);
	}else if(storage_account.token_type == 'googledrive'){
		return new GoogleDriveDataUploadExecutor(storage_account, filename, filetype, filesize);
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
function GoogleDriveDataUploadExecutor(filename, filetype, access_token, filesize){
	this.currentByteOffset = 0;
	this.fileName = filename;
	this.fileType = filetype || 'application/octet-stream';;
	this.accessToken = access_token;
	this.fileSize = filesize;
	this.chunkUploadUrl = '';
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
			308: function() {
					uploadexecutor.currentByteOffset = byteOffsetEnd + 1;
					uploadexecutor.chunkUploaded(data);
				}
			},
			success: function(data) {
				uploadexecutor.chunkUploaded(data);
				uploadexecutor.complete(data.id);
			},
			error: function(xhr, status, error) {
				
			}
		});
	});
}
GoogleDriveDataUploadExecutor.prototype.uploadWhole = function(data){
	if(this.chunkUploadUrl == ''){
		this.chunkUploadUrl = this.getUploadUrl();
	}
	var executor = this;
	this.chunkUploaded = function(response){
		//get the offsets
		var end_offset = (executor.currentByteOffset+CHUNK_SIZE-1>executor.fileSize-1)?executor.fileSize-1:executor.currentByteOffset+CHUNK_SIZE-1;
		//create the sub array buffer
		executor.chunkUpload(data.slice(executor.currentByteOffset, end_offset));
	};
	var first_end_offset = (executor.currentByteOffset+CHUNK_SIZE-1>executor.fileSize-1)?executor.fileSize-1:executor.currentByteOffset+CHUNK_SIZE-1;
	this.chunkUpload(data.slice(executor.currentByteOffset, first_end_offset));
}
function OneDriveDataUploadExecutor(filename, filetype, access_token, filesize){
	this.currentByteOffset = 0;
	this.fileName = filename;
	this.fileType = filetype || 'application/octet-stream';;
	this.accessToken = access_token;
	this.fileSize = filesize;
	this.chunkUploadUrl = '';
}
OneDriveDataUploadExecutor.prototype.uploadChunk = function(data){
	console.log('chunk upload for onedrive is not supported');
}
OneDriveDataUploadExecutor.prototype.uploadWhole = function(data){
	var uploadexecutor = this;
	var reader = new FileReader();
	reader.onload = function(e){
		var bindata = reader.result;
		var xhr = new XMLHttpRequest();
		xhr.onload = function(e){
			uploadexecutor.complete(e.data.id);
		};
		xhr.open('PUT', 'https://apis.live.net/v5.0/me/skydrive/files/'+uploadexecutor.fileName+'?access_token='+uploadexecutor.accessToken+'&downsize_photo_uploads=false&overwrite=false', true);
		xhr.send(bindata);
	}
	reader.readAsArrayBuffer(data);
}
function DataDownloadExecutorFactory(){
	
}
DataDownloadExecutorFactory.createDataDownloadExecutor(storage_account, file_id){
	if(storage_account.token_type == 'onedrive'){
		return new OneDriveDataDownloadExecutor(storage_account.access_token, file_id);
	}else if(storage_account.token_type == 'googledrive'){
		return new GoogleDriveDataDownloadExecutor(storage_account.access_token, file_id);
	}
}
function DataDownloadExecutor(access_token, file_id){//interface
	//function downloadChunk(byte_offset_start, byte_offset_end, callback)//callback(data:blob)
	//function downloadWhole(callback)//callback(data:blob)
}
//concrete classes for each cloud provider
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
			oReq.open("GET", executor.downloadUrl, true);
			oReq.responseType = "arraybuffer";
			oReq.setRequestHeader('Authorization', 'Bearer '+executor.accessToken);
			oReq.setRequestHeader('Range', 'bytes='+byte_offset_start+'-'+byte_offset_end);
			oReq.onload = function(oEvent) {
				var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
				callback(blob);
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
			oReq.setRequestHeader('Authorization', 'Bearer '+accessToken);
			oReq.onload = function(oEvent) {
				var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
				callback(blob);
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