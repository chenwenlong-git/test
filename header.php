<?php 
session_start();
header("Content-type:text/html;charset=utf-8");
require_once 'include/function.php';	
require_once 'include/config.inc.php';

if(empty($_SESSION['uName'])) echo "<script>location.href='../error.php'</script>";
$vename=$_SESSION['uName'];
$role =isset($_SESSION['uRole']) ? $_SESSION['uRole'] : 0;
//if($role==2) echo "<script>location.href='../error.php'</script>";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1,maximum-scale=1, user-scalable=no">
<title>管理后台</title>
<link href="public/css/bootstrap.min.css" title="" rel="stylesheet" />
<link title="" href="public/css/body.css" rel="stylesheet" type="text/css"  />
<link title="" href="public/css/style.css" rel="stylesheet" type="text/css"  />
<link title="blue" href="public/css/dermadefault.css" rel="stylesheet" type="text/css"/>
<link title="green" href="public/css/dermagreen.css" rel="stylesheet" type="text/css" disabled="disabled"/>
<link title="orange" href="public/css/dermaorange.css" rel="stylesheet" type="text/css" disabled="disabled"/>
<link href="public/css/templatecss.css" rel="stylesheet" title="" type="text/css" />
<link rel="stylesheet" type="text/css" href="public/css/paging.css">

<script src="public/js/jquery-1.11.1.min.js" type="text/javascript"></script>
<script src="public/js/jquery.cookie.js" type="text/javascript"></script>
<script src="public/js/bootstrap.min.js" type="text/javascript"></script>
<script src="public/js/style.js"></script>

<!--angular ui--->
<link rel="stylesheet" title="" type="text/css" href="public/css/ui-layout.css"/>
<script src="public/js/angular.min.js"></script>
<script src="public/js/ui-layout.js"></script>
</head>

<body>
  <div class="right-product right-full" id="bdheader">
     <div class="container-fluid">
		 <div class="message-manage info-center">
			 <div class="page-head">
	          <div class="pull-left">
	          	 <img src="../public/images/uses.png"/>
				           <?php if($role==0){ ?>
						   <h4>欢迎登录：</h4><span><?php echo $vename; ?></span><span class="pu-user">【系统管理员】</span>
						   <?php }else{ ?>
						   <h4>欢迎登录：</h4><span><?php echo $vename; ?></span><span class="pu-user">【配置管理员】</span>
						   <?php } ?>
						</div>
						<div class="pull-back">
	          	<img src="../public/images/backs.png"/>
	          	<span><a href="../ajax.php?act=layout">退出</a></span>
			</div>
		  </div>
       </div>
  </div>
</div>
</body>
</html>