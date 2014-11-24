<input type='file' id='fileinput'>choose file</input>
<button onclick='uploadfile()'>upload</button>
<button onclick='showcookie()'>show cookie</button>
<button onclick='testencode()'>encode</button>
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>

<script src="//js.live.net/v5.0/wl.js"></script>
<script>
function testencode(){
    var obj = {
        'name1': 'val1',
        'obj1': {
            'objn1':'objv1'
        }
    }
    alert(jQuery.param(obj));
}
function writeWLCookie(){
    var wl_auth = {
        'access_token': expacc.token,
        'scope': ['wl.skydrive_update','wl.basic','wl.offline_access','wl.contacts_skydrive'],
        'expires_in': 3600
    }
    document.cookie='wl_auth='+jQuery.param(wl_auth)+';';
}
function showcookie(){
    alert(document.cookie);
}
function uploadfile(){
    var file = document.getElementById('fileinput').files[0];
    if(file){
        uploadFileToOneDrive(file);
    }
}
function uploadFileOnedriveApi() {
    //write cookies
    writeWLCookie();
    var scope = 'wl.skydrive_update wl.basic wl.offline_access wl.contacts_skydrive';
    WL.upload({
                path: "folder.e8fb92ade8af1352.E8FB92ADE8AF1352!54",
                element: "fileinput",
                overwrite: "rename"
            }).then(
                function (response) {
                    alert(response);
                },
                function (responseFailed) {
                    alert(responseFailed);
                }
            );
    /*
    WL.init();
    
    WL.login({
        scope: "wl.skydrive_update"
    }).then(
        function (response) {
            WL.upload({
                path: "folder.e8fb92ade8af1352.E8FB92ADE8AF1352!54",
                element: "fileinput",
                overwrite: "rename"
            }).then(
                function (response) {
                    alert(response);
                },
                function (responseFailed) {
                    alert(responseFailed);
                }
            );                    
        },
        function (responseFailed) {
            alert(responseFailed);
        }
    );
    */
}

function uploadFileToOneDrive(file){
    
	//oReq.responseType = "arraybuffer";
	//oReq.setRequestHeader('Authorization', 'Bearer '+accessToken);
	//oReq.setRequestHeader('Content-Type', 'application/octet-stream');
	//var blob = {};
    var reader = new FileReader();
    reader.onload = function(evt){
        var data = evt.target.result;
        //alert(data.byteLength);
        //console.log(typeof(file));
        //console.log(typeof(data));
        var oReq = new XMLHttpRequest();
        //oReq.open("PUT", 'https://apis.live.net/v5.0/folder.e8fb92ade8af1352.E8FB92ADE8AF1352!54/files/'+file.name+'?access_token='+expacc.token, true);
        oReq.open("PUT", 'https://apis.live.net/v5.0/me/skydrive/files/'+file.name+'?access_token='+expacc.token, true);
        oReq.onload = function(oEvent) {
            //var blob = new Blob([oReq.response], {type: oReq.getResponseHeader('content-type')});
            console.log(oReq.response);
        };
        oReq.send(new Int8Array(data));
    };
    //alert(file.size);
    reader.readAsArrayBuffer(file);
    
	
    /*
    var contentType = file.type || 'application/octet-stream';
    $.ajax({
        //url: 'https://apis.live.net/v5.0/me/skydrive/files/'+file.name+'?access_token='+expacc.token,
        url: 'https://apis.live.net/v5.0/folder.e8fb92ade8af1352.E8FB92ADE8AF1352!54/files/'+file.name+'?access_token='+expacc.token,
        type: 'PUT',
        processData: false,
        headers: {
          'Content-Type': 'application/octet-stream',
          //'Content-Type': 'application/json; charset=UTF-8',
          //'X-Upload-Content-Type': file.type,
          //'X-Upload-Content-Length': ''+file.size
        },
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
    */
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
var expacc = {};
$(document).ready(function(){
	accounts = getAccounts();
	expacc = accounts[1];
});
</script>
