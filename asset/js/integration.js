function registerFileToSystem(type, filename, extension, parent_file_id, storage_file_data){
  var output = false;
  $.ajax({
    url: '../../files/registerfiletosystem',
    type: 'POST',
    data: {
    	'file_type': type,
      'name': filename,
      'extension': extension,
      'parent_virtual_file_id': parent_file_id,
			'storage_file_data': JSON.stringify(storage_file_data)
		},
    async: false,
    success: function(data, textstatus, request) {
      renderFileSystem();
    },
    error: function(xhr, status, error) {
      var err = xhr.responseText;
      console.log(err);
      alert(err);
      alert(error);
    }
  });
  return output;//outputs the virtual_file_id of the just registered file
}
function addPermissionForThisUser(virtual_file_id,role){
	var output = false;
	$.ajax({
    url: '../../files/addpermissionforthisuser',
    type: 'POST',
    data: {
    	'virtual_file_id': virtual_file_id,
			'role': role
		},
    async: false,
    success: function(data, textstatus, request) {
      //alert('檔案登錄成功');
      //var filetree = getFileTree();
      //renderFileSystem(filetree);
			if(data.status == 'success'){
				output = true;
			}
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
function moveFileInFileSystem(files, parent_id){
  var output = false;
  $.ajax({
    url: '../../files/movefileinsystem',
    type: 'POST',
    data: {
      'files': files,
      'parent_virtual_file_id': parent_id,
    },
    async: false,
    success: function(data, textstatus, request) {
      renderFileSystem();
      output = true;
    },
    error: function(xhr, status, error) {
      var err = xhr.responseText;
      console.log(err);
      alert(err);
      alert(error);
    }
  });
  return true;
}

function getCurrentRightClickedParentId(){
  if(rightClickedNode==null){
    return -1;
  }
  if(rightClickedNode.file_type == 'folder'){
    return rightClickedNode.virtual_file_id;
  }else{
    return rightClickedNode.parent_virtual_file_id;
  }
}
var rightClickedNode = {};
function fileOnRightClick(event, treeId, treeNode){
  //event.preventDefault();
  rightClickedNode = treeNode;
	$("#rightclickdialog").dialog( "option", "position", { my: "left top", of: event } );
  $("#rightclickdialog").dialog("open");
  $("#rightclickdialog").show();
}
/*
function fileOnClick(event, treeId, treeNode, clickFlag){
  if (clickFlag==1) {//single select
    selectedNodes = [];
  }
  selectedNodes.push(treeNode);
}
*/
function makeDir(parent_id, filename){
  if(parent_id==-2){
    alert('無法在共享根目錄下創造資料夾');
    return;
  }
  registerFileToSystem('folder', filename, 'dir', parent_id);
	
}
function showFilenameDialog(){
  $('#filenamedialog').dialog('open');
  return false;
}
/*
function checkFileGrouping(file){
  var ext = file.name.substring(file.name.lastIndexOf('.')+1);
  var mime = file.type;
  var extensionToFileGroupDataMap = {
    'xls': {'highest_functionality':'editable', 'capable_providers':['onedrive']};
    'xlsx': {'highest_functionality':'editable', 'capable_providers':['onedrive']};
  };
}
*/
function showNoSuitableAccountDialog(msg){
  return confirm(msg);
}
function getUploadInstructions(file, chosen_provider){
  //convertToDoc = (typeof convertToDoc === "undefined") ? false : convertToDoc;
  //chosen_provider = (typeof chosen_provider === "undefined") ? 'none' : chosen_provider;
  var ext = file.name.substring(file.name.lastIndexOf('.')+1);
  var mime = file.type;
  var size = file.size;
  var name = file.name
  
  var instructions = {};
  $.ajax({
      url: '../../files/getuploadinstructions',
      type: 'POST',
      async: false,
      data: {
        'extension': ext,
        'mime': mime,
        'size': size,
        'name': name,
      //  'convertToDoc': convertToDoc,
      //  'chosen_provider':chosen_provider
      },
      success: function(data, textstatus, request) {
        instructions = data;
        return instructions;
      },
      error: function(xhr, status, error) {
        var err = xhr.responseText;
        console.log(err);
        alert(err);
        alert(error);
      }
    });
    
}
function executeUploadInstructions(instructions){
  
}
function uploadAndConvertToDocument(file, chosen_provider){
  if(chosen_provider=='treate_as_file'){
    uploadNoneDocumentFile(file);
    return;
  }
	/*
  var instructions = getUploadInstructions(file, true, chosen_provider);
  if(instructions.status=='success'){
    var result = executeUploadInstructions(instructions);
  }else if(instructions.status=='no_suitable_account'){
    var decision = showNoSuitableAccountDialog(instructions.error_message);
    if(decision){
      window.location.href = '../../pages/view/manageaccount';
    }
  }
	*/
	//find chosen_provider account
	var chosen = null;
	for(var i=0;i<accounts.length;++i){
		if(accounts[i].token_type == chosen_provider){
			chosen = accounts[i];
		}
	}
	//currently only google drive
	var url = getGoogleUploadURL(chosen.token, file.name, file.type, file.size, true);
	var storage_id = uploadFileToGoogle(url, chosen.token, file);
	var storage_file_data = [{
		'storage_account_id':chosen.storage_account_id,
		'storage_file_type':'document',
		'byte_offset_start': 0,
		'byte_offset_end': 0,
		'storage_id': storage_id
	}];
	resetUploadItems();
  var parent_id = getCurrentRightClickedParentId();
  //console.log(parent_id);
  if(parent_id==-2){
    parent_id=-1;
  }
	registerFileToSystem('file', file.name, file.name.substring(file.name.lastIndexOf('.')+1), parent_id, storage_file_data);
}
function resetUploadItems(){
	resetFileChooser();
	$('#fileuploaddialog').dialog('close');
	$('#documenthostingdialog').dialog('close');
	//renderFileSystem();
}
function chooseStorageProvider(file){
  var ext = file.name.substring(file.name.lastIndexOf('.')+1);
  var size = file.size;
  var output = 'treat_as_file';
  var mapforext = documentExtensionConvertTable[ext];
  //unregister
  
  //load the options
  $('#selecthostingtable').append('<tr><th>雲端硬碟類型</th><th>以後下載時能夠選擇的檔案格式</th><th>檔案占用大小</th><th>選擇此類型</th></tr>');
  for (var provider in mapforext) {
		if (mapforext.hasOwnProperty(provider)) {
			// iterate through each provider
			var rowhtml = '<tr><td>'+provider+'</td><td>';
      //$('#selecthostingtable').append('<tr><td>'+provider+'</td><td>')
      for(var i=0;i<mapforext[provider].download_extensions.length;++i){
        //$('#selecthostingtable').append(mapforext.download_extensions[i]+', ');
				rowhtml+=mapforext[provider].download_extensions[i]+'<br>'
      }
      if(provider=='googledrive'){
        //$('#selecthostingtable').append('</td><td>0 Bytes</td><td>');
				rowhtml+='</td><td>0 Bytes</td><td>';
      }else{
        //$('#selecthostingtable').append('</td><td>'+size+' Bytes</td><td>');
				rowhtml+='</td><td>'+size+' Bytes</td><td>';
      }
      //$('#selecthostingtable').append('</td><td><button class="providerchoice" value="'+provider+'">上傳至'+provider+'</button></td></tr>');
			rowhtml+='<button class="providerchoice" value="'+provider+'">上傳至'+provider+'</button></td></tr>';
			$('#selecthostingtable').append(rowhtml);
    }
		
  }
	$('.providerchoice').off('click');
	$('.providerchoice').click(function(){
		var chosen_provider = $(this).val();
		$('#selecthostingdialog').dialog('close');
		uploadAndConvertToDocument(file, chosen_provider);
		$('#selecthostingtable').empty();
  });
	//alert('before open');
  $('#documenthostingdialog').dialog('open');
  //remove the options
  return output;
}
function uploadNoneDocumentFile(file){
  //files grouping: (i)can be edited on some providers (ii) can be previewed on some providers (iii) other
  //for (i): check if an account capable of providing editing exists(and has space, or can have space after shuffling), tell the user to link a new one
  //for (ii): check if any account capable of providing previewing exists(and has space, or can have space after shuffling), if not, tell the user to link a new one
  //for (iii): just try to find any account with enough size(after shuffling), if there isn't, then tell the user to link a random account.
  //if there isn't then tell them to link one, if there is then just ask for the upload instruction
  //var group = checkFileGrouping(file);//returns group, and highest applicable storage providers
  var instructions = getUploadInstructions(file);
	alert('got instructions');
	/*
  if(instructions.status=='success'){
    var result = executeUploadInstructions(instructions);
  }else if(instructions.status=='no_suitable_account'){
    var decision = showNoSuitableAccountDialog(instructions.error_message);
    if(decision){
      window.location.href = '../../pages/view/manageaccount';
    }
  }
  resetUploadItems();
  renderFileSystem();
	*/
}
function upload() {
  var reader = new FileReader();
  var file = document.getElementById('file').files[0];
  if (file) {
    $('#fileuploadpanel').dialog('close');
    $('#fileuploaddialog').dialog('open');
    /*
    //determine which account to upload to
    var chosenaccount = decideAccountToUploadTo(accounts, file.size);
    var storageprovider = chosenaccount.token_type;
    //upload the file
    if (storageprovider=='googledrive') {
      var uploadlocation = getGoogleUploadUrl(chosenaccount.token, file);
      if (uploadlocation.length>0) {
        var uploadedFileId = uploadFileToGoogle(uploadlocation, chosenaccount.token, file);
      }
    }
    //register the file to our system
    if(uploadedFileId!=undefined){
      //var virtual_file_id = registerFileToSystem(uploadedFileId, 'file', chosenaccount.account_data_id, file.name, '', file.type, 'owner', parent_id);
			var virtual_file_id = registerFileToSystem('file', file.name, file.name.substring(file.name.lastIndexOf('.')+1), parent_id);
			
      registerStorageFileToSystem('virtual_file_id');
    }else{
      alert('檔案上傳失敗');
    }
    */
    var ext = file.name.substring(file.name.lastIndexOf('.')+1);
    var storage_provider_choice = '';
    if(documentExtensionConvertTable[ext]!=undefined){
			chooseStorageProvider(file);
			return;
    }
    uploadNoneDocumentFile(file);
	}
}
function resetFileChooser(){
  $('#file').remove();
  $('#fileuploadpanel').append('<input type="file" name="file" id="file" onchange="return upload();"/>');
}
function searchForStorageAccount(account_data_id){
  for(var i=0;i<accounts.length;++i){
    if(accounts[i]['account_data_id']==account_data_id) return accounts[i];
  }
  return null;
}
function deleteFileOnStorageProvider(node){
  var storage_account = searchForStorageAccount(node['account_data_id']);
  var accessToken = storage_account['token'];
  var provider = storage_account['token_type'];
  var storage_id = node['storage_id'];
  if(provider == 'googledrive'){
    deleteFileFromGoogleDrive(storage_id, accessToken);
  }else if(provider == 'onedrive'){
  
  }else if(provider == 'dropbox'){
  
  }
}
function deleteSelectedFiles(){
  var treeObj = $.fn.zTree.getZTreeObj("filesystempanel");
  var selectedNodes = treeObj.getSelectedNodes();
  if(selectedNodes.length>0){
    $.ajax({
      url: '../../files/deletefiles',
      type: 'POST',
      async: false,
      data: {
        'files': selectedNodes,
      },
      success: function(data, textstatus, request) {
        renderFileSystem();
      },
      error: function(xhr, status, error) {
        var err = xhr.responseText;
        console.log(err);
        alert(err);
        alert(error);
      }
    });
  }
}
function getAccounts(){
  var output = [];
	/*
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
	*/
  return output;
}
function getFileTree(){
  var output = [];
  $.ajax({
    url: '../../files/getfiletreeforuser',
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
function beforeDrag(treeId, treeNodes) {
			for (var i=0,l=treeNodes.length; i<l; i++) {
				if (treeNodes[i].drag === false) {
					return false;
				}
      }
			return true;
		}
		function beforeDrop(treeId, treeNodes, targetNode, moveType) {
			//return targetNode ? targetNode.drop !== false : true;
      if(targetNode){
        //if(targetNode.type != 'dir') return false;
        moveFileInFileSystem(treeNodes, targetNode.file_id);
      }else{//no target node
        moveFileInFileSystem(treeNodes, -1);
      }
      return false;
		}
function getPrevOpenedDirIds(prevnodes){
  var output = {};
  for(var i=0;i<prevnodes.length;++i){
    if(prevnodes[i].file_type == 'folder' && prevnodes[i].open){
      output[prevnodes[i].virtual_file_id] = true;
    }
  }
  return output;
}
function constructZTree(filetree, prevOpenedDirIds){
  
  for (var i=0;i<filetree.length;++i) {
    var file = filetree[i];
	//if it was previously opened, open it now
    if(prevOpenedDirIds.hasOwnProperty(file['virtual_file_id'])){
      file['open'] = true;
      delete(prevOpenedDirIds[file['virtual_file_id']]);
    }
    //set droppable to true if it is a dir, otherwise false.
    if(file['file_type'] == 'folder'){
      file['drop'] = true;
      file['isParent'] = true;
    }else{
      file['drop'] = false;
    }
    //ToDo: set icons according to extension
    
    //--------------------------------
  }
  return filetree;
}
function renderFileSystem(){
  var treedata = getFileTree();
  var prevtree = $.fn.zTree.getZTreeObj('filesystempanel');
  var prevOpenedDirIds = {};
  if(prevtree != null){
    var prevnodes = prevtree.transformToArray(prevtree.getNodes());
    prevOpenedDirIds = getPrevOpenedDirIds(prevnodes);
  }
  var zNodes = constructZTree(treedata, prevOpenedDirIds);
  var setting = {
    edit: {
      enable: true,
      showRemoveBtn: false,
      showRenameBtn: false
    },
    data: {
      simpleData: {
        enable: true
      }
    },
    callback: {
      beforeDrag: beforeDrag,
      beforeDrop: beforeDrop,
      onRightClick: fileOnRightClick
    }
  };
  $.fn.zTree.destroy();
  $.fn.zTree.init($("#filesystempanel"), setting, zNodes);
  
}
function resetFilenameDialog(){
  $('#filenamedialog').find('input').val('');
}
function showUploadPanel(){
  $('#fileuploadpanel').dialog('open');
}
var accounts = [];
var documentExtensionConvertTable = {
  'xls':{'googledrive':{'download_extensions':['ods','xlsx','pdf']}},
  'xlsx':{'googledrive':{'download_extensions':['ods','xlsx','pdf']}},
  'ods':{'googledrive':{'download_extensions':['ods','xlsx','pdf']}},
  'ppt':{'googledrive':{'download_extensions':['pdf','pptx']}},
  'pptx':{'googledrive':{'download_extensions':['pdf','pptx']}},
  'odp':{'googledrive':{'download_extensions':['pdf','pptx']}},
  'doc':{'googledrive':{'download_extensions':['odt','html','pdf','docx']}},
  'docx':{'googledrive':{'download_extensions':['odt','html','pdf','docx']}},
  'odt':{'googledrive':{'download_extensions':['odt','html','pdf','docx']}}
}
$(document).ready(function(){
  accounts = getAccounts();
  //console.log(filetree);
  renderFileSystem();
  //dialog init
  $('#fileuploaddialog').dialog({
    autoOpen: false,
    height: 'auto',
    width: 'auto'
  });
  $('#rightclickmenu').menu();
  $('#rightclickdialog').dialog({autoOpen: false, dialogClass: 'noTitleStuff', width:'auto',height:'auto'});
  $('#rightclickdialog').click(function(){
    $('#rightclickdialog').dialog('close');
  });
	//$("#rightclickdialog").find(".ui-dialog-titlebar").hide();
  $('#filenamedialog').dialog({
    title: '輸入名稱',
    autoOpen: false,
    width:'auto',
    height:'auto',
    buttons: {
      "以此名稱建立資料夾": function(){
        var filename = $('#inputfilename').val();
        if(filename.length>0){
          var parent_id = getCurrentRightClickedParentId();
          makeDir(parent_id, filename);
        }
        renderFileSystem();
        $('#filenamedialog').dialog( "close" );
      },
      Cancel: function() {
        $('#filenamedialog').dialog( "close" );
      }
    },
    close: function() {
      resetFilenameDialog();
    }
  });
  
  $('#fileuploadpanel').dialog({title:'檔案上傳', height:'auto', width:'auto', autoOpen:false});
  $('#documenthostingdialog').dialog({title:'選擇檔案轉換類型', height:'auto', width:'auto', autoOpen:false});
	$(document.body).click(function(e){
		if(e.which==1){
			$('#rightclickdialog').dialog('close');
		}
	});
});