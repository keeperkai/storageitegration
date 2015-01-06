<!DOCTYPE html>

<html>

<head>
  <title>
    <?php echo $title ?>
  </title>
  <meta http-equiv="Content-Type" content="text/html" charset="utf-8">
  <script src="<?php echo base_url();?>asset/jquery/js/jquery-2.1.3.min.js"></script>
 <script src="<?php echo base_url();?>asset/jquery/js/jquery-ui-1.11.2/jquery-ui.min.js"></script>
 <script src="<?php echo base_url();?>asset/js/util.js"></script>
  <link rel="stylesheet" href="<?php echo base_url();?>asset/jquery/css/jquery-ui-themes-1.11.2/themes/smoothness/jquery-ui.min.css">
  <link rel="stylesheet" href="<?php echo base_url();?>asset/ztree/css/demo.css" type="text/css">
  <link rel="stylesheet" href="<?php echo base_url();?>asset/ztree/css/zTreeStyle/zTreeStyle.css" type="text/css">
  
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree/js/jquery.ztree.core-3.5.js"></script>
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree/js/jquery.ztree.excheck-3.5.js"></script>
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree/js/jquery.ztree.exedit-3.5.js"></script>
  
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="<?php echo base_url();?>asset/bootstrap-3.2.0-dist/css/bootstrap.min.css">

  <!-- Optional theme -->
  <link rel="stylesheet" href="<?php echo base_url();?>asset/bootstrap-3.2.0-dist/css/bootstrap-theme.min.css">

  <!-- Latest compiled and minified JavaScript -->
  <script src="<?php echo base_url();?>asset/bootstrap-3.2.0-dist/js/bootstrap.min.js"></script>
  <script src="<?php echo base_url();?>asset/js/controllers.js"></script>
<style>
.noTitleStuff .ui-dialog-titlebar {display:none}
#rightclickmenu {
	border-width: 0;
	padding: 0;
	border: 0;
	margin: 0;
	cursor: pointer;
}
#rightclickmenu li{
	cursor: pointer;
	font-size: large;
}
</style>
</head>

<body>
  <div class="navbar navbar-default navbar-static-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <span class="navbar-brand"><?php echo $title ?></span>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav">
            <li id="integration_li"><a href="integration">檔案操作介面</a></li>
            <li id="manageaccount_li"><a href="manageaccount">雲端硬碟聯結</a></li>
			<li id="settings_li"><a href="settings">系統設定</a></li>
          </ul>
          <ul class="nav navbar-nav navbar-right">
            <li>
			  <p class="navbar-text"><?php echo $username ?> 歡迎您!</p>
			</li>
			<li>
			  <button class="btn btn-default navbar-btn" onclick='window.location.href="<?php echo base_url()?>index.php/users/logout"'>登出</button>
			</li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<script>
$(document).ready(function(){
	$('#<?php echo $page; ?>_li').addClass('active');
});
</script>
  