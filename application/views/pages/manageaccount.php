<div class="panel panel-info">
  <div class="panel-heading">
    <h3 class="panel-title">雲端硬碟聯結</h3>
  </div>
  <div class="panel-body">
<button onclick = 'return connectGoogleAccount();'>連結google雲端硬碟帳號</button>
<button onclick = 'return connectOneDriveAccount();'>連結onedrive雲端硬碟帳號</button>
</div>
</div>
<div class="panel panel-info">
  <div class="panel-heading">
    <h3 class="panel-title">目前已連結之帳號</h3>
  </div>
    <table class='table'>
	<thead>
	<tr><th>平台類型</th><th>代碼</th><th>聯結日期/時間</th></tr>
	</thead>

<tbody>

<?php
foreach($storage_account as $acc) {
    echo "<tr><td>".$acc['token_type']."</td><td>".$acc['storage_account_id']."</td><td>".$acc['time_stamp']."</td></tr>";
}

?>
</tbody>
</table>
</div>
<script src="<?php echo base_url();?>asset/js/manageaccount.js"></script>
