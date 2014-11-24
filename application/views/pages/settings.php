<button class='btn btn-success btn-lg' onclick='loadModifySettingDialog();'>新增設定</button>
<table id="settingtable" class="table table-hover table-bordered">
<thead>
</thead>
<tbody>
</tbody>
</table>

<div id='modifysettingdialog'>

</div>
<script>
function cancelModify(){
	$('#modifysettingdialog').dialog('close');
}
function submitModify(){
	//read the data from dialog
	var extension = $('#extension-input').val();
	var providers = [];
	$('#modifysettingdialog').find('select').each(function(){
		if($(this).val()!='none'){
			providers.push($(this).val());
		}
	});
	//---
	//ajax to our server
	$.ajax({
		url: '../../settings/setsetting',
		type: 'POST',
		data: {
			'extension': extension,
			'providers': providers
		},
		async: true,
		success: function(data, textstatus, request) {
			loadInfo();
			$('#modifysettingdialog').dialog('close');
		},
		error: function(xhr, status, error) {
		  var err = xhr.responseText;
		  console.log(err);
		  alert(err);
		  alert(error);
		}
	});
	
}
function loadModifySettingDialog(data){
	var extension = '';
	var providers = [];
	var provider_info_length = objectPropertyLength(provider_info);
	if(data){
		extension = data.extension;
		providers = data.providers;
	}
	$('#modifysettingdialog').empty();
	$('#modifysettingdialog').append(
		'<div class="form-group">'+
			'<label for="extension-input">副檔名</label>'+
			'<input id="extension-input" class="form-control" type="text" value="'+extension+'"></input>'+
		'</div>'+
		'<h3>上傳類型順序</h3>'
	);
	/*
	for(var pro in provider_info){
		if(provider_info.hasOwnProperty(pro)){
			for(var i=0;i<providers.length;++i){
				
			}
		}
	}
	*/
	for(var i=0;i<providers.length;++i){
		//load the options of each provider selection
		var opt = '<option value="none">提示我空間不夠</option>';
		for(var pro in provider_info){
			if(provider_info.hasOwnProperty(pro)){
				if(pro == providers[i]){
					opt+='<option selected value="'+pro+'">'+pro+'</option>';
				}else{
					opt+='<option value="'+pro+'">'+pro+'</option>';
				}
			}
		}
		$('#modifysettingdialog').append(
			'<div class="form-group>'+
				'<label for="extension-input">選擇類型</label>'+
				'<select>'+
					opt+
				'</select>'+
			'</div>'
		);
	};
	//load the rest with none options
	for(var i=0;i<provider_info_length-providers.length;++i){
		var opt = '<option value="none" selected>提示我空間不夠</option>';
		for(var pro in provider_info){
			if(provider_info.hasOwnProperty(pro)){
				opt+='<option value="'+pro+'">'+pro+'</option>';
			}
		}
		$('#modifysettingdialog').append(
			'<div class="form-group>'+
				'<label for="extension-input">選擇類型</label>'+
				'<select>'+
					opt+
				'</select>'+
			'</div>'
		);
	}
	$('#modifysettingdialog').append('<button id="submit-modify-setting" class="btn btn-success" onclick="submitModify();">確認修改</button>');
	$('#modifysettingdialog').append('<button class="btn btn-danger" onclick="cancelModify();">取消</button>');
	//dummy prevention
	//extension detection
	$('#extension-input').on('input', function(){
		if($(this).val()==''){//disable submit
			$('#submit-modify-setting').attr('disabled', 'disabled');
		}else{
			$('#submit-modify-setting').removeAttr('disabled');
		}
	});
	//initialize the submit button
	if($('#extension-input').val()==''){//disable submit
		$('#submit-modify-setting').attr('disabled', 'disabled');
	}else{
		$('#submit-modify-setting').removeAttr('disabled');
	}
	//----
	//selection adjustments, this is dummy free, no need to check settings afterwards.
	$('#modifysettingdialog').find('select').each(function(){
		$(this).data('previous_val', $(this).val());
	});
	$('#modifysettingdialog').find('select').change(function(){
		//console.log('change fired');
		var select = $(this);
		var selected_opt = select.find('option:selected');
		var idx = select.index();
		var all_selects = $('#modifysettingdialog').find('select');
		if(selected_opt.val()=='none'){
			//if the selection is none, change every select after this one to none
			var after = all_selects.filter(':gt('+idx+')');
			//change each selection to select none
			after.val('none');
		}else{
			//check if the option is already selected, if so swap, if not then do nothing.
			all_selects.each(function(){
				if(selected_opt.val() == $(this).data('previous_val')){
					$(this).val(select.data('previous_val'));
				}
			});
			//check if the options ahead of this one is none, if so pull it to the front
			all_selects.each(function(){
				if($(this).closest('div').index()==select.closest('div').index()){
					return false;
				}
				if($(this).val()=='none'){
					$(this).val(select.val());
					select.val('none');
					$('#modifysettingdialog').find('select').each(function(){
						$(this).data('previous_val', $(this).val());
					});
					return false;
				}
			});
		}
		$('#modifysettingdialog').find('select').each(function(){
			$(this).data('previous_val', $(this).val());
		});
	});
	//----------------
	$('#modifysettingdialog').dialog('open');
}
function objectPropertyLength(obj){
	var count = 0;
	for(var property in obj){
		if(obj.hasOwnProperty(property)){
			count++;
		}
	}
	return count;
}
function checkRenderReady(settings_refreshed, provider_info_refreshed){
	if(settings_refreshed&&provider_info_refreshed){
		renderTable(settings, provider_info);
	}
}
function renderTable(settings, provider_info){
	//empty the content in the table
	$('#settingtable thead').empty();
	$('#settingtable tbody').empty();
	
	var provider_size = objectPropertyLength(provider_info);
	$('#settingtable thead').append('<tr><th>副檔名</th><th colspan='+provider_size+'>上傳順位</th><th>修改</th><th>刪除</th></tr>');
	//load rows of body
	for(var j=0;j<settings.length;j++){
		var setting = settings[j];
	//for(var setting in settings){
		//if(settings.hasOwnProperty(setting)){
			var rowstring = '<tr><td class="ext">'+setting.extension+'</td>';
			for(var i=0;i<setting.provider.length;++i){
				rowstring +='<td class="provider">'+setting.provider[i].provider+'</td>';
			}
			for(var i=0;i<provider_size-setting.provider.length;++i){
				rowstring +='<td>提示我空間不夠</td>';
			}
			rowstring+='<td><button class="modify btn btn-default">修改</button></td>';
			rowstring+='<td><button class="delete btn btn-danger">刪除</button></td>';
			rowstring+='</tr>';
			$('#settingtable tbody').append(rowstring);
		//}
	}
	$('#settingtable').find('.modify').click(function(){
		//get the data from this row
		var row = $(this).closest('tr');
		var rowdata = {};
		rowdata['providers'] = [];
		rowdata['extension'] = row.find('.ext').text();
		row.find('.provider').each(function(){
			rowdata['providers'].push($(this).text());
		});
		//----
		loadModifySettingDialog(rowdata);
	});
	$('#settingtable').find('.delete').click(function(){
		$.ajax({
			url: '../../settings/deletesetting',
			type: 'POST',
			data: {
				'extension': $(this).closest('tr').find('.ext').text(),
			},
			async: true,
			success: function(data, textstatus, request) {
				//render table
				loadInfo();
			},
			error: function(xhr, status, error) {
			  var err = xhr.responseText;
			  console.log(err);
			  alert(err);
			  alert(error);
			}
		});
	});
	/*
	for(var provider in provider_info){
		if(provider_info.hasOwnProperty(provider)){
			
		}
	}
	*/
}
function loadInfo(){
	var settings_refreshed = false;
	var provider_info_refreshed = false;
	$.ajax({
		url: '../../settings/getallsettings',
		type: 'GET',
		async: true,
		success: function(data, textstatus, request) {
			//render table
			settings_refreshed = true;
			settings = data;
			checkRenderReady(settings_refreshed, provider_info_refreshed);
		},
		error: function(xhr, status, error) {
		  var err = xhr.responseText;
		  console.log(err);
		  alert(err);
		  alert(error);
		}
	});
	$.ajax({
		url: '../../settings/getproviderinfo',
		type: 'GET',
		async: true,
		success: function(data, textstatus, request) {
			//render table
			provider_info_refreshed = true;
			provider_info = data;
			checkRenderReady(settings_refreshed, provider_info_refreshed);
		},
		error: function(xhr, status, error) {
		  var err = xhr.responseText;
		  console.log(err);
		  alert(err);
		  alert(error);
		}
	});
}
function initializeDialogs(){
	$('#modifysettingdialog').dialog({
		autoOpen:false,
		width: '300px',
		//height: '300px',
		modal: true,
		title: '編輯設定'
	});
}
var settings = null;
var provider_info = null;
$(document).ready(function(){
	loadInfo();
	initializeDialogs();
});
</script>
