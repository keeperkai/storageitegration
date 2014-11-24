function getQuotaForGoogleDriveAccount(accessToken){
  var output = {};
  $.ajax({
    url: 'https://www.googleapis.com/drive/v2/about',
    type: 'GET',
    //processData: false,
    headers: {
      'Authorization': 'Bearer '+accessToken,
      //'Content-Type': 'application/json; charset=UTF-8',
      //'X-Upload-Content-Type': file.type,
      //'X-Upload-Content-Length': ''+file.size
    },
    async: false,
    success: function(data, textstatus, request) {
      output['totalQuota'] = data['quotaBytesTotal'];
      output['usedQuota'] = data['quotaBytesUsed'];
      output['freeQuota'] = output.totalQuota - output.usedQuota;
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
function deleteFileFromGoogleDrive(storage_id, accessToken){
  $.ajax({
    url: 'https://www.googleapis.com/drive/v2/files/'+storage_id,
    type: 'DELETE',
    //processData: false,
    headers: {
      'Authorization': 'Bearer '+accessToken,
      //'Content-Type': 'application/json; charset=UTF-8',
      //'X-Upload-Content-Type': file.type,
      //'X-Upload-Content-Length': ''+file.size
    },
    async: false,
    success: function(data, textstatus, request) {
      //console.log(data);
    },
    error: function(xhr, status, error) {
      var err = xhr.responseText;
      console.log(err);
      alert(err);
      alert(error);
    }
  });
}
function getGoogleUploadURL(accessToken, fileName, fileType, fileSize, convert) {
  convert = (typeof convert === "undefined") ? false : convert;
  var uploadlocation = '';
	var contentType = fileType || 'application/octet-stream';
	var metadata = {
		'title': fileName,
		'mimeType': contentType
	};
	var requesturlwithquery = 'https://www.googleapis.com/upload/drive/v2/files?uploadType=resumable';
	if(convert){
		requesturlwithquery +='&convert=true'
	}
	//requesturlwithquery = encodeURIComponent(requesturlwithquery);
	metadata = JSON.stringify(metadata);
	var uploadlocation = {};
	$.ajax({
		url: requesturlwithquery,
		type: 'POST',
		processData: false,
		headers: {
			'Authorization': 'Bearer '+accessToken,
			'Content-Type': 'application/json; charset=UTF-8',
			'X-Upload-Content-Type': fileType,
			'X-Upload-Content-Length': ''+fileSize
		},
		data: metadata,
		async: false,
		success: function(data, textstatus, request) {
					uploadlocation = request.getResponseHeader('location');
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
  return uploadlocation;
}

function uploadFileToGoogle(url, accessToken, file) {//returns the storage_id
  var storage_id = '';
  if (file){
    var contentType = file.type || 'application/octet-stream';
    $.ajax({
      url: url,
      type: 'PUT',
      processData: false,
      headers: {
        'Authorization': 'Bearer '+accessToken,
        'Content-Type': contentType,
        'Content-Range': 'bytes 0-'+(file.size-1)+'/'+file.size,
      },
      data: file,
      async: false,
      success: function(data) {
        storage_id = data.id;
      },
      error: function(xhr, status, error) {
        var err = xhr.responseText;
        console.log(err);
        alert(err);
        alert(error);
      }
    });
  }
  return storage_id;
}
function getGoogleDownloadURL(sourceToken, sourceStorageId){
	//get the file meta data, this includes the download link
	var downloadurl = '';
	$.ajax({
		url: 'https://www.googleapis.com/drive/v2/files/'+sourceStorageId,
		type: 'GET',
		processData: false,
		headers: {
			'Authorization': 'Bearer '+sourceToken,
		},
		async: false,
		success: function(data) {
			downloadurl = data.downloadUrl;
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
	return downloadurl;
}
function getGoogleFileSize(sourceToken, sourceStorageId){
	//get the file meta data, this includes the download link
	var size = 0;
	$.ajax({
		url: 'https://www.googleapis.com/drive/v2/files/'+sourceStorageId,
		type: 'GET',
		processData: false,
		headers: {
			'Authorization': 'Bearer '+sourceToken,
		},
		async: false,
		success: function(data) {
			size = data.fileSize;
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
	return size;
}
function getGoogleFileChunk(accessToken, downloadURL, byteOffsetStart, byteOffsetEnd, callback){
	var oReq = new XMLHttpRequest();
	oReq.open("GET", downloadURL, true);
	oReq.responseType = "arraybuffer";
	oReq.setRequestHeader('Authorization', 'Bearer '+accessToken);
	oReq.setRequestHeader('Range', 'bytes='+byteOffsetStart+'-'+byteOffsetEnd);
	//var blob = {};
	oReq.onload = function(oEvent) {
		var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
		//alert(blob.size);
		callback(blob);
	};

	oReq.send();
	
	//get the file chunk data and write it to output on success
	/*
	var chunkdata = {};
	$.ajax({
		url: downloadURL,
		type: 'GET',
		//dataType: 'arraybuffer',//doesn't work
		processData: false,
		headers: {
			'Authorization': 'Bearer '+accessToken,
			'Range': 'bytes='+byteOffsetStart+'-'+byteOffsetEnd
		},
		async: false,
		success: function(data) {
			chunkdata = data;
			alert(typeof(data)+' : '+data.size);
			//chunkdata = new Blob([data], {type: "application/octet-stream"});
			//alert(chunkdata.size);//11158, when it's supposed to be around 6k
			//chunkdata = data;
		},
		error: function(xhr, status, error) {
			var err = xhr.responseText;
			console.log(err);
			alert(err);
			alert(error);
		}
	});
	return chunkdata;
	*/
}
 
function uploadChunkToGoogle(fileData, accessToken, uploadURL, byteOffsetStart, totalSize, callback){
	var storage_id = false;
	var byteOffsetEnd = byteOffsetStart+fileData.size-1;
	var contentType = fileData.type || 'application/octet-stream';
	/*
	var oReq = new XMLHttpRequest();
	oReq.open("PUT", uploadURL, true);
	oReq.setRequestHeader('Authorization', 'Bearer '+accessToken);
	oReq.setRequestHeader('Content-Type', contentType);
	oReq.setRequestHeader('Content-Range', 'bytes '+byteOffsetStart+'-'+byteOffsetEnd+'/'+totalSize);
	//var blob = {};
	oReq.onload = function(oEvent) {
		var obj = oReq.response;
		alert(typeof(obj)+":"+obj);
		callback(obj.id);
	};

	oReq.send();
	*/
	$.ajax({
		url: uploadURL,
		type: 'PUT',
		processData: false,
		headers: {
			'Authorization': 'Bearer '+accessToken,
			'Content-Type': contentType,
			//'Content-Range': 'bytes '+byteOffsetStart+'-'+(byteOffsetStart+fileData.size-1)+'/'+totalSize
			'Content-Range': 'bytes '+byteOffsetStart+'-'+byteOffsetEnd+'/'+totalSize
		},
		data: fileData,
		async: false,
		statusCode: {
    308: function() {
				//alert('308 called');
				callback('not done');
			}
		},
		success: function(data) {
			callback(data.id);
		},
		error: function(xhr, status, error) {
			
		}
	});
	//return storage_id;
}