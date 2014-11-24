<!DOCTYPE html>

<html>

<head>
  <title>
    <?php echo $title ?>
  </title>
  <meta http-equiv="Content-Type" content="text/html" charset="utf-8">
  <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
  <script src="http://code.jquery.com/ui/1.11.0/jquery-ui.js"></script>
  <link rel="stylesheet" href="http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css">
  
  
  
  <link rel="stylesheet" href="<?php echo base_url();?>asset/ztree/css/demo.css" type="text/css">
  <link rel="stylesheet" href="<?php echo base_url();?>asset/ztree/css/zTreeStyle/zTreeStyle.css" type="text/css">
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree//js/jquery.ztree.core-3.5.js"></script>
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree/js/jquery.ztree.excheck-3.5.js"></script>
  <script type="text/javascript" src="<?php echo base_url();?>asset/ztree/js/jquery.ztree.exedit-3.5.js"></script>
</head>

<body>
  <div class='greenheader'>
    <span class='pagename'><?php echo $title ?></span>
    <span class='greetuser'>
      <b><?php echo $username ?> 歡迎您!</b>
      <button class='logoutbutton' onclick='window.location.href="<?php echo base_url()?>index.php/users/logout"'>
        登出
      </button>
    </span>
  </div>
  <nav class='adminnavbar'>
    <a href="integration">檔案操作介面</a> |
    <a href='manageaccount'>雲端硬碟聯結</a>
  </nav>
  