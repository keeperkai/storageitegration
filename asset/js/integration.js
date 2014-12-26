//Edit/Preview Link related
function editOrPreview(){
  var virtual_file_id = rightClickedNode.virtual_file_id;
  FileController.getEditViewLink(virtual_file_id, function(resp){
    if(resp.status == 'error'){
      alert(resp.errorMessage);
    }else if(resp.status == 'need_account'){
      if(confirm(resp.errorMessage + '，轉載至帳號管理頁面?')){
        window.location.href = './manageaccount';
      }
    }else{//success
      window.open(resp.link);
    }
  });
}
//share panel related
function getUserRole(user, info){
  if(info.owner == user){
    return 'owner';
  }else if(info.writers.hasOwnProperty(user)) return 'writer';
  else if(info.reader.hasOwnProperty(user)) return 'reader';
  else return false;
}
/*
function permissionDeleted(target_account, original_info){
  
  
  var original_role = getUserRole(original_info);
  //see if the account is in the original info
  if(original_role!= false){//if so we need to update or insert the delete
    //check if it has already been deleted before( this could happen when a user deletes the permission and adds a new one with the same account )
  }else{//if the account isn't in the original info, we need to delete the permission in the share_changes.permission_insert
  
  }
}
function permissionModified(target_account, new_permission, original_info){
  
  if(share_changes.permission_insert.hasOwnProperty(target_account)){//check if the permission exists in the share_changes inserted
    //if so, change the permission in the permission_insert
    share_changes.permission_insert[target_account] = new_permission;
  }else{//if the permission does not exist in the inserted changes, it was originally from the original_info
    //see if we already changed this before, if so change the permission_change
    if(share_changes.permission_change.hasOwnProperty(target_account)){
      //we changed it before, just check if it is the same as the original permission, if so delete this change
      //search for the account in the original array
      var original_role = getUserRole(target_account, original_info);
      if(original_role != false){
        console.log('there was an error, the account does not exist in inserted and the original share info while trying to change it');
        return;
      }//there should be no case where this happens
      if(original_role == new_permission){//delete the change
        delete share_changes.permission_change[target_account];
      }else{//modify the change
        share_changes.permission_change[target_account] = new_permission;
      }
    }else{//we haven't changed it before, so we need to set the permission
      share_changes.permission_change[target_account] = new_permission;
    }
  }
}
*/

//inserts a permission from the bottom of the share panel
function insertPermission(){
  var accountname = $('#filesharepanel').find('#file_share_account_input').val();
  if(accountname == original_info.owner){
    alert('不能重新設定檔案擁有者之權限');
    return;
  }
  var role = $('#file_share_permission_input option:selected').val();
  if(accountname==''){
    alert('你沒有輸入帳號');
    return;
  }
  //see if the table already has this account, if so then just update, remember to filter the owner
  
  var matches = $('#permission_table').find('td:contains("'+accountname+'")');
  if(matches.length>0){
    var selection = matches.closest('tr').find('select');
    selection.val(role);
  }else{
  //if not then insert
    var selection_str = '';
    if(role == 'writer'){
      selection_str = '<select><option value="writer" selected>可編輯</option><option value="reader">可觀看</option></select>';
    }else if(role == 'reader'){
      selection_str = '<select><option value="writer">可編輯</option><option value="reader" selected>可觀看</option></select>';
    }
    $('#permission_table').append('<tr><td>'+accountname+'</td><td>'+selection_str+'</td><td><button class="delete_permission_button">刪除</button></td></tr>');
    $('#filesharepanel').find('.delete_permission_button').off('click');
    $('#filesharepanel').find('.delete_permission_button').click(function(){
      $(this).closest('tr').remove();
    });
  }
}
/*
  output:
  {
    owner: owner account
    writer:
    [
      writer account1
      ...
    ],
    reader:
    [
      reader account 1
      ...
    ]
  }
  
*/
function extractSharePanelInfo(){
  var output = {};
  //get the infos from permission table
  var owner = $('#permission_table').find('td:contains("檔案擁有者")').closest('tr').children(':nth-child(1)').text();
  output['owner'] = owner;
  var writers = [];
  var readers= [];
  $('#permission_table').find('select').each(function(){
    var user = $(this).closest('tr').children(':nth-child(1)').text();
    if($(this).val() == 'reader'){
      readers.push(user);
    }else{
      writers.push(user);
    }
  });
  /*
  $('#permission_table').find('select[value="writer"]').each(function(){
    var writer = $(this).closest('tr').children(':nth-child(1)').text();
    writers.push(writer);
  });
  $('#permission_table').find('select[value="reader"]').each(function(){
    var reader = $(this).closest('tr').children(':nth-child(1)').text();
    readers.push(reader);
  });
  */
  output['writer'] = writers;
  output['reader'] = readers;
  console.log('extracted:')
  console.log(output);
  return output;
}
/*
  makes the share info into a map, easy for query
  input: look at the output of extractSharePanelInfo
  output:
  {
    account_name: role
    account_name2: role2
    ...
  }
  
*/
function createShareInfoMap(info){
  var output = {};
  output[info.owner] = 'owner';
  for(var i=0; i<info.writer.length; ++i){
    output[info.writer[i]] = 'writer';
  }
  for(var i=0; i<info.reader.length; ++i){
    output[info.reader[i]] = 'reader';
  }
  return output
}
//compares two share infos, and get's the changes
/*
format:
  {
    updated: true or false
    permission_change:
    {
      user_account: writer or reader,
      user_account2: ...
    }
    permission_delete:
    [
      user_account,
      useraccount2,
      ..
    ]
    permission_insert:
    {
      user_account: writer or reader
    }
    
  }
*/
function getShareInfoChanges(originalinfo, newinfo){
  output = {};
  var updated = false;
  //generate maps
  var original_map = createShareInfoMap(original_info);
  var new_map = createShareInfoMap(newinfo);
  //owner cannot be changed, so we will do nothing for the owner part
  //try to find deleted permissions
  var deleted = [];
  for(var user in original_map){
    if(original_map.hasOwnProperty(user)){
      if(!new_map.hasOwnProperty(user)){//new map doesn't have a permission that is in the old map, meaning it is deleted
        updated = true;
        deleted.push(user);
      }
    }
  }
  //try to find changed permissions
  var changed = {};
  for(var user in original_map){
    if(original_map.hasOwnProperty(user)){
      if(new_map.hasOwnProperty(user)){//new map has the user, check if the permission is the same, if not add new permission to changed
        if(new_map[user] != original_map[user]){
          updated = true;
          changed[user] = new_map[user];
        }
      }
    }
  }
  //try to find inserted permissions(permissions that are not in the original but are in the new info)
  var inserted = {};
  for(var user in new_map){
    if(new_map.hasOwnProperty(user)){
      if(!original_map.hasOwnProperty(user)){//old map doesn't have this user, but new one does, it is inserted
        updated = true;
        inserted[user] = new_map[user];
      }
    }
  }
  //format the output
  output['permission_change'] = changed;
  output['permission_delete'] = deleted;
  output['permission_insert'] = inserted;
  output['updated'] = updated;
  console.log('changes:');
  console.log(output);
  return output;
}
function closeSharePanel(){
  $('#filesharepanel').dialog('close');
}
function commitShareChange(original_info){//commits the changes to share info to the server
  //get virtual file id from the hidden input
  var virtual_file_id = $('#vfile_id_input').val();
  var newinfo = extractSharePanelInfo();
  var changes = getShareInfoChanges(original_info, newinfo);
  if(changes.updated){//there was a change
    //ajax
    FileController.modifyShareInfo(virtual_file_id, changes, function(){
      closeSharePanel();
    });
  }
  closeSharePanel();
}
/*
var share_changes = {
  'changed':false,
  'permission_change':{},
  'permission_delete':{},
  'permission_insert':{}
};
*/
var original_info = {};
function renderShareInfo(virtual_file_id){
  $('#filesharepanel').dialog( 'option', 'title', '設定共享權限 : '+ rightClickedNode.name );
  var info = original_info
  //clear the dialog
  $('#filesharepanel').empty();
  //start rendering
  //find the owner first
  var tablestr = '<table id="permission_table"><thead><tr><th>使用者帳號</th><th>權限</th><th>刪除此權限</th></tr></thead><tbody>';
  //render the owner row, the owner cannot be changed nor deleted
  tablestr += '<tr><td>'+info.owner+'</td><td>檔案擁有者</td><td>N/A</td></tr>';
  //render the writers
  for(var i=0;i<info.writer.length;++i){
    var access_select_string = '<select><option value="writer" selected>可編輯</option><option value="reader">可觀看</option></select>';;
    
    var info_user = info.writer[i];
    tablestr += '<tr><td>'+info_user+'</td><td>'+access_select_string+'</td><td><button class="delete_permission_button">刪除</button></td></tr>';
  }
  
  for(var i=0;i<info.reader.length;++i){
    var access_select_string = '<select><option value="writer">可編輯</option><option value="reader" selected>可觀看</option></select>';
    
    var info_user = info.reader[i];
    tablestr += '<tr><td>'+info_user+'</td><td>'+access_select_string+'</td><td><button class="delete_permission_button">刪除</button></td></tr>';
  }
  tablestr +='</tbody></table>';
  //add the table to the panel
  $('#filesharepanel').append(tablestr);
  //add the change detect functions to the elements in the table
  /*
  $('#filesharepanel').find('select').change(function(){
    //var permission = $(this).find('option :selected').val();
    //var account_name = $(this).closest('tr').children('nth-child(1)');//get the account name
    //permissionModified(account_name, permission, original_info);
  });
  */
  $('#filesharepanel').find('.delete_permission_button').click(function(){
    //var account_name = $(this).closest('tr').children('nth-child(1)');//get the account name
    //permissionDeleted(account_name, original_info);
    //update the dom table, delete the row
    //console.log('11111');
    //console.log($(this).html());
    //console.log($(this).closest('tr').html());
    $(this).closest('tr').remove();
  });
  
  //add insert inputs
  $('#filesharepanel').append('<hr>新增權限<br><hr>');
  $('#filesharepanel').append('<input id="file_share_account_input" type="text"></input>');
  $('#filesharepanel').append('<select id="file_share_permission_input"><option value="writer" selected>可編輯</option><option value="reader">可觀看</option></select>');
  $('#filesharepanel').append('<button onclick="insertPermission();">新增</button>');
  $('#filesharepanel').append('<hr>');
  //add commit and cancel button
  $('#filesharepanel').append('<button onclick="commitShareChange();">確認修改</button><button onclick="closeSharePanel();">取消</button>');
  //add the hidden virtual_file_id
  $('#filesharepanel').append('<input type="hidden" id="vfile_id_input" value="'+virtual_file_id+'"></input>');
}
function showSharePanel(){
  /*
    get sharing data
  */
  if(rightClickedNode == null) return;
  if(rightClickedNode.virtual_file_id < 0) return;//any of the root directory or shared root directory.
  var virtual_file_id = rightClickedNode.virtual_file_id;
  FileController.getShareInfo(virtual_file_id, function(info){
    //check if the user has the right to access this info
    if(info.status == 'success'){
      $('#filesharepanel').dialog('open');
      //render the info
      original_info = info.info;
      renderShareInfo(virtual_file_id);
      
    }else{
      alert('你沒有改變此檔案權限的權利');
      return;
    }
  });
  
}
//-------------------------------------------------------------------------

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
//for ztree goto:http://www.jqueryscript.net/demo/Powerful-Multi-Functional-jQuery-Folder-Tree-Plugin-zTree/demo/en/
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
function getUploadInstructions(file, callback){
  //convertToDoc = (typeof convertToDoc === "undefined") ? false : convertToDoc;
  //chosen_provider = (typeof chosen_provider === "undefined") ? 'none' : chosen_provider;
  var ext = file.name.substring(file.name.lastIndexOf('.')+1);
  var mime = file.type;
  var size = file.size;
  var name = file.name
  
  $.ajax({
      url: '../../files/getuploadinstructions',
      type: 'POST',
      async: true,
      data: {
        'extension': ext,
        'mime': mime,
        'size': size,
        'name': name,
      //  'convertToDoc': convertToDoc,
      //  'chosen_provider':chosen_provider
      },
      success: function(data, textstatus, request) {
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
  var instructions = getUploadInstructions(file, function(instructions){
    if(instructions.status!='impossible'){
      var executor = new WholeUploadExecutor(instructions.schedule_data, file, getCurrentRightClickedParentId());
      executor.complete = function(){
        resetUploadItems();
        renderFileSystem();
      };
      executor.execute();
    }else{
      var decision = showNoSuitableAccountDialog(instructions.errorMessage);
      if(decision){
        window.location.href = '../../pages/view/manageaccount';
      }
      resetUploadItems();
      renderFileSystem();
    }
  });
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
  $('#filesharepanel').dialog({title:'設定共享權限', height:'auto', width:'auto', autoOpen:false});
  $('#fileuploadpanel').dialog({title:'檔案上傳', height:'auto', width:'auto', autoOpen:false});
  $('#documenthostingdialog').dialog({title:'選擇檔案轉換類型', height:'auto', width:'auto', autoOpen:false});
	$(document.body).click(function(e){
		if(e.which==1){
			$('#rightclickdialog').dialog('close');
		}
	});
});