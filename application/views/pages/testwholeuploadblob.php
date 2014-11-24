<input type='file' id='file'>choose file</input>
<button onclick='return upload();'>upload</button>
<button onclick='uploadGeneratedBlob();'>upload generated file</button>
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>

<script>
function uploadGeneratedBlob(){
	var myblob = new Blob(['Hello world bitches!!!!!!!!!yeahyeahyeah!!!'],{type:'text/plain'});
	var file = document.getElementById('file').files[0];
    myblob.name = 'HelloWorld.txt';
	myblob.lastModified = '1416552891000';
	myblob.lastModifiedDate = 'Fri Nov 21 2014 14:54:51 GMT+0800';
	for(var prop in myblob){
		console.log(prop + ' : ' + myblob[prop]);
	}
	/*
	for(var prop in file){
		console.log(prop + ' : ' + file[prop]);
	}
	*/
	var formdata = new FormData();
	formdata.append('file', myblob);
	
	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'https://apis.live.net/v5.0/me/skydrive/files?access_token='+expacc.token, true);//../../test/justechook
	//xhr.open('POST', '../../test/justechook', true);
	xhr.send(formdata);
	//this will upload the file, but is unable to set the filename = 'HelloWorld.txt', it seems that the formdata has it's
	//own way of serializing blobs and files, for blobs the filename = 'blob' always, you cannot change it.
	//as for files they look at the name property to determine that.
}
function upload(){
    var formdata = new FormData();
	var file = document.getElementById('file').files[0];
	formdata.append('file', file.slice(0, Math.floor(file.size/2)));
	/*
		when we slice up the file, the file name is still the old filename in the form data
	*/
	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'https://apis.live.net/v5.0/me/skydrive/files?access_token='+expacc.token, true);//../../test/justechook
	//xhr.open('POST', '../../test/justechook', true);
	xhr.send(formdata);
}
/*
function upload(){
    var file = document.getElementById('file').files[0];
	var xhr = new XMLHttpRequest();
	xhr.open('PUT', 'https://apis.live.net/v5.0/me/skydrive/files/'+file.name+'?access_token='+expacc.token, true);
	xhr.send(file);//the content-type header image/png is not supported, any content type header is not supported by 
	//this resource of onedrive...which is stupid because according to w3c standards when xhr2 sends blob data, it
	//will be forced to send the mime-type of the blob, in other words, onedrive does not support blob sending through the put
	//method.
	
}
*/
/*
function upload(){
    var file = document.getElementById('file').files[0];
	var reader = new FileReader();
	reader.onload = function(e){
		var filedata = e.target.result;
		var xhr = new XMLHttpRequest();
        xhr.open('PUT', 'https://apis.live.net/v5.0/me/skydrive/files/'+file.name+'?access_token='+expacc.token, true);
		xhr.send(filedata);
	}
	reader.readAsArrayBuffer(file);
}
*/
/*
function upload(){
	var file = document.getElementById('file').files[0];
	var formstring = '<form enctype="multipart/form-data" method="post" name="fileinfo">'
	+'<input type="file">
	+'</form>;
	;
    $.ajax({
        url: 'https://apis.live.net/v5.0/me/skydrive/files?access_token='+expacc.token,
        type: 'POST',
		//headers: {'Content-Type':file.type},
        processData: false,
        data: file,
        async: false,
        success: function(data, textstatus, request) {
          alert(data);
        },
        error: function(xhr, status, error) {
          var err = xhr.responseText;
          console.log(err);
          alert(err);
          alert(error);
        }
    });
    return false;
}
*/
/*
function upload(){
    var file = document.getElementById('file').files[0];
    $.ajax({
        url: '../../test/justechook',
        type: 'POST',
        processData: false,
        data: file,
        async: false,
        success: function(data, textstatus, request) {
          alert(data);
        },
        error: function(xhr, status, error) {
          var err = xhr.responseText;
          console.log(err);
          alert(err);
          alert(error);
        }
    });
    return false;
}
*/
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
var accounts = [];
var expacc = {};
$(document).ready(function(){
	accounts = getAccounts();
	expacc = accounts[1];
    
});
</script>
