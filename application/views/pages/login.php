<!doctype html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html"; charset="utf-8" />
	<title>雲端硬碟整合系統登入頁面</title>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css">
	<!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">

  <!-- Optional theme -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">

  <!-- Latest compiled and minified JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
</head>
<body>  
	<center>
	<div class="panel panel-default" style="width:50%;margin-top:10%;">
		<div class="panel-heading">
			<h1 class="panel-title" style="font-size: 150%;">雲端硬碟整合系統</h3>
		</div>
		<div class="panel-body">
    		<div>
			<form action="<?php echo base_url()?>index.php/users/login" method="post">
				<div style="width:80%;">
					<div class="input-group input-group-lg">
					  <span class="input-group-addon">帳號</span>
					  <input type="text" class="form-control" name="account" placeholder="account@email.com">
					</div>
					<br>
					<div class="input-group input-group-lg">
					  <span class="input-group-addon">密碼</span>
					  <input type="password" class="form-control" name="password">
					</div>
				</div>
				<br>
				<div>
					<input type="submit" class="btn btn-default btn-lg loginbutton" value="登入"></input>
				</div>
			</form>
			</div>
			<div>
			<div>
              <span onclick="return showforgotpw();" style="cursor:pointer;">忘記密碼</span>
              <span>  |  </span>
              <span onclick="return showregister();" style="cursor:pointer;">線上註冊帳號</span>
            </div>
			</div>
		</div>
	</div>
		<div id="registerwindow" hidden>
			<span>輸入信箱(此信箱即帳號)<input id="registeraccount" type="text" maxlength="30"></input></span>
			<span>輸入密碼<input id="registerpw" type="password" maxlength="64"/></span>
			<span>重新輸入密碼<input id="registerpwconfirm" type="password" maxlength="64"/></span>
			<button onclick="return submitregister();">註冊</button>
		</div>
		
		<div id="forgotpwwindow" hidden>
			<span>輸入帳號<input id="forgotpwaccount" type="text"></input></span>
			<button onclick="return submitforgotpw();">將Hash寄到信箱</button>
			<br>
			<span>
			輸入信箱內的Hash
			<input id="forgotpwhash" type="text"></input>
			設定新密碼
			<input id="forgotpwnewpass" type="password"></input>
			<button onclick="return resetwithhash();">重設密碼</button>
			</span>
		</div>
	</center>
	<script src="<?php echo base_url();?>asset/js/login.js"></script>
</body>
</html>
