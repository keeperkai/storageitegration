function connectGoogleAccount(){
  createInputStringDialog('設定google drive帳號名稱', '請輸入名稱', '前往連結帳號', function(input){
    window.location.href = '../../ciauthorization/connectgoogleaccount?storage_account_name='+encodeURIComponent(input);
  });
  return false;
}
function connectOneDriveAccount(){
  createInputStringDialog('設定one drive帳號名稱', '請輸入名稱', '前往連結帳號', function(input){
    //window.location.href = 'https://login.live.com/oauth20_authorize.srf?client_id=0000000048127A68&scope=wl.skydrive_update%20wl.basic%20wl.offline_access%20wl.contacts_skydrive&response_type=code&redirect_uri='+encodeURIComponent('https://storageintegration.twbbs.org/index.php/ciauthorization/onedrivecode')+'&state='+encodeURIComponent(input);
    window.location.href = '../../ciauthorization/connectonedriveaccount?storage_account_name='+encodeURIComponent(input);
  });
	
	return false;
}
function connectDropboxAccount(){
  createInputStringDialog('設定dropbox帳號名稱', '請輸入名稱', '前往連結帳號', function(input){
    window.location.href = '../../ciauthorization/connectdropboxaccount?storage_account_name='+encodeURIComponent(input);
  });
  return false;
}
