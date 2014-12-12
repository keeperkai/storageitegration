<button onclick='runExecutorTest();'>
run test!
</button>
<script src="<?php echo base_url();?>asset/js/jobexecutors.js"></script>
<script>
var accounts = getAccounts();
function runExecutorTest(){
	var chunksize = 10*1024*1024;//4096;
	var googleacc = accounts[0];
	//var googlefileid = '0B6FHqPdI70kubU1JdGlRRGptVVE';//3mb file(.blend file)
    var googlefileid = '0B6FHqPdI70kucTJ4UjJZVmRMakU';//300mb file(mp4 file)
    var googlefileid = '0B5RzcMP4KMEsc0JaMGw5YlhUZ0U';//5.xxgb file(mp4 file)
	//var googlefilesize = 3241436;
    //var googlefilesize = 294627482;
    var googlefilesize = 5293561980;
	//var googlefilemimetype = 'application/octet-stream';
    var googlefilemimetype = 'video/mp4';
	//var googlefilename = 'testmodel.blend';
    var googlefilename = 'whatever.mp4';
	var onedriveacc = accounts[1];
	var onedrivefileid = 'file.4acff2ecd6502abe.4ACFF2ECD6502ABE!124';
	var onedrivefilesize = 10803;
	var onedrivefilemimetype = 'image/jpg';
	var onedrivefilename = 'testgraph.jpg';
	
	//testcase 1: download a googledrive file in chunks and upload as whole to onedrive
	/*
	var downloader = new GoogleDriveDataDownloadExecutor(googleacc.token, googlefileid);
	var uploader = new OneDriveDataUploadExecutor(googlefilename, googlefilemimetype, onedriveacc.token, googlefilesize);
	var filedatas = [];
	//merge file datas into one blob
	var b = null;
	var bytestart = 0;
	function requestchunk(data){
		if(b == null){
			b = data;
		}else{
			b = new Blob([b, data], { type: googlefilemimetype });
		}
		
		bytestart = bytestart+data.size;
		if(bytestart>googlefilesize-1) {
			filedownloaded();
			return;
		};
		var byteend = (bytestart+chunksize-1> googlefilesize -1)? googlefilesize -1:bytestart+chunksize-1;
		downloader.downloadChunk(bytestart, byteend, requestchunk);	
	}
	downloader.downloadChunk(0, chunksize-1, requestchunk);
	
	function filedownloaded(){
		//start uploading
		console.log('filedownloaded called');
		uploader.complete = function(id){
			console.log('file uploaded, id = '+id);
		};
		uploader.uploadWhole(b);
	}
	*/
	
	//testcase 2:download a onedrive file in chunks and upload as whole to googledrive
	//this testcase is impossible, because onedrive doesn't support the range header for cors
	//however I've seen some discussion about range header being supported but only not for cors.
	/*
	var downloader = new OneDriveDataDownloadExecutor(onedriveacc.token, googlefileid);
	var uploader = new GoogleDriveDataUploadExecutor(onedrivefilename, onedirvefilemimetype, onedriveacc.token, onedrivefilesize);
	var b = null;
	var bytestart = 0;
	function requestchunk(data){
		if(b == null){
			b = data;
		}else{
			b = new Blob([b, data], { type: onedrivefilemimetype });
		}
		
		bytestart = bytestart+data.size;
		if(bytestart>onedrivefilesize-1) {
			filedownloaded();
			return;
		};
		var byteend = (bytestart+chunksize-1> onedrivefilesize -1)? onedrivefilesize -1:bytestart+chunksize-1;
		downloader.downloadChunk(bytestart, byteend, requestchunk);	
	}
	downloader.downloadChunk(0, chunksize-1, requestchunk);
	
	function filedownloaded(){
		//start uploading
		uploader.complete = function(id){
			console.log('file uploaded, id = '+id);
		};
		uploader.uploadWhole(b);
	}
	*/
	//new test case 2: download whole file from onedrive and upload whole to googledrive
    /*
	var downloader = new OneDriveDataDownloadExecutor(onedriveacc.token, onedrivefileid);
	var uploader = new GoogleDriveDataUploadExecutor(onedrivefilename, onedrivefilemimetype, onedriveacc.token, onedrivefilesize);
	downloader.downloadWhole(function(data){
		uploader.complete = function(id){
			console.log('file uploaded id = '+id);
		};
		uploader.uploadWhole(data);
	});
    */
	//testcase3: download a googledrive file in chunks and upload as chunks to google drive
	
	var downloader = new GoogleDriveDataDownloadExecutor(googleacc.token, googlefileid);
	var uploader = new GoogleDriveDataUploadExecutor(googlefilename, googlefilemimetype, googleacc.token, googlefilesize);
	//merge file datas into one blob
	var bytestart = 0;
	uploader.complete = function(id){
		console.log('file successfully uploaded, id = '+id);
	}
	function requestchunk(resp){
		var byteend = (bytestart+chunksize-1>googlefilesize-1)?googlefilesize-1:bytestart+chunksize-1;
		if(bytestart>googlefilesize-1){
			return;
		}else{
			downloader.downloadChunk(bytestart, byteend, chunkdownloaded);
		}
	}
	uploader.chunkUploaded = requestchunk;
	function chunkdownloaded(data){
		bytestart+=data.size;
		uploader.uploadChunk(data);
	}
	requestchunk();
	
}
function getAccounts(){
  var output = [];
  $.ajax({
    url: '../../storageaccounts/getstorageaccounts',
    type: 'GET',
    async: false,
    success: function(data, textstatus, request) {
      output = data;
    },
    error: function(xhr, status, error) {
      var err = xhr.responseText;
      console.log(err);
      alert(err);
      alert(error);
    }
  });
  return output;
}
</script>