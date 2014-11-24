<form id='uploadform' enctype='multipart/form-data' method='post'>
<input type='file' id='file' name='file'>choose file</input>
<input type='submit'></input>
</form>
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>

<script>

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
    var form = $('#uploadform');
    var formactionurl = 'https://apis.live.net/v5.0/me/skydrive/files?access_token='+expacc.token;//+encodeURIComponent(expacc.token)
    //var formactionurl = '<?php echo base_url()?>index.php/test/echopostbody';
    form.attr('action', formactionurl);
});
</script>
