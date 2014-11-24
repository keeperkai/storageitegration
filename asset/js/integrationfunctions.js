function getStorageAccountQuotas(accounts){
  for(var i=0;i<accounts.length;++i){
    var provider = accounts[i]['token_type'];
    var accessToken = accounts[i]['token'];
    var quotas = {};
    if(provider == 'googledrive'){
      quotas = getQuotaForGoogleDriveAccount(accessToken);
    }else if(provider == 'onedrive'){
    
    }else if(provider == 'dropbox'){
    
    }
    accounts[i]['quotas'] = quotas;
  }
  return accounts;
}
function decideAccountToUploadTo(accounts, filesize){//accounts should have quotas
  if(accounts.length == 0) return null;
  accounts = getStorageAccountQuotas(accounts);
  
  return bestFit(accounts, filesize);
}
function bestFit(accounts, filesize){
  accounts.sort(function(a, b){
    return a.quotas.freeQuota - b.quotas.freeQuota;
  });
  var chosen_account = null;
  for(var i=0;i<accounts.length;++i){
    if(filesize<=accounts[i].quotas.freeQuota){
      chosen_account = accounts[i];
      break;
    }
  }
  return chosen_account;
}
function moveFileChunk(sourceProvider, sourceAccessToken, sourceFileId, byteOffsetStart, byteOffsetEnd, targetProvider, targetAccessToken, targetFileName, chunkSize, completed){
	chunkSize = chunkSize || 4*1024*1024;//4MB
	var totalsize = byteOffsetEnd-byteOffsetStart+1;
	//get the download url
	var downloadurl = '';
	if(sourceProvider=='googledrive'){
		downloadurl = getGoogleDownloadURL(sourceAccessToken, sourceFileId);
	}else if(sourceProvider=='onedrive'){
	
	}
	//get the upload url
	var uploadurl = '';
	if(targetProvider=='googledrive'){
		uploadurl = getGoogleUploadURL(targetAccessToken, targetFileName, 'application/octet-stream', totalsize, false);
		//uploadurl = getGoogleUploadURL(targetAccessToken, targetFileName, 'image/jpeg', totalsize, false);
	}else if(targetProvider=='onedrive'){
	
	}
	//start moving the chunk data
	/*
	var offset = byteOffsetStart;
	var uploadoffset = 0;
	var lastchunk = false;
	var fileid = '';
	while(!lastchunk){
		var chunkdata = '';
		var chunklen = chunkSize;
		var lastbyteoffset = offset+chunkSize-1;
		if(lastbyteoffset>=byteOffsetEnd){
			lastchunk = true;
			chunklen = byteOffsetEnd-offset+1;
			lastbyteoffset = byteOffsetEnd
		}
		if(sourceProvider=='googledrive'){
			chunkdata = getGoogleFileChunk(sourceAccessToken, downloadurl, offset, lastbyteoffset);
			alert(typeof(chunkdata)+', size:'+chunkdata.size);
		}else if(sourceProvider=='onedrive'){
		
		}
		
		if(targetProvider=='googledrive'){
			//fileid = uploadChunkToGoogle(targetAccessToken, uploadurl, chunkdata, 'application/octet-stream', offset, lastbyteoffset, totalsize);
		}else if(targetProvider=='onedrive'){
		
		}
		offset = lastbyteoffset+1;
	}
	return fileid;
	*/
	var sourceOffsetStart = byteOffsetStart;
	var sourceOffsetEnd = byteOffsetStart+chunkSize;
	if(sourceOffsetEnd>byteOffsetEnd) sourceOffsetEnd = byteOffsetEnd;
	var targetOffsetStart = 0;
	var targetOffsetEnd = chunkSize;
	if(targetOffsetEnd>totalsize-1) targetOffsetEnd = totalsize-1;
	/*
	
	moveChunk(downloadurl, uploadurl, sourceProvider, sourceAccessToken, sourceOffsetStart, sourceOffsetEnd,
		targetProvider, targetAccessToken, targetOffsetStart, targetOffsetEnd, chunkSize, function(){
			sourceOffsetStart = sourceOffsetEnd+1;
			sourceOffsetEnd = sourceOffsetStart+chunkSize;
			if(sourceOffsetEnd>byteOffsetEnd) sourceOffsetEnd = byteOffsetEnd;
			targetOffsetStart = targetOffsetEnd+1;
			targetOffsetEnd = targetOffsetStart+chunkSize;
			if(targetOffsetEnd>totalsize-1) targetOffsetEnd = totalsize-1;
			moveChunk(...);
	});
	*/
	moveChunk(byteOffsetStart, byteOffsetEnd, downloadurl, uploadurl, sourceProvider, sourceAccessToken, sourceOffsetStart, 
		targetProvider, targetAccessToken, targetOffsetStart, chunkSize, completed);
}

function moveChunk(totalOffsetStart, totalOffsetEnd, downloadUrl, uploadUrl, sourceProvider, sourceAccessToken, sourceOffsetStart, targetProvider, targetAccessToken, targetOffsetStart, chunkSize, callback){
	//calculate the offsets for download
	console.log('source offset: '+sourceOffsetStart+'. at the beginning');
	//alert('source offset: '+sourceOffsetStart+'. at the beginning');
	var totalSize = totalOffsetEnd-totalOffsetStart+1;
	var dloffsetstart = sourceOffsetStart;
	var dloffsetend = sourceOffsetStart+chunkSize-1;
	if(dloffsetend>totalOffsetEnd) dloffsetend = totalOffsetEnd;
	getGoogleFileChunk(sourceAccessToken, downloadUrl, dloffsetstart, dloffsetend, function(blob){
		//alert(blob.size);
		uploadChunkToGoogle(blob, targetAccessToken, uploadUrl, targetOffsetStart, totalSize, function(id){
			var newsourcestart = dloffsetend+1;
			if(newsourcestart>totalOffsetEnd){//it's done, we should get the id
				callback(id);
			}else{//call this function again, with the new offsets
				var newtargetstart = targetOffsetStart + (newsourcestart-dloffsetstart);
				moveChunk(totalOffsetStart, totalOffsetEnd, downloadUrl, uploadUrl, sourceProvider, sourceAccessToken, newsourcestart, targetProvider, targetAccessToken, newtargetstart, chunkSize, callback);
			}
		});
	});
}