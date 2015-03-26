var isPageBusy = false;
function startWaitingAnimation(){
  $(document.body).addClass('waiting');
  isPageBusy = true;
}
function endWaitingAnimation(){
  $(document.body).removeClass('waiting');
  isPageBusy = false;
}
function humanReadableBytes(numBytes){
  if(numBytes<1024){
    return numBytes+' Bytes';
  }else if(numBytes<1024*1024){
    return Math.round(numBytes/1024) + ' KB';
  }else if(numBytes<1024*1024*1024){
    return Math.round(numBytes/(1024*1024)) + ' MB';
  }else if(numbytes<1024*1024*1024*1024){
    return Math.round(numBytes/(1024*1024*1024)) + ' GB';
  }else{
    return Math.round(numBytes/(1024*1024*1024*1024)) + ' TB';
  }
}
//download a file directly from the browser
function downloadFile(){
  var virtual_file_id = rightClickedNode.virtual_file_id;
  var win = window.open('../../../waiting.html');
  FileController.getDownloadLink(virtual_file_id, function(resp){
    if(resp.status == 'error'){
      alert(resp.errorMessage);
    }else if(resp.status == 'need_account'){
      if(confirm(resp.errorMessage + '，轉載至帳號管理頁面?')){
        window.location.href = './manageaccount';
      }
    }else{//success
      win.location.href = resp.link;
    }
  });
}
//Edit/Preview Link related
function editOrPreview(){
  var virtual_file_id = rightClickedNode.virtual_file_id;
  var win = window.open('../../../waiting.html');
  FileController.getEditViewLink(virtual_file_id, function(resp){
    if(resp.status == 'error'){
      alert(resp.errorMessage);
    }else if(resp.status == 'need_account'){
      if(confirm(resp.errorMessage + '，轉載至帳號管理頁面?')){
        window.location.href = './manageaccount';
      }
    }else{//success
      win.location.href = resp.link;
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
    startWaitingAnimation();
    FileController.modifyShareInfo(virtual_file_id, changes, function(){
      closeSharePanel();
      endWaitingAnimation();
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
  startWaitingAnimation();
  FileController.getShareInfo(virtual_file_id, function(info){
    endWaitingAnimation();
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
function moveFileInFileSystem(files, parent_virtual_file_id){
  FileController.moveFileInSystem(files, parent_virtual_file_id, function(){
    renderFileSystem();
  });
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
function updateContextMenuOptions(){
  //新增
  var new_disabled = 'false';
  if(rightClickedNode == null){
    new_disabled = 'false';
  }else if(rightClickedNode.virtual_file_id == -2){//shared root
    new_disabled = 'true';
  }else if(rightClickedNode.role == 'reader'|| rightClickedNode.file_type != 'folder'){//user doesn't have the access to the file/directory he is rclicking on
    new_disabled = 'true';
  }else{
    new_disabled = 'false';
  }
  $('#contextmenustub').contextMenu('update', [
    {
      name:'新增',
      disable:new_disabled
    }
  ]);
  //上傳檔案至此資料夾
  var upload_disabled = 'false';
  if(rightClickedNode == null){
    upload_disabled = 'false';
  }else if(rightClickedNode.virtual_file_id == -2){//shared root
    upload_disabled = 'true';
  }else if(rightClickedNode.role == 'reader' || rightClickedNode.file_type != 'folder'){
    upload_disabled = 'true';
  }else{
    upload_disabled = 'false';
  }
  $('#contextmenustub').contextMenu('update', [
      {
        name:'上傳檔案至此資料夾',
        disable: upload_disabled
      }
    ]);
  //刪除已選擇之檔案(可按住crtl+滑鼠左鍵重複選擇)
  var delete_disabled = 'false';
  var treeObj = $.fn.zTree.getZTreeObj("filesystempanel");
  var selectedNodes = treeObj.getSelectedNodes();
  if(selectedNodes.length>0) delete_disabled = 'false';
  else delete_disabled = 'true';
  $('#contextmenustub').contextMenu('update', [
    {
      name:'刪除已選擇之檔案(可按住crtl+滑鼠左鍵重複選擇)',
      disable:delete_disabled
    }
  ]);
  //與其他使用者共用
  var share_disabled = 'false';
  if(rightClickedNode == null){
    share_disabled = 'true';
  }else if(rightClickedNode.virtual_file_id == -2){//shared root
    upload_disabled = 'true';
  }else if(rightClickedNode.role == 'reader' || rightClickedNode.virtual_file_id<0){
    share_disabled = 'true';
  }
  $('#contextmenustub').contextMenu('update', [
    {
      name:'與其他使用者共用',
      disable:share_disabled
    }
  ]);
  //檢視檔案資訊, 編輯/檢視, 下載檔案
  var metadata_disabled = 'false';
  if(rightClickedNode == null){
    metadata_disabled = 'true';
  }else if(rightClickedNode.virtual_file_id == -2){//shared root
    upload_disabled = 'true';
  }else if(rightClickedNode.file_type == 'folder'){
    metadata_disabled = 'true';
  }
  $('#contextmenustub').contextMenu('update', [
    {
      name:'檢視檔案資訊',
      disable:metadata_disabled
    },
    {
      name:'編輯/檢視',
      disable:metadata_disabled
    },
    {
      name:'下載檔案',
      disable:metadata_disabled
    }
  ]);
}
//for ztree goto:http://www.jqueryscript.net/demo/Powerful-Multi-Functional-jQuery-Folder-Tree-Plugin-zTree/demo/en/
var rightClickedNode = {};
function fileOnRightClick(event, treeId, treeNode){
  rightClickedNode = treeNode;
  //test if the right clicked node is a node currently selected, if so, keep the selection, if not select the current node
  var treeObj = $.fn.zTree.getZTreeObj("filesystempanel");
  var selectedNodes = treeObj.getSelectedNodes();
  var matched = false;
  for(var i=0;i<selectedNodes.length;++i){
    if(rightClickedNode == selectedNodes[i]){
      matched = true;
      break;
    }
  }
  if(!matched){
    treeObj.selectNode(rightClickedNode, false);
  }
  //update the enable/disabled options according to the file that has been clicked on
  updateContextMenuOptions();
  $('#contextmenustub').contextMenu('update', [], {'left':event.pageX,'top':event.pageY});//set it to the right click position
  $('#contextmenustub').trigger('FileRightClicked', event);
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
function showFilenameDialog(){// for make folder
  //$('#filenamedialog').dialog('open');
  isPageBusy = true;
  createInputStringDialog(
      '輸入資料夾名稱',
      '輸入名稱',
      '建立資料夾',
      function(filename){
        startWaitingAnimation();
        var parent_id = (rightClickedNode == null)? -1 : rightClickedNode.virtual_file_id;
        if(parent_id==-2){
          parent_id=-1;
        }
        FileController.registerVirtualFileToSystem('', 'folder', filename, parent_id, [], function(){
          endWaitingAnimation();
          renderFileSystem();
        });
      },
      function(){
        isPageBusy = false;
      }
    );
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
  //var parent_id = getCurrentRightClickedParentId();
  var parent_id = (rightClickedNode == null)? -1 : rightClickedNode.virtual_file_id;
  //console.log(parent_id);
  if(parent_id==-2){
    parent_id=-1;
  }
	registerFileToSystem('file', file.name, file.name.substring(file.name.lastIndexOf('.')+1), parent_id, storage_file_data);
}
function resetUploadItems(){
  resetFileChooser();
  //$('#fileuploaddialog').dialog('close');
  endWaitingAnimation();
  $('#documenthostingdialog').dialog('close');
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
  var instructions = FileController.getUploadInstructions(file, ((rightClickedNode == null)? -1: rightClickedNode.virtual_file_id), function(instructions){
    
    if(instructions.status!='impossible'){
      var schedule_data = instructions.schedule_data;
      if(instructions.status == 'need_shuffle'){//tell the user how much data will need to be moved and ask whether they want to shuffle or not
        var msg = '帳號沒有足夠的空間，必須要先進行資料挪移，完成挪移後才能上傳此檔案:\n'
          +'總共需要移動: '+humanReadableBytes(schedule_data.shuffle.total_shuffle_size)+' 的資料\n'
          +'伺服器將會幫您移動: '+humanReadableBytes(schedule_data.shuffle.server_shuffle_size) +' 的資料\n'
          +'您的瀏覽器需要移動: '+humanReadableBytes(schedule_data.shuffle.client_shuffle_size) +' 的資料\n'
          +'是否要挪移資料?選擇"確定"進行挪移。或者選擇"取消"，連結一個有足夠空間的'+schedule_data.upload[0].target_account.token_type+'帳號。\n';
        if(!confirm(msg)){
          resetUploadItems();
          return;
        }
      }
      var executor = new WholeUploadExecutor(instructions.schedule_data, file, ((rightClickedNode == null)? -1: rightClickedNode.virtual_file_id));
      executor.complete = function(){
        resetUploadItems();
        renderFileSystem();
      };
      executor.execute();
    }else{
      endWaitingAnimation();
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
    //$('#fileuploaddialog').dialog('open');
    startWaitingAnimation();
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
    /*
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
    */
    startWaitingAnimation();
    FileController.deleteFiles(selectedNodes, function(){
      renderFileSystem();
      endWaitingAnimation();
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
var isDraggingFile = false;
function beforeDrag(treeId, treeNodes) {
  isPageBusy = true;
  isDraggingFile = true;
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
    moveFileInFileSystem(treeNodes, targetNode.virtual_file_id);
  }else{//no target node
    moveFileInFileSystem(treeNodes, -1);
  }
  isPageBusy = false;
  isDraggingFile = false;
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
/*
  transforms the received file tree from server to a z-tree acceptable format,
  including setting icons for files, opening previously opened directories...
*/
function constructZTree(filetree, prevOpenedDirIds, prevSelectedNodes){
  //map: file extension --> icon image path.
  var icon_map = {
    'google_document':'../../../asset/img/google_doc_icon.jpg',
    'google_spreadsheet':'../../../asset/img/google_spreadsheet_icon.jpg',
    'google_presentation':'../../../asset/img/google_presentation_icon.jpg',
    'docx':'../../../asset/img/ms_word.png',
    'xlsx':'../../../asset/img/ms_excel.png',
    'pptx':'../../../asset/img/ms_powerpoint.png',
    //sound formats
    'wav':'../../../asset/img/sound.png',
    'aiff':'../../../asset/img/sound.png',
    'mp3':'../../../asset/img/sound.png',
    'au':'../../../asset/img/sound.png',
    'wma':'../../../asset/img/sound.png',
    'wv':'../../../asset/img/sound.png',
    //video formats:
    'mp4':'../../../asset/img/video.png',
    'flv':'../../../asset/img/video.png',
    'wmv':'../../../asset/img/video.png',
    'avi':'../../../asset/img/video.png',
    'mov':'../../../asset/img/video.png',
    'rmvb':'../../../asset/img/video.png',
    'mpg':'../../../asset/img/video.png',
    'mpeg':'../../../asset/img/video.png',
    
    //compressed file formats
    'zip':'../../../asset/img/compress.png',
    'rar':'../../../asset/img/compress.png',
    //image file formats
    'png':'../../../asset/img/image.png',
    'bmp':'../../../asset/img/image.png',
    'jpg':'../../../asset/img/image.png',
    'gif':'../../../asset/img/image.png',
    'tif':'../../../asset/img/image.png',
    'tiff':'../../../asset/img/image.png'
    
  
  };
  for (var i=0;i<filetree.length;++i) {
    var file = filetree[i];
	//if it was previously opened, open it now
    if(prevOpenedDirIds.hasOwnProperty(file['virtual_file_id'])){
      file['open'] = true;
      delete(prevOpenedDirIds[file['virtual_file_id']]);
    }
    //set droppable to true if it is a dir, otherwise false.
    if(file.virtual_file_id == -2){
      file['drop'] = false;
      file['isParent'] = true;
    }else if(file['file_type'] == 'folder'){
      file['drop'] = true;
      file['isParent'] = true;
    }else{
      file['drop'] = false;
    }
    //ToDo: set icons according to extension
    var extension = file['extension'];
    if(icon_map.hasOwnProperty(extension)){
      file['icon'] = icon_map[extension];
    }
    //--------------------------------
  }
  return filetree;
}
function renderFileSystem(){
  var treedata = getFileTree();
  var prevtree = $.fn.zTree.getZTreeObj('filesystempanel');
  //preserve the previous opened folders
  var prevOpenedDirIds = {};
  if(prevtree != null){
    var prevnodes = prevtree.transformToArray(prevtree.getNodes());
    prevOpenedDirIds = getPrevOpenedDirIds(prevnodes);
  }
  //preserve the previous selected nodes
  var sel_vfile_id_map = {};
  if(prevtree !=null){
    var selectedNodes = prevtree.getSelectedNodes();
    for(var i=0;i<selectedNodes.length;++i){
      sel_vfile_id_map[selectedNodes[i].virtual_file_id] = true;
    }
  }
  //console.log(sel_vfile_id_map);
  var zNodes = constructZTree(treedata, prevOpenedDirIds, selectedNodes);
  var setting = {
    edit: {
      enable: true,
      showRemoveBtn: false,
      showRenameBtn: false,
      drag:{
        inner: true,
        prev: false,
        next: false
      }
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
  //select prev selected nodes
  var newtree = $.fn.zTree.getZTreeObj('filesystempanel');
  var newnodes = newtree.transformToArray(newtree.getNodes());
  for(var i=0;i<newnodes.length;++i){
    //console.log('new node vfile id:'+newnodes[i].virtual_file_id);
    if(sel_vfile_id_map.hasOwnProperty(newnodes[i].virtual_file_id)){
      //console.log('selected:'+newnodes[i].virtual_file_id);
      newtree.selectNode(newnodes[i], true)
    }
  }
  
  
}
function resetFilenameDialog(){
  $('#filenamedialog').find('input').val('');
}
function showUploadPanel(){
  $('#fileuploadpanel').dialog('open');
}
function showFileInfo(){
  //get the right click virtual_file_id
  if(rightClickedNode == null) return;
  if(rightClickedNode.virtual_file_id < 0) return;//any of the root directory or shared root directory.
  var virtual_file_id = rightClickedNode.virtual_file_id;
  //get the file info of the virtual file
  FileController.getVirtualFileInfo(virtual_file_id, function(resp){
    if(resp.status == 'error'){
      alert(resp.errorMessage);
    }else{//success
      var file_info = resp.file_info;
      //render the file info in a dialog
      createFileInfoDialog(file_info);
    }
  })
}

/*
  create a new dialog showing the info file_info, the dom element will be created and appended to the document and destroyed/deleted when closed
  file_info format:
  {
    name:,
    mime_type:,
    ...virtual file properties
    total_file_size: the sum of the storage files
    ,
    storage_files:
    [
      {
        storage file properties...including storage_account_id
      },
      {
        ...
      }
    ],
    storage_account_map:
      {
        size: number of storage accounts,
        storage_accounts:
        {
          'storage_account_id1': {
            owner:account_name
            provider: googledrive, dropbox, onedrive ...etc
          },
          'storage_account_id2': ...
        }
      }
  }
*/
function createFileInfoDialog(fileinfo){
  var new_dialog = $('<div></div>').appendTo(document.body);
  //basic info
  $(new_dialog).append(
    '<dl class="dl-horizontal">'+
    '<dt>檔案型態</dt><dd>'+fileinfo.extension+'</dd>'+
    '<dt>資料型態</dt><dd>'+fileinfo.mime_type+'</dd>'+
    '<dt>檔案大小</dt><dd>'+fileinfo.total_file_size+'</dd>'+
    '<dt>允許切割</dt><dd>'+((fileinfo.allow_chunk == true)? '是' : '否')+'</dd>'+
    '<dt>檔案是否已經分割</dt><dd>'+((fileinfo.storage_account_map.size>1)? '是' : '否')+'</dd>'+
    '<dt>檔案存放在</dt><dd>'+fileinfo.storage_account_map.size+'組帳號中</dd></dl>'
  );
  //storage info for each account, render into a table
  var tablestr = '<table class = "table table-hover table-bordered"><thead><tr><td>帳戶擁有人</td><td>帳戶名稱</td><td>帳戶類型</td><td>分割檔案資訊</td></tr></thead><tbody>';
  var storage_account_map = fileinfo.storage_account_map.storage_accounts;
  var storage_files = fileinfo.storage_files;
  for(var storage_account_id in storage_account_map){
    if(storage_account_map.hasOwnProperty(storage_account_id)){
      var acc = storage_account_map[storage_account_id];
      //render each storage account in a row
      var rowstr = '<tr><td>'+acc['owner']+'</td><td>'+acc['storage_account_name']+'</td><td>'+acc['provider']+'<td>';
      var slicestr = '';
      //generate slice info
      for(var i=0;i<storage_files.length;++i){
        var sfile = storage_files[i];
        if(sfile['storage_account_id'] == storage_account_id){
          slicestr+='分割大小: '+sfile['storage_file_size']+', 位元順序: '+sfile['byte_offset_start']+' - '+sfile['byte_offset_end']+'<br>';
        }
      }
      rowstr += slicestr+'</td></tr>';
    }
    tablestr += rowstr;
  }
  tablestr += '</tbody></table>';
  $(new_dialog).append(tablestr);
  $(new_dialog).dialog({
    title: fileinfo.name+'檔案資訊',
    height:'auto',
    width:'auto',
    close: function(){
      $(this).dialog('destroy').remove();
    }
  });
}
function createOnedriveDoc(doc_type){
  //show a filename dialog
  var parent_virtual_file_id = -1;
  if(rightClickedNode != null){
    parent_virtual_file_id = rightClickedNode.virtual_file_id;
  }
  (function(d_type, parent_folder_id){
    var ms_d_type = '';
    if(d_type == 'document') ms_d_type = 'Word';
    else if(d_type == 'spreadsheet') ms_d_type = 'Excel';
    else if(d_type == 'presentation') ms_d_type = 'Powerpoint';
    isPageBusy = true;
    createInputStringDialog(
      '輸入OneDrive '+ms_d_type+' 檔名稱',
      '輸入名稱',
      '建立'+ms_d_type,
      function(doc_name){
        startWaitingAnimation();
        FileController.createOnedriveDoc(d_type, doc_name, parent_folder_id, function(resp){
          endWaitingAnimation();
          if(resp.status=='success'){
            renderFileSystem();
          }else if(resp.status == 'error'){
            alert(resp.errorMessage);
          }else if(resp.status == 'need_account'){
            if(confirm(resp.errorMessage + '，轉載至帳號管理頁面?')){
              window.location.href = './manageaccount';
            }
          }else{
            alert('Unrecognized status!');
          }
        });
      },
      function(){
        isPageBusy = false;
      }
    );
  })(doc_type, parent_virtual_file_id);
}
function createGoogleDoc(doc_type){
  //show a filename dialog
  var parent_virtual_file_id = -1;
  if(rightClickedNode != null){
    parent_virtual_file_id = rightClickedNode.virtual_file_id;
  }
  (function(d_type, parent_folder_id){
    isPageBusy = true;
    createInputStringDialog(
      '輸入Google '+d_type+' 名稱',
      '輸入名稱',
      '建立'+d_type,
      function(doc_name){
        startWaitingAnimation();
        FileController.createGoogleDoc(d_type, doc_name, parent_folder_id, function(resp){
          endWaitingAnimation();
          if(resp.status=='success'){
            renderFileSystem();
          }else if(resp.status == 'error'){
            alert(resp.errorMessage);
          }else if(resp.status == 'need_account'){
            if(confirm(resp.errorMessage + '，轉載至帳號管理頁面?')){
              window.location.href = './manageaccount';
            }
          }else{
            alert('Unrecognized status!');
          }
        });
      },
      function(){
        isPageBusy = false;
      }
    );
  })(doc_type, parent_virtual_file_id);
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
var context_menu_object = [
    {
        name: '新增',
        title: 'update button'
        ,
      subMenu: [
        {
          name: '新增資料夾',
          title: 'make_folder',
          fun: function () {
              showFilenameDialog();
          }
        },
        {
          name: 'Google Document',
          title: 'make_google_doc',
          fun: function() {
            createGoogleDoc('document');
          }
        },
        {
          name: 'Google Spreadsheet',
          title: 'make_google_spreadsheet',
          fun: function() {
            createGoogleDoc('spreadsheet');
          }
        },
        {
          name: 'Google Presentation',
          title: 'make_onedrive_presentation',
          fun: function() {
            createGoogleDoc('presentation');
          }
        },
        {
          name: 'Microsoft Word',
          title: 'make_onedrive_doc',
          fun: function() {
            createOnedriveDoc('document');
          }
        },
        {
          name: 'Microsoft Excel',
          title: 'make_onedrive_spreadsheet',
          fun: function() {
            createOnedriveDoc('spreadsheet');
          }
        },
        {
          name: 'Microsoft PowerPoint',
          title: 'make_onedrive_presentation',
          fun: function() {
            createOnedriveDoc('presentation');
          }
        }
      ]
    },
    
    {
      name: '上傳檔案至此資料夾',
      title: 'upload',
      fun: function () {
        showUploadPanel();
      }
    }, {
        name: '刪除已選擇之檔案(可按住crtl+滑鼠左鍵重複選擇)',
        title: 'delete',
        fun: function () {
          deleteSelectedFiles();
        }
  }, {
        name: '與其他使用者共用',
        title: 'share',
        fun: function () {
          showSharePanel();
        }
    }, {
        name: '編輯/檢視',
        title: 'edit_or_preview',
        fun: function () {
          editOrPreview();
        }
    }, {
        name: '下載檔案',
        title: 'download',
        fun: function () {
          downloadFile();
        }
  },
    {
      name: '檢視檔案資訊',
      title: 'show_meta_data',
      fun: function(){
        showFileInfo();
      }
    }];
function tryRenderFileSystem(){
  if(document.hasFocus()&& !isPageBusy) renderFileSystem();
  setTimeout(tryRenderFileSystem, 7000);
}
$(document).ready(function(){
  accounts = getAccounts();
  //console.log(filetree);
  renderFileSystem();
  //dialog init
  $('#fileuploaddialog').dialog({
    autoOpen: false,
    height: 'auto',
    width: 'auto',
    modal: true
  });
  //we use an invisible div to trigger the context menu
  $('#contextmenustub').contextMenu(context_menu_object,{triggerOn:'FileRightClicked','left':0,'top':0});
  $('#filenamedialog').dialog({
    title: '輸入名稱',
    autoOpen: false,
    width:'auto',
    height:'auto',
    buttons: {
      "以此名稱建立資料夾": function(){
        var filename = $('#inputfilename').val();
        if(filename.length>0){
          var parent_id = (rightClickedNode == null)? -1:rightClickedNode.virtual_file_id;
          //makeDir(parent_id, filename);
          startWaitingAnimation();
          FileController.registerVirtualFileToSystem('', 'folder', filename, parent_id, [], function(){
            endWaitingAnimation();
            renderFileSystem();
            $('#filenamedialog').dialog( "close" );
          });
        }
        /*
        renderFileSystem();
        $('#filenamedialog').dialog( "close" );
        */
      },
      Cancel: function() {
        $('#filenamedialog').dialog( "close" );
      }
    },
    close: function() {
      resetFilenameDialog();
    }
  });
  $('#filesharepanel').dialog({title:'設定共享權限', height:'auto', width:'auto', autoOpen:false, modal:true});
  //$( ".selector" ).on( "dialogopen", function( event, ui ) {} );
  
  $('#fileuploadpanel').dialog({title:'檔案上傳', height:'auto', width:'auto', autoOpen:false});
  $('#documenthostingdialog').dialog({title:'選擇檔案轉換類型', height:'auto', width:'auto', autoOpen:false});
  $('html').mouseup(function(){
    if(isDraggingFile&&isPageBusy){
      isPageBusy = false;
      isDraggingFile = false;
    }
  });
  //periodically try to render file tree
  setTimeout(tryRenderFileSystem, 7000);
});