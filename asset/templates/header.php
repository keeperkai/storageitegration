<!DOCTYPE html>

<html>

<head>
  <title>
    <?php echo $title ?>
  </title>
  <meta http-equiv="Content-Type" content="text/html" charset="utf-8">
  <link rel="stylesheet" href="<?php echo base_url();?>asset/css/Prj1.css">
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  <script src="http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
  <link rel="stylesheet" href="http://jqueryui.com/resources/demos/style.css">
  <link rel="stylesheet" href="http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css">
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
  <nav class="adminnavbar">
    <a href="manageaccount">連結雲端硬碟帳號</a> |
    <a href="integration">檔案操作介面</a> |
  </nav>
