function connectGoogleAccount(){
  window.location.href = '../../ciauthorization/connectgoogleaccount';
  return false;
}
function connectOneDriveAccount(){
	window.location.href = 'https://login.live.com/oauth20_authorize.srf?client_id=0000000048127A68&scope=wl.skydrive_update%20wl.basic%20wl.offline_access%20wl.contacts_skydrive&response_type=code&redirect_uri='+encodeURIComponent('http://storageintegration.twbbs.org/index.php/ciauthorization/onedrivecode');
	return false;
}
$(document).ready(function(){

});