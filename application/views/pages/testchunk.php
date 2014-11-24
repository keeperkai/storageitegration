input source file id:<input type='text' id='fileid'></input><br>
<button onclick='movechunks();'>move chunks</button>

<div id='documenthostingdialog' hidden>
你想要上傳的檔案可以轉換成線上文件，轉換之後就能直接在瀏覽器中線上編輯，若您與其他使用者共享此文件，其他使用者能夠和你
同時在線上編輯。
<table id='selecthostingtable'>
</table>
</div>
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>
<script>
function createFormAndSubmit(url, method, formdata){
	var formstring = '';
	$(document.body).append('<form id="requestform" action="'+url+'" method="'+method+'"><input id="formdata" type="hidden" name="formdata"></input></form>');
	$('#formdata').val(JSON.stringify(formdata));
	
	//formstring += '<form id="requestform" action="'+url+'" method="'+method+'">';
	//var form = $('#requestform');
	/*
	for(var property in formdata){
		if(formdata.hasOwnProperty(property)){
			//alert(property+" : "+formdata[property]);
			//$(document.body).append('<input type="hidden" value="'+formdata[property]+'"></input>');
			
			formstring+='<input type="hidden" name="'+property+'" value="'+JSON.stringify(formdata[property])+'"></input>';
			//alert(JSON.stringify(formdata[property]));
		}
	}
	*/
	//formstring+= '<input type="submit"></input></form>';
	//$(document.body).append(formstring);
	var domform = document.getElementById("requestform");
	//alert(domform.innerHTML);
	domform.submit();
	form.remove();
}
function movechunks(){
	var sourcefileid = $('#fileid').val();
	var filesize = getGoogleFileSize(expacc.token, sourcefileid);
	var chunk1id = '';
	var chunk2id = '';
	moveFileChunk(expacc.token_type, expacc.token, sourcefileid, 0, Math.floor(filesize/2), expacc.token_type, expacc.token, 'chunk1', 1024*1024*4,
		function(id){
			chunk1id = id;
			moveFileChunk(expacc.token_type, expacc.token, sourcefileid, Math.floor(filesize/2)+1, filesize-1, expacc.token_type, expacc.token, 'chunk2', 1024*1024*4,
				function(id2){
					chunk2id = id2;
					//all the chunks are done
					var formdata = {
						'chunk_ids':[id,id2],
						'access_token':expacc.token,
						'storage_account_id':expacc.storage_account_id
					};
					createFormAndSubmit('../../files/downloadwholefilefromchunks', 'post', formdata);
				}
			);
		}
	);
	
	//var chunk3id =	moveFileChunk(expacc.token_type, expacc.token, sourcefileid, filesize*2/3+1, filesize-1, expacc.token_type, expacc.token, 'chunk3', 1024*1024*4);
	//call the service that will merge those files and download
	
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
var accounts = [];
var expacc = false;
$(document).ready(function(){
	accounts = getAccounts();
	expacc = accounts[0];
});
</script>
