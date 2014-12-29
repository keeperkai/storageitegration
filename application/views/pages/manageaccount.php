<div class="panel panel-info">
  <div class="panel-heading">
    <h3 class="panel-title">雲端硬碟聯結</h3>
  </div>
  <div class="panel-body">
<button onclick = 'return connectGoogleAccount();'>連結google雲端硬碟帳號</button>
<button onclick = 'return connectOneDriveAccount();'>連結onedrive雲端硬碟帳號</button>
<button onclick = 'return connectDropboxAccount();'>連結dropbox雲端硬碟帳號</button>
</div>
</div>
<div class="panel panel-info">
  <div class="panel-heading">
    <h3 class="panel-title">目前已連結之帳號</h3>
  </div>
    <table class='table'>
	<thead>
	<tr><th>平台類型</th><th>代碼</th><th>聯結日期/時間</th><th>容量(MB)</th></tr>
	</thead>

<tbody>

<?php
$i = 0;
foreach($storage_account as $acc) {
    echo "<tr><td>".$acc['token_type']."</td><td>".$acc['storage_account_id']."</td><td>".$acc['time_stamp']."</td><td><div style='height:150px;width:150px;' id='pie_chart_".$i."'></div></td></tr>";
    $i++;
}

?>
</tbody>
</table>
</div>
<script type="text/javascript" src="../../../asset/jquery/js/jquery-migrate.min.js"></script>
<script type="text/javascript" src="../../../asset/jqplot/jquery.jqplot.min.js"></script>
<script type="text/javascript" src="../../../asset/jqplot/plugins/jqplot.pieRenderer.min.js"></script>
<link rel="stylesheet" type="text/css" href="../../../asset/jqplot/jquery.jqplot.min.css" />
<script src="<?php echo base_url();?>asset/js/manageaccount.js"></script>
<script>
$(document).ready(function(){
<?php
$i = 0;
$mb = 1024*1024;
//['total',".($acc['quota_info']['total']/$mb)."],
foreach($storage_account as $acc) {
    echo "$.jqplot('pie_chart_".$i."', [[['used',".($acc['quota_info']['used']/$mb)."],['free',".($acc['quota_info']['free']/$mb)."]]], {
        gridPadding: {top:0, bottom:38, left:0, right:0},
        seriesDefaults:{
            renderer:$.jqplot.PieRenderer, 
            trendline:{ show:false }, 
            rendererOptions: { padding: 8, showDataLabels: true, dataLabels: 'value' }
        },
        legend:{
            show:true, 
            placement: 'outside', 
            rendererOptions: {
                numberRows: 1
            }, 
            location:'s',
            marginTop: '15px'
        }       
    });".PHP_EOL;
    $i++;
}

?>
});
</script>