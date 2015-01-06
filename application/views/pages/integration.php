<script src="<?php echo base_url();?>asset/context_menu/contextMenu.min.js"></script>
<link href="<?php echo base_url();?>asset/context_menu/contextMenu.css" rel="stylesheet" type="text/css" />
<link href="<?php echo base_url();?>asset/css/waiting.css" rel="stylesheet" type="text/css" />


<center>
<div class='waiting_screen_full'></div>
<div id='filesharepanel' style = "width:1000px;height:600px;overflow:scroll;"></div>
<ul id="filesystempanel" class="ztree" style="width:500px;"></ul>
<div id='fileuploadpanel' hidden>
選擇上傳檔案:<input type="file" name="file" id="file" onchange='return upload();'/>
</div>
<div id='contextmenustub' hidden></div>
<div id='fileuploaddialog' hidden>檔案上傳中，請稍候</div>
<div id='filenamedialog' hidden>
請輸入檔案名稱:<input id='inputfilename' type='text' size=32></input>
</div>
</center>

<div id='documenthostingdialog'>
你想要上傳的檔案可以轉換成線上文件，轉換之後就能直接在瀏覽器中線上編輯，若您與其他使用者共享此文件，其他使用者能夠和你
同時在線上編輯。
<table id='selecthostingtable' class='table'>
</table>
</div>
<div id='fileinfodialog'>

</div>
<script src="<?php echo base_url();?>asset/js/jobexecutors.js"></script>
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integration.js"></script>
<script src="<?php echo base_url();?>asset/bootstrap-contextmenu/bootstrap-contextmenu.js"></script>
