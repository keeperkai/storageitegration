<center>
<ul id="filesystempanel" class="ztree" style="width:500px;"></ul>
<div id='fileuploadpanel' hidden>
選擇上傳檔案:<input type="file" name="file" id="file" onchange='return upload();'/>
</div>
<div id='rightclickdialog' style='padding: 0;border: 0;height:0;width:auto;' hidden>
<ul id='rightclickmenu'>
<li onclick='return showFilenameDialog();'>新增資料夾</li>
<li onclick='return showUploadPanel();'>上傳檔案至此資料夾</li>
<li onclick='return deleteSelectedFiles();'>刪除已選擇之檔案(可按住crtl+滑鼠左鍵重複選擇)</li>
<li onclick='return showSharePanel();'>與其他使用者共用</li>
</ul>
</div>
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
<script src="<?php echo base_url();?>asset/js/googlefunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integrationfunctions.js"></script>
<script src="<?php echo base_url();?>asset/js/integration.js"></script>
<script src="<?php echo base_url();?>asset/bootstrap-contextmenu/bootstrap-contextmenu.js"></script>
