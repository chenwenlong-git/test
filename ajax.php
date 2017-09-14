<?php
session_start();
require_once './include/function.php';
require_once './include/config.inc.php';
require_once './include/syslog.class.php';
require_once './include/class.phpmailer.php';
$syslogclass = new SysLog();

$page = isset($_GET['page']) ? $_GET['page'] : 1;
$act = strip_tags(trim($_REQUEST['act']));
$token = 123456;
function sendSocketMsg($host, $port, $str, $back = 0)
{

    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    if ($socket < 0) return false;
    //$result = socket_connect($socket,$host,$port);
    $result = @socket_connect($socket, $host, $port); //加上@可显示错误信息
    if ($result == false) return false;
    socket_write($socket, $str, strlen($str));
    if ($back != 0) {
        $input = socket_read($socket, 1024);
        socket_close($socket);
        return $input;
    } else {
        socket_close($socket);
        return true;
    }
}

switch ($act) {
    case "ptpl"://打印模版的创建
        $val = $_POST['val'];
        $arr1 = explode('|-|', $val);
        $left = $_POST['left'];
        $arr2 = explode('-', $left);
        $top = $_POST['top'];
        $arr3 = explode('-', $top);
        $fsize = $_POST['fsize'];
        $arr4 = explode('-', $fsize);
        $ffamily = $_POST['ffamily'];
        $arr5 = explode('-', $ffamily);
        $count = $_POST['count'];
        $PrtTpl = "";
        for ($i = 0; $i < $count; $i++) {
            $PrtTpl .= "[" . $i . "," . $arr2[$i + 1] . "," . $arr3[$i + 1] . ",\"" . $arr5[$i + 1] . "\"," . $arr4[$i + 1] . ",\"{" . $arr1[$i + 1] . "}\"],";
        }
        $PrtTpl = substr($PrtTpl, 0, -1);
        $PrtTpl = "[" . $PrtTpl . "]";
        $data = array(
            "Width" => $_POST['width'],
            "Height" => $_POST['height'],
            "BkgImg" => $_POST['href'],
            "PrtTpl" => $PrtTpl,
            "RegTime" => date("Y-m-d H:i:s")
        );
        $rel = $db->save("bc_prttpl", $data);
        if ($rel) outData(1, "增加成功");

        outData(2, "操作有误");

    case "seluser"://得到用户资料信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $vendid = $_SESSION['ven']['venId'];


        $rel = $db->findALL("select count(1) as num from bc_usr where VendInvitCode='" . $vendid . "'");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select uName,Mobile,Email,RegTime from bc_usr where VendInvitCode='" . $vendid . "'limit " . $lim);
        outData(1, $rel, $data);
        outData(2, "操作有误");


    case "chinfo"://修改资料
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $uName = $_SESSION['uName'];
        $data = $db->find("select Mobile,Email from bc_usr where uName='" . $uName . "' limit 1");
        $phone = $_POST['phone'];
        if (empty($phone)) outData(2, "手机号码不能为空");
        if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
            outData('2', '请输入正确的手机号码', '');
        }
        if ($data['Mobile'] != $phone) {
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");
        }
        $email = $_POST['email'];
        if (empty($email)) outData(2, "邮箱不能为空");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
        if ($data['Email'] != $email) {
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");
        }
        //	$VendInvitCode=$_POST['VendInvitCode'];

        $veninfo = $db->find("select Id from " . DB_PREFIX . "vendor where VendName='" . $uName . "' limit 1");
        if ($veninfo) {
            $arr2 = array(
                "VendAddr" => $_POST['VendAddr'],
                "Contacter" => $_POST['Contacter'],
                "VendTelphone" => $_POST['VendTelphone'],
                "VendIntro" => $_POST['VendIntro'],
                "Phone" => $phone,
                "Email" => $email,
                "Name" => $_POST['Name']
            );

            $arr = array(
                "Mobile" => $phone,
                "Email" => $email

            );
            $rel = $db->update(DB_PREFIX . "usr", $arr, "uName='" . $uName . "'");
            if ($rel) {
                $rels = $db->update(DB_PREFIX . "vendor", $arr2, "VendName='" . $uName . "'");
                if ($rels) outData(1, "修改成功");
            }
        } else {
            $arr3 = array(
                "Mobile" => $phone,
                "Email" => $email,
                "VendInvitCode" => $_POST['VendInvitCode']
            );
            $rel = $db->update(DB_PREFIX . "usr", $arr3, "uName='" . $uName . "'");
            if ($rel) outData(1, "修改成功");
        }
        outData(2, "请重新登陆");

    case "alluserinfo"://所有用户信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from com_usr where uRole='" . $role . "'");

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select uName,Mobile,Email,RegTime from com_usr where uRole='" . $role . "' limit " . $lim);

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "allbusinessinfo"://所有业务模型信息

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "business");

        $pagepar = 5;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "business limit " . $lim);

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "allbusinesslist"://所有业务模型列表全部显示出来

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "business");

        $pagepar = 5;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "business ");

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "allmoduleinfo"://所有功能信息

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "module");

        $pagepar = 5;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "module limit " . $lim);

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "allmodulelist"://所有功能信息列表全部显示出来

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "module");

        $pagepar = 5;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "module");

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "allfunctioninfo"://所有模块信息

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "function");

        $pagepar = 5;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "function limit " . $lim);

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "functionbox"://所有模块信息

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "function");

        $pagepar = 500;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("SELECT " . DB_PREFIX . "function.Id," . DB_PREFIX . "function.ModID," . DB_PREFIX . "function.Function," . DB_PREFIX . "module.Module FROM " . DB_PREFIX . "module INNER JOIN " . DB_PREFIX . "function ON " . DB_PREFIX . "module.Id = " . DB_PREFIX . "function.ModID ORDER BY " . DB_PREFIX . "module.Id");

        if ($data) outData(1, $rel, $data);
        outData(2, "未知错误，请重新登陆");


    case "addbusiness"://添加业务模型
        $oldpas = trim($_POST['oldpas']);
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Business" => $oldpas
        );
        $rel = $db->save(DB_PREFIX . "business", $arr);
        if ($rel) {
            outData(1, "增加成功");
        }
        outData(2, "增加失败");


    case "addmodule"://添加功能
        $oldpas = trim($_POST['oldpas']);
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Module" => $oldpas
        );
        $rel = $db->save(DB_PREFIX . "module", $arr);
        if ($rel) {
            outData(1, "增加成功");
        }
        outData(2, "增加失败");

    case "addfunction"://添加模块
        $oldpas = trim($_POST['oldpas']);
        $ModID = trim($_POST['modid']);
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Function" => $oldpas,
            "ModID" => $ModID

        );
        $rel = $db->save(DB_PREFIX . "function", $arr);
        if ($rel) {
            outData(1, "增加成功");
        }
        outData(2, "增加失败");

    case "functionedit"://修改模块
        $oldpas = trim($_POST['oldpas']);
        $ModID = trim($_POST['modid']);
        $id = $_POST['id'];
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Function" => $oldpas,
            "ModID" => $ModID
        );
        $rel = $db->update(DB_PREFIX . "function", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        outData(2, "修改失败");

    case "usrbusinessedit"://修改用户所属的业务
        $oldpas = trim($_POST['oldpas']);
        $ModID = trim($_POST['modid']);
        $id = $_POST['id'];
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "BusinessID" => $ModID
        );
        $rel = $db->update(DB_PREFIX . "usrbusiness", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        outData(2, "修改失败");


    case "businessedit"://修改业务模型
        $oldpas = trim($_POST['oldpas']);
        $id = $_POST['id'];
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Business" => $oldpas
        );
        $rel = $db->update(DB_PREFIX . "business", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        outData(2, "修改失败");


    case "moduleedit"://修改功能
        $oldpas = trim($_POST['oldpas']);
        $id = $_POST['id'];
        if ($oldpas == '') {
            outData(3, "业务模型名称不能为空！");
        }
        $arr = array(
            "Module" => $oldpas
        );
        $rel = $db->update(DB_PREFIX . "module", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        outData(2, "修改失败");


    case "allcodeinfo"://条件筛选功能信息

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");
        $mid = $_GET['mid'];
        $role = isset($_GET['role']) ? $_GET['role'] : 0;
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "function where ModID=" . $mid . "");

        $pagepar = 15;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $data = $db->findAll("select * from " . DB_PREFIX . "function where ModID=" . $mid . " limit " . $lim);

        if ($data) outData(1, $rel, $data);


    case "businessinfo":// 查询开通防伪溯源的用户

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;
        $bid = isset($_GET['bid']) ? $_GET['bid'] : 0;
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "usrbusiness where BusinessID=" . $bid . "");

        $pagepar = 15;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        $data = $db->findAll("select " . DB_PREFIX . "usr.Id as Ida," . DB_PREFIX . "usrbusiness.Id as Idb," . DB_PREFIX . "usr.uName," . DB_PREFIX . "usr.Tel," . DB_PREFIX . "usr.Email," . DB_PREFIX . "usr.RegTime from " . DB_PREFIX . "usr INNER JOIN " . DB_PREFIX . "usrbusiness ON " . DB_PREFIX . "usr.Id = " . DB_PREFIX . "usrbusiness.UsrId where BusinessID=" . $bid . " limit " . $lim);

        if ($data) outData(1, $rel, $data);


    case "showuserinfo"://普通用户登陆显示的主页面
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $uName = $_SESSION['uName'];
        $rel = $db->find("select uName,Mobile,Email from " . DB_PREFIX . "usr where uName='" . $uName . "' limit 1");//用户信息
        if ($rel) {
            $data1 = $db->find("select PrivilLevel,VendID from " . DB_PREFIX . "vendauthor where ReservedInfo='" . $uName . "' limit 1");//是否授权
            if ($data1) {
                $data1['PrivilLevel'] == 0 ? $data1['PrivilLevel'] = "否" : $data1['PrivilLevel'] = "是";
                if ($data1['PrivilLevel'] == "是") {
                    $datav = $db->find("select VendName from " . DB_PREFIX . "vendor where Id='" . $data1['VendID'] . "' limit 1");//厂商是否授权
                } else {
                    $datav['VendName'] = "无";
                }


                $data2 = $db->find("select ProName from " . DB_PREFIX . "pnbind where VendID='" . $data1['VendID'] . "' order by RegTime desc limit 1");//最新产品
                $data3 = $db->find("select ProName from qy_antiforgeryquery where VendID='" . $data1['VendID'] . "' order by QtyCount desc limit 1");//最热产品

            } else {
                $data1['PrivilLevel'] = "否";
                $datav['VendName'] = "无";
            }


            //	$data3=$db->find("select count(1) as num,VendID from ".DB_PREFIX."pnbind group by VendID order by num desc");

            if ($data1['PrivilLevel'] == "否") {
                $data3['ProName'] = "-";
                $data2['ProName'] = "-";
            }

            $data = array(
                "name" => $rel['uName'],
                "Mobile" => $rel['Mobile'],
                "Email" => $rel['Email'],
                "PrivilLevel" => $data1['PrivilLevel'],
                "VendName" => $datav['VendName'],
                "hot" => $data3['ProName'],
                "new" => $data2['ProName']
            );
            outData(1, "查找成功", $data);
        }
        outData(2, "操作错误,请重新登陆");

    case "showveninfo"://厂商登陆显示的主页面
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if (!empty($_SESSION['ven']['venId'])) {
            $venId = $_SESSION['ven']['venId'];
            //print_r($venId);
            //	$data3=$db->find("select count(1) as num from  bc_pnbind  where VendID=".$venId." limit 1" );
            //	$num=$data3['num'];
            unset($data);//清空数组
            $data = $db->findAll("select ProName,TplID,ProPN,RegTime from  bc_pnbind  where VendID=" . $venId . " order by RegTime desc");//得出厂商基本数据
            if (empty($data)) {
                outData(1, "查询成功,暂无数据");
            }


            $data2 = $db->findAll("select ProName,QtyCount from  qy_antiforgeryquery  where VendID=" . $venId);    //得出厂商被查询的数据

            //为了把两次的查询结果放到同一个数组中去.
            foreach ($data as $k => $v) {
                $data[$k]['QtyCount'] = 0;
                foreach ($data2 as $i => $j) {
                    if ($data[$k]['ProName'] == $j['ProName']) {
                        $data[$k]['QtyCount'] = $j['QtyCount'];
                    }
                }
            }

            //分页
            $perPage = 20;
            $len = count($data);
            $data['pageCount'] = ceil($len / $perPage);
            $data['current'] = isset($_GET['page']) ? $_GET['page'] : 1;
            if ($data['current'] == $data['pageCount']) {
                $perPage2 = $len - ($data['current'] - 1) * $perPage;
                $data['list'] = array_slice($data, ($page - 1) * $perPage, $perPage2);
            } else {
                $data['list'] = array_slice($data, ($page - 1) * $perPage, $perPage);
            }

            //	$len=(count($data));//20
            //	$page=isset($_GET['page']) ? $_GET['page'] : 1;
            //	$pageshow = 7;
            //		$totol=ceil($len/$pageshow);
            //	$data=array_slice($data,($page-1)*$pageshow,$pageshow);
            outData(1, $len, $data);
        }
        outData(2, "操作错误,请重新登陆");


    case "selPro":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (isset($_POST['Id'])) {
            $VendID = $_POST['Id'];
            $data = $db->findALL("select ProName from " . DB_PREFIX . "pnbind where VendID=" . $VendID);
            if (!$data) {
                outData(2, "此厂商没有产品");
            }
            outData(1, "查询成功", $data);
        }

    case "emcode":    //通过邮件重置密码
        $keypas = $_POST['keypas'];
        if ($keypas != md5($token)) outData(2, "操作错误");
        $username = $_POST['username'];
        if (empty($pas)) outData(2, "操作错误");
        $pas = $_POST['pas'];
        if (empty($pas)) outData(2, "密码不能为空");

        $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
        if (!preg_match($pattern, $pas)) {
            outData('2', '至少6位且必须包含字母和数字');
        }

        $rpas = $_POST['rpas'];
        if (empty($rpas)) outData(2, "密码不能为空");
        if ($pas != $rpas) outData(2, "两次输的密码不一样");
        $arr = array(
            "uPwd" => md5($rpas)
        );
        $rel = $db->update(DB_PREFIX . "usr", $arr, "uName='" . $username . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        outData(2, "操作不当");


    case "sendemail": //发邮件重新获得密码
        $ename = $_POST['ename'];
        if (empty($ename)) outData(2, "用户名不能为空");
        $useremail = $_POST['useremail'];
        if (empty($useremail)) outData(2, "邮箱地址不能为空");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $useremail)) outData(2, "请输入正确的邮箱地址");
        $rel = $db->find("select uName, Email from " . DB_PREFIX . "usr where uName='" . $ename . "'");
        if ($rel) {
            if ($rel['Email'] == $useremail) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->IsSMTP();
                    $mail->CharSet = 'UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码
                    $mail->SMTPAuth = true;                  //开启认证
                    $mail->Port = 25;
                    $mail->Host = "smtp.163.com";
                    $mail->Username = "15112586572@163.com";
                    $mail->Password = "*2016wangyi*";
                    //$mail->IsSendmail(); //如果没有sendmail组件就注释掉，否则出现“Could  not execute: /var/qmail/bin/sendmail ”的错误提示
                    $mail->AddReplyTo("15112586572@163.com", "华会云数据");//回复地址
                    $mail->From = "15112586572@163.com";
                    $mail->FromName = "华会云数据";
                    $to = $useremail;
                    $mail->AddAddress($to);
                    $mail->Subject = "重置密码" . date("Y-m-d H:i:s");
                    $key = md5($ename . $useremail . $token);
                    $time = time() + 60 * 30;
                    $http = $_SERVER['HTTP_HOST'];
                    $mail->Body = "<html>请尽快点下面链接,时间(半小时内)过期则无效<br>http://" . $http . "/email_code.php?act=" . base64_encode($key) . "&ename=" . $ename . "&useremail=" . $useremail . "&time=" . $time . "</html>";
                    $mail->AltBody = "To view the message, please use an HTML compatible email viewer!"; //当邮件不支持html时备用显示，可以省略
                    $mail->WordWrap = 80; // 设置每行字符串的长度
                    //$mail->AddAttachment("f:/test.png");  //可以添加附件
                    $mail->IsHTML(true);
                    $mail->Send();
                    outData(1, "邮件已发送");
                } catch (phpmailerException $e) {
                    echo "邮件发送失败：" . $e->errorMessage();
                    outData(2, "邮件发送失败");
                }


            }
            outData(2, "邮箱地址与注册的时填的不一样");
        }
        outData(2, "输入的用户名错误");


    case "layout": //登陆退出
        $_SESSION = array();
        session_destroy();
        //	outData(1,"退出成功");
        exit('<script>top.location.href="index.php"</script>');
        break;


    case "chcode": //修改密码
        if (empty($_SESSION['uName'])) outData(3, "你还没有登陆");
        $uName = $_SESSION['uName'];

        $oldpas = $_POST['old'];
        //	if(empty($oldpas)) outData(2,"旧密码不能为空");
        $oldpas = md5($oldpas);
        $rels = $db->find("select uPwd from " . DB_PREFIX . "usr where uName='" . $uName . "' limit 1");
        if ($oldpas != $rels['uPwd']) outData(2, "旧密码输入有误");
        $pas = $_POST['pas'];
        $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
        if (!preg_match($pattern, $pas)) {
            outData('2', '至少6位且必须包含字母和数字');
        }

        if (empty($pas)) outData(2, "密码不能为空");
        $rpas = $_POST['rpas'];
        if (empty($rpas)) outData(2, "请再次输入密码");
        if ($pas != $rpas) outData(2, "两次输入的密码不一样");
        if ($oldpas == md5($rpas)) outData(2, "修改的密码不能和原密码一样");
        $arr = array(
            "uPwd" => md5($rpas)
        );

        $rel = $db->update(DB_PREFIX . "usr", $arr, "uName='" . $uName . "'");
        if ($rel) outData("1", "修改成功");
        outData(2, "操作不当");

    case "authoreidt": //授权用户预留信息修改

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //	if($_SESSION['uRole']!=1) 	 outData(2,"你还没有权限");
        $VendID = $_POST['VendID'];
        if (empty($VendID)) outData(2, "操作有误,请重新登陆");
        $ReservedInfo = $_POST['ReservedInfo'];
        if (empty($ReservedInfo)) outData(3, "用户名预留信息不能为空");
        if ($ReservedInfo == "暂无用户") outData(3, "你还没有用户,不能增加");
        $PrivilLevel = $_POST['PrivilLevel'];
        $AuthoPerson = $_POST['AuthoPerson'];
        if (empty($AuthoPerson)) outData(3, "授权人员不能为空");
        //	$Record=$_POST['Record'];
        //	if(empty($Record)) outData(3,"授权记录不能为空");

        $data = $db->find("select State from " . DB_PREFIX . "vendor where Id='" . $VendID . "' limit 1");
        if ($data['State'] == 0) outData(3, "该厂商还没有权限授权");

        $auth = $_POST['auth'];
        $qauth = "";
        if (strpos($auth, "ata_anal")) $qauth .= " | 质量查询 ";
        if (strpos($auth, "rder")) $qauth .= " | 订单查询 ";
        if (strpos($auth, "rodect")) $qauth .= " | 库存查询 ";

        $str = "";
        if ($PrivilLevel == 1) {
            $str .= $AuthoPerson . ":授权了权限等级;";
            if ($qauth != "")
                $str .= "权限分配为：(" . $qauth . ")";
        } else {
            $str .= $AuthoPerson . ":没有授权权限等级;";
        }

        //	$Whether_syn=$_POST['Whether_syn'];
        //	if(empty($Whether_syn)) outData(2,"是否同步不能为空");
        //	$Time=$_POST['Time'];
        //	if(empty($Time)) outData(2,"创建不能为空");

        //	$rell=$db->find("select Auth from ".DB_PREFIX."vendor where Id=".$VendID." limit 1");
        //	$auth="";
        //	if($rell) $auth=$rell['Auth'];

        $Id = $_POST['Id'];
        $arr = array(
            "Auth" => $qauth,
            "VendID" => $VendID,
            "ReservedInfo" => $ReservedInfo,
            "PrivilLevel" => $PrivilLevel,
            "AuthoPerson" => $AuthoPerson,
            //	"Record"		=>$Record,
            "Record" => $str,
            "Time" => date("Y-m-d H:i:s")

        );

        if (empty($Id)) {
            $rels = $db->find("select VendID  from " . DB_PREFIX . "vendauthor where ReservedInfo='" . $ReservedInfo . "' limit 1");
            if ($rels) outData(3, "预留信息已存在,请更换一个");
            $rel = $db->save(DB_PREFIX . "vendauthor", $arr);
            if ($rel) outData(1, "增加成功");
            outDAta(2, "输入有误");
        } else {
            $rel = $db->update(DB_PREFIX . "vendauthor", $arr, "Id=" . $Id);
            if ($rel) outData(1, "修改成功");
            outDAta(2, "输入有误");
        }


    case "userauthinfo"://用户预留信息查询
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //	if($_SESSION['uRole']!=1) 	 outData(2,"你还没有权限");
        if (!empty($_SESSION['ven']['venId'])) {
            $venId = $_SESSION['ven']['venId'];
            //分页
            $perPage = 20;
            $page = isset($_GET['page']) ? $_GET['page'] : 1;
            $showpage = ($page - 1) * $perPage;
            $num = $db->findAll("select count(1) as num from " . DB_PREFIX . "vendauthor where VendID=" . $venId);//总记录数
            $num[1]['pageCount'] = ceil($num[0]['num'] / $perPage);
            $data = $db->findAll("select * from " . DB_PREFIX . "vendauthor where VendID=" . $venId . " limit " . $showpage . "," . $perPage . "");
            //输出的时候特殊字符要转义
            foreach ($data as $k => $v) {
                $htm1 = $v['ReservedInfo'];
                $htm2 = $v['AuthoPerson'];
                $htm3 = $v['Record'];
                $data[$k]['ReservedInfo'] = htmlspecialchars($htm1);
                $data[$k]['AuthoPerson'] = htmlspecialchars($htm2);
                $data[$k]['Record'] = htmlspecialchars($htm3);
            }

            if ($data) outData(1, $num, $data);
            else outData(1, "查找成功,没有预留用户信息", "");
        }
        outData(2, "未知错误,请重新登陆");

    case "vendel": //厂商删除
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //	if($_SESSION['uRole']!=2) 	 outData(2,"你还没有权限");
        $venid = $_POST['venid'];
        $venau = $_POST['venau'];
        if (empty($venid)) outData(2, "操作有误");
        if (empty($venau)) {
            if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");
            $rel = $db->find("select VendName from " . DB_PREFIX . "vendor where Id=" . $venid . " limit 1");
            if (!$rel) outData(2, "未知错误,请刷新页面");
            $uName = $rel['VendName'];
            $rel = $db->delete(DB_PREFIX . "usr", "uName='" . $uName . "'");
            $rel = $db->delete(DB_PREFIX . "vendor", "Id=" . $venid);
            //$rel=$db->delete(DB_PREFIX."vendauthor","VendID=".$venid);

            //$dname=$_POST['dname'];
            //if($dname!='comm'){           //判断是不是comm数据库，因为这个数据库没下面这些表
            //$rel=$db->delete(DB_PREFIX."datatpl","VendID=".$venid);
            //$rel=$db->delete(DB_PREFIX."pnbind","VendID=".$venid);
            //}

            if ($rel) outData(1, "删除成功", 1);        //1代表厂商信息
            outData(2, "删除失败");
        }
        if ($venau == 2) {
            if ($_SESSION['uRole'] == 1 || $_SESSION['uRole'] == 2) {
                $rel = $db->delete(DB_PREFIX . "vendauthor", "Id=" . $venid);
                if ($rel) outData(1, "删除成功", 2);        //2代表用预留信息
            }
            outData(2, "你还没有权限");
        }


    case "del": //删除功能（参数1：ID 参数2：表名称）
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;
        $venid = $_POST['venid'];
        $venau = $_POST['venau'];
        $table = $_POST['table'];
        if (empty($venid)) outData(2, "操作有误");
        if (empty($venau)) {
            //if($_SESSION['uRole']!=0) outData(2,"你还没有权限");

            $rel = $db->delete(DB_PREFIX . $table, "Id=" . $venid);

            if ($rel) outData(1, "删除成功", 1);
            outData(2, "删除失败");
        }


    case "veneidt": //厂商信息修改
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");
        $venname = $_POST['venname'];
        if (empty($venname)) outData(2, "操作有误");
        $venid = $_POST['venid'];
        if (empty($venid)) outData(2, "操作有误");
        $regtime = date("Y-m-d H:i:s");
        $approval = 1;
        //     if(empty($regtime)) outData(2,"注册时间不能为空");
        $auth = $_POST['auth'];
        $qauth = substr($auth, 1);
        $arr = explode('|', $qauth);
        $test = array();
        $h = 1;
        foreach ($arr as $value) {
            $rel = $db->find("select * from " . DB_PREFIX . "function where Function='" . $value . "'");
            $rel2 = $db->find("select * from " . DB_PREFIX . "module where Id='" . $rel['ModID'] . "'");
            $Md = $rel2['Module'];
            $test['' . $Md . ''][$value]['DBNAME'] = $rel['Dbname'];
            if ($value == '') {
                $h = 0;
            }
        }

        $qauth = json_encode($test, JSON_UNESCAPED_UNICODE);

        if ($h == 0) {
            $qauth = '';
        }
        $rel2 = $db->find("select * from " . DB_PREFIX . "usrbusiness where UsrId='" . $venid . "'");

        if ($rel2) {
            $arr = array(
                "RegTime" => $regtime,
                "BusinessInfo" => $qauth
            );
            $rel = $db->update(DB_PREFIX . "usrbusiness", $arr, "UsrId=" . $venid);    //修改厂商信息
        } else {
            $arr = array(
                "RegTime" => $regtime,
                "BusinessInfo" => $qauth,
                "UsrId" => $venid
            );
            $rel = $db->save(DB_PREFIX . "usrbusiness", $arr);
        }
        if ($rel) {
            outData(1, "修改成功");
        }
        outDAta(2, "修改失败");

    case "AuthorityEdit": //申请信息
        $rel1 = $db->find("select * from " . DB_PREFIX . "application where UsrName='" . $_SESSION['uName'] . "' and Review=0");
        if ($rel1) {
            outData(2, "您上次申请的还没审核通过，需要审核通过才能进行下一次的申请");
        }

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");
        $venname = $_POST['venname'];
        if (empty($venname)) outData(2, "操作有误");
        $venid = $_POST['venid'];
        if (empty($venid)) outData(2, "操作有误");
        $regtime = date("Y-m-d H:i:s");
        $approval = 1;
        //     if(empty($regtime)) outData(2,"注册时间不能为空");
        $auth = $_POST['auth'];
        $qauth = substr($auth, 1);
        $arr = explode('|', $qauth);
        $test = array();
        $h = 1;
        foreach ($arr as $value) {
            $rel = $db->find("select * from " . DB_PREFIX . "function where Function='" . $value . "'");
            $rel2 = $db->find("select * from " . DB_PREFIX . "module where Id='" . $rel['ModID'] . "'");
            $rel3 = $db->find("select * from " . DB_PREFIX . "usr where uName='" . $_SESSION['uName'] . "'");
            $Md = $rel2['Module'];
            $test['' . $Md . ''][$value]['DBNAME'] = $rel['Dbname'];
            if ($value == '') {
                $h = 0;
            }
        }

        $qauth = json_encode($test, JSON_UNESCAPED_UNICODE);

        if ($h == 0) {
            $qauth = '';
        }


        $arr = array(
            "RegTime" => $regtime,
            "UsrName" => $_SESSION['uName'],
            "ApplicationInfo" => $qauth,
            "Role" => $rel3['uRole']
        );
        $rel = $db->save(DB_PREFIX . "application", $arr);    //修改厂商信息


        if ($rel) {
            outData(1, "申请已提交，正在审核中。。");
        }
        outDAta(2, "申请提交失败");

    case "authorityModify": //用户权限修改
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $auth = substr($_POST['auth'], 1);
        $arr = explode(",", $auth);
        $authdata = json_encode($arr);
        if (empty($auth)) {
            $authdata = '';
        }
        $id = $_POST['id'];
        if (empty($id)) outData(2, "操作有误");
        $regtime = date("Y-m-d H:i:s");
        $arr = array(
            "RegTime" => $regtime,
            "CountInfo" => $authdata
        );
        if ($_SESSION['uRole'] == 0) {
            $relv = $db->find("select * from " . DB_PREFIX . "usrbusiness where UsrId=" . $id . "");
            if ($relv) {
                $rel = $db->update(DB_PREFIX . "usrbusiness", $arr, "UsrId=" . $id);
            } else {
                $arr = array(
                    "RegTime" => $regtime,
                    "CountInfo" => $authdata,
                    "UsrId" => $id
                );
                $rel = $db->save(DB_PREFIX . "usrbusiness", $arr);
            }

        } else {

        }
        if ($rel) outData(1, "修改成功");
        outData(2, "修改失败");

    case "businesspower": //业务权限修改
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $auth = substr($_POST['auth'], 1);
        $arr = explode(",", $auth);
        $authdata = json_encode($arr);
        if (empty($auth)) {
            $authdata = '';
        }
        $id = $_POST['id'];
        if (empty($id)) outData(2, "操作有误");
        $regtime = date("Y-m-d H:i:s");
        $arr = array(
            "RegTime" => $regtime,
            "BusinessInfo" => $authdata
        );
        if ($_SESSION['uRole'] == 0) {
            $relv = $db->find("select * from " . DB_PREFIX . "businesspower where UsrId=" . $id . "");
            if ($relv) {
                $rel = $db->update(DB_PREFIX . "businesspower", $arr, "UsrId=" . $id);
            } else {
                $arr = array(
                    "RegTime" => $regtime,
                    "BusinessInfo" => $authdata,
                    "UsrId" => $id
                );
                $rel = $db->save(DB_PREFIX . "businesspower", $arr);
            }

        } else {

        }
        if ($rel) outData(1, "修改成功");
        outData(2, "修改失败");


    case "veninfo"://厂商信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");

        //分页
        $perPage = 10;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $showpage = ($page - 1) * $perPage;
        $num = $db->findAll("select count(1) as num from " . DB_PREFIX . "vendor");//总记录数
        $num[1]['pageCount'] = ceil($num[0]['num'] / $perPage);
        $data = $db->findAll("select * from " . DB_PREFIX . "vendor order by Id desc limit " . $showpage . "," . $perPage . "");


        //	$data=$db->findAll("select * from ".DB_PREFIX."vendor order by Id desc");
        //输出的时候特殊字符要转义
        foreach ($data as $k => $v) {
            $htm1 = $v['VendName'];
            $htm2 = $v['VendIntro'];
            $data[$k]['VendName'] = htmlspecialchars($htm1);
            $data[$k]['VendIntro'] = htmlspecialchars($htm2);
        }
        if ($data) outData(1, $num, $data);
        outData(2, "未知错误,请重新登陆");
    case "mousover":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 2) outData(2, "你还没有权限");
        //$id=$_POST['id'];
        print_r($_POST);
        exit;
        outData(1, "用户名11111或密码错误");

    case "login":
		$loginbox = $_POST['loginbox'];
        $name = $_POST['username'];
        if (empty($name)) outData(2, "用户名不能为空");
        $password = $_POST['password'];
        if (empty($password)) outData(2, "密码不能为空");
        $password = md5($password);
        $code = $_POST['code'];
        if (empty($code)) outData(2, "请拖动滑块进行验证");
        //if(empty($code)) outData(2,"验证码不能为空");
        //if(strtolower($code)!=strtolower($_SESSION['code'])) outData(2,"验证码不对");
        $rel = $db->find("select Id,uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' and uPwd='" . $password . "' limit 1 ");
        if ($rel) {
            //根据角色的不同来跳转
            $_SESSION['uName'] = $rel['uName'];
            $_SESSION['uRole'] = isset($rel['uRole']) ? $rel['uRole'] : 0;
            $_SESSION['Id'] = $rel['Id'];
			if($loginbox==1){
              setcookie("usrname",$name,time()+12*7*24*3600);  //保存12个星期
			  setcookie("userpass",$_POST['password'],time()+12*7*24*3600);  //保存12个星期
			}else{
              setcookie("usrname", "", time()-3600);
			  setcookie("userpass", "", time()-3600);
			}
			if($rel['uRole']==0 || $rel['uRole']==1){
			  outData(1, "登陆成功");
			}else{
			 outData(3, "登陆成功");
			}
            
        } else {
            outData(2, "用户名或密码错误");
        }
        break;

    case "rusername":
        $name = $_POST['rusername'];
        if (empty($name)) outData(2, "用户名不能为空");
        $pattern = '/administrator/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/admin/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/test/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
        if ($oldrel) outData(2, "用户名已被注册");
        outData(1, "通过");
    case "rpassword":
        $password = $_POST['rpassword'];
        $rpassword = $_POST['rrpassword'];
        if (empty($password)) outData(2, "密码不能为空");
        $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
        if (!preg_match($pattern, $password)) {
            //outData('2','至少6位且包含字母和数字');
            outData('2', '密码格式错误');
        }
        if (empty($rpassword)) {
            outData(1, "通过");
        } else if ($rpassword != "") {
            if ($password != $rpassword) {
                outData(3, "通过", "两次密码不一样");
            } else {
                outData(4, "通过", "通过");
            }
        }

    case "rrpassword":
        $rpassword = $_POST['rrpassword'];
        if (empty($rpassword)) outData(2, "请再输一次密码");
        $password = $_POST['rpassword'];
        if ($password != $rpassword) outData(2, "两次密码不一样");
        outData(1, "通过");
    case "phone":
        $phone = $_POST['phone'];
        if (empty($phone)) outData(2, "手机号码不为空");
        if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
            outData('2', '手机号格式错误', '');
        }
        $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
        if ($oldrel2) outData(2, "该手机已被注册");
        outData(1, "通过");

    case "phonee": //只验证手机号码的格式
        $phone = $_POST['phone'];
        if (!empty($phone)) {
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
                outData('2', '手机号格式错误', '');
            }
        } else {
            outData(1, "");
        }
        outData(1, "通过");


    case "remail":
        $email = $_POST['remail'];
        if (empty($email)) outData(2, "邮箱不能为空");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $email)) outData(2, "邮箱格式错误");
        $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
        if ($oldrel3) outData(2, "该邮箱已被注册");
        outData(1, "通过");

    case "rcode":
        $code = $_POST['rcode'];
        if (empty($code)) outData(2, "验证码不能为空");
        if (strtolower($code) != strtolower($_SESSION['code'])) outData(2, "验证码不对");
        outData(1, "通过");

    case "vename":
        $vename = $_POST['vename'];
        if (empty($vename)) outData(2, "厂商名不能为空");
        outData(1, "通过");
    case "person":
        $person = $_POST['person'];
        if (empty($person)) outData(2, "联系人不能为空");
        outData(1, "通过");
    case "mobile":
        $mobile = $_POST['mobile'];
        if (empty($mobile)) outData(2, "固定电话不能为空");
        if (!preg_match('/0\d{2,3}-\d{5,9}|0\d{2,3}-\d{5,9}/', $mobile)) {
            outData('2', '请输入格式如：010-1234567');
        }
        outData(1, "通过");

    case "addre":
        $addre = $_POST['addre'];
        if (empty($addre)) outData(2, "厂商地址不能为空");
        outData(1, "通过");


    case "adminreg":    //后台增加厂商
        $role = $_POST['role'];//分普通用户和厂商注册两种情况
        if ($role == 1) {
            $name = $_POST['rusername'];
            if (empty($name)) outData(2, "用户名不能为空");
            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
            if ($oldrel) outData(2, "用户名已被注册过了");
            $password = $_POST['rpassword'];
            $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
            if (!preg_match($pattern, $password)) {
                outData('2', '至少6位且必须包含字母和数字');
            }
            if (empty($password)) outData(2, "密码不能为空");
            $rpassword = $_POST['rrpassword'];
            if (empty($rpassword)) outData(2, "请再输一次密码");
            if ($password != $rpassword) outData(2, "两次密码不一样");

            $vename = $_POST['vename'];
            if (empty($vename)) outData(2, "厂商名不能为空");

            $department = trim($_POST['department']);
            if (empty($department)) outData(2, "所属部门不能为空");

            $urole = $_POST['urole'];
            if (empty($urole)) outData(2, "参数错误！");

            $person = $_POST['person'];
            if (empty($person)) outData(2, "联系人不能为空");

            $phone = $_POST['phone'];
            if (empty($phone)) outData(2, "手机号码不能为空");
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
                outData('2', '请输入正确的手机号码', '');
            }
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");

            // $mobile=$_POST['mobile'];
            //if(empty($mobile)) outData(2,"固定电话不能为空");
            //if(!preg_match('/0\d{2,3}-\d{5,9}|0\d{2,3}-\d{5,9}/',$mobile)){
            //	outData('2','请输入正确的固定电话格式如：010-1234567');
            //	}

            $email = $_POST['remail'];
            if (empty($email)) outData(2, "邮箱不能为空");
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");
            $vendintro = $_POST['vendintro'];
            if (empty($vendintro)) outData(2, "厂商简介不能为空");
            $addre = $_POST['addre'];
            if (empty($addre)) outData(2, "厂商地址不能为空");
            $auth = $_POST['auth'];
            $arr = array(
                "uNick" => $vename,
                "Corp" => $person,
                "Tel" => $vendintro,
                "Addr" => $addre,
                "uName" => $name,
                "uPwd" => md5($password),
                "Email" => $email,
                "RegTime" => date("Y-m-d H:i:s"),
                "Mobile" => $phone,
                "uRole" => $urole,
                "Department" => $department
            );
            $rel = $db->save(DB_PREFIX . "usr", $arr);

            if ($rel) {
                outData(1, "增加成功");
            }

            outData(2, "操作错误");
        }
        outData(2, "操作错误");

    case "useredit":    //后台管理员修改用户信息
        $role = $_POST['role'];//分普通用户和厂商注册两种情况
        if ($role == 1) {
            $id = $_POST['id'];
            $name = $_POST['rusername'];
            if (empty($name)) outData(2, "用户名不能为空");
            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Id!=" . $id . " and uName='" . $name . "' limit 1 ");
            if ($oldrel) outData(2, "用户名已被注册过了");

            $password = $_POST['rpassword'];
            if (!empty($password)) {
                $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
                if (!preg_match($pattern, $password)) {
                    outData('2', '至少6位且必须包含字母和数字');
                }
                if (empty($password)) outData(2, "密码不能为空");
                $rpassword = $_POST['rrpassword'];
                if (empty($rpassword)) outData(2, "请再输一次密码");
                if ($password != $rpassword) outData(2, "两次密码不一样");
            }


            $vename = $_POST['vename'];
            if (empty($vename)) outData(2, "厂商名不能为空");

            $department = trim($_POST['department']);
            if (empty($department)) outData(2, "所属部门不能为空");

            $urole = $_POST['urole'];
            if (empty($urole)) outData(2, "参数错误！");

            $person = $_POST['person'];
            if (empty($person)) outData(2, "联系人不能为空");

            $phone = $_POST['phone'];
            if (empty($phone)) outData(2, "手机号码不能为空");
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
                outData('2', '请输入正确的手机号码', '');
            }
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Id!=" . $id . " and Mobile='" . $phone . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");

            // $mobile=$_POST['mobile'];
            //if(empty($mobile)) outData(2,"固定电话不能为空");
            //if(!preg_match('/0\d{2,3}-\d{5,9}|0\d{2,3}-\d{5,9}/',$mobile)){
            //	outData('2','请输入正确的固定电话格式如：010-1234567');
            //	}

            $email = $_POST['remail'];
            if (empty($email)) outData(2, "邮箱不能为空");
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Id!=" . $id . " and Email='" . $email . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");
            $vendintro = $_POST['vendintro'];
            if (empty($vendintro)) outData(2, "厂商简介不能为空");
            $addre = $_POST['addre'];
            if (empty($addre)) outData(2, "厂商地址不能为空");
            $auth = $_POST['auth'];

            
            if (!empty($password)) {
				$arr = array(
                "uNick" => $vename,
                "Corp" => $person,
                "Tel" => $vendintro,
                "Addr" => $addre,
                "uName" => $name,
                "Email" => $email,
                "RegTime" => date("Y-m-d H:i:s"),
                "Mobile" => $phone,
                "uRole" => $urole,
                "Department" => $department,
				"uPwd" => md5($password)

              );
                
            }else{
			   $arr = array(
                "uNick" => $vename,
                "Corp" => $person,
                "Tel" => $vendintro,
                "Addr" => $addre,
                "uName" => $name,
                "Email" => $email,
                "RegTime" => date("Y-m-d H:i:s"),
                "Mobile" => $phone,
                "uRole" => $urole,
                "Department" => $department,
				
              );
			
			}

            $rel = $db->update(DB_PREFIX . "usr", $arr, "Id='" . $id . "'");
            /*  //增加业务和功能
				if($rel){
					$arrven=array(
					"VendIntro"	=>$vendintro,
					"Name"	=>$vename,
					"VendName" =>$name,
					"Contacter"=>$person,
					"VendTelphone" =>$mobile,
					"Email"	=>$email,
					"VendAddr" =>$addre,
					"RegTime" =>date("Y-m-d H:i:s"),
					"Phone"  =>$phone,
					"Auth"  =>$auth,
					"State" =>0

				);
				   $rels = $db->save(DB_PREFIX."vendor",$arrven);*/
            if ($rel) {
                outData(1, "修改成功");
            }
            //}
            outData(2, "操作错误");
        }
        outData(2, "操作错误");

//运维管理后台信息修改
    case "mgtedit":
        $mgtjson = $_POST['mgtjson'];
        $role = $_POST['role'];
        if ($role == 1) {
            $id = $_POST['id'];
            $Name = $_POST['softName'];
            if (empty($Name)) outData(2, "请填写软件名称");
//            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Id!=" . $id . " and uName='" . $name . "' limit 1 ");
//            if ($oldrel) outData(2, "用户名已被注册过了");
            $SoftInfo = $_POST['SoftInfo'];
            if (empty($SoftInfo)) outData(2, "请填写软件信息");
            $status = $_POST['status'];
            $Ip = $_POST['Ip'];
            if (empty($Ip)) outData(2, "请填写IP地址");
            if (!filter_var($Ip, FILTER_VALIDATE_IP)) {
                outData(2, "请输入合法的IP地址");
                // it's valid
            }
            $Mark = $_POST['Mark'];
            if (empty($Mark)) outData(2, "请填写软件详情");
            $arr = array(
                "Id" => $id,
                "Name" => $Name,
                "SoftInfo" => $SoftInfo,
                "status" => $status,
                "Ip" => $Ip,
                "Mark" => $Mark,
                "ModTime" => date("Y-m-d H:i:s")
            );
            $mgtjson["PARAM"]["SOFTINFO"] = $SoftInfo;
            $mgtjson["PARAM"]["REPORTTIME"] = date("Y-m-d H:i:s", time());
            $mgtjson["PARAM"]["MESSAGE"]["NAME"] = $Name;
            $mgtjson["PARAM"]["MESSAGE"]["IP"] = $Ip;
            $rel = $db->update(DB_PREFIX . "devsoft", $arr, "Id='" . $id . "'");
            $host = "192.168.0.138";
            $port = 5566;
            if ($rel) {
                //操作日志成功数组

                sendSocketMsg($host, $port, json_encode($mgtjson) . ";");
                outData(1, "修改成功");
            } else {
                //操作日志失败数组

                outData(2, "操作错误");

            }

        }
        outData(2, "操作错误");


    //运维管理处理模块预警信息修改
    case "mgwarningtedit":
        $id = $_POST['id'];
        $Wname = $_POST['Wname'];
        if (empty($Wname)) outData(2, "请填写预警名称");
        $Wlevel = $_POST['Wlevel'];
        $ProStatus = $_POST['ProStatus'];
        $Wtime = $_POST['Wtime'];
        $arr = array(
            "Wname" => $Wname,
            "Wlevel" => $Wlevel,
            "ProStatus" => $ProStatus,
            "Wtime" => $Wtime
        );
        $rel = $db->update(DB_PREFIX . "warninginfo", $arr, "id='" . $id . "'");
        if ($rel) {
            //操作日志成功数组
            outData(1, "修改成功");
        } else {
            //操作日志失败数组
            outData(2, "操作错误");
        }
        //}
        outData(2, "操作错误");
        outData(2, "操作错误");


    //运维管理处理模块预警信息修改
    case "sendNotice":
        $receiver = $_POST['receiver'];
        if (empty($receiver)) outData(2, "请输入接收用户");
        $result = $db->find("select uName from " . DB_PREFIX . "usr  where uName='" . $receiver . "' limit 1 ");
        if (!$result) outData(2, "输入的用户不存在，请重新输入");
        $content = $_POST['content'];
        if (empty($content)) outData(2, "请输入发送内容");
        $sender = $_SESSION["uName"];
        $posttime = time();
        $arr = array(
            "sender" => $sender,
            "receiver" => $receiver,
            "posttime" => $posttime,
            "content" => $content
        );
        $rel = $db->save(DB_PREFIX . "sysnotice", $arr);
        if ($rel) {
            //操作日志成功数组
            outData(1, "修改成功");
        } else {
            //操作日志失败数组
            outData(2, "操作错误");
        }
        //}
        outData(2, "操作错误");
        outData(2, "操作错误");

    //运维管理部署模块文件信息修改
    case "mgtsoftuploaded":
        $SoftName = $_POST['SoftName'];
        $FilePath = $_POST['FilePath'];
        $CurrVersion = $_POST['CurrVersion'];
        $detail = $_POST['detail'];
        if (empty($SoftName)) outData(2, "请填写文件名称");
        if (empty($FilePath)) outData(2, "请选择文件路径");
        $arr = array(
            "SoftName" => $SoftName,
            "FilePath" => $FilePath,
            "detail" => $detail,
            "CurrVersion" => 0,
            "HisVersion" => 0,
            "NewVersion" => $CurrVersion,
            "creater" => $_SESSION['uName'],
            "posttime" => date("Y-m-d H:i:s")
        );

        $rel = $db->save(DB_PREFIX . "softversion", $arr);
        if ($rel) {
            outData(1, "上传成功");
        }
        //}
        outData(2, "操作错误");

    //运维管理部署模块文件信息修改
    case "mgtfileuploaded":
        $filename = $_POST['filename'];
        $filepath = $_POST['filepath'];
        $pid = $_POST['pid'];
        $detail = $_POST['detail'];
        if (empty($filename)) outData(2, "请填写文件名称");
        if (empty($filepath)) outData(2, "请选择文件路径");
        $arr = array(
            "filename" => $filename,
            "filepath" => $filepath,
            "detail" => $detail,
            "pid" => $pid,
            "creater" => $_SESSION['uName'],
            "creattime" => date("Y-m-d H:i:s")
        );
        $rel = $db->save(DB_PREFIX . "fileinfo", $arr);
        if ($rel) {
            outData(1, "上传成功");
        }
        //}
        outData(2, "操作错误");


    //运维管理部署模块文件信息修改
    case "mgtfileedit":
        $id = $_POST['id'];
        $filename = $_POST['filename'];
        $filepath = $_POST['filepath'];
        $detail = $_POST['detail'];
        if (empty($filename)) outData(2, "请填写文件名称");
        if (empty($filepath)) outData(2, "请选择文件路径");
        $arr = array(
            "filename" => $filename,
            "filepath" => $filepath,
            "detail" => $detail,
            "ModTime" => date("Y-m-d H:i:s")
        );
        $rel = $db->update(DB_PREFIX . "fileinfo", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");
        outData(2, "操作错误");


    //运维管理配置模块软件信息修改
    case "mgtsoftedit":
        $id = $_POST['id'];
        $SoftName= $_POST['SoftName'];
        $FilePath= $_POST['FilePath'];
        $detail = $_POST['detail'];
        if (empty($SoftName)) outData(2, "请填写软件名称");
        if (empty($FilePath)) outData(2, "请填写软件路径");
        $arr = array(
            "SoftName" => $SoftName,
            "FilePath" => $FilePath,
            "detail" => $detail,
            "modifer" => $_SESSION["uName"],
            "UpdTime" => date("Y-m-d H:i:s")
        );
        $rel = $db->update(DB_PREFIX . "softversion", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");

    //运维管理配置模块软件信息更新
    case "mgtsoftupd":
        $id = $_POST['id'];
        $SoftName= $_POST['SoftName'];
        $CurrVersion= $_POST['CurrVersion'];
        $FilePath= $_POST['FilePath'];
        $detail = $_POST['detail'];
        if (empty($SoftName)) outData(2, "请填写软件名称");
        if (empty($FilePath)) outData(2, "请填写软件路径");
        $arr = array(
            "SoftName" => $SoftName,
            "FilePath" => $FilePath,
            "CurrVersion" => $CurrVersion,
            "detail" => $detail,
            "modifer" => $_SESSION["uName"],
            "UpdTime" => date("Y-m-d H:i:s")
        );
        $rel = $db->update(DB_PREFIX . "softversion", $arr, "Id='" . $id . "'");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");


    //运维管理部署模块反馈问题信息修改
    case "mgtfeedbackedit":
        $attachment = $_POST['attachment'];
        $title = $_POST['title'];
        if (empty($title)) outData(2, "请填写反馈标题");
        $content = $_POST['content'];
        if (empty($content)) outData(2, "请填写反馈内容");
        //修改问题反馈
        if ($_POST['id']) {
            $userid = $_POST['userid'];
            $feedtime = $_POST['feedtime'];
            $over = $_POST['over'];
            $arr = array(
                "title" => $title,
                "userid" => $userid,
                "feedtime" => $feedtime,
                "over" => $over,
                "attachment" => $attachment,
                "content" => $content
//            "ModTime" => date("Y-m-d H:i:s")
            );
            $rel = $db->update(DB_PREFIX . "feedback", $arr, "id='" . $id . "'");
            if ($rel) {
                outData(1, 2, "修改成功");
            }
            //新增问题反馈
        } else {
            $arr = array(
                "title" => $title,
                "userid" => $_SESSION["Id"],
                "feedtime" => date("Y-m-d H:i:s", time()),
                "over" => 0,
                "attachment" => $attachment,
                "content" => $content
//            "ModTime" => date("Y-m-d H:i:s")
            );
            $rel = $db->save(DB_PREFIX . "feedback", $arr);
            if ($rel) {
                outData(1, 1, "新增成功");
            }

        }

        //}
        outData(2, "操作错误");
        outData(2, "操作错误");
    //运维管理部署管理管理后台部署信息设置
    case "deployConfig":
        $arr = $_POST;
        $rel = $db->update(DB_PREFIX . "sysconfig", $arr, "Id=1");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");


    //运维管理部署管理一键部署
    case "oneClickDeploy":
        $file="/home/deploy/QRCloudServer.tar.gz";
        $dir="/home/deploy/";
        $sh="/home/deploy/deploy.sh";
        // system("cd /home/deploy",$value);
        system("cd /home/deploy;/home/deploy/deploy.sh /home/deploy/QRCloudServer.tar.gz /home/deploy/ >> log.txt",$value);
        // system("cd /home/deploy;./monitor.sh start >> log.txt",$value);
        if($value==0){
            outData(1, "部署成功");
        }
        outData(2, "操作错误");
    //运维管理安全管理信息设置
    case "safeConfig":
        $arr = $_POST;
        $rel = $db->update(DB_PREFIX . "sysconfig", $arr, "Id=1");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");

        //运维管理安全管理手动还原设置
    case "handreset"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from com_handreset ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from com_handreset ORDER BY  id DESC limit " . $lim);
        outData(1, $rel, $data);
        outData(2, "操作有误");

//   产品管理

    ////产品名管理
    case "productInfo":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from qis_productinfo ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from qis_productinfo ORDER BY  id DESC limit " . $lim);
        foreach($data as $key=>$val){
            if($val["Support"]==1){
                $data[$key]["isSupport"]="支持";
            }else{
                $data[$key]["isSupport"]="不支持";
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
* 新增/修改产品
*/
    case "productAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!trim($_POST["Products"])) outData(2, "请输入产品名称");
        if(!trim($_POST["Alias"])) outData(2, "请设置别名");
        $arr["Products"] = trim($_POST["Products"]);
        $arr["Alias"]=trim($_POST["Alias"]);
        $arr["Support"]=trim($_POST["Support"]);
        //新增
        if(empty($_POST["Id"])){
            $arr["RegTime"]=date("Y-m-d H:i:s");
            //验证产品名是否重复
            $products = $db->findALL("select id from qis_productinfo where Products='" . $arr["Products"] . "'");
            if($products){
                outData(2, "产品名[".$arr["Products"]."]已经被使用，请重新输入");
            }
            $Alias= $db->findALL("select id from qis_productinfo where Alias='" . $arr["Alias"] . "'");
            if($Alias){
                outData(2, "别名[".$arr["Alias"]."]已经被使用，请重新输入");
            }
            $rel = $db->save("qis_productinfo", $arr);
            if ($rel) outData(1, "新增成功", 2);
        //修改
        }else{
            //验证产品名是否重复
            $productspre = $db->findALL("select Products ,Alias from qis_productinfo where Id='" . $_POST["Id"] . "'");
            if($productspre[0]["Products"]!=$arr["Products"]){
                $products = $db->findALL("select count(1) as num  from qis_productinfo where Products='" . $arr["Products"] . "'");
                if($products[0]['num']>0){
                    outData(2, "产品名[".$arr["Products"]."]已经被使用，请重新输入");
                }
            }
            if($productspre[0]["Alias"]!=$arr["Alias"]) {
                $Alias= $db->findALL("select count(1) as num  from qis_productinfo where Alias='" . $arr["Alias"] . "'");
                if($Alias[0]['num']>0){
                    outData(2, "别名[".$arr["Alias"]."]已经被使用，请重新输入");
                }
            }

            $rel = $db->update("qis_productinfo", $arr, "Id=".$_POST["Id"]);
            if ($rel) outData(1, "修改成功", 2);
        }
        outData(2, "操作有误");


    /*
* 产品更改系统支持状态
*/
    case "productDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $arr["Support"]=0;
        $rel = $db->update("qis_productinfo", $arr, "Id=".$_POST["Id"]);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");

    ////供应商管理
    case "supplierInfo":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from qis_supplierinfo ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from qis_supplierinfo ORDER BY  id DESC limit " . $lim);

        outData(1, $rel, $data);
        outData(2, "操作有误");



    /*
* 新增修改供应商
*/
    case "supplierAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!trim($_POST["Supplier"])) outData(2, "请输入供应商名称");
        $arr["Supplier"]=trim($_POST["Supplier"]);
        //新增
        if(empty($_POST["Id"])){
            $arr["RegTime"]=date("Y-m-d H:i:s");
            //验证产品名是否重复
            $products = $db->findALL("select id from qis_supplierinfo where Supplier='" . $arr["Supplier"] . "'");
            if($products){
                outData(2, "供应商名称[".$arr["Supplier"]."]已经被使用，请重新输入");
            }
            $rel = $db->save("qis_supplierinfo", $arr);
            if ($rel) outData(1, "新增供应商成功", 2);
        //修改
        }else{
            //验证产品名是否重复
            $productspre = $db->findALL("select Supplier  from qis_supplierinfo where Id='" . $_POST["Id"] . "'");
            if($productspre[0]["Supplier"]!=$arr["Supplier"] ){
                $products = $db->findALL("select count(1) as num  from qis_supplierinfo where Supplier='" . $arr["Supplier"] . "'");
                if($products[0]['num']>0){
                    outData(2, "供应商名称[".$arr["Supplier"]."]已经被使用，请重新输入");
                }
            }

            $rel = $db->update("qis_supplierinfo", $arr, "Id=".$_POST["Id"]);
            if ($rel) outData(1, "修改供应商成功", 2);
        }
        outData(2, "操作有误");



    /*
* 删除供应商
*/
    case "supplierDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->delete("qis_supplierinfo", "Id=" . $_POST["Id"]);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");



    ////SN编码管理
    case "sncodeInfo":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from qis_sncodeinfo ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from qis_sncodeinfo ORDER BY  id DESC limit " . $lim);

        outData(1, $rel, $data);
        outData(2, "操作有误");



    /*
* 新增修改SN编码
*/
    case "sncodeAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!trim($_POST["Prefix"])) outData(2, "请输入Prefix");
        if(!trim($_POST["EEEECode"])) outData(2, "请输入EEEECode");
        $arr["Prefix"]=trim($_POST["Prefix"]);
        $arr["EEEECode"]=trim($_POST["EEEECode"]);
        //新增
        if(empty($_POST["Id"])){
            $arr["RegTime"]=date("Y-m-d H:i:s");
            //验证Prefix是否重复
            $products = $db->findALL("select id from qis_sncodeinfo where Prefix='" . $arr["Prefix"] . "'");
            if($products){
                outData(2, "Prefix：[".$arr["Prefix"]."]已经被使用，请重新输入");
            }
            //验证EEEECode是否重复
            $products = $db->findALL("select id from qis_sncodeinfo where EEEECode='" . $arr["EEEECode"] . "'");
            if($products){
                outData(2, "EEEECode：[".$arr["EEEECode"]."]已经被使用，请重新输入");
            }
            $rel = $db->save("qis_sncodeinfo", $arr);
            if ($rel) outData(1, "新增SN编码成功", 2);
            //修改
        }else{
        }
        outData(2, "操作有误");


    /*
* 删除SN编码
*/
    case "sncodeDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->delete("qis_sncodeinfo", "Id=" . $_POST["Id"]);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");


    ////APN/CPN管理
    case "apncpnInfo":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from qis_apncpninfo ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from qis_apncpninfo ORDER BY  id DESC limit " . $lim);

        outData(1, $rel, $data);
        outData(2, "操作有误");

    /*
* 新增修改apn/cpn
*/
    case "apncpnAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!trim($_POST["APN"])) outData(2, "请输入APN");
        if(!trim($_POST["CPN"])) outData(2, "请输入CPN");
        $arr["APN"]=trim($_POST["APN"]);
        $arr["CPN"]=trim($_POST["CPN"]);
        //新增
        if(empty($_POST["Id"])){
            $arr["RegTime"]=date("Y-m-d H:i:s");
            //验证Prefix是否重复
            $products = $db->findALL("select id from qis_apncpninfo where APN='" . $arr["APN"] . "'");
            if($products){
                outData(2, "APN：[".$arr["APN"]."]已经被使用，请重新输入");
            }
            //验证EEEECode是否重复
            $products = $db->findALL("select id from qis_apncpninfo where CPN='" . $arr["CPN"] . "'");
            if($products){
                outData(2, "CPN：[".$arr["CPN"]."]已经被使用，请重新输入");
            }
            $rel = $db->save("qis_apncpninfo", $arr);
            if ($rel) outData(1, "新增APN/CPN成功", 2);
            //修改
        }else{
        }
        outData(2, "操作有误");

    /*
* 删除apn/cpn
*/
    case "apncpnDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->delete("qis_apncpninfo", "Id=" . $_POST["Id"]);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");


    ////产品关系管理
    case "prorelationship":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from qis_productrela WHERE Support ='1' ORDER BY  id DESC ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select *from qis_productrela WHERE Support ='1' ORDER BY  id DESC limit " . $lim);
        foreach($data as $key=>$val){
            if($val["Support"]==1){
                $data[$key]["isSupport"]="支持";
            }else{
                $data[$key]["isSupport"]="不支持";
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");

    ////获取EEEECode
    case "getEEEECode":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $data = $db->find("select *from qis_sncodeinfo WHERE Id =".$_POST["Id"]);
        outData(1,"",$data);
        outData(2, "操作有误");


    ////获取CPN
    case "getCPN":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $data = $db->find("select *from qis_apncpninfo WHERE Id =".$_POST["Id"]);
        outData(1,"",$data);
        outData(2, "操作有误");



    /*
* 新增修改apn/cpn
*/
    case "prorelaAdd":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!trim($_POST["Products"])) outData(2, "请输入产品名称");
        if(!trim($_POST["Supplier"])) outData(2, "请输入供应商");
        if(!trim($_POST["Prefix"])) outData(2, "请输入Prefix");
        if(!trim($_POST["APN"])) outData(2, "请输入APN");
        $arr["Products"]=trim($_POST["Products"]);
        $arr["Supplier"]=trim($_POST["Supplier"]);

        $arr["CPN"]=trim($_POST["CPN"]);

        $arr["EEEECode"]=trim($_POST["EEEECode"]);
        $data = $db->findAll("select Products,Supplier,APN,CPN,Prefix,EEEECode from qis_productrela  ORDER BY  id DESC ");
        //新增
        if(empty($_POST["Id"])){
            $arr["Prefix"]=trim($_POST["Prefix"]);
            $sncodeinfo= $db->find("select *from qis_sncodeinfo WHERE Id =".$arr["Prefix"]);
            $arr["Prefix"]=trim($sncodeinfo["Prefix"]);
            $arr["APN"]=trim($_POST["APN"]);
            $apncpninfo= $db->find("select *from qis_apncpninfo WHERE Id =".$arr["APN"]);
            $arr["APN"]=trim($apncpninfo["APN"]);
            //验证是否重复
            foreach($data as $key=>$value){
                $diff=array_diff($arr,$value);
                if(empty($diff)){
                    outData(2, "产品关系已经存在，请重新输入");
                }
            }
            $arr["RegTime"]=date("Y-m-d H:i:s");
            $rel = $db->save("qis_productrela", $arr);
            if ($rel) outData(1, "新增产品关系成功", 2);
            //修改
        }else{
            $productrela = $db->find("select Products,Supplier,CPN,EEEECode,Prefix,APN from qis_productrela  WHERE Id=".$_POST["Id"]);
            //未修改Prefix
            if($_POST["Prefix"]==$productrela["Prefix"]){
                $arr["Prefix"]=trim($_POST["Prefix"]);
            }else{
                $arr["Prefix"]=trim($_POST["Prefix"]);
                $apncpninfo= $db->find("select *from qis_sncodeinfo WHERE Id =".$arr["Prefix"]);
                $arr["Prefix"]=trim($apncpninfo["Prefix"]);
            }
            //未修改APN
            if($_POST["APN"]==$productrela["APN"]){
                $arr["APN"]=trim($_POST["APN"]);
            }else{
                $arr["APN"]=trim($_POST["APN"]);
                $sncodeinfo= $db->find("select *from qis_apncpninfo WHERE Id =".$arr["APN"]);
                $arr["APN"]=trim($sncodeinfo["APN"]);
            }
            $diff=array_diff($arr,$productrela);
                if(!empty($diff)){
                    //验证是否重复
                    foreach($data as $key=>$value){
                        $diff2=array_diff($arr,$value);
                        if(empty($diff2)){
                            outData(2, "产品关系已经存在，请重新输入");
                        }
                    }
                }
            $rel = $db->update("qis_productrela", $arr, "Id=".$_POST["Id"]);
            if ($rel) outData(1, "修改成功", 2);
        }
        outData(2, "操作有误");

    /*
* 新增设备
*/
    case "deviceadd":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!trim($_POST["location"])) outData(2, "请输入位置");
        if (!trim($_POST["teststation"])) outData(2, "请输入测试站名称");
        if (!trim($_POST["SoftInfo"])) outData(2, "请输入软件名称");
        if (!trim($_POST["Ip"])) outData(2, "请输入IP地址");
        $arr["location"] = trim($_POST["location"]);
        $arr["teststation"] = trim($_POST["teststation"]);
        $arr["SoftInfo"] = trim($_POST["SoftInfo"]);
        $arr["Ip"] = trim($_POST["Ip"]);
        if (!preg_match('/^((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1 -9]?\d))))$/', $arr["Ip"])) outData(2, "输入的IP地址错误！");

        $data = $db->findAll("select location,teststation,softname,SoftInfo,Ip from com_devsoft ORDER BY  id DESC ");
        //新增
        if (empty($_POST["Id"])) {
            $arr["softVersion"] = trim($_POST["softVersion"]);

            $tmp["Ip"] = $arr["Ip"];
            //验证是否重复
            $ishas = $db->find("select Id from com_devsoft  WHERE Ip= '" . $tmp["Ip"] . "'");
            if (!empty($ishas)) {
                outData(2, "IP地址已经存在，请重新输入");
            }
            $arr["RegTime"] = date("Y-m-d H:i:s");
            $arr["ModTime"] = date("Y-m-d H:i:s");
            $rel = $db->save("com_devsoft", $arr);
            //新增设备的同时，新增软件版本信息
            if ($rel) {
                $arr2["SoftInfo"] = $arr["SoftInfo"];
                $arr2["CurrVersion"] = trim($_POST["softVersion"]);
                $arr2["HisVersion"] = trim($_POST["softVersion"]);
                $arr2["NewVersion"] = trim($_POST["softVersion"]);
                $arr2["posttime"] = date("Y-m-d H:i:s");
                $arr2["UpdTime"] = date("Y-m-d H:i:s");
                $arr2["creater"] = $_SESSION['uName'];
                $arr2["modifer"] = $_SESSION['uName'];
                $rel2 = $db->save("com_softversion", $arr2);

            }
            if ($rel2) outData(1, "新增设备成功", 2);
            //修改
        }
        outData(2, "操作有误");


    /*
* 修改设备
*/
    case "deviceedit":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!trim($_POST["location"])) outData(2, "请输入位置");
        if (!trim($_POST["teststation"])) outData(2, "请输入测试站名称");
        $arr["location"] = trim($_POST["location"]);
        $arr["teststation"] = trim($_POST["teststation"]);
        $arr["ModTime"] = date("Y-m-d H:i:s");
        //新增
        if (!empty($_POST["Id"])) {
            $rel = $db->update("com_devsoft", $arr, "Id=" . $_POST["Id"]);
            if ($rel) outData(1, "修改成功", 2);
            //修改
        }
        outData(2, "操作有误");

    /*
* 删除设备
*/
    case "devicedel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->delete("com_devsoft", "Id=" . $_POST["Id"]);
        //
//        if($rel){
//            $rel2 = $db->delete("com_softversion", "SoftInfo=" . $_POST["SoftInfo"]);
//
//        }
//        if ($rel2) outData(1, "删除成功", 2);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");

    /*
* 删除产品关系
*/
    case "proralaDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = $db->delete("qis_productrela", "Id=" . $_POST["Id"]);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "操作有误");


    case "importProrela"://使用xls导入
        $name = $_SESSION['uName'];
        if (!$name) outData(2, "还没有登陆");
        $name = 'newcel';
        require_once ROOT_PATH . 'include/Uploader.class.php';
        require_once ROOT_PATH . "PHPExcel/Classes/PHPExcel.php";
        require_once ROOT_PATH . 'PHPExcel/Classes/PHPExcel/IOFactory.php';
        $arr = array(
            ".xls",
            ".xlsx",
            ".csv"
        );

        $config = array(
            //  "pathFormat" =>"/upimage/".date("Y-m-d H:i:s"),
            "pathFormat" => "/upfile/" . time(),
            "maxSize" => 2048000,
            "allowFiles" => $arr
        );

        $up = new Uploader('newcel', $config);

        $img = $up->getFileInfo();

        if(empty($img["url"])){
            outData(2, "请先导入EXCEL");
        }
        $filename = ROOT_PATH ."upfile/".$img['title'];
        $filename = str_replace('\\', '/', $filename);

        // Check prerequisites
        if (!file_exists($filename)) {
            outData(2,"上传文件类型错误");
        }
        $info=pathinfo($filename);
        if($info['extension']!="xlsx"){
            outData(2, "请导入xlsx格式的表格");

        }
        $reader = $objReader = new PHPExcel_Reader_Excel2007(); //设置以Excel5格式(Excel97-2003工作簿)
        //需要ZIPARCHIVE扩展
        $PHPExcel = $reader->load($filename); // 载入excel文件
        unset($reader);
        $sheet = $PHPExcel->getSheet(0); // 读取第一個工作表
        $highestRow = $sheet->getHighestRow(); // 取得总行数
        $highestColumm = $sheet->getHighestColumn(); // 取得总列数
        /** 循环读取每个单元格的数据 */
        $li = "";
        for ($row = 1; $row <= $highestRow; $row++) {//行数是以第1行开始
            $k=0;
            for ($column = 'A'; $column <= $highestColumm; $column++) {//列数是以A列开始
                $col = $sheet->getCell($column . $row)->getValue();
                $data[$row][$k]=$col;
                $k++;
            }
        }
//        if($data[1][0]!="Products"){
//            outData(2, "导入的EXCEL表为空或格式有误");
//        }
        $excelarr=array("Products","Supplier","Prefix","EEEECode","APN","CPN");
        foreach($data[1] as $key=>$v){
            if(!in_array($v,$excelarr,true)){
                outData(2, "导入的EXCEL表中第".($key+1)."列第1行数据错误");
            }
        }

        $message['Row'] = $highestRow;
        $message['Col'] = $highestColumm;
        foreach ($data as $key => $val) {
            if ($key > 1) {
                //去掉空格
                foreach($val as $k=> $v){
                    $val[$k]=trim($v);
                }
                $result[$key]["Products"] = $val[0];
                $result[$key]["Supplier"] = $val[1];
                $result[$key]["Prefix"] = $val[2];
                $result[$key]["EEEECode"] = $val[3];
                $result[$key]["APN"] = $val[4];
                $result[$key]["CPN"] = $val[5];
                //验证Prefix长度和类型preg_match("/^[0-9a-zA-Z]{3,12}$/",$variable)
//                if(mb_strlen($val[2])!=3){
//                    outData(2, "导入的EXCEL表中第".($key)."行“Prefix”数据长度错误（三位数字或字母）");
//                }
//                验证数据类型
                if(!ctype_alnum($val[2])){
                    outData(2, "导入的EXCEL表中第".($key)."行“Prefix”数据类型错误（三位数字或字母）");
                }
                if(!ctype_alnum($val[3])){
                    outData(2, "导入的EXCEL表中第".($key)."行“EEEECode”数据类型错误（三位数字或字母）");
                }
                if(!ctype_alnum($val[4])){
                    outData(2, "导入的EXCEL表中第".($key)."行“APN”数据类型错误（三位数字或字母）");
                }
                if(!ctype_alnum($val[5])){
                    outData(2, "导入的EXCEL表中第".($key)."行“APN”数据类型错误（三位数字或字母）");
                }
            }
        }
//        print_r($result);
        if (empty($result)) {
            outData(2, "导入的EXCEL表为空或格式有误");
        }
        //查询所有产品关系
        $productrelas= $db->findAll("select Products,Supplier,APN,CPN,Prefix,EEEECode from qis_productrela  ORDER BY  id DESC ");
            //验证导入的EXCEL表中是否有重复
        $len = count ( $result );
        for($i = 2; $i < $len+2; $i ++) {
            for($j = $i + 1; $j < $len+2; $j ++) {
                if ($result [$i] == $result [$j]) {
                    outData(2, "导入的EXCEL表中第".$i."行与第".$j."行产品关系重复，请处理");
                }
            }
        }
        //验证是否与数据库中已有的重复
            foreach($productrelas as $key=>$value){
                foreach($result as $k=>$v){
                    $diff=array_diff($value,$v);
                    if(empty($diff)){
                        outData(2, "导入的EXCEL表中第".$k."行产品关系已经存在，请处理");
                    }
                }

            }

        foreach($result as $key=>$val){
            $val["RegTime"]=date("Y-m-d H:i:s");
            $val["Support"]=1;
            $fields = array ();
            $values = array ();
            foreach ( $val as $k=>$value ) {
                    $fields [] = $k;
                    $values [] = "'" .$value. "'";
            }

            $sql = 'INSERT INTO qis_productrela  ('.implode(',',$fields).') VALUES ('.implode ( ',',$values ).')';
            $rel = $db->query($sql);
            //操作日志成功数组
            $array = array(
                "action" => "新增",
                "uname" => $_SESSION['uName'],
                "model" => "账号管理",
                "posttime" => time(),
                "result" => 1,
                "db" => $_REQUEST["dname"],
                "dbtable" =>"qis_productrela",
                "ip" => $syslogclass->GetIP()
            );
            $db->sysevent($array);
//            if($db->save("qis_productrela", $val)){
//                print_r("7-");
//            }
////            print_r($rel);
//            if(empty($rel)){
//                outData(2, "导入的EXCEL表中第".$key."行开始产品关系导入失败，请处理");
//            }
        }
        outData(1, "导入产品关系成功", 2);

    //运维管理配置管理后台系统信息修改
    case "editSysConfig":
        $postarray = array("MaxHd", "MidHd", "MinHd", "MaxMem", "MidMem", "MinMem", "MaxRealMem", "MidRealMem", "MinRealMem", "MaxSwap", "MidSwap", "MinSwap", "MaxCPU", "MidCPU", "MinCPU", "MinRealMem");
//        print_r($postarray);
        $arr = $_POST;
        if ($arr["MinHd"] >= $arr["MidHd"]) {
            outData(2, "硬盘低级预警不能大于中级预警，请重新输入！");
        }
        if ($arr["MinHd"] >= $arr["MaxHd"]) {
            outData(2, "硬盘低级预警不能大于高级预警，请重新输入！");
        }
        if ($arr["MidHd"] >= $arr["MaxHd"]) {
            outData(2, "硬盘中级预警不能大于高级预警，请重新输入！");
        }
        //
        if ($arr["MinMem"] >= $arr["MidMem"]) {
            outData(2, "物理内存低级预警不能大于中级预警，请重新输入！");
        }
        if ($arr["MinMem"] >=$arr["MaxMem"]) {
            outData(2, "物理内存低级预警不能大于高级预警，请重新输入！");
        }
        if ($arr["MidMem"] >= $arr["MaxMem"]) {
            outData(2, "物理内存中级预警不能大于高级预警，请重新输入！");
        }
        //
        if ($arr["MinRealMem"] >= $arr["MidRealMem"]) {
            outData(2, "真实内存低级预警不能大于中级预警，请重新输入！");
        }
        if ($arr["MinRealMem"] >= $arr["MaxRealMem"]) {
            outData(2, "真实内存低级预警不能大于高级预警，请重新输入！");
        }
        if ($arr["MidRealMem"] >= $arr["MaxRealMem"]) {
            outData(2, "真实内存中级预警不能大于高级预警，请重新输入！");
        }
        //
        if ($arr["MinSwap"] >= $arr["MidSwap"]) {
            outData(2, "SWAP区低级预警不能大于中级预警，请重新输入！");
        }
        if ($arr["MinSwap"] >= $arr["MaxSwap"]) {
            outData(2, "SWAP区低级预警不能大于高级预警，请重新输入！");
        }
        if ($arr["MidSwap"] >= $arr["MaxSwap"]) {
            outData(2, "SWAP区中级预警不能大于高级预警，请重新输入！");
        }
        //
        if ($arr["MinCPU"] >= $arr["MidCPU"]) {
            outData(2, "CPU低级预警不能大于中级预警，请重新输入！");
        }
        if ($arr["MinCPU"] >= $arr["MaxCPU"]) {
            outData(2, "CPU低级预警不能大于高级预警，请重新输入！");
        }
        if ($arr["MidCPU"] >= $arr["MaxCPU"]) {
            outData(2, "CPU中级预警不能大于高级预警，请重新输入！");
        }
        foreach ($arr as $key => $val) {
            if (in_array($key, $postarray)) {
                if ($val >= 100) {
                    outData(2, "预设值不能大于等于100，请重新输入！");
                }
            }
        }
//        $arr["user"]=json_encode($_POST["user"]);
        $arr["user"] = isset($_POST["user"]) ? implode(",", $_POST['user']) : "";
//        print_r($_POST["user"]);
//        exit;
        $rel = $db->update(DB_PREFIX . "warnningconfig", $arr, "Id=1");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");


    //运维管理配置管理后台系统信息修改
    case "editSysConfig1":
        $arr = $_POST;
        $rel = $db->update(DB_PREFIX . "sysconfig", $arr, "Id=1");
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");

    //运维管理配置管理后台业务模块信息修改
    case "editbusiness":
        $bid = $_POST['bid'];
        $bCheckinfo = $_POST['bCheckinfo'];
        $mid = $_POST['mid'];
        $mCheckinfo = $_POST['mCheckinfo'];
        $arr = array();
        $arr1 = array();
        foreach ($bid as $key => $val) {
            $arr["Checkinfo"] = $bCheckinfo[$key];
            $rel = $db->update(DB_PREFIX . "business", $arr, "Id='" . $val . "'");
        }
        foreach ($mid as $key => $val) {
            $arr1["Checkinfo"] = $mCheckinfo[$key];
            $rel = $db->update(DB_PREFIX . "module", $arr1, "Id='" . $val . "'");
        }
        if ($rel) {
            outData(1, "修改成功");
        }
        //}
        outData(2, "操作错误");

    //运维管理系统日志导出
    case "exportLog":
        $poststr = implode("','", $_POST["chk_value"]);
        $sql = "select *from " . DB_PREFIX . "syslog WHERE id in ('" . $poststr . "')order by posttime desc";
        $data = $db->findAll("select *from " . DB_PREFIX . "syslog WHERE id in ('" . $poststr . "')order by posttime desc");
        $aaa = exportLog($data);
//        if ($aaa) {
//            outData(1, "修改成功");
//        }
        outData(1, "操作错误");


    case "reg":
        $role = $_POST['role'];//分普通用户和厂商注册两种情况
        if ($role == 0) {
            $name = $_POST['rusername'];
            if (empty($name)) outData(2, "用户名不能为空");
            $pattern = '/administrator/i';
            if (preg_match($pattern, $name)) {
                outData('2', '用户名不可用');
            }
            $pattern = '/admin/i';
            if (preg_match($pattern, $name)) {
                outData('2', '用户名不可用');
            }
            $pattern = '/test/i';
            if (preg_match($pattern, $name)) {
                outData('2', '用户名不可用');
            }
            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
            if ($oldrel) outData(2, "用户名已被注册过了");

            $password = $_POST['rpassword'];
            if (empty($password)) outData(2, "密码不能为空");
            //	$pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$).{6,50}$/';
            $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
            if (!preg_match($pattern, $password)) {
                outData('2', '密码至少6位且必须包含字母和数字');
            }

            $rpassword = $_POST['rrpassword'];
            if (empty($rpassword)) outData(2, "请再输一次密码");
            if ($password != $rpassword) outData(2, "两次密码不一样");
            $phone = $_POST['phone'];
            if (empty($phone)) outData(2, "手机号码不能为空");
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
                outData('2', '请输入正确的手机号码', '');
            }
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");

            $email = $_POST['remail'];
            if (empty($email)) outData(2, "邮箱不能为空");
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");


            $code = $_POST['rcode'];
            if (empty($code)) outData(2, "验证码不能为空");
            if (strtolower($code) != strtolower($_SESSION['code'])) outData(2, "验证码不对");

            $arr = array(
                "uName" => $name,
                "uPwd" => md5($password),
                "Email" => $email,
                "RegTime" => date("Y-m-d H:i:s"),
                "Mobile" => $phone,
                "uRole" => 1
            );

            $rel = $db->save(DB_PREFIX . "usr", $arr);
            if ($rel) {
                $_SESSION['uName'] = $name;
                // 由于发送邮件太慢建议取消这步
                /*			try {
							$mail = new PHPMailer(true);
							$mail->IsSMTP();
							$mail->CharSet='UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码
							$mail->SMTPAuth   = true;                  //开启认证
							$mail->Port       = 25;
							$mail->Host       = "smtp.163.com";
							$mail->Username   =  "rujjtg@163.com";
							$mail->Password   = 154357318;
							//$mail->IsSendmail(); //如果没有sendmail组件就注释掉，否则出现“Could  not execute: /var/qmail/bin/sendmail ”的错误提示
							$mail->AddReplyTo("rujjtg@163.com","norepy");//回复地址
							$mail->From       = "rujjtg@163.com";
							$mail->FromName   = "norepy";
							$to = $email;
							$mail->AddAddress($to);
							$mail->Subject  = "注册资料".date("Y-m-d H:i:s");
						//	$key=md5($ename.$useremail.$token);
						//	$time=time()+60*30;
							$mail->Body ="<html>".$name."您好!<br>这是您的注册资料请妥善保管好<br>密码:".$password."<br>注册邮箱:".$email."<br>注册手机号码:".$phone."</html>";
						// 	$mail->Body = "<html>请尽快点下面链接,时间过期则无效<br>http://secbc.com/email_code.php?act=".base64_encode($key)."&ename=".$ename."&useremail=".$useremail."&time=".$time."</html>";
							$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; //当邮件不支持html时备用显示，可以省略
							$mail->WordWrap   = 80; // 设置每行字符串的长度
							//$mail->AddAttachment("f:/test.png");  //可以添加附件
							$mail->IsHTML(true);
							$mail->Send();
						} catch (phpmailerException $e) {
							echo "邮件发送失败：".$e->errorMessage();
						    outData(2,"邮件发送失败");
						}
						outData(1,"注册成功,并已发送邮件");
						*/
                outData(1, "注册成功");
            }
            outData(2, "操作错误");
        }
        if ($role == 1) {
            $name = $_POST['rusername'];
            if (empty($name)) outData(2, "用户名不能为空");
            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
            if ($oldrel) outData(2, "用户名已被注册过了");


            $password = $_POST['rpassword'];
            $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
            if (!preg_match($pattern, $password)) {
                outData('2', '至少6位且必须包含字母和数字');
            }
            if (empty($password)) outData(2, "密码不能为空");
            $rpassword = $_POST['rrpassword'];
            if (empty($rpassword)) outData(2, "请再输一次密码");
            if ($password != $rpassword) outData(2, "两次密码不一样");

            $vename = $_POST['vename'];
            if (empty($vename)) outData(2, "厂商名不能为空");

            $person = $_POST['person'];
            if (empty($person)) outData(2, "联系人不能为空");

            $phone = $_POST['phone'];
            if (empty($phone)) outData(2, "手机号码不能为空");
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
                outData('2', '请输入正确的手机号码', '');
            }
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");

            $mobile = $_POST['mobile'];
            if (empty($mobile)) outData(2, "固定电话不能为空");
            if (!preg_match('/0\d{2,3}-\d{5,9}|0\d{2,3}-\d{5,9}/', $mobile)) {
                outData('2', '请输入正确的固定电话格式如：010-1234567');
            }

            $email = $_POST['remail'];
            if (empty($email)) outData(2, "邮箱不能为空");
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");

            $addre = $_POST['addre'];
            if (empty($addre)) outData(2, "厂商地址不能为空");

            $code = $_POST['rcode'];
            if (empty($code)) outData(2, "验证码不能为空");
            if (strtolower($code) != strtolower($_SESSION['code'])) outData(2, "验证码不对");

            $arr = array(
                "uName" => $name,
                "uPwd" => md5($password),
                "Email" => $email,
                "RegTime" => date("Y-m-d H:i:s"),
                "Mobile" => $phone,
                "uRole" => 1  //表示厂商注册但没有授权
            );

            $rel = $db->save(DB_PREFIX . "usr", $arr);
            if ($rel) {
                $arrven = array(
                    "Name" => $vename,
                    "VendName" => $name,
                    "Contacter" => $person,
                    "VendTelphone" => $mobile,
                    "Email" => $email,
                    "VendAddr" => $addre,
                    "RegTime" => date("Y-m-d H:i:s"),
                    "Phone" => $phone,
                    "State" => 0

                );
                $rels = $db->save(DB_PREFIX . "vendor", $arrven);
                if ($rels) {
                    $_SESSION['uName'] = $name;
                    $_SESSION['uRole'] = 0;
                    outData(1, "注册成功");
                }
            }
            outData(2, "操作错误");
        }
        outData(2, "操作错误");

    /*
		 * 根据企业用户ID查询其直属用户信息
		 * by zhangping
		 */


    case "regtwo":
        $name = $_POST['rusername'];
        if (empty($name)) outData(2, "用户名不能为空");
        $pattern = '/administrator/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/admin/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/test/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
        if ($oldrel) outData(2, "用户名已被注册过了");

        $password = $_POST['rpassword'];
        if (empty($password)) outData(2, "密码不能为空");
        //	$pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$).{6,50}$/';
        $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
        if (!preg_match($pattern, $password)) {
            outData('2', '密码至少6位且必须包含字母和数字');
        }

        $rpassword = $_POST['rrpassword'];
        if (empty($rpassword)) outData(2, "请再输一次密码");
        if ($password != $rpassword) outData(2, "两次密码不一样");
        $phone = $_POST['phone'];
        if (empty($phone)) outData(2, "手机号码不能为空");
        if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
            outData('2', '请输入正确的手机号码', '');
        }
        $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
        if ($oldrel2) outData(2, "该手机已被注册过了");


        $email = $_POST['remail'];
        if (empty($email)) outData(2, "邮箱不能为空");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
        $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
        if ($oldrel3) outData(2, "该邮箱已被注册过了");


        //$code = $_POST['rcode'];
        // if (empty($code)) outData(2, "验证码不能为空");
        // if (strtolower($code) != strtolower($_SESSION['code'])) outData(2, "验证码不对");
        //$enterprise = $_POST['enterprise'];
        $arr = array(
            "uName" => $name,
            "uPwd" => md5($password),
            "Email" => $email,
            "RegTime" => date("Y-m-d H:i:s"),
            "Mobile" => $phone,
            "uRole" => 2,
            // "uNick" => $enterprise,
            "Corp" => $name,
            "Business" => $name,
            "Addr" => $name


        );

        $rel = $db->save(DB_PREFIX . "usr", $arr);


        if ($rel) {
            outData(1, "注册成功");
        } else {
            outData(2, "操作错误");
        }


    /*
		 * 根据企业用户ID查询其直属用户信息
		 * by zhangping
		 */


    case "regthree":

        $name = $_POST['rusername'];
        if (empty($name)) outData(2, "用户名不能为空");
        $pattern = '/administrator/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/admin/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $pattern = '/test/i';
        if (preg_match($pattern, $name)) {
            outData('2', '用户名不可用');
        }
        $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $name . "' limit 1 ");
        if ($oldrel) outData(2, "用户名已被注册过了");

        $password = $_POST['rpassword'];
        if (empty($password)) outData(2, "密码不能为空");
        //	$pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$).{6,50}$/';
        $pattern = '/((?=.*[0-9].*)(?=.*[A-Za-z].*)).{6,50}$/';
        if (!preg_match($pattern, $password)) {
            outData('2', '密码至少6位且必须包含字母和数字');
        }

        $rpassword = $_POST['rrpassword'];
        if (empty($rpassword)) outData(2, "请再输一次密码");
        if ($password != $rpassword) outData(2, "两次密码不一样");
        $phone = $_POST['phone'];
        if (empty($phone)) outData(2, "手机号码不能为空");
        if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $phone)) {
            outData('2', '请输入正确的手机号码', '');
        }
        $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $phone . "' limit 1 ");
        if ($oldrel2) outData(2, "该手机已被注册过了");


        $email = $_POST['remail'];
        if (empty($email)) outData(2, "邮箱不能为空");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $email)) outData(2, "请输入正确的邮箱地址");
        $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $email . "' limit 1 ");
        if ($oldrel3) outData(2, "该邮箱已被注册过了");


        $code = $_POST['rcode'];
        if (empty($code)) outData(2, "验证码不能为空");
        if (strtolower($code) != strtolower($_SESSION['code'])) outData(2, "验证码不对");
        $enterprise = $_POST['enterprise'];
        $arr = array(
            "uName" => $name,
            "uPwd" => md5($password),
            "Email" => $email,
            "RegTime" => date("Y-m-d H:i:s"),
            "Mobile" => $phone,
            "uRole" => 0,
            "uNick" => $enterprise,
            "Corp" => $name,
            "Addr" => $name


        );

        $rel = $db->save(DB_PREFIX . "usr", $arr);


        if ($rel) {
            outData(1, "注册成功");
        } else {
            outData(2, "操作错误");
        }


    /*
		 * 根据企业用户ID查询其直属用户信息
		 * by zhangping
		 */


    case "selVenUser"://企业用户所有用户资料信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $vendid = $_SESSION['ven']['venId'];
        $rel = $db->findALL("select count(1) as num from bc_usr where uRole='" . $_SESSION['uRole'] . "'and qisId='" . $_SESSION['Id'] . "'");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll("select uName,Mobile,Email,RegTime,Addr from bc_usr where uRole='" . $_SESSION['uRole'] . "'and qisId='" . $_SESSION['Id'] . "' limit " . $lim);
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
		* 根据企业用户直属用户条件查询用户信息
		* by zhangping
		*/
    case "searchUsr"://企业用户直属用户资料信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $uName = isset($_POST) ? trim($_POST['uName']) : "";
        $rel = $db->findALL("select count(1) as num from bc_usr where  uRole='" . $_SESSION['uRole'] . "'and qisId='" . $_SESSION['Id'] . "' and uName LIKE '%" . $uName . "%' ");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findALL("select * from bc_usr where  uRole='" . $_SESSION['uRole'] . "' and qisId='" . $_SESSION['Id'] . "' and uName LIKE '%" . $uName . "%' ");
        outData(1, $rel, $data);
        outData(2, "操作有误");

    /*
		* 用户管理信息（获取企业所属用户信息）
		* by zhangping
		*/
    case "manUsr"://企业用户所有用户资料信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        $rel3 = $db->find("select * from " . DB_PREFIX . "usr where uName='" . $_SESSION['uName'] . "'");
        if ($rel3['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole != '0'";
            $sql1 = "select * from " . DB_PREFIX . "usr where uRole != '0' ";
        } else {
            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole='2'";
            $sql1 = "select * from " . DB_PREFIX . "usr where uRole='2'";
        }
        if (isset($_POST['uName']) && trim($_POST['uName']) != null) {
            $sql .= " and uName like '%" . $_POST['uName'] . "%'";
            $sql1 .= " and uName like '%" . $_POST['uName'] . "%'";

        }

        $rel = $db->findALL($sql);
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
        outData(1, $rel, $data);
        outData(2, "操作有误");



    /*
* 获取在线用户
*
*/
    case "getOnlineUsr"://企业用户所有用户资料信息
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        $sql = "select count(1) as num from " . DB_PREFIX . "usr where  uStatus='1'";
        $sql1 = "select * from " . DB_PREFIX . "usr where  uStatus='1' ";
        $rel = $db->findALL($sql);
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
        outData(1, $rel, $data);
        outData(2, "操作有误");
    /* if (isset($_GET['uName']) && trim($_GET['uName']) != null) {     //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");
            }else{
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");

             }


            /*
		* 查询运维管理查看模块中的软件信息
		*
		*/
    case "devSoft"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        //只显示在线设备
        if (isset($_POST["op"]) && $_POST["op"] == 1) {
            $sql = "select count(1) as num from " . DB_PREFIX . "devsoft where  ison='1' ";
            $sql1 = "select *from " . DB_PREFIX . "devsoft where  ison='1' order by ModTime desc ";
            $rel = $db->findALL($sql);

            $pagepar = 10;
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
            $data = $db->findAll($sql1 . "limit  " . $lim);
            if ($data) {
                foreach ($data as $k => $v) {
                    //软件状态
                    if ($data[$k]["softstatus"] == 1) {
                        $data[$k]["softStatusName"] = "空闲";
                    } else if($data[$k]["softstatus"] == 2) {
                        $data[$k]["softStatusName"] = "测试中";
                    }else{
                        $data[$k]["softStatusName"] = "故障";
                    }
                    //是否在线
                    if ($data[$k]["ison"] == 1) {
                        $data[$k]["isonname"] = "在线";
                    }else{
                        $data[$k]["isonname"] = "不在线";
                    }
                    //获取软件版本

                    $softversion = $db->find("select CurrVersion from " . DB_PREFIX . "softversion where SoftInfo='" . $v["SoftInfo"] . "'");
                    $data[$k]["softVersion"] = $softversion["CurrVersion"];
                }
            }
            $rel[2]['op']=$_POST["op"];
            outData(1, $rel, $data);
            outData(2, "操作有误");
            //显示所有设备
        } else {
            $sql = "select count(1) as num from " . DB_PREFIX . "devsoft";
            $sql1 = "select *from " . DB_PREFIX . "devsoft order by ModTime desc ";
            $rel = $db->findALL($sql);

            $pagepar = 10;
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
            $data = $db->findAll($sql1 . "limit  " . $lim);
            if ($data) {
                foreach ($data as $k => $v) {
                    //软件状态
                    if ($data[$k]["softstatus"] == 1) {
                        $data[$k]["softStatusName"] = "空闲";
                    } else if($data[$k]["softstatus"] == 2) {
                        $data[$k]["softStatusName"] = "测试中";
                    }else{
                        $data[$k]["softStatusName"] = "故障";
                    }
                    //是否在线
                    if ($data[$k]["ison"] == 1) {
                        $data[$k]["isonname"] = "在线";
                    }else{
                        $data[$k]["isonname"] = "不在线";
                    }

                    //获取软件版本

                    $softversion = $db->find("select CurrVersion from " . DB_PREFIX . "softversion where SoftInfo='" . $v["SoftInfo"] . "'");
                    $data[$k]["softVersion"] = $softversion["CurrVersion"];
                }
            }
            outData(1, $rel, $data);
            outData(2, "操作有误");
        }


    /*
* 查询运维管理查看模块中的软件信息
*
*/
    case "getlocation"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $location=trim($_POST["location"]);
        $sql = "select count(1) as num from " . DB_PREFIX . "devsoft where location like '%".$location."%' ";
        $sql1 = "select *from " . DB_PREFIX . "devsoft where location like '%".$location."%' ";
        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
        if ($data) {
            foreach ($data as $k => $v) {
                //软件状态
                if ($data[$k]["softstatus"] == 1) {
                    $data[$k]["softStatusName"] = "空闲";
                } else if ($data[$k]["softstatus"] == 2) {
                    $data[$k]["softStatusName"] = "测试中";
                } else {
                    $data[$k]["softStatusName"] = "故障";
                }
                //是否在线
                if ($data[$k]["ison"] == 1) {
                    $data[$k]["isonname"] = "在线";
                } else {
                    $data[$k]["isonname"] = "不在线";
                }

                //获取软件版本

                $softversion = $db->find("select CurrVersion from " . DB_PREFIX . "softversion where SoftInfo='" . $v["SoftInfo"] . "'");
                $data[$k]["softVersion"] = $softversion["CurrVersion"];
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
* 查询运维管理产看模块中的系统日志信息
*
*/
    case "sysLog":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = array();
        $action = trim($_POST["action"]);
        $uname = trim($_POST["opname"]);
        $result = trim($_POST["result"]);
        $dbname = trim($_POST["dbname"]);
        $begintime = trim(strtotime($_POST["begintime"]));
        $endtime = trim(strtotime($_POST["endtime"]));
        if(!empty($begintime)&&!empty($endtime)){
            if($endtime<$begintime){
                outData(2, "结束时间不能小于开始时间！");
            }
        }
        $setSearch = trim($_POST["setSearch"]);
        $sql = "";
        $sql1 = "";

//        if ($endtime < $begintime) {
//            outData(2, "结束时间不能小于开始时间！");
//        }
        //无查询条件
        if ($setSearch != 2) {

//            $sql = "select count(1) as num from " . DB_PREFIX . "syslog";
            $sql1 = "select *from " . DB_PREFIX . "syslog order by posttime desc limit 10";
//            $rel = $db->findALL($sql);
            $data = $db->findAll($sql1);
            $pagepar = 10;
            $rel[0]['num'] = count($data);
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
//            print_r($sql1);
            if ($data) {
                foreach ($data as $k => $v) {
                    if ($data[$k]["result"] == 1) {
                        $data[$k]["resultname"] = "成功";
                    } else {
                        $data[$k]["resultname"] = "失败";
                    }

                    if ($data[$k]["action"] == 1) {
                        $data[$k]["actionname"] = "新增";
                    } else if ($data[$k]["action"] == 2) {
                        $data[$k]["actionname"] = "修改";
                    } else {
                        $data[$k]["actionname"] = "删除";
                    }

//                    //数据库
//                    if ($data[$k]["db"] == 1) {
//                        $data[$k]["dbname"] = "common";
//                    } else if ($data[$k]["db"] == 2) {
//                        $data[$k]["dbname"] = "qis";
//                    } else {
//                        $data[$k]["dbname"] = "secbc";
//                    }
                    $data[$k]["edittime"] = date("Y-m-d H:i:s", $v["posttime"]);
                }
            }
            outData(1, $rel, $data);
        } //有查询条件
        else {
            $sql = "select count(1) as num from " . DB_PREFIX . "syslog where  1='1' ";
            $sql1 = "select *from " . DB_PREFIX . "syslog where  1='1' ";
            if ($action) {
                $sql .= "and  action='" . $action . "'";
                $sql1 .= "and  action='" . $action . " '";
            }
            if ($uname) {
                $sql .= "and  uname like'%" . $uname . "%'";
                $sql1 .= "and uname like'%" . $uname . "%'";
            }
            if ($result) {
                $sql .= "and  result='" . $result . "'";
                $sql1 .= "and result='" . $result . "'";
            }
            if ($dbname) {
                $sql .= "and  db='" . $dbname . "'";
                $sql1 .= "and db='" . $dbname . "'";
            }
            if ($begintime) {
                $sql .= "and  posttime>='" . $begintime . "'";
                $sql1 .= "and posttime>='" . $begintime . "'";
            }
            if ($endtime) {
                $sql .= "and  posttime<='" . $endtime . "'";
                $sql1 .= "and posttime<='" . $endtime . "'";
            }
            $rel = $db->findALL($sql);
            $pagepar = 10;
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
//            print_r($sql);
//            print_r($sql1);
            $data = $db->findAll($sql1 . " order by posttime desc limit  " . $lim);
            if ($data) {
                foreach ($data as $k => $v) {
                    //操作结果
                    if ($data[$k]["result"] == 1) {
                        $data[$k]["resultname"] = "成功";
                    } else {
                        $data[$k]["resultname"] = "失败";
                    }
                    //操作呢内容
                    if ($data[$k]["action"] == 1) {
                        $data[$k]["actionname"] = "新增";
                    } else if ($data[$k]["action"] == 2) {
                        $data[$k]["actionname"] = "修改";
                    } else {
                        $data[$k]["actionname"] = "删除";
                    }
                    //数据库
                    if ($data[$k]["db"] == 1) {
                        $data[$k]["dbname"] = "common";
                    } else if ($data[$k]["db"] == 2) {
                        $data[$k]["dbname"] = "qis";
                    } else {
                        $data[$k]["dbname"] = "secbc";
                    }
                    $data[$k]["edittime"] = date("Y-m-d H:i:s", $v["posttime"]);
                }
            }
            outData(1, $rel, $data);
        }

        outData(2, "操作有误");


    /*
* 业务管理数据清理
*
*/
    case "dataclear":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $rel = array();
        $Alias = trim($_POST["Alias"]);
        $begintime = trim($_POST["begintime"]);
        $endtime = trim($_POST["endtime"]);
        if(!empty($begintime)&&!empty($endtime)){
            if($endtime<$begintime){
                outData(2, "结束时间不能小于开始时间！");
            }
        }

        $ts=MD5("huahui");
        $setSearch = trim($_POST["setSearch"]);
        $sql = "";
        $sql1 = "";
//        if ($endtime < $begintime) {
//            outData(2, "结束时间不能小于开始时间！");
//        }
        //无查询条件
        if ($setSearch != 2) {

//            $sql = "select count(1) as num from " . DB_PREFIX . "syslog";
            $sql1 = "select *from " . DB_PREFIX . "analysisresult where Alias!='".$ts."' order by posttime desc limit 10";
//            $rel = $db->findALL($sql);
            $data = $db->findAll($sql1);
            $pagepar = 10;
            $rel[0]['num'] = count($data);
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
//            print_r($sql1);
            if ($data) {
                foreach ($data as $k => $v) {
                    $data[$k]["edittime"] = date("Y-m-d H:i:s", $v["posttime"]);
                }
            }
            outData(1, $rel, $data);
        } //有查询条件
        else {
            $sql = "select count(1) as num from " . DB_PREFIX . "analysisresult where Alias!='".$ts."' and 1='1' ";
            $sql1 = "select *from " . DB_PREFIX . "analysisresult where Alias!='".$ts."' and 1='1' ";
            if ($Alias) {
                $sql .= "and  Alias like'%" . $Alias . "%'";
                $sql1 .= "and Alias like'%" . $Alias . "%'";
            }
            if ($begintime) {
                $sql .= "and  StartTime>='" . $begintime . "'";
                $sql1 .= "and StartTime>='" . $begintime . "'";
            }
            if ($endtime) {
                $sql .= "and  StopTime<='" . $endtime . "'";
                $sql1 .= "and StopTime<='" . $endtime . "'";
            }
            $rel = $db->findALL($sql);
            $pagepar = 10;
            $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
            $curren = isset($_POST['page']) ? $_POST['page'] : 1;
            $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

            if ($curren == $rel[1]['sum']) {
                $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
                $lim = (($curren - 1) * $pagepar) . "," . $end;
            }
            $data = $db->findAll($sql1 . " order by Alias desc limit  " . $lim);
            if ($data) {
                foreach ($data as $k => $v) {
//                    $data[$k]["edittime"] = date("Y-m-d H:i:s", $v["posttime"]);
                }
            }
            outData(1, $rel, $data);
        }

        outData(2, "操作有误");

    /*
* 查询运维管理部署管理模块中的问题反馈信息
*
*/
    case "feedback"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        if ($_SESSION['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "feedback ";
            $sql1 = "select *from " . DB_PREFIX . "feedback " . " order by feedtime  desc ";

        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
        if ($data) {
            foreach ($data as $k => $v) {
                //根据用户ID查询用户名
                $result = $db->find("select uRole, uName  from " . DB_PREFIX . "usr where Id='" . $v['userid'] . "'");

                $data[$k]["username"] = $result["uName"];
                if ($v['over'] == 1) {
                    $data[$k]["overname"] = "已解决";
                } else {
                    $data[$k]["overname"] = "未解决";
                }
                //根据模块ID查询模块名称
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
* 查询运维管理部署管理模块中的文件设置信息
*
*/
    case "fileinfo"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        if ($_SESSION['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "fileinfo ";
            $sql1 = "select *from " . DB_PREFIX . "fileinfo " . " order by creattime and modtime desc ";

        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
//        if ($data) {
//            foreach ($data as $k => $v) {
//                //根据用户ID查询用户名
//                $result = $db->find("select uRole, uName  from " . DB_PREFIX . "usr where Id='" . $v['userid'] . "'");
//                $data[$k]["username"] = $result["uName"];
//                //根据模块ID查询模块名称
//            }
//        }
        outData(1, $rel, $data);
        outData(2, "操作有误");

    /*
* 查询运维管理部署管理模块中的系统链接信息
*
*/
    case "getSysLink"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = array();
        if ($_SESSION['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "fileinfo ";
            $sql1 = "select *from " . DB_PREFIX . "fileinfo " . " order by creattime and modtime desc ";

        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findAll($sql1 . "limit  " . $lim);
//        if ($data) {
//            foreach ($data as $k => $v) {
//                //根据用户ID查询用户名
//                $result = $db->find("select uRole, uName  from " . DB_PREFIX . "usr where Id='" . $v['userid'] . "'");
//                $data[$k]["username"] = $result["uName"];
//                //根据模块ID查询模块名称
//            }
//        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
* 查询运维管理部署管理模块中的文件设置信息
*
*/
    case "downloadfile"://
        define('HUAHUI_INC', preg_replace("/[\/\\\\]{1,}/", '/', dirname(__FILE__)));
//        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $result = $db->find("select  *from " . DB_PREFIX . "fileinfo where id='" . $_POST['id'] . "'");
        $filepath = $result["filepath"];
        $length = strlen($_SERVER ['HTTP_HOST']);

        $path = HUAHUI_INC . mb_substr($filepath, $length);
        $example_name = basename($path);
        if (file_exists($path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . mb_convert_encoding($example_name, "gb2312", "utf-8"));  //ת���ļ����ı���
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($path));
            ob_clean();
            flush();
            readfile($path);
        } else {

        }
    /*
* 检测系统信息,显示预警信息
*
*/
    case "getSysSet"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $data = $db->findALL("select *from " . DB_PREFIX . "syssetting");
        outData(1, $data);
        outData(2, "操作有误");

    case "dataconfig"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $data = array();
        //计算common大小
        $comsize = $db->findAll("select concat(round(sum(DATA_LENGTH/1024/1024),2),'MB') as data from information_schema.TABLES where table_schema='common'");
        //计算qis大小
        $qissize = $db->findAll("select concat(round(sum(DATA_LENGTH/1024/1024),2),'MB') as data from information_schema.TABLES where table_schema='qis'");
        //计算secbc大小
        $secbcsize = $db->findAll("select concat(round(sum(DATA_LENGTH/1024/1024),2),'MB') as data from information_schema.TABLES where table_schema='secbc'");
        //计算common中表的数量
        $comnum = $db->findAll("SELECT COUNT( * ) as num FROM information_schema.tables WHERE TABLE_SCHEMA = 'common'");
        //计算qis中表的数量
        $qisnum = $db->findAll("SELECT COUNT( * ) as num FROM information_schema.tables WHERE TABLE_SCHEMA = 'qis'");
        //计算secbc中表的数量
        $secbcnum = $db->findAll("SELECT COUNT( * ) as num FROM information_schema.tables WHERE TABLE_SCHEMA = 'secbc'");
        //计算common中记录数
        $comrows = $db->findAll("select table_name,table_rows from information_schema.tables where TABLE_SCHEMA = 'common' ");
        //计算qis中记录数
        $qisrows = $db->findAll("select table_name,table_rows from information_schema.tables where TABLE_SCHEMA = 'qis' ");
        //计算secbc中记录数
        $secbcrows = $db->findAll("select table_name,table_rows from information_schema.tables where TABLE_SCHEMA = 'secbc' ");
        $rows1 = 0;
        $rows2 = 0;
        $rows3 = 0;
        foreach ($comrows as $v) {
            $rows1 += $v["table_rows"];
        }
        foreach($qisrows as $v){
            $rows2+=$v["table_rows"];
        }
        foreach($secbcrows as $v){
            $rows3+=$v["table_rows"];
        }
        //获取数据库最新备份时间和备份人
        $commdata = $db->find("select *from " . DB_PREFIX . "dbbackup" . " where db='common' " );
        $qisdata = $db->find("select *from " . DB_PREFIX . "dbbackup" . " where db='qis' " );
        $secbcdata = $db->find("select *from " . DB_PREFIX . "dbbackup" . " where db='secbc' " );
        $data = array(
            0 => array(
                "db" => "common",
                "size" => $comsize[0]["data"],
                "editor" => $commdata["editor"],
                "rows" => $rows1,
                "posttime" => date("Y-m-d H:i:s",$commdata["posttime"]),
                "num" => $comnum[0]["num"]
            ),
            1 => array(
                "db" => "qis",
                "size" => $qissize[0]["data"],
                "editor" => $qisdata["editor"],
                "rows" => $rows2,
                "posttime" => date("Y-m-d H:i:s",$qisdata["posttime"]),
                "num" => $qisnum[0]["num"]
            ),
            2 => array(
                "db" => "secbc",
                "size" => $secbcsize[0]["data"],
                "editor" => $secbcdata["editor"],
                "rows" => $rows3,
                "posttime" => date("Y-m-d H:i:s",$secbcdata["posttime"]),
                "num" => $secbcnum[0]["num"]
            ),
        );
        outData(1, $data);
        outData(2, "操作有误");

    // 数据库备份
    case "dbbackup"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
        require_once './include/dbmanage.class.php';
//        exit;
        if ($_POST["i"] == 1) {
            $oldpath = str_replace('\\', '/', ROOT_PATH . "backup/common/");
            $aaa = scandir($oldpath, 1);
            //循环删除SQL文件夹
            foreach ($aaa as $val) {
                if ($val !== ".." && $val != ".") {
                    $bbb = scandir($oldpath . $val, 1);
                    //循环删除sql文件
                    foreach ($bbb as $v) {
                        if ($v !== ".." && $v != ".") {
                            unlink($oldpath . $val . "/" . $v);
                        }
                    }
                    rmdir($oldpath . $val);
                    //删除数据库原有备份记录
                    $rel = $db->delete(DB_PREFIX . "dbbackup", "db= 'common'");
                }
            }
            $path = ROOT_PATH . "backup/common/" . date('YmdHis') . "/";
            $path = str_replace('\\', '/', $path);
            $arr = array(
                "path" => $path,
                "posttime" => time(),
                "editor" => $_SESSION["uName"],
                "db" => "common"
            );
            $rel = $db->save(DB_PREFIX . "dbbackup", $arr);

//                $rel = mysql_query('INSERT INTO com_dbbackup (path,posttime,editor,db) VALUES ("' . $path . '","' . time() . '","' . $_SESSION["uName"] . '","common")');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');
//            $dbmanage = new DBManage ('localhost', 'root', '', 'common', 'utf8');

            $result = $dbmanage->backup('', ROOT_PATH . "backup/common/" . date('YmdHis') . "/", 4000);
            if ($result) outData(1, $rel, "备份成功");
            outData(2, "操作有误");


        }
        if ($_POST["i"] == 2) {
            $oldpath = str_replace('\\', '/', ROOT_PATH . "backup/qis/");
            $aaa = scandir($oldpath, 1);
            //循环删除SQL文件夹
            foreach ($aaa as $val) {
                if ($val !== ".." && $val != ".") {
                    $bbb = scandir($oldpath . $val, 1);
                    //循环删除sql文件
                    foreach ($bbb as $v) {
                        if ($v !== ".." && $v != ".") {
                            unlink($oldpath . $val . "/" . $v);
                        }
                    }
                    rmdir($oldpath . $val);
                    //删除数据库原有备份记录
                    $rel = $db->delete(DB_PREFIX . "dbbackup", "db= 'qis'");

                }
            }
            $path = ROOT_PATH . "backup/qis/" . date('YmdHis') . "/";
            $path = str_replace('\\', '/', $path);
            $arr = array(
                "path" => $path,
                "posttime" => time(),
                "editor" => $_SESSION["uName"],
                "db" => "qis"
            );
            $rel = $db->save(DB_PREFIX . "dbbackup", $arr);
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');
//            $dbmanage = new DBManage ('localhost', 'root', '', 'qis', 'utf8');
            $result = $dbmanage->backup('', ROOT_PATH . "backup/qis/" . date('YmdHis') . "/", 4000);
            if ($result) outData(1, $rel, "备份成功");
            outData(2, "操作有误");

        }
        if ($_POST["i"] == 3) {
            $oldpath = str_replace('\\', '/', ROOT_PATH . "backup/secbc/");
            $aaa = scandir($oldpath, 1);
            //循环删除SQL文件夹
            foreach ($aaa as $val) {
                if ($val !== ".." && $val != ".") {
                    $bbb = scandir($oldpath . $val, 1);
                    //循环删除sql文件
                    foreach ($bbb as $v) {
                        if ($v !== ".." && $v != ".") {
                            unlink($oldpath . $val . "/" . $v);
                        }
                    }
                    rmdir($oldpath . $val);
                    //删除数据库原有备份记录
                    $rel = $db->delete(DB_PREFIX . "dbbackup", "db= 'secbc'");
                }
            }
            $path = ROOT_PATH . "backup/secbc/" . date('YmdHis') . "/";
            $path = str_replace('\\', '/', $path);
            $arr = array(
                "path" => $path,
                "posttime" => time(),
                "editor" => $_SESSION["uName"],
                "db" => "secbc"
            );
            $rel = $db->save(DB_PREFIX . "dbbackup", $arr);
//                $rel = mysql_query('INSERT INTO com_dbbackup (path,posttime,editor,db) VALUES ("' . $path . '","' . time() . '","' . $_SESSION["uName"] . '","secbc")');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');

//            $dbmanage = new DBManage ('localhost', 'root', '', 'secbc', 'utf8');
            $result = $dbmanage->backup('', ROOT_PATH . "backup/secbc/" . date('YmdHis') . "/", 4000);
            if ($result) outData(1, $rel, "备份成功");
            outData(2, "操作有误");


        }
        outData(1, $rel, "备份成功");
        outData(2, "操作有误2");


    case "datarestore"://数据库还原列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "dbbackup");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;
        $data = $db->findALL("select *from " . DB_PREFIX . "dbbackup" . " order by posttime  desc limit " . $lim);
        if ($data) {
            foreach ($data as $k => $v) {
                $data[$k]["ptime"] = date("Y-m-d H:i:s", $v["posttime"]);
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    // 数据库还原
    case "restore"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
        require_once './include/dbmanage.class.php';
        if ($_POST["i"] == 1) {
            $res = $db->find('select *from com_dbbackup where db= "common"');
//            $dbmanage = new DBManage ('localhost', 'root', '', 'common', 'utf8');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');
            $result = $dbmanage->restore($res["path"]);
//            print_r($result);
            if ($result) outData(1,"还原成功！");
            outData(2, "还原失败！");
        }
        if ($_POST["i"] == 2) {
            $res = $db->find('select *from com_dbbackup where db= "qis"');
//            $dbmanage = new DBManage ('localhost', 'root', '', 'qis', 'utf8');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');
            $result = $dbmanage->restore($res["path"]);
            if ($result) outData(1, $rel, "还原成功");
            outData(2, "操作有误");
            exit;
            $path = ROOT_PATH . "backup/qis/" . date('YmdHis') . "/";
            $path = str_replace('\\', '/', $path);
//            $dbmanage = new DBManage ('localhost', 'root', '', 'qis', 'utf8');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'common', 'utf8');
            $dbmanage->backup('', ROOT_PATH . "backup/qis/" . date('YmdHis') . "/", 4000);
            $result = $dbmanage->backup('', ROOT_PATH . "backup/qis/" . date('YmdHis') . "/", 4000);
            if ($result) {
                $rel = mysql_query('INSERT INTO com_dbbackup (path,posttime,editor,db) VALUES ("' . $path . '","' . time() . '","' . $_SESSION["uName"] . '","qis")');
                if ($rel) outData(1, $rel, "备份成功");
            } else {
                outData(2, "操作有误");

            }
        }
        if ($_POST["i"] == 3) {
            $res = $db->find('select *from com_dbbackup where db= "secbc"');
            $dbmanage = new DBManage ('localhost', 'secbchh', 'hhui2016*&^%', 'secbc', 'utf8');
//            $dbmanage = new DBManage ('localhost', 'root', '', 'secbc', 'utf8');
            $result = $dbmanage->restore($res["path"]);
            if ($result) outData(1,  "还原成功");
            outData(2, "操作有误");
            exit;
            $path = ROOT_PATH . "backup/secbc/" . date('YmdHis') . "/";
            $path = str_replace('\\', '/', $path);
            $dbmanage = new DBManage ('localhost', 'root', '', 'secbc', 'utf8');
            $dbmanage->backup('', ROOT_PATH . "backup/secbc/" . date('YmdHis') . "/", 4000);
            $result = $dbmanage->backup('', ROOT_PATH . "backup/secbc/" . date('YmdHis') . "/", 4000);
            if ($result) {
                $rel = mysql_query('INSERT INTO com_dbbackup (path,posttime,editor,db) VALUES ("' . $path . '","' . time() . '","' . $_SESSION["uName"] . '","secbc")');
                if ($rel) outData(1, $rel, "备份成功");
            } else {
                outData(2, "操作有误");
            }
        }
        outData(1, "备份成功");
        outData(2, "操作有误2");

    /*
* 检测系统信息,显示预警信息
*
*/
    case "getwarninginfo"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "warninginfo");
//        $data=$db->findALL("select *from " . DB_PREFIX . "warninginfo");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findALL("select *from " . DB_PREFIX . "warninginfo" . " order by Wtime  desc limit " . $lim);
        if ($data) {
            foreach ($data as $k => $v) {
                if ($v["Wlevel"] == 0) {
                    $data[$k]["Wlevelname"] = "低";
                } elseif ($v["Wlevel"] == 1) {
                    $data[$k]["Wlevelname"] = "中";
                } else {
                    $data[$k]["Wlevelname"] = "高";
                }
                if ($v["ProStatus"] == 1) {
                    $data[$k]["ProStatusname"] = "已处理";
                } else {
                    $data[$k]["ProStatusname"] = "未处理";
                }

                if($data[$k]["user"]){
                    $data[$k]["userstr"]=explode(",",$data[$k]["user"]);
//                    $data[$k]["userstr"]=implode(",",$data[$k]["user"]);
                }
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
* 获取通告消息
*
*/
    case "getNotice"://
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $data = array();
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "sysnotice");

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if ($rel) {
            $data = $db->findALL("select *from " . DB_PREFIX . "sysnotice" . " order by posttime  desc limit " . $lim);
        }
        if ($data) {
            foreach ($data as $k => $v) {
                $data[$k]["ptime"] = date("Y-m-d H:i:s", $v["posttime"]);
            }
        }
        outData(1, $rel, $data);
        outData(2, "操作有误");

    //修改邮件配置
    case "emailconfig":
        $name = $_SESSION['uName'];
        if (!$name) outData(2, "还没有登陆");
        $id = $_POST['id'];
        $arr = $_POST;
//        print_r($arr);
//        exit;
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $arr['Account'])) outData(2, "收件人请输入正确的邮箱地址");
        $data = $db->update("com_emailconfig", $arr, "id=" . $id);
        if ($data) {
            //操作日志成功数组
            outData(1, "设置成功");

        } else {
            //操作日志失败数组
            outData(2, '操作有误');

        }

//查询所有邮件模板
    case "emailtpl":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $sql = "select count(1) as num from " . DB_PREFIX . "emailtpl ";
        $rel = $db->findALL($sql);
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $sql1 = "select *from " . DB_PREFIX . "emailtpl order by id desc limit " . $lim;
        $data = $db->findAll($sql1);
//        print_r($data);
        outData(1, $rel, $data);
        outData(2, "操作有误");


    //查询单条邮件模板
    case "showThisTpl":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (isset($_POST["id"])) {
            $sql = "select *from " . DB_PREFIX . "emailtpl where id =" . $_POST["id"];
            $data = $db->find($sql);
//        print_r($data);
            outData(1, "", $data);
        }
        outData(2, "操作有误");


    /*

    /*
 * 删除邮件模板
 */
    case "emailtpldel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $id = $_POST['id'];
        if (empty($id)) outData(2, "操作有误");
        $rel = $db->delete(DB_PREFIX . "emailtpl", "id=" . $id);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "你还没有权限");

    /*
* 删除文件信息
*/
    case "delfile":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $id = $_POST['id'];
        if (empty($id)) outData(2, "操作有误");
        $rel = $db->delete(DB_PREFIX . "fileinfo", "id=" . $id);
        if ($rel) outData(1, "删除成功", 2);
        outData(2, "你还没有权限");

    /*
* 修改邮件模板
*/
    case "emailtpledited":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!$_POST["arr"]) outData(2, "请输入模板内容");
        $id = $_POST['id'];
        $arr=$_POST["arr"];
        $pieces["tplcontent"] = $arr[0];
        $pieces["edittime"]=date("Y-m-d H:i:s");
        if (empty($id)) outData(2, "操作有误");
        $rel = $db->update("com_emailtpl", $pieces, "id=" . $id);
        if ($rel) outData(1, "修改成功", 2);
        outData(2, "你还没有权限");

    /*
* 修改邮件模板
*/
    case "emailtpladd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if(!$_POST["tplname"]) outData(2, "请输入模板名称");
        if(!$_POST["arr"]) outData(2, "请输入模板内容");
        $arr=$_POST["arr"];
        $pieces["tplcontent"] = $arr[0];
        $pieces["tplname"] = $_POST["tplname"];
        $pieces["creattime"] = date("Y-m-d H:i:s");
        $pieces["creator"] = $_SESSION['uName'];
        print_r($pieces);
        exit;
        $rel = $db->save(DB_PREFIX . "emailtpl", $pieces);
        if ($rel) outData(1, "新增成功", 2);
        outData(2, "你还没有权限");

//    case "readExcel":
//        require_once './include/ExcelToArrary.class.php';
//        if(!defined('ROOT_PATH')) define('ROOT_PATH',dirname(dirname(__FILE__)).'/');
//        $ExcelToArrary=new ExcelToArrary();
//        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
//        if(isset($_POST["filePath"])&&$_POST["filePath"]!=null){
//                $tmp_file = $_POST["filePath"];
//                            $tmp_file = "C:/Users/Huahui/Desktop/buglist.xlsx";
//
//                $file_types = explode(".", $tmp_file);
//                $file_type = $file_types [count($file_types) - 1];
//
//                /*判别是不是.xls文件，判别是不是excel文件*/
////                if (strtolower($file_type) != "xls"||strtolower($file_type) != "xlsx") {
////                    outData('不是Excel文件，重新上传');
////                }
//                /*设置上传路径*/
//                $savePath = 'F:/WAMP/wamp/www/html/uploads/upfile/excel/';
//                /*以时间来命名上传的文件*/
//                $str = date('Ymdhis');
//                $file_name = $str . "." . $file_type;
//                /*是否上传成功*/
//                if (!copy($tmp_file, $savePath . $file_name)) {
//                    $this->error('上传失败');
//                }
//                /*
//                   *对上传的Excel数据进行处理生成编程数据,这个函数会在下面第三步的ExcelToArray类中
//                  注意：这里调用执行了第三步类里面的read函数，把Excel转化为数组并返回给$res,再进行数据库写入
//                */
//                $res = $ExcelToArrary->read($savePath . $file_name);
//            print_r($res);
//            print_r(888);
//            exit;
//                /*
//                     重要代码 解决Thinkphp M、D方法不能调用的问题
//                     如果在thinkphp中遇到M 、D方法失效时就加入下面一句代码
//                 */
//                //spl_autoload_register ( array ('Think', 'autoload' ) );
//                /*对生成的数组进行数据库的写入*/
//                foreach ($res as $k => $v) {
//                    if ($k != 0) {
//                        $data ['uid'] = $v [0];
//                        $data ['password'] = sha1('111111');
//                        $data ['email'] = $v [1];
//                        $data ['uname'] = $v [3];
//                        $data ['institute'] = $v [4];
//                        $result = M('user')->add($data);
//                        if (!$result) {
//                            $this->error('导入数据库失败');
//                        }
//                    }
//                }
//        }
//        print_r($_POST);
//        exit;
//
////        print_r($data);
//        outData(1, $rel, $data);
//        outData(2, "操作有误");


    case "readExcel"://使用xls导入
        $name = $_SESSION['uName'];
        /*
        if (!$name) outData(2, "还没有登陆");
        if (isset($_FILES['newcel'])) {
            $name = 'newcel';
        } else {
            $name = 'newcel';
        }*/
        $name = 'newcel';
        require_once ROOT_PATH . 'include/Uploader.class.php';
        require_once ROOT_PATH . "PHPExcel/Classes/PHPExcel.php";
        require_once ROOT_PATH . 'PHPExcel/Classes/PHPExcel/IOFactory.php';

        $arr = array(
            ".xls",
            ".xlsx",
            ".csv"
        );

        $config = array(
            //  "pathFormat" =>"/upimage/".date("Y-m-d H:i:s"),
            "pathFormat" => "/upfile/" . time(),
            "maxSize" => 2048000,
            "allowFiles" => $arr
        );

        $up = new Uploader('newcel', $config);

        $img = $up->getFileInfo();

        $filename = ROOT_PATH ."upfile/".$img['title'];
//        print_r($img);
        $filename = str_replace('\\', '/', $filename);


        // Check prerequisites
        if (!file_exists($filename)) {
            exit("not found");
        }


        $reader = $objReader = new PHPExcel_Reader_Excel2007(); //设置以Excel5格式(Excel97-2003工作簿)

        $PHPExcel = $reader->load($filename); // 载入excel文件
        //print_r($reader);
//
//        echo "8888".$filename;
//        exit;
        $sheet = $PHPExcel->getSheet(0); // 读取第一個工作表

        $highestRow = $sheet->getHighestRow(); // 取得总行数
        $highestColumm = $sheet->getHighestColumn(); // 取得总列数

        /** 循环读取每个单元格的数据 */
        $li = "";
        for ($row = 1; $row <= $highestRow; $row++) {//行数是以第1行开始

            $li .= '<tr class="cz_submits">';

            for ($column = 'A'; $column <= $highestColumm; $column++) {//列数是以A列开始
                $col = $sheet->getCell($column . $row)->getValue();
                $li .= '<td>' . $col . '</td>';
            }
            $li .= '</tr>';
        }
        $message['Row'] = $highestRow;
        $message['Col'] = $highestColumm;

        outData(1, $message, $li);


    case "getroot"://
        print_r($_FILES);
        $name = $_SESSION['uName'];
        if (!$name) outData(2, "还没有登陆");
        if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
        if ($_POST["path"]) {

        }
        $tmp_file = 'F:\WAMP\wamp\www\html\_PxCook.png';
        $file_types = explode(".", $tmp_file);
        $file_type = $file_types [count($file_types) - 1];

        /*判别是不是.xls文件，判别是不是excel文件*/
//                if (strtolower($file_type) != "xls"||strtolower($file_type) != "xlsx") {
//                    outData('不是Excel文件，重新上传');
//                }
        /*设置上传路径*/
        print_r($tmp_file);

        $savePath = ROOT_PATH . '/uploads/upfile/tmp/img/';
        /*以时间来命名上传的文件*/
        $str = date('Ymdhis');
        $file_name = $str . "." . $file_type;
        /*是否上传成功*/
        if (!copy($tmp_file, $savePath . $file_name)) {
            $this->error('上传失败');
        }

        require_once ROOT_PATH . 'include/Uploader.class.php';
        require_once ROOT_PATH . "PHPExcel/Classes/PHPExcel.php";
        require_once ROOT_PATH . 'PHPExcel/Classes/PHPExcel/IOFactory.php';

        outData(1, $message, $li);
    /*
		* 用户权限管理
		* by zhangping
		*/
    case "UsrAuthority":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole !=0";
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole !=0 and uName like '%" . $_GET['uName'] . "%'";
        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        $sql1 = "select * from " . DB_PREFIX . "usr where uRole !=0 limit " . $lim;
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $sql1 = "select * from " . DB_PREFIX . "usr where uRole !=0 and uName like '%" . $_GET['uName'] . "%' limit " . $lim;
        }

        $data = $db->findAll($sql1);

        foreach ($data as $k => $v) {
            $data2 = $db->find("select CountInfo,Id from " . DB_PREFIX . "usrbusiness where  UsrId=" . $v['Id'] . " limit 1");
            if ($data2) {
                $a = '';
                $countdat = json_decode($data2["CountInfo"]);
                $n = count($countdat);
                for ($x = 0; $x <= $n - 1; $x++) {
                    if ($countdat[$x] == 'ADD') {
                        $a .= ' | 增加';
                    }
                    if ($countdat[$x] == 'DEL') {
                        $a .= ' | 删除';
                    }
                    if ($countdat[$x] == 'MODIFY') {
                        $a .= ' | 修改';
                    }
                    if ($countdat[$x] == 'QUERY') {
                        $a .= ' | 查询';
                    }
                    if ($countdat[$x] == 'TOKEN') {
                        $a .= ' | 口令管理';
                    }


                }
                $data[$k]['CountInfo'] = $a;
            }

        }

        outData(1, $rel, $data);
        outData(2, "操作有误");
    /*
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {    //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");
            }else{
               if($data) outData(1,$rel ,$data);
               outData(2,"未知错误，请重新登陆");

             }
            */
    /*
        * 业务查看管理
        * by zhangping
        */
    case "BusinessInquire":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        if ($_SESSION['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole !=0";
        } else {
            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole =2";
        }


        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {

            if ($_SESSION['uRole'] == 0) {
                $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole !=0 and uName like '%" . $_GET['uName'] . "%'";
            } else {
                $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole =2 and  uName like '%" . $_GET['uName'] . "%'";

            }

        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        if ($_SESSION['uRole'] == 0) {
            $sql1 = "select * from " . DB_PREFIX . "usr where uRole !=0 limit " . $lim;
        } else {
            $sql1 = "select * from " . DB_PREFIX . "usr where uRole =2 limit " . $lim;

        }
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            if ($_SESSION['uRole'] == 0) {
                $sql1 = "select * from " . DB_PREFIX . "usr where uRole !=0 and uName like '%" . $_GET['uName'] . "%' limit " . $lim;
            } else {
                $sql1 = "select * from " . DB_PREFIX . "usr where uRole =2 and uName like '%" . $_GET['uName'] . "%' limit " . $lim;

            }

        }

        $data = $db->findAll($sql1);

        foreach ($data as $k => $v) {

            $businesses = array();//业务数组
            $modules = array();//模块数组
            $funcs = array();//功能数组
            //根据用户的ID从common.usrbusiness表中获取该用户所有的业务、模块、功能信息
            $business = $db->find("select BusinessInfo from " . DB_PREFIX . "usrbusiness  where  UsrId=" . $v['Id'] . "  limit 1 ");
            if ($business) {
                $data1 = json_decode($business["BusinessInfo"], true);    //json转数组
                if ($data1) {
                    if ($data1) {
                        $i = 0;
                        $j = 0;
                        $h = 0;
                        //第一层循环获取业务
                        foreach ($data1 as $key => $val) {
                            //第二层循环获取模块
                            $busines[$h] = $key;//包含所有模块
                            foreach ($val as $kk => $vv) {
                                $modules[$i] = $kk;//包含所有模块
                                //第三层循环获取功能
                                foreach ($vv as $item => $func) {
                                    $funcs[$j] = $item;
                                    $j++;
                                }
                                $i++;
                            }
                            $h++;
                        }

                    } else {
                        outData(2, "操作有误");
                    }

                    $modulesInfo = array();//模块信息数组
                    $funcInfo = array();//功能信息数组
                    //根据模块名称从commmon.module中查询模块信息
                    $a = '';
                    foreach ($busines as $key => $busi) {
                        //$a .= " | ".$busi;   //json 获取第一层
                    }

                    //根据功能名称从commmon.funcyion中查询功能信息
                    foreach ($modules as $key => $func) {
                        $a .= " | " . $func;
                        //$funcInfo[$key] = $db->find("select Function,Id,Dbname,Addr from " . DB_PREFIX . "function  where Function='" . $func . "' limit 1 ");

                    }

                    $data[$k]['CountInfo'] = $a;

                }


            }


        }


        outData(1, $rel, $data);
        outData(2, "操作有误");
    /*
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {    //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");
            }else{  
               if($data) outData(1,$rel ,$data);
               outData(2,"未知错误，请重新登陆");

             }	
            */


    /*
        * 申请业务同意功能
        * by zhangping
        */

    case "Agree":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;
        $venid = $_POST['venid'];
        $venau = $_POST['venau'];

        if (empty($venid)) outData(2, "操作有误");


        $rel1 = $db->find("select * from " . DB_PREFIX . "application  where  Id=" . $venid . "  limit 1 ");
        $rel2 = $db->find("select * from " . DB_PREFIX . "usr  where  uName='" . $rel1['UsrName'] . "'  limit 1 ");
        $rel3 = $db->find("select * from " . DB_PREFIX . "usrbusiness  where  UsrId='" . $rel2['Id'] . "' limit 1 ");
        if ($rel3) {
            $arr = array(
                "RegTime" => date("Y-m-d H:i:s"),
                "BusinessInfo" => $rel1['ApplicationInfo']
            );
            $rel = $db->update(DB_PREFIX . "usrbusiness", $arr, "UsrId='" . $rel2['Id'] . "'");
        } else {
            $arr = array(
                "RegTime" => date("Y-m-d H:i:s"),
                "BusinessInfo" => $rel1['ApplicationInfo'],
				 "UsrId"  =>$rel2['Id']
            );
            $rel = $db->save(DB_PREFIX . "usrbusiness", $arr);
        }

        if ($rel) {
            $arr = array(
                "RegTime" => date("Y-m-d H:i:s"),
                "Review" => 1
            );
            $rel4 = $db->update(DB_PREFIX . "application", $arr, "Id='" . $venid . "'");
        }
        if ($rel4) outData(1, "审核通过", 1);
        outData(2, "审核失败");


    /*
        * 申请业务管理列表
        * by zhangping
        */
    case "Application":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;
        if ($_SESSION['uRole'] == 0) {
            $sql = "select count(1) as num from " . DB_PREFIX . "application";
        } else {
            $sql = "select count(1) as num from " . DB_PREFIX . "application where Role=2";
        }

        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            if ($_SESSION['uRole'] == 0) {
                $sql = "select count(1) as num from " . DB_PREFIX . "application where  UsrName like '%" . $_GET['uName'] . "%'";
            } else {
                $sql = "select count(1) as num from " . DB_PREFIX . "application where Role=2 and UsrName like '%" . $_GET['uName'] . "%'";

            }

        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        if ($_SESSION['uRole'] == 0) {
            $sql1 = "select * from " . DB_PREFIX . "application limit " . $lim;
        } else {
            $sql1 = "select * from " . DB_PREFIX . "application where Role=2 limit " . $lim;

        }


        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            if ($_SESSION['uRole'] == 0) {
                $sql1 = "select * from " . DB_PREFIX . "application where  UsrName like '%" . $_GET['uName'] . "%' limit " . $lim;
            } else {
                $sql1 = "select * from " . DB_PREFIX . "application where Role=2 and UsrName like '%" . $_GET['uName'] . "%' limit " . $lim;
            }
            $sql1 = "select * from " . DB_PREFIX . "application where  UsrName like '%" . $_GET['uName'] . "%' limit " . $lim;
        }

        $data = $db->findAll($sql1);

        foreach ($data as $k => $v) {

            $businesses = array();//业务数组
            $modules = array();//模块数组
            $funcs = array();//功能数组
            //根据用户的ID从common.usrbusiness表中获取该用户所有的业务、模块、功能信息
            $business = $db->find("select ApplicationInfo from " . DB_PREFIX . "application  where  Id=" . $v['Id'] . "  limit 1 ");
            if ($business) {
                $data1 = json_decode($business["ApplicationInfo"], true);    //json转数组
                if ($data1) {
                    if ($data1) {
                        $i = 0;
                        $j = 0;
                        $h = 0;
                        //第一层循环获取业务
                        foreach ($data1 as $key => $val) {
                            //第二层循环获取模块
                            $busines[$h] = $key;//包含所有模块
                            foreach ($val as $kk => $vv) {
                                $modules[$i] = $kk;//包含所有模块
                                //第三层循环获取功能
                                foreach ($vv as $item => $func) {
                                    $funcs[$j] = $item;
                                    $j++;
                                }
                                $i++;
                            }
                            $h++;
                        }

                    } else {
                        outData(2, "操作有误");
                    }

                    $modulesInfo = array();//模块信息数组
                    $funcInfo = array();//功能信息数组
                    //根据模块名称从commmon.module中查询模块信息
                    $a = '';
                    foreach ($busines as $key => $busi) {
                        //$a .= " | ".$busi;   //json 获取第一层
                    }

                    //根据功能名称从commmon.funcyion中查询功能信息
                    foreach ($modules as $key => $func) {
                        $a .= " | " . $func;
                        //$funcInfo[$key] = $db->find("select Function,Id,Dbname,Addr from " . DB_PREFIX . "function  where Function='" . $func . "' limit 1 ");

                    }

                    $data[$k]['ApplicationInfo'] = $a;

                }


            }


        }


        outData(1, $rel, $data);
        outData(2, "操作有误");
    /*
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {    //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");
            }else{  
               if($data) outData(1,$rel ,$data);
               outData(2,"未知错误，请重新登陆");

             }	
            */


    /*
* KEY管理列表
* by zhangping
*/

    case "BusinessAuthority":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

            $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole =1";
			if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
                $sql = "select count(1) as num from " . DB_PREFIX . "usr where uRole =1 and uName like '%" . $_GET['uName'] . "%'";
            }

			$rel = $db->findALL($sql);

			$pagepar =10;
			$rel[1]['sum'] =ceil($rel[0]['num']/$pagepar);

			$curren =isset($_GET['page']) ? $_GET['page'] : 1;
			$lim=(($curren-1)*$pagepar).",".$pagepar;

			if($curren==$rel[1]['sum']){
				$end=$rel[0]['num']-(($curren-1)*$pagepar);
				$lim=(($curren-1)*$pagepar).",".$end;
			}

            $sql1="select * from ".DB_PREFIX."usr where uRole =1 limit ".$lim;
			if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $sql1 = "select * from ".DB_PREFIX."usr where uRole =1 and uName like '%" . $_GET['uName'] . "%' limit ".$lim;
            }

			$data=$db->findAll($sql1);

			foreach ($data as $k => $v) {
            $data2 = $db->find("select * from ".DB_PREFIX."businesspower where  UsrId=" . $v['Id'] . " limit 1");
              if($data2){
              	$a='';
              	$countdat=json_decode($data2["BusinessInfo"]);
              	$n=count($countdat);
              	for ($x=0; $x<=$n-1; $x++) {
              		if($countdat[$x]=='CADD'){
              			$a .= ' | 业务查看增加';
              		}
              		if($countdat[$x]=='CDEL'){
              			$a .= ' | 业务查看删除';
              		}
              		if($countdat[$x]=='CMODIFY'){
              			$a .= ' | 业务查看修改';
              		}
              		if($countdat[$x]=='CQUERY'){
              			$a .= ' | 业务查看查询';
              		}
              		if($countdat[$x]=='YADD'){
              			$a .= ' | 业务预警增加';
              		}
              		if($countdat[$x]=='YDEL'){
              			$a .= ' | 业务预警删除';
              		}
              		if($countdat[$x]=='YMODIFY'){
              			$a .= ' | 业务预警修改';
              		}
              		if($countdat[$x]=='YQUERY'){
              			$a .= ' | 业务预警查询';
              		}
              		if($countdat[$x]=='LADD'){
              			$a .= ' | 业务策略增加';
              		}
              		if($countdat[$x]=='LDEL'){
              			$a .= ' | 业务策略删除';
              		}
              		if($countdat[$x]=='LMODIFY'){
              			$a .= ' | 业务策略修改';
              		}
              		if($countdat[$x]=='LQUERY'){
              			$a .= ' | 业务策略查询';
              		}
              		if($countdat[$x]=='BADD'){
              			$a .= ' | 统计报表增加';
              		}
              		if($countdat[$x]=='BDEL'){
              			$a .= ' | 统计报表删除';
              		}
              		if($countdat[$x]=='BMODIFY'){
              			$a .= ' |统计报表修改';
              		}
              		if($countdat[$x]=='BQUERY'){
              			$a .= ' | 统计报表查询';
              		}


                 }
                $data[$k]['BusinessInfo'] = $a;

              }

        }

        outData(1, $rel, $data);
        outData(2, "操作有误");
    /*
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {    //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
               if($data) outData(1,$rel ,$data);
               outData(2,"搜索不到相关信息");
            }else{
               if($data) outData(1,$rel ,$data);
               outData(2,"未知错误，请重新登陆");

             }	
            */

    /*
* KEY管理列表
*
*/

    case "keyall":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if(empty($_SESSION['uRole']))  outData(2,"你还没有权限");

        $role = isset($_GET['role']) ? $_GET['role'] : 0;

        $sql = "select count(1) as num from " . DB_PREFIX . "softproduct";
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $sql = "select count(1) as num from " . DB_PREFIX . "softproduct where SoftInfo like '%" . $_GET['uName'] . "%'";
        }

        $rel = $db->findALL($sql);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }


        $sql1 = "select " . DB_PREFIX . "softproduct.SoftInfo," . DB_PREFIX . "softproduct.Id," . DB_PREFIX . "softproduct.Product," . DB_PREFIX . "token.TokeyKey," . DB_PREFIX . "token.RegTime FROM " . DB_PREFIX . "softproduct INNER JOIN " . DB_PREFIX . "token ON " . DB_PREFIX . "softproduct.Id = " . DB_PREFIX . "token.SoftID ORDER BY " . DB_PREFIX . "softproduct.Id limit " . $lim;
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $sql1 = "select " . DB_PREFIX . "softproduct.SoftInfo," . DB_PREFIX . "softproduct.Id," . DB_PREFIX . "softproduct.Product," . DB_PREFIX . "token.TokeyKey," . DB_PREFIX . "token.RegTime FROM " . DB_PREFIX . "softproduct INNER JOIN " . DB_PREFIX . "token ON " . DB_PREFIX . "softproduct.Id = " . DB_PREFIX . "token.SoftID where SoftInfo like '%" . $_GET['uName'] . "%'  ORDER BY " . DB_PREFIX . "softproduct.Id limit " . $lim;
        }

        $data = $db->findAll($sql1);

        foreach ($data as $k => $v) {
            $data2 = $db->find("select Product,Id from " . DB_PREFIX . "softproduct where  Id=" . $v['Id'] . " limit 1");
            if ($data2) {
                $a = '';
                $countdat = json_decode($data2["Product"]);
                $n = count($countdat);
                for ($x = 0; $x <= $n - 1; $x++) {
                    $a .= " | " . $countdat[$x];
                }
                $data[$k]['Product'] = $a;
            }

        }

        outData(1, $rel, $data);
        outData(2, "操作有误");
    /*
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {    //针对实时查询功能，如果查询卡，请打开这个关闭上两行代码
           if($data) outData(1,$rel ,$data);
           outData(2,"搜索不到相关信息");
        }else{
           if($data) outData(1,$rel ,$data);
           outData(2,"未知错误，请重新登陆");

         }

     */

    /*



    /*
		* 消息推送管理（显示推送消息的类型、消息内容、消息接收端、消息推送的时间间隔、开始/停止推送；）
		* by zhangping
		*/
    case "pushMsg":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $vendid = $_SESSION['ven']['venId'];

        $rel = $db->findALL("select count(1) as num from bc_msg where  senderId=" . $_SESSION['Id']);

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        $data = $db->findALL("select *from  bc_msg where  senderId=" . $_SESSION['Id'] . " order by msgStatus desc limit " . $lim);
        foreach ($data as $k => $v) {
            $receiverName = $db->find("select uName from bc_usr where  Id=" . $v['receiverId'] . " limit 1");
            $data[$k]['receiverName'] = $receiverName['uName'];

        }

        outData(1, $rel, $data);
        outData(2, "操作有误");


    /*
		* 企业用户新增一条推送消息
		* by zhangping
		*/
    case "qisMsgAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $data = array();
        if ($_POST) {
            $data['content'] = trim($_POST['content']);
            $receiverName = trim($_POST['receiverName']);
            $Id = $db->find("select Id from bc_usr where  uName='" . $receiverName . "' limit 1");
            if (empty($Id)) {
                outData(2, "输入的用户不存在，请重新输入！");
            }
            if ($Id['Id'] == $_SESSION['Id']) outData(2, "不能发送消息给自己，请重新输入！");
            $data['receiverId'] = $Id['Id'];
            $data['timeLag'] = trim($_POST['timeLag']);
            $data['type'] = trim($_POST['type']);
            $data['msgStatus'] = trim($_POST['msgStatus']);
            $data['senderId'] = trim($_SESSION['Id']);
        }
        $rel = $db->save("bc_msg", $data);
        if ($rel) outData(1, "增加成功");
        outDAta(2, "输入有误");


    /*
    * 企业用户修改一条推送消息
    * by zhangping
    */
    case "qisMsgEdit":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $data = array();
        if ($_POST) {
            $data['msgId'] = trim($_POST['msgId']);
            $data['content'] = trim($_POST['content']);
            if (!isset($data['content'])) outData(2, "请输入消息内容");
            $receiverName = trim($_POST['receiverName']);
            if (!isset($receiverName)) outData(2, "请输入接收用户");

            $Id = $db->find("select Id from bc_usr where  uName='" . $receiverName . "' limit 1");
            if (empty($Id)) {
                outData(2, "输入的用户不存在，请重新输入！");
            }
            if ($Id['Id'] == $_SESSION['Id']) outData(2, "不能发送消息给自己，请重新输入！");
            $data['receiverId'] = $Id['Id'];
            $data['timeLag'] = trim($_POST['timeLag']);
            if (!isset($data['timeLag'])) outData(2, "请输入发送时间间隔");
            if (trim($_POST['type']) == null) {
                $data['type'] = '0';
            } else {
                $data['type'] = trim($_POST['type']);
            }
            if (trim($_POST['msgStatus']) == null) {
                $data['msgStatus'] = '0';
            } else {
                $data['msgStatus'] = trim($_POST['msgStatus']);
            }
            $data['senderId'] = trim($_SESSION['Id']);
        }
        $rel = $db->update(DB_PREFIX . "msg", $data, "msgId='" . $data['msgId'] . "'");

        if ($rel) outData(1, "修改消息成功");
        outDAta(2, "输入有误");

    /*
   		* 软件版本管理（显示该系统所有软件的名称、当前版本信息、新版本信息、历史版本信息、软件升级管理；）
		* by zhangping
		*/
    case "softVersion":
        $rel = $db->findALL("select count(1) as num from " . DB_PREFIX . "softversion");
//        $data=$db->findALL("select *from " . DB_PREFIX . "warninginfo");
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        $data = $db->findALL("select *from " . DB_PREFIX . "softversion" . " order by UpdTime desc limit " . $lim);

        outData(1, $rel, $data);
        outData(2, "操作有误");

    case "upgradesoft":
        $id = $_POST["id"];
        $data = $db->find("select *from " . DB_PREFIX . "softversion  where  Id=" . $id . " order by UpdTime desc");
        $rel = $db->query("update " . DB_PREFIX . "softversion set CurrVersion=" . $data["NewVersion"] . " where Id=" . $data["Id"] . "");
        if ($rel) outData(1);
        outData(2, "操作有误");

    /*
		 * 删除企业用户下属用户
		 */
    case "qisUsrDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
        $userId = $_POST['userId'];
        $venau = $_POST['venau'];
        if (empty($userId)) outData(2, "操作有误");
        if (empty($venau)) {
            if ($_SESSION['uRole'] != 1) outData(2, "你还没有权限");
            $rel = $db->delete(DB_PREFIX . "usr", "Id=" . $userId);
            if ($rel) outData(1, "删除成功", 1);        //1代表厂商信息
            outData(2, "删除失败");
        }
        if ($venau == 2) {
            if ($_SESSION['uRole'] == 1 || $_SESSION['uRole'] == 2) {
                $rel = $db->delete(DB_PREFIX . "usr", "Id=" . $userId);
//					$rel=$db->delete(DB_PREFIX."vendauthor","Id=".$venid);
                if ($rel) outData(1, "删除成功", 2);        //2代表用预留信息
            }
            outData(2, "你还没有权限");
        }

    /*
		 * 删除企业用户权限
		 */
    case "AuthorityDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $userId = $_POST['userId'];
        if ($_SESSION['uRole'] == 0) {

            if ($_SESSION['uRole'] != 0) {
                outData(2, "你还没有权限");
            }
            if (empty($userId)) outData(2, "操作有误");
            $rel = $db->query("update " . DB_PREFIX . "usrbusiness set CountInfo='' where UsrId=" . $userId . "");
            if ($rel) outData(1, "删除成功");
            outData(2, "删除失败");

        } else {
            if (empty($userId)) outData(2, "操作有误");
            $data = $db->find("select CountInfo from " . DB_PREFIX . "usrbusiness where UsrId=" . $userId . "");
            $countdat = json_decode($data["CountInfo"]);
            if (in_array("DEL", $countdat)) {
                $rel = $db->query("update " . DB_PREFIX . "usrbusiness set CountInfo='' where UsrId=" . $userId . "");
                if ($rel) outData(1, "删除成功");
                outData(2, "删除失败");

            } else {
                outData(2, "你还没有权限");
            }

        }


    /*
		 * 删除业务查看列表
		 */
    case "InquireDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $userId = $_POST['userId'];
        if ($_SESSION['uRole'] == 0) {

            if ($_SESSION['uRole'] != 0) {
                outData(2, "你还没有权限");
            }
            if (empty($userId)) outData(2, "操作有误");
            $rel = $db->query("update " . DB_PREFIX . "usrbusiness set BusinessInfo='' where UsrId=" . $userId . "");
            if ($rel) outData(1, "删除成功");
            outData(2, "删除失败");

        } else {

            if (empty($userId)) outData(2, "操作有误");
            $rel = $db->query("update " . DB_PREFIX . "usrbusiness set BusinessInfo='' where UsrId=" . $userId . "");
            if ($rel) outData(1, "删除成功");
            outData(2, "删除失败");

            /*  判断是是否开通，需要修改一下
            if (empty($userId)) outData(2, "操作有误"); 
            $data=$db->find("select BusinessInfo from ".DB_PREFIX."usrbusiness where UsrId=".$userId."");
            $countdat=json_decode($data["CountInfo"]);
            if(in_array("DEL", $countdat)){
               $rel = $db->query("update " . DB_PREFIX . "usrbusiness set BusinessInfo='' where UsrId=".$userId."");
               if ($rel) outData(1, "删除成功");     
               outData(2, "删除失败");
            }else{
            	outData(2, "你还没有权限");
            }

             */

        }


    /*
		 * 删除用户业务权限
		 */
    case "BusinessInfoDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $userId = $_POST['userId'];
        if ($_SESSION['uRole'] == 0) {

            if ($_SESSION['uRole'] != 0) {
                outData(2, "你还没有权限");
            }
            if (empty($userId)) outData(2, "操作有误");
            $rel = $db->query("update " . DB_PREFIX . "businesspower set BusinessInfo='' where UsrId=" . $userId . "");
            if ($rel) outData(1, "删除成功");
            outData(2, "删除失败");

        } else {
            if (empty($userId)) outData(2, "操作有误");
            $data = $db->find("select CountInfo from " . DB_PREFIX . "businesspower where UsrId=" . $userId . "");
            $countdat = json_decode($data["CountInfo"]);
            if (in_array("DEL", $countdat)) {
                $rel = $db->query("update " . DB_PREFIX . "businesspower set BusinessInfo='' where UsrId=" . $userId . "");
                if ($rel) outData(1, "删除成功");
                outData(2, "删除失败");

            } else {
                outData(2, "你还没有权限");
            }

        }


    /*
		 * 删除KEY
		 */
    case "KeyDel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $userId = $_POST['userId'];

        if ($_SESSION['uRole'] != 0) {
            $userid = $db->find("select Id from " . DB_PREFIX . "usr  where uName='" . $_SESSION['uName'] . "' limit 1 ");
            $business = $db->find("select CountInfo from " . DB_PREFIX . "usrbusiness  where UsrId='" . $userid["Id"] . "' limit 1 ");
            $countdat = json_decode($business["CountInfo"]);
            $n = count($countdat);
            $k = 0;
            for ($x = 0; $x <= $n - 1; $x++) {
                if ($countdat[$x] == 'TOKEN') {
                    $k = 1;

                }
            }
            if ($k == 0) {
                outData(2, "操作有误");
            }
        }

        if (empty($userId)) outData(2, "操作有误");

        $rel = $db->query("delete from " . DB_PREFIX . "softproduct  where Id=" . $userId . "");
        $rel1 = $db->query("delete from " . DB_PREFIX . "token  where SoftID=" . $userId . "");
        if ($rel && $rel1) outData(1, "删除成功");
        outData(2, "删除失败");


    /*
		 * 更新KEY
		 */
    case "KeyUpdate":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $userId = $_POST['userId'];
        if ($_SESSION['uRole'] != 0) {
            $userid = $db->find("select Id from " . DB_PREFIX . "usr  where uName='" . $_SESSION['uName'] . "' limit 1 ");
            $business = $db->find("select CountInfo from " . DB_PREFIX . "usrbusiness  where UsrId='" . $userid["Id"] . "' limit 1 ");
            $countdat = json_decode($business["CountInfo"]);
            $n = count($countdat);
            $k = 0;
            for ($x = 0; $x <= $n - 1; $x++) {
                if ($countdat[$x] == 'TOKEN') {
                    $k = 1;

                }
            }
            if ($k == 0) {
                outData(2, "操作有误");
            }
        }
        if (empty($userId)) outData(2, "操作有误");

        $key = md5(date('Y-m-d H:i:s'));
        $arr = array(
            "TokeyKey" => $key
        );

        $rel = $db->update(DB_PREFIX . "token", $arr, "SoftID=" . $userId);

        if ($rel) outData(1, "更新成功");
        outData(2, "更新失败");

    /*
		 * 增加KEY
		 */
    case "keyadd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $auth = substr($_POST['auth'], 1);
        $arr = explode(",", $auth);
        $authdata = json_encode($arr);
        if (empty($auth)) {
            $authdata = '';
        }
        if (empty($authdata)) outData(2, "请选择功能！");

        $oldpas = $_POST['oldpas'];
        if (empty($oldpas)) outData(2, "请输入设备的名称！");
        $regtime = date("Y-m-d H:i:s");
        $key = MD5(date("Y-m-d H:i:s"));
        $arr = array(
            "RegTime" => $regtime,
            "Product" => $authdata,
            "SoftInfo" => $oldpas
        );

        $rel = $db->save(DB_PREFIX . "softproduct", $arr);
        $gid = mysql_insert_id();
        $arrr = array(
            "RegTime" => $regtime,
            "TokeyKey" => $key,
            "SoftID" => $gid
        );
        $rel2 = $db->save(DB_PREFIX . "token", $arrr);


        if ($rel2) outData(1, "增加成功");
        outData(2, "增加失败");

   case "Warning"://业务预警列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."collectwarn  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."collectwarn");
        }
        }else{
          $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."collectwarn where UsrName='".$_SESSION['uName']."'");  
        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "collectwarn where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);     
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "collectwarn  limit " . $lim);
        }
        }else{
           $data = $db->findAll("select * from " . DB_PREFIX . "collectwarn where UsrName='".$_SESSION['uName']."' limit " . $lim); 
        } 
        outData(1, $rel, $data);


 case "Warnlist"://分析预警列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarn  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarn");
        }
        }else{
          $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarn where UsrName='".$_SESSION['uName']."'");
        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarn where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarn  limit " . $lim);
        }
        }else{
           $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarn where UsrName='".$_SESSION['uName']."' limit " . $lim);
        }

        foreach ($data as $k => $v) {
            $data2 = $db->find("select * from ".DB_PREFIX."analysis  where Alias='".$data[$k]['Alias']."'");
            $data[$k]['AnalysisId']=$data2['Id']; 
        }
        outData(1, $rel, $data);


   case "Report"://统计报表列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."report  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."report");
        }
        }else{
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."report  where UsrName and UsrRole=2 like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."report where UsrRole=2");
        }

        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "report where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "report limit " . $lim);
        }
        }else{
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "report where UsrRole=2 and UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "report where UsrRole=2  limit " . $lim);
        }

        }

       foreach ($data as $k => $v) {
        $m='';
        $countdat=json_decode($v['ReportInfo']);
        $n=count($countdat);
        for ($x=0; $x<=$n-1; $x++) {
            if($countdat[$x]==1){  $m.=" | <a href='#'>数据表</a>"; }
            if($countdat[$x]==2){  $m.=" | <a href='#'>信息表</a>"; }
            if($countdat[$x]==3){  $m.=" | <a href='#'>统计表</a>"; }
          //$m.=$countdat[$x];
        }
         $data[$k]['ReportInfoo']=$m;
       }
      outData(1, $rel, $data);


  //业务策略列表
   case "Strategy":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){

        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."strategy  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."strategy");
        }

        }else{

        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."strategy  where UsrName='".$_SESSION['uName']."' or UsrRole=2 like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."strategy where UsrName='".$_SESSION['uName']."' or UsrRole=2");
        }

        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){

        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "strategy where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "strategy limit " . $lim);
        }

        }else{

            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "strategy where UsrName='".$_SESSION['uName']."' or UsrRole=2  like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "strategy where UsrName='".$_SESSION['uName']."' or UsrRole=2  limit " . $lim);
        }

        }

       foreach ($data as $k => $v) {
         $m='';
         if($data[$k]['Cpk']==1){ $m.='| CPK';}
         if($data[$k]['Badrate']==1){ $m.=' | 不良率';}
         $data[$k]['condition']=$m;
       }
      outData(1, $rel, $data);

  //业务策略增加和修改

    case "StrategyAdd":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $usrname = trim($_POST['usrname']);
        $method = trim($_POST['method']);
        $begintime = trim($_POST['begintime']);
        $endtime = trim($_POST['endtime']);
        $cpk = trim($_POST['cpk']);
        $badrate = trim($_POST['badrate']);
        $praduct = trim($_POST['praduct']);
        if($method==1){
        if (empty($begintime)) outData(2, "请填开始时间！！");
        if (empty($endtime)) outData(2, "请填写结束时间！！");
        }else{
            $begintime=date("Y-m-d H:i:s");
            $endtime=date("Y-m-d H:i:s");
            $praduct='';
            $cpk=0;
            $badrate=0;
        }

       if($_POST['id']==0){
        $relb = $db->find("select * from " . DB_PREFIX . "strategy where UsrName='".$usrname."'");
        if($relb){

               outData(2, "该用户已经增加策略！！");

        }
        }else{
        $relb = $db->find("select * from " . DB_PREFIX . "strategy where UsrName='".$usrname."' and Id!=".$_POST['id']."");
        if($relb){

                outData(2, "该用户已经增加策略！！");
        }
        }
         $relm = $db->find("select * from " . DB_PREFIX . "usr where uName='".$usrname."'");
        $arr = array(
            "UsrName" => $usrname,
            "UsrRole" => $relm['uRole'],
            "Method" => $method,
            "StartTime" => $begintime,
            "EndTime" => $endtime,
            "Cpk" => $cpk,
            "Badrate" => $badrate,
            "Praduct" => $praduct
        );
        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX . "strategy", $arr);
        }else{
            $rel = $db->update(DB_PREFIX . "strategy", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


    /*
         * 增加统计报表
         */
    case "ReportAdd":
        if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
        if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");
        $auth=substr($_POST['auth'],1);
        $arr = explode(",",$auth);
        $authdata=json_encode($arr);
        if(empty($auth)){ $authdata='';  }
        if (empty($authdata)) outData(2, "请选择报表！");

        $regtime = date("Y-m-d H:i:s");
        $usrname = $_POST['usrname'];
        $relm = $db->find("select * from " . DB_PREFIX . "usr where uName='".$usrname."'");

        if($_POST['id']==0){
            $reld = $db->find("select * from " . DB_PREFIX . "report where UsrName='".$usrname."'");
        }else{
           $reld = $db->find("select * from " . DB_PREFIX . "report where UsrName='".$usrname."' and  Id!=".$_POST['id']."");
        }
        if($reld){ outData(2, "该用户已经增加有统计报表"); }
        $arr = array(
            "RegTime" => $regtime,
            "UsrName" => $usrname,
            "ReportInfo" => $authdata,
            "UsrRole" => $relm['uRole']
        );
        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX . "report", $arr);
        }else{
             $rel = $db->update(DB_PREFIX . "report",$arr, "Id=".$_POST['id']);
        }

        if($_POST['id']==0){
        if ($rel) outData(1, "增加成功");
        outData(2, "增加失败");
        }else{
        if ($rel) outData(1, "修改成功");
        outData(2, "修改失败");
        }
   case "WarningInfo"://业务预警提示信息列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
         if(isset($_GET['y'])){ $y=$_GET['y']; if($y==0){ $w=''; }  if($y==1){ $w=' and Status=0 '; }  if($y==2){ $w=' and Status=1 '; } if($y==3){ $w=' and Status=2 '; } }
         if ($_SESSION['uRole'] == 0){
            if(isset($_GET['y'])){
               $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."warninglist where 1=1 ".$w.""); 
            }else{
               $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."warninglist where 1=1");  
            }
           
         }else{
            if(isset($_GET['y'])){
              $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."warninglist where 1=1 and Status!=2 and UsrName='".$_SESSION['uName']."' ".$w." ");       
                  }else{
              $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."warninglist where 1=1 and Status!=2 and UsrName='".$_SESSION['uName']."' ".$w." ");       
           }
           
         }
           
    
       
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        if ($_SESSION['uRole'] == 0){
            if(isset($_GET['y'])){  
                $data = $db->findAll("select * from " . DB_PREFIX . "warninglist where 1=1 ".$w." limit " . $lim);
                 }else{
                    $data = $db->findAll("select * from " . DB_PREFIX . "warninglist where 1=1 limit " . $lim);
                 }
             
        }else{
             if(isset($_GET['y'])){ 
                $data = $db->findAll("select * from " . DB_PREFIX . "warninglist where 1=1 and Status!=2 and  UsrName='".$_SESSION['uName']."' ".$w."  limit " . $lim); 
              }else{ 
                 $data = $db->findAll("select * from " . DB_PREFIX . "warninglist where 1=1 and Status!=2 and  UsrName='".$_SESSION['uName']."' limit " . $lim); 
                }
           
        }
        outData(1, $rel, $data);

 case "WarnInfo"://分析预警提示信息列表
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
         if(isset($_GET['y'])){ $y=$_GET['y']; if($y==0){ $w=''; }  if($y==1){ $w=' and Status=0 '; }  if($y==2){ $w=' and Status=1 '; }  if($y==3){ $w=' and Status=2 '; } }
         if ($_SESSION['uRole'] == 0){
            if(isset($_GET['y'])){
               $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarninfo where 1=1 ".$w.""); 
            }else{
               $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarninfo where 1=1");  
            }
           
         }else{
            if(isset($_GET['y'])){
              $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarninfo where 1=1 and Status!=2 and UsrName='".$_SESSION['uName']."' ".$w." ");       
                  }else{
              $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysiswarninfo where 1=1 and Status!=2 and UsrName='".$_SESSION['uName']."' ".$w." ");
           }

         }



        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }

        if ($_SESSION['uRole'] == 0){
            if(isset($_GET['y'])){
                $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarninfo where 1=1 ".$w." limit " . $lim);
                 }else{
                    $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarninfo where 1=1 limit " . $lim);
                 }

        }else{
             if(isset($_GET['y'])){
                $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarninfo where 1=1 and Status!=2 and  UsrName='".$_SESSION['uName']."' ".$w."  limit " . $lim);
              }else{
                 $data = $db->findAll("select * from " . DB_PREFIX . "analysiswarninfo where 1=1 and Status!=2 and  UsrName='".$_SESSION['uName']."' limit " . $lim);
                }

        }

   

        outData(1, $rel, $data);


    case "process": //修改功能（参数1：ID 参数2：表名称）
            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");

            $venid=$_POST['venid'];
            $status=$_POST['status'];
            $hendleinfo =$_POST['hendleinfo'];
            $hendleinfo = isset($hendleinfo) ? $hendleinfo : "";
            if(empty($venid)) outData(2,"操作有误");
            $arr = array(
             "Status" => $status,
             "Hendle" => $hendleinfo
             );
                $rel = $db->update(DB_PREFIX . "warninglist",$arr, "Id=".$venid);
                if($rel) outData(1,"修改成功",1);       
                outData(2,"修改失败");

    case "Release": //解除功能（参数1：ID 参数2：表名称）
            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");

            $venid=$_POST['venid'];
            $status=$_POST['status'];
            $hendleinfo =$_POST['hendleinfo'];
            if(empty($venid)) outData(2,"操作有误");        
            $rel = $db->delete(DB_PREFIX . "warninglist","Id=".$venid);
               
             if($rel) outData(1,"修改成功",1);       
             outData(2,"修改失败");

    case "ProcessWarn": //分析修改功能（参数1：ID 参数2：表名称）
            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");

            $venid=$_POST['venid'];
            $status=$_POST['status'];
            $hendleinfo =$_POST['hendleinfo'];
            $hendleinfo = isset($hendleinfo) ? $hendleinfo : "";
            if(empty($venid)) outData(2,"操作有误");
            $arr = array(
             "Status" => $status,
             "Hendle" => $hendleinfo
             );
                $rel = $db->update(DB_PREFIX . "analysiswarninfo",$arr, "Id=".$venid);
                if($rel) outData(1,"修改成功",1);
                outData(2,"修改失败");

    case "ReleaseWarn": //分析解除功能（参数1：ID 参数2：表名称）
            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");

            $venid=$_POST['venid'];
            $status=$_POST['status'];
            $hendleinfo =$_POST['hendleinfo'];
            if(empty($venid)) outData(2,"操作有误");
            $rel = $db->delete(DB_PREFIX . "analysiswarninfo","Id=".$venid);
            if($rel) outData(1,"修改成功",1);
            outData(2,"修改失败");

    case "SetCollection": //设置采集（参数1：ID 参数2：表名称）
            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            if(!isset($_SESSION['uRole']))  outData(2,"你还没有权限");

            $hendleinfo =$_POST['hendleinfo'];

            $arr = array(
             "Type" => $hendleinfo
             );
                $rel = $db->update(DB_PREFIX . "collection",$arr, "Id=6");
                if($rel) outData(1,"修改成功",1);
                outData(2,"修改失败");


       /*
         * 增加采集预警信息
         */    
    case "Collection":

          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $usrname = $_POST['usrname'];
        $defaultvalue = trim($_POST['defaultvalue']);
        $Alias = trim($_POST['Alias']);
        $product = trim($_POST['product']);
        $condition = trim($_POST['condition']);
        $warning = trim($_POST['warning']);
        if (empty($Alias)) outData(2, "请填写名称！");
        if($_POST['id']==0){
             $relm = $db->find("select * from ".DB_PREFIX."collectwarn  where Alias='".$Alias."'");
             if($relm){ outData(2, "该名称已被使用！"); }
        }else{
           $relm = $db->find("select * from ".DB_PREFIX."collectwarn  where Alias='".$Alias."' and Id!='".$_POST['id']."'");
           if($relm){ outData(2, "该名称已被使用！"); }
        }
       
        if($condition==1){
        if (empty($defaultvalue)) outData(2, "请填写阀值！");
         }
        if($condition==1){ $condition="不良率"; }else{ $condition="频点和数据数量不匹配"; $defaultvalue=''; }

        $usrname = implode(",",$usrname);//将数组转为字符串
        $arr = array(
            "UsrName" => $usrname,
            "DefaultValue" => $defaultvalue,
            "Alias" => $Alias,
            "Product" => $product,
            "ConditionInfo" => $condition,
            "Level" => $warning
        );

        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX."collectwarn", $arr);
        }else{
            $rel = $db->update(DB_PREFIX."collectwarn", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }

       /*
         * 看板设置列表
         */
   case "Kanbanset":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."kanbanset  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."kanbanset");
        }
        }else{
          $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."kanbanset where UsrName='".$_SESSION['uName']."'");
        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "kanbanset where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "kanbanset  limit " . $lim);
        }
        }else{
           $data = $db->findAll("select * from " . DB_PREFIX . "kanbanset where UsrName='".$_SESSION['uName']."' limit " . $lim);
        }
        outData(1, $rel, $data);

       /*
         * 增加看板设置
         */
    case "Kanbanadd":

        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        //outData(2, "请填写看板名称！");
        $alias = trim($_POST['alias']);
        $mouldshow = trim($_POST['mouldshow']);
        $ipaddress = trim($_POST['ipaddress']);

        if (empty($alias)) outData(2, "请填写看板名称！");
        if (empty($ipaddress)) outData(2, "请填写IP地址！");
        if (!preg_match('/^((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1 -9]?\d))))$/', $ipaddress)) outData(2, "输入的IP地址错误！");

       if($_POST['id']==0){
          $relu = $db->find("select * from ".DB_PREFIX."kanbanset where Alias='".$alias."' ");
          if ($relu) outData(2, "该看板名称已被使用！");
          $relw = $db->find("select * from ".DB_PREFIX."kanbanset where IpAddress='".$ipaddress."' ");
          if ($relw) outData(2, "该IP地址已被使用！");
        }else{
          $relu = $db->find("select * from ".DB_PREFIX."kanbanset where Alias='".$alias."' and Id!= ".$_POST['id']."");
          if ($relu) outData(2, "该看板名称已被使用！");
          $relw = $db->find("select * from ".DB_PREFIX."kanbanset where IpAddress='".$ipaddress."' and Id!= ".$_POST['id']."");
          if ($relw) outData(2, "该IP地址已被使用！");
        }
        $arr = array(
            "Alias" => $alias,
            "Mouldshow" => $mouldshow,
            "IpAddress" => $ipaddress
        );

        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX."kanbanset", $arr);
        }else{
            $rel = $db->update(DB_PREFIX."kanbanset", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


        /*
         * 展示模型管理列表
         */
   case "Mouldshow":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        //if (empty($_SESSION['uRole'])) outData(2, "你还没有权限");
        //if ($_SESSION['uRole'] != 0) outData(2, "你还没有权限");
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."mouldshow  where UsrName like '%" . $_GET['uName'] . "%'");
        }else{
            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."mouldshow");
        }
        }else{
          $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."mouldshow where UsrName='".$_SESSION['uName']."'");
        }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if($_SESSION['uRole'] == 0){
        if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
             $data = $db->findAll("select * from " . DB_PREFIX . "mouldshow where UsrName like '%" . $_GET['uName'] . "%' limit " . $lim);
        }else{
             $data = $db->findAll("select * from " . DB_PREFIX . "mouldshow  limit " . $lim);
        }
        }else{
           $data = $db->findAll("select * from " . DB_PREFIX . "mouldshow where UsrName='".$_SESSION['uName']."' limit " . $lim);
        }
        outData(1, $rel, $data);


        /*
         * 增加展示模型
         */
    case "Mouldshowadd":

          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $alias = trim($_POST['alias']);
        $product = $_POST['product'];
        $type = trim($_POST['type']);
        $content = $_POST['content'];

        if (empty($alias)) outData(2, "请填写模型名称！");
        if($_POST['id']==0){
          $relu = $db->find("select * from ".DB_PREFIX."mouldshow where Alias='".$alias."' ");
          if ($relu) outData(2, "模型名称已被使用！");
        }else{
          $relu = $db->find("select * from ".DB_PREFIX."mouldshow where Alias='".$alias."' and Id!= ".$_POST['id']."");
          if ($relu) outData(2, "模型名称已被使用！");
        }

        if (empty($product)) outData(2, "请选择产品！");
        if (empty($type)) outData(2, "请选择类型！");
        if (empty($content)) outData(2, "请选择展示内容！");


        $product = implode(",",$product);//将数组转为字符串
        $arr = array(
            "Alias" => $alias,
            "Product" => $product,
            "Type" => $type,
            "Content" => $content
        );

        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX."mouldshow", $arr);
        }else{
            $rel = $db->update(DB_PREFIX."mouldshow", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


      //分析列表
   case "Analysislist":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $Alias=isset($_GET['uName']) ? $_GET['uName'] : "";
         if($Alias){
                $Alias=" and Alias like '%" .$Alias. "%'";
            }else{
                 $Alias="";
            }

       $Status=isset($_GET['Status']) ? $_GET['Status'] : "";
         if($Status!=3){
                $Status=" and Status=".$Status."";
            }else{
                 $Status="";
            }

       $Type=isset($_GET['Type']) ? $_GET['Type'] : "";
         if($Type!=3){
                $Type=" and Type=".$Type."";
            }else{
                 $Type="";
            }


        //if($_SESSION['uRole'] == 0){

            $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysis where 1=1 and Type!=2  ".$Alias.$Status.$Type."");
  
       // }else{

         //   $rel = $db->findALL("select count(1) as num from " . DB_PREFIX ."analysis where UsrName='".$_SESSION['uName']."' or UsrRole=2 ".$Alias.$Status.$Type."");

       // }
        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_GET['page']) ? $_GET['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        //if($_SESSION['uRole'] == 0){

             $data = $db->findAll("select * from " . DB_PREFIX . "analysis where 1=1 and Type!=2 ".$Alias.$Status.$Type." limit " . $lim);

       // }else{

           // if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
            // $data = $db->findAll("select * from " . DB_PREFIX . "strategy where UsrName='".$_SESSION['uName']."' or UsrRole=2  like '%" . $_GET['uName'] . "%' limit " . $lim);
       // }else{
           //  $data = $db->findAll("select * from " . DB_PREFIX . "strategy where UsrName='".$_SESSION['uName']."' or UsrRole=2  limit " . $lim);
       // }

        //} 

      outData(1, $rel, $data);


      //分析增加和修改

    case "Analysis":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $alias = trim($_POST['alias']);
        $product = trim($_POST['product']);
        $starttime = trim($_POST['starttime']);
        $endtime = trim($_POST['endtime']);
        $conditioninfo = trim($_POST['conditioninfo']);
        $type = trim($_POST['type']);
        $analysisamount = trim($_POST['analysisamount']);
        
        $oldrel = $db->find("select * from " . DB_PREFIX . "analysis  where Type!=2 and Alias='" . $alias . "' limit 1 ");
        if ($oldrel) outData(2, "该名称已被使用！");
        if($alias==MD5("huahui")){ outData(2, "该名称禁止使用！");  }
        if (empty($alias)) outData(2, "请填写名称！");
        if (empty($starttime)) outData(2, "请填开始时间！");
        if (empty($endtime)) outData(2, "请填写结束时间！");
        $starttime = $starttime."/00/00/00";
        $endtime = $endtime."/24/00/00";
        $arr = array(
            "Alias" => $alias,
            "Product" => $product,
            "Type" => $type,
            "StartTime" => $starttime,
            "EndTime" => $endtime,
            "ConditionInfo" => $conditioninfo,
            "AnalysisAmount" => $analysisamount
        );
        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX . "analysis", $arr);
        }else{
            $rel = $db->update(DB_PREFIX . "analysis", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


      ////分析临时增加

    case "AnalysisSnap":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $id = $_POST['id'];
        $alias = MD5("huahui");
        $product = trim($_POST['product']);
        $starttime = trim($_POST['starttime']);
        $endtime = trim($_POST['endtime']);
        $conditioninfo = trim($_POST['conditioninfo']);
        $type = trim($_POST['type']);
        $analysisamount = trim($_POST['analysisamount']);
        
        $oldrel = $db->find("select * from " . DB_PREFIX . "analysis  where Alias='" . $alias . "' limit 1 ");
        if ($oldrel){
            $db->delete(DB_PREFIX . "analysis", "Alias='" . $alias . "'"); 
            $db->delete(DB_PREFIX . "analysisresult", "Alias='" . $alias . "'");
            $db->delete(DB_PREFIX . "cpk", "Alias='" . $alias . "'");   
        }
        $starttime = $starttime."/00/00/00";
        $endtime = $endtime."/24/00/00";

        $arr = array(
            "Alias" => $alias,
            "Product" => $product,
            "Type" => $type,
            "StartTime" => $starttime,
            "EndTime" => $endtime,
            "ConditionInfo" => $conditioninfo,
            "AnalysisAmount" => $analysisamount,
            "Status" => 1

        );
       
        $rel = $db->save(DB_PREFIX . "analysis", $arr);
        if($id==1){ 
            $db->delete(DB_PREFIX . "analysis", "Alias='" . $alias . "'"); 
            $db->delete(DB_PREFIX . "analysisresult", "Alias='" . $alias . "'");
            $db->delete(DB_PREFIX . "cpk", "Alias='" . $alias . "'");   
        }
        if ($rel) {
            outData(1, "增加成功");
        }else{
            ouaData(2, "增加失败");
        }


      ////数据统计的计数器检查分析结果是否产生

    case "AnalysisTime":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $alias = MD5("huahui");
        $conditioninfo = $_POST['conditioninfo'];
        if($conditioninfo=='CPK'){
           $rel = $db->find("select * from " . DB_PREFIX . "cpk  where Alias='".$alias."' limit 1 ");
        }else{
          $rel = $db->find("select * from " . DB_PREFIX . "analysisresult  where Alias='".$alias."' limit 1 ");
        }
        
    
        if ($rel) {
            outData(1, "检查成功");
        }else{
            ouaData(2, "检查失败");
        }


        /*
         * 选择报表模板列表
         */  
   case "Changetpl":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");

         $analysis = $_POST['analysis'];
         //outData(2, $analysis);
         $data = $db->findAll("select * from " .DB_PREFIX."template where ConditionInfo='".$analysis."'");
        if($data){
           outData(1,888, $data);
         }else{
           outData(2,"没有找到对应的模板！");
         }


        /*
         * 选择报表模板列表
         */  
   case "Cpkfreq":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");

         $uName = trim($_POST['uName']);

         
         $relm = $db->find("select * from ".DB_PREFIX."cpk"); 
         $Value1 = json_decode($relm['Value1'], true);
         $Value2=$Value1['freq'];
         $data = explode(',', $Value2);
        

    if (isset($_POST['uName']) && trim($_POST['uName']) != null) {

        //数组进行模糊查询
         $data1 = array();
        foreach($data as $key=>$values ){
        if (strstr( $values , $uName ) !== false ){
           array_push($data1, $values);
          }
        }
        if($data1){
           outData(1,888, $data1);
         }else{
           outData(2,"搜索不到相关信息！");
         }

        }else{

         if($data){
           outData(1,888, $data);
         }else{
           outData(2,"搜索不到相关信息！");
         }

         }
       /*
         * 汇总图的列表
         */  
   case "Summarylist":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");

         $alias = $_POST['alias'];
         //outData(2, $alias);
         if($alias=="huahui"){
            $alias=MD5($alias);
         }
         $data = $db->findAll("select * from ".DB_PREFIX."analysisresult where Alias='".$alias."'");
        if($data){
            if($alias==MD5("huahui")){
              foreach($data as $key=>$values ){
                  $va=$values['Values1'];
              }
              if($va==$alias){
                    outData(2,"没有分析结果数据！");
                }else{
                    outData(1,888, $data);  
                }
            }else{
             outData(1,888, $data); 
            }
           
         }else{
           outData(2,"没有分析结果数据！");
         }
       /*
         * 增加分析预警信息
         */
    case "AnalysisWarn":

          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");
        $Analysislist = $_POST['Analysislist'];
        $usrname = $_POST['usrname'];
        $defaultvalue = trim($_POST['defaultvalue']);
        $product = trim($_POST['product']);
        $condition = trim($_POST['condition']);
        $warning = trim($_POST['warning']);

        if (empty($defaultvalue)) outData(2, "请填写阀值！");
        if ($Analysislist==0) outData(2, "请选择分析策略！");
        if($condition==1){ $condition="不良率"; }else{ $condition="CPK";  }

        $usrname = implode(",",$usrname);//将数组转为字符串
        $relm = $db->find("select * from ".DB_PREFIX."analysis  where Type!=2 and Id=".$Analysislist."");
        $Analysislist=$relm['Alias'];
        $arr = array(
            "Alias" => $Analysislist,
            "UsrName" => $usrname,
            "DefaultValue" => $defaultvalue,
            "Type" => 0,
            "Product" => $product,
            "ConditionInfo" => $condition,
            "Level" => $warning
        );

        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX."analysiswarn", $arr);
        }else{
            $rel = $db->update(DB_PREFIX."analysiswarn", $arr, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


        /*
         * 增加业务预警的数据格式
         */
    case "WarningData":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $usrname = trim($_POST['usrname']);
        $defaultvalue = trim($_POST['defaultvalue']);
        $complete = trim($_POST['complete']);
        $limit = trim($_POST['limit']);
        $mismatch = trim($_POST['mismatch']);

        if (empty($defaultvalue)) outData(2, "请填写预设值！！");
        if($_POST['id']==0){
        $relb = $db->findALL("select * from " . DB_PREFIX . "warning where UsrName='".$usrname."'");
        if($relb){
            foreach ($relb as $k => $v) {
               if($relb[$k]['Type']==1){ outData(2, "该用户已经增加有数据格式的条件"); }
            }
        }
        }else{
        $relb = $db->findALL("select * from " . DB_PREFIX . "warning where UsrName='".$usrname."' and Id!=".$_POST['id']."");
        if($relb){
            foreach ($relb as $k => $v) {
               if($relb[$k]['Type']==1){ outData(2, "该用户已经增加有数据格式的条件"); }
            }
        }
        }
        $arr1 = array(
            "UsrName" => $usrname,
            "DefaultValue" => $defaultvalue,
            "Type" => 1,
            "Complete" => $complete,
            "Limitinfo" => $limit,
            "Mismatch" => $mismatch
        );
        if($_POST['id']==0){
            $rel = $db->save(DB_PREFIX . "warning", $arr1);
        }else{
            $rel = $db->update(DB_PREFIX . "warning", $arr1, "Id=".$_POST['id']);
        }

        if ($rel) {
            if($_POST['id']==0){
            outData(1, "增加成功");
            }else{
            outData(1, "修改成功");
            }
        }else{
            if($_POST['id']==0){
            ouaData(2, "增加失败");
            }else{
            ouaData(2, "修改失败");
            }

        }


        /*
         * 修改状态
         */
    case "StatusChange":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $id = $_POST['id'];
        $status = $_POST['status'];

        if (empty($id)) outData(2, "参数错误！");

        $arr = array(
            "Status" => $status
        );
        $rel = $db->update(DB_PREFIX . "analysis", $arr, "Id=".$_POST['id']);

        if ($rel) outData(1, "启动成功");
        outData(2, "启动失败");


        /*
         * 数据分析时手动模式下向服务器发出指令
         */
    case "StatusExecute":
          if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
          if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        $id = $_POST['id'];
        if (empty($id)) outData(2, "参数错误！");

        //$host = "192.168.0.138";
        //$port = 5566;
        //$mgtjson= '{"ACTION": "querydashboard","TYPE": 3,"COMPANY": "2002","PARAM": {"PRODUCT":"AJ Flex","SUPP":"ALL","SUBSUPP":"ALL","SITE":"Compal","LINE":"Iqc","STATION":"Station","START_TIME": "2016-3-28 14:46:21","STOP_TIME": "2016-3-28 14:47:15"}}';
        //sendSocketMsg($host, $port, $mgtjson);


        //$rel = $db->update(DB_PREFIX . "analysis", $arr, "Id=".$_POST['id']);

        //if ($rel) outData(1, "启动成功");
        //outData(2, "启动失败");


    /*
		 * 修改KEY
		 */
    case "keyedit":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        if ($_SESSION['uRole'] != 0) {
            $userid = $db->find("select Id from " . DB_PREFIX . "usr  where uName='" . $_SESSION['uName'] . "' limit 1 ");
            $business = $db->find("select CountInfo from " . DB_PREFIX . "usrbusiness  where UsrId='" . $userid["Id"] . "' limit 1 ");
            $countdat = json_decode($business["CountInfo"]);
            $n = count($countdat);
            $k = 0;
            for ($x = 0; $x <= $n - 1; $x++) {
                if ($countdat[$x] == 'TOKEN') {
                    $k = 1;

                }
            }
            if ($k == 0) {
                outData(2, "操作有误");
            }
        }

        $auth = substr($_POST['auth'], 1);
        $arr = explode(",", $auth);
        $authdata = json_encode($arr);
        if (empty($auth)) {
            $authdata = '';
        }
        if (empty($authdata)) outData(2, "请选择功能！");

        $oldpas = $_POST['oldpas'];
        $approval = $_POST['approval'];
        $id = $_POST['id'];
        if (empty($oldpas)) outData(2, "请输入设备的名称！");
        $regtime = date("Y-m-d H:i:s");
        $key = MD5(date("Y-m-d H:i:s"));
        $arr = array(
            "RegTime" => $regtime,
            "Product" => $authdata,
            "SoftInfo" => $oldpas
        );

        $rel = $db->update(DB_PREFIX . "softproduct", $arr, "Id=" . $id);
        if ($approval == 1) {
            $arr2 = array(
                "RegTime" => $regtime,
                "TokeyKey" => $key,
            );
            $rel = $db->update(DB_PREFIX . "token", $arr2, "SoftID=" . $id);
        }

        if ($rel) outData(1, "修改成功");
        outData(2, "修改失败");


   case "showpeo"://显示联系人
        if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
        $name=$_SESSION['uName'];
        if(!$name) outData(2,"还没有登陆");

       $rel=$db->findAll("select count(1) as num from ".DB_PREFIX."emailcontact ");

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);
        $curren = isset($_POST['page']) ? $_POST['page'] : 1;

       $curren = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
       $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

       if ($curren == $rel[1]['sum']) {
           $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
           $lim = (($curren - 1) * $pagepar) . "," . $end;
       }
        if($_SESSION['uRole']==0){
          $data=$db->findAll("select * from ".DB_PREFIX."emailcontact order by id desc limit ".$lim);
        }else{
          $data=$db->findAll("select * from ".DB_PREFIX."emailcontact where UsrName='".$name."' order by id desc limit ".$lim);
        }

        outData(1, $rel, $data);

        /*
        * 邮件审核
        * by zhangping
        */
    case "EmailInfo":

            if(!isset($_SESSION['uName']))  outData(2,"你还没有权限");
            //if($_SESSION['uRole']!=0)  outData(2,"你还没有权限");
            //select * from e_emailpush where AuditStatus=0 order by Id desc
            $sql="select count(1) as num from ".DB_PREFIX."emailpush where AuditStatus=0";
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
                $sql = "select count(1) as num from ".DB_PREFIX."emailpush where AuditStatus=0 and UsrName like '%" . $_GET['uName'] . "%'";
            }

            $rel=$db->findALL($sql);

            $pagepar =10;
            $rel[1]['sum'] =ceil($rel[0]['num']/$pagepar);

            $curren =isset($_GET['page']) ? $_GET['page'] : 1;
            $lim=(($curren-1)*$pagepar).",".$pagepar;

            if($curren==$rel[1]['sum']){
                $end=$rel[0]['num']-(($curren-1)*$pagepar);
                $lim=(($curren-1)*$pagepar).",".$end;
            }

            $sql1="select * from ".DB_PREFIX."emailpush where AuditStatus=0 order by Urgent DESC limit ".$lim;
            if (isset($_GET['uName']) && trim($_GET['uName']) != null) {
                 $sql1 = "select * from ".DB_PREFIX."emailpush where AuditStatus=0 and UsrName like '%" . $_GET['uName'] . "%'  order by Urgent DESC limit ".$lim;
            }

            $data=$db->findAll($sql1);

            outData(1, $rel, $data);

    case "showemail"://显示邮件

        $name = $_SESSION['uName'];
        if (!$name) outData(2, "还没有登陆");
        $str = $_POST['str'];
        $message = "";
        if ($str == 1) {
            $rel = $db->findAll("select count(1) as num  from " . DB_PREFIX . "emailpush where AuditStatus=0 order by Id desc");

        } else if ($str == 2) {
            $rel = $db->findAll("select count(1) as num  from " . DB_PREFIX . "emailpush where AuditStatus=1 and UsrName='" . $_SESSION['uName'] . "' order by Id desc");

        } else if ($str == 3) {
            $rel = $db->findAll("select count(1) as num  from " . DB_PREFIX . "emailtpl order by Id desc  ");
        }

        $pagepar = 10;
        $rel[1]['sum'] = ceil($rel[0]['num'] / $pagepar);

        $curren = isset($_POST['page']) ? $_POST['page'] : 1;
        $lim = (($curren - 1) * $pagepar) . "," . $pagepar;

        if ($curren == $rel[1]['sum']) {
            $end = $rel[0]['num'] - (($curren - 1) * $pagepar);
            $lim = (($curren - 1) * $pagepar) . "," . $end;
        }
        if ($str == 1) {
            $data = $db->findAll("select  *from " . DB_PREFIX . "emailpush where AuditStatus=0 order by Id desc limit " . $lim);
            $rel[2]['url'] = "/email/wemail1.php?id";
        } else if ($str == 2) {
            $data = $db->findAll("select  *from " . DB_PREFIX . "emailpush where AuditStatus=1 and UsrName='" . $_SESSION['uName'] . "' order by Id desc limit " . $lim);
            $rel[2]['url'] = "/email/wemail1.php?new=1&id";
        } else if ($str == 3) {
            $data = $db->findAll("select  *from " . DB_PREFIX . "emailtpl order by Id desc limit " . $lim);
            $rel[2]['url'] = "/email/wemail1.php?new=2&id";
        }

        if ($data) outData(1, $rel, $data);
        outData(2, "没有资料");

    case "addpeo"://添加联系人
        $name=$_SESSION['uName'];
        if(!$name) outData(2,"还没有登陆");
        $arr=$_POST;
        $tmp=trim($_POST["Name"]);
        if (empty($tmp)) outData(2, "请输入姓名！");
        $tmp= $db->find("select Name from " . DB_PREFIX . "emailcontact  where Name='" . trim($_POST["Name"]) . "' limit 1 ");
//        print_r($tmp);
//        exit;
        if ($tmp) outData(2, "该联系人已经存在联系列表上，请重新输入！");
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $arr['Email'])) outData(2, "请输入正确的邮箱地址");
        $Email = $db->find("select Email from " . DB_PREFIX . "emailcontact  where Email='" . trim($arr['Email']) . "' limit 1 ");
        if ($Email) outData(2, "该邮箱已经被使用，请重新输入！");
        $tmp1 = trim($_POST["Dept"]);
        if (empty($tmp1)) outData(2, "请输入部门！");
        $data=$db->save("".DB_PREFIX."emailcontact",$arr);
        if($data) outData(1,'增加成功');
        outData(2,'操作有误');

    case "editpeo"://修改联系人
        $name=$_SESSION['uName'];
        if(!$name) outData(2,"还没有登陆");
        $id=$_POST['id'];
        $tmp=trim($_POST["Name"]);
        if (empty($tmp)) outData(2, "请输入姓名！");
        $tmp1=trim($_POST["Dept"]);
        if (empty($tmp1)) outData(2, "请输入部门！");
        $arr=array(
            "Name" =>$_POST['Name'],
            "Email" =>$_POST['Email'],
            "Dept" =>$_POST['Dept'],
            "Remark" =>$_POST['Remark']
                );
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if(!preg_match($pattern,$arr['Email']) ) outData(2,"收件人请输入正确的邮箱地址");
        $data=$db->update("".DB_PREFIX."emailcontact",$arr,"id=".$id);
        if($data) outData(1,'修改成功');
        outData(2,'操作有误');


   case "sendup"://提交申请

        $name=$_SESSION['uName'];
        if(!$name) outData(2,"还没有登陆");

        require_once ROOT_PATH.'include/class.phpmailer.php';
        if(!trim($_POST["ReceAddr"])){
            outData(2,"请选择收件人");
        }
       if(!trim($_POST["Title"])){
           outData(2,"请输入标题");
       }
       if(!trim($_POST["ContentId"])){
           outData(2,"请输入发送内容");
       }
        $attachs="";

            if($_FILES){
                if($_POST['str']!=3){
                $fil=$_FILES;
                require_once ROOT_PATH.'include/Uploader.class.php';
                 $arrs=array(".png",
                            ".jpg",
                            ".jpeg",
                            ".gif",
                            ".rar"
                );

                       foreach($fil as $k=>$v){

                                $config = array(
                                     "pathFormat" =>"/upload/".md5(rand()),
                                     "maxSize" => 10480000,
                                     "allowFiles" =>$arrs
                                 );

                                $up = new Uploader($k,$config);
                                $img=$up->getFileInfo();
                                $attachs.=$img['url'].";";

                                if($img['state']!="SUCCESS"){
                                    outData(2,$img['state']);
                                }

                        }

                }

            }



            $ho="http://".$_SERVER['HTTP_HOST'];

            $arr=$_POST;
        //  $arr['ContentId']=str_replace("<img src=\"","<img src=\"".$ho,$arr['ContentId']);
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";


            $ReceAddr=$_POST['ReceAddr'];
            $rarr=explode(";",$ReceAddr);

            foreach($rarr as $k=>$v){
                if(!preg_match( $pattern, $v ) ) outData(2,"收件人请输入正确的邮箱地址");
            }
            if(isset($_POST['CopyerAddr'])){
                $CopyerAddr=$_POST['CopyerAddr'];
                $carr=explode(";",$CopyerAddr);
                foreach($carr as $k=>$v){
                    if(!preg_match($pattern, $v) ) outData(2,"抄送人请输入正确的邮箱地址");
                }
            }

                $len=strlen(ROOT_PATH)-1;
                $path=substr(ROOT_PATH,0,$len);



                if($_POST['str']==1){
                //  $Attach=isset($img['url']) ? $path.$img['url'] : '';        //存放附件地址

                //  $arr['Attach']=str_replace("\\","/",$Attach);
                    $Attach=isset($attachs) ? $attachs : '';        //存放附件地址
                    $arr['Attach']=$Attach;

                }
                $arr['PushDate']=date("Y-m-d H:i:s");
                if($_POST['id']){
                    $arr['AuditStatus']=1;
                    $arr['EmailStatus']=1;
                    if($_POST['str']!='3'){

                        if($_POST['getnew']==2){
                            $arr['AuditStatus']=0;
                            $arr['EmailStatus']=0;
                            $data=$db->save("".DB_PREFIX."emailpush",$arr);
                        }
                        $data=$db->update("".DB_PREFIX."emailpush",$arr,"id=".$_POST['id']);

                    }else{
                        $data=$db->update("".DB_PREFIX."emailtpl",$arr,"id=".$_POST['id']);
                    }
                }else{
                    if($_POST['str']!='3'){
                        $data=$db->save("".DB_PREFIX."emailpush",$arr);
                    }else{
                        $data=$db->save("".DB_PREFIX."emailtpl",$arr);
                    }
                }

            if($_POST['str']==2){
                    $id=$_POST['id'];
                    $rel=$db->find("select * from ".DB_PREFIX."emailpush where id=".$id);
                    $sender=$db->find("select * from ".DB_PREFIX."emailconfig limit 1");

                    if($rel['Attach']){
                            $len=strlen($rel['Attach'])-1;
                            $pathlen=substr($rel['Attach'],0,$len);
                            $paths=explode(";",$pathlen);
                    }



                    try {
                        $mail = new PHPMailer(true);
                        $mail->IsSMTP();
                        $mail->CharSet = 'UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码
                        $mail->SMTPAuth = true;                  //开启认证
                        $mail->Port = $sender['PortNum'];
                        $mail->Host = $sender['SendServer'];
                        $mail->Username = $sender['Account'];
                        $mail->Password = $sender['Passwd'];
                        //$mail->IsSendmail(); //如果没有sendmail组件就注释掉，否则出现“Could  not execute: /var/qmail/bin/sendmail ”的错误提示
                        $mail->AddReplyTo($sender['Account'], "华会云数据");//回复地址
                        $mail->From = $sender['Account'];
                        $mail->FromName = "华会云数据";

                            foreach($rarr as $k=>$v){
                               $mail->AddAddress($v);
                            }

                        if(isset($_POST['CopyerAddr'])){
                            foreach($carr as $k=>$v){
                                $mail->addCC($v);
                            }
                        }
                            //$mail->Subject  = $_POST['Title'].date("Y-m-d H:i:s");
                            $mail->Subject  = $_POST['Title'];
                            $mail->Body = "<html>".$arr['ContentId']."</html>";

                            $mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; //当邮件不支持html时备用显示，可以省略
                            $mail->WordWrap   = 80; // 设置每行字符串的长度

                            if($rel['Attach']){
                                foreach($paths as $k=>$v){
                                    $mail->AddAttachment($path.$v);  //可以添加附件
                                }
                            }
                            $mail->IsHTML(true);
                            $mail->Send();

                            $arr3=array(
                                'Auditor'=>$_SESSION['uName']
                            );
                            $data=$db->update("".DB_PREFIX."emailpush",$arr3,"id=".$id);
                            if($_SESSION['uRole']==0){ $url="/vendor/inquire.php";}
                            else{
                                $url="semail.php?dname=comm";
                            }

                            outData(1,"邮件已发送",$url);
                        } catch (phpmailerException $e) {
                            $erro="邮件发送失败：".$e->errorMessage();
                            $arr2=array(
                                'AuditStatus'=>0,
                                'EmailStatus'=>0,
                            );
                            $data=$db->update("".DB_PREFIX."emailpush",$arr2,"id=".$id);

                            outData(2,$erro);
                        }
            }

        if($data) outData(1,"提交成功");
        outData(2,"操作有误");

  case "donwload"://下载文件
        $id=$_GET['id'];
    //  $data=$db->find("select * from e_emailpush where id='".$id."' limit 1");
    //  $fileinfo = pathinfo($data['Attach']);
        $href=$_GET['href'];

        $len=strlen(ROOT_PATH)-1;
        $path=substr(ROOT_PATH,0,$len);

        $Attach=$path.$href;

        $fileinfo = pathinfo($Attach);

        $file=$Attach;
        $len=filesize($file);
        header('Content-Type: application/octet-stream');
        header("Accept-Ranges: bytes");
        header('Content-Length: '.$len);
        header('Content-Disposition: attachment; filename='.$fileinfo['basename']);
        readfile($file);
        exit;
        outData(1,"下载功能","");

    /*
		* 批量删除企业用户的下属用户
		*/
    case "batchDelUsr":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        if ($_SESSION['uRole'] != 0) {
            $userid = $db->find("select Id from " . DB_PREFIX . "usr  where uName='" . $_SESSION['uName'] . "' limit 1 ");
            $data2 = $db->find("select CountInfo,Id from " . DB_PREFIX . "usrbusiness where  UsrId=" . $userid['Id'] . " limit 1");
            $addshow = 0;
            if ($data2) {
                $countdat = json_decode($data2["CountInfo"]);
                $n = count($countdat);
                for ($x = 0; $x <= $n - 1; $x++) {
                    if ($countdat[$x] == 'DEL') {
                        $addshow = 1;
                    }
                }

            }
            if ($addshow == 0) {
                outData(2, "你还没有权限");
            }
        }

        $userId = implode(",", $_POST);
        if (empty($userId)) outData(2, "操作有误");
        $userId = trim($userId, "[]");
        $rel = $db->query("delete  from " . DB_PREFIX . "usr  where Id in ($userId)");
        if ($rel) outData(1, "删除成功", 1);
        outData(2, "删除失败");
    /*
      * 运维管理后台应用程序设置
    *
     */
    case "setExe":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $uName = $_SESSION['uName'];
        if ($_POST) {
//            system("cd /home/deploy;./cloud.sh status >> log.txt",$value);

//            passthru("cd /home/deploy;./cloud.sh status >> log.txt",$value);
//            exec("cd /home/deploy;./cloud.sh status >> log.txt");
//            print_r($value1);
//            print_r($value);
//            $out01 = shell_exec("cd /home/deploy;./cloud.sh stop >> log.txt");
//            shell_exec("cd /home/deploy;./cloud.sh status >> log.txt",$arr);
//            echo '只读取结果的最后一行'.$out01."\n";
//            var_dump(exec("cd /home/deploy;./cloud.sh status >> log.txt",$v,$v1));
//            var_dump($v);
//            var_dump($v1);
//            exit;
            $i = $_POST['i'];
            if($i==1){
                system("cd /home/deploy;./cloud.sh start >> log.txt",$value);
                if ($value==0) outData(1, "开启成功");

            } else if ($i == 3) {
                system("cd /home/deploy;./cloud.sh stop  >> log.txt", $value);
                if ($value == 0) outData(1, "关闭成功");

            }else if($i==2){
                system("cd /home/deploy;./cloud.sh restart >> log.txt",$value);
                outData(1, "重启成功");

            }
            outData(2, "操作失败");
//            if ($rel) outData(1, "修改成功");
        } else {
            outData(2, "修改失败");
        }


    /*
* 批量删除系统日志
*/
    case "batchDelLog":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $sysLogId = implode(",", $_POST);
        if (empty($sysLogId)) outData(2, "操作有误");
        $sysLogId = trim($sysLogId, "[]");
        $rel = $db->query("delete  from " . DB_PREFIX . "syslog  where id in ($sysLogId)");
        //操作日志成功数组
        $array = array(
            "action" => "删除",
            "uname" => $_SESSION['uName'],
            "model" => "运维管理",
            "posttime" => time(),
            "result" => 1,
            "db" => $_REQUEST["dname"],
            "dbtable" => DB_PREFIX . "syslog",
            "ip" => $syslogclass->GetIP()
        );
        $syslogclass->sysevent($array);
        if ($rel) outData(1, "删除成功", 1);
        outData(2, "删除失败");

    /*
* 批量数据清理
*/
    case "batchdeldata":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $resultId = implode(",", $_POST);
        if (empty($resultId)) outData(2, "操作有误");
        $resultId = trim($resultId, "[]");
        $rel = $db->query("delete  from " . DB_PREFIX . "analysisresult  where id in ($resultId)");
        //操作日志成功数组
        $array = array(
            "action" => "删除",
            "uname" => $_SESSION['uName'],
            "model" => "信息管理",
            "posttime" => time(),
            "result" => 1,
            "db" => $_REQUEST["dname"],
            "dbtable" => DB_PREFIX . "syslog",
            "ip" => $syslogclass->GetIP()
        );
        $syslogclass->sysevent($array);
        if ($rel) outData(1, "删除成功", 1);
        outData(2, "删除失败");

    case "usrdel":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        if (!isset($_SESSION['uRole'])) outData(2, "你还没有权限");

        if ($_SESSION['uRole'] != 0) {
            $userid = $db->find("select Id from " . DB_PREFIX . "usr  where uName='" . $_SESSION['uName'] . "' limit 1 ");
            $data2 = $db->find("select CountInfo,Id from " . DB_PREFIX . "usrbusiness where  UsrId=" . $userid['Id'] . " limit 1");
            $addshow = 0;
            if ($data2) {
                $countdat = json_decode($data2["CountInfo"]);
                $n = count($countdat);
                for ($x = 0; $x <= $n - 1; $x++) {
                    if ($countdat[$x] == 'DEL') {
                        $addshow = 1;
                    }
                }


            }
            if ($addshow == 0) {
                outData(2, "你还没有权限");
            }
        }
        $venid = $_POST['venid'];
        if (empty($venid)) outData(2, "操作有误");
        $rel = $db->query("delete  from " . DB_PREFIX . "usr where Id in (" . $venid . ")");
        if ($rel) outData(1, "删除成功", 1);
        outData(2, "删除失败");

    /*
	 	 * 修改企业用户直属用户信息
		*zhangping
		 */
    case "qisUsrEidt":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $uName = $_SESSION['uName'];
        if ($_POST) {
            $Id = $_POST['Id'];
            $arr = array(
                "Email" => $_POST['Email'],
                "Addr" => $_POST['Addr'],
                "uStatus" => $_POST['uStatus'],
                "Mobile" => $_POST['Mobile'],
//					"Corp"=>$_POST['Corp'],
                "level" => $_POST['level']
            );
            $rel = $db->update(DB_PREFIX . "usr", $arr, "Id='" . $Id . "'");
            if ($rel) outData(1, "修改成功");
        } else {
            outData(2, "修改失败");
        }

    /*
		  * 新增企业用户的直属用户
		*zhangping
		 */
    case "qisUsrAdd":
        if (!isset($_SESSION['uName'])) outData(2, "你还没有权限");
        $uName = $_SESSION['uName'];
        $data = array();
        $items = array();
        if ($_POST) {
            //验证用户名
            $data['uName'] = trim($_POST['uName']);//用户名
            if (empty($data['uName'])) outData(2, "用户名不能为空");
            $pattern = '/administrator/i';
            if (preg_match($pattern, $data['uName'])) {
                outData('2', '用户名不可用');
            }
            $pattern = '/admin/i';
            if (preg_match($pattern, $data['uName'])) {
                outData('2', '用户名不可用');
            }
            $pattern = '/test/i';
            if (preg_match($pattern, $data['uName'])) {
                outData('2', '用户名不可用');
            }
            $oldrel = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where uName='" . $data['uName'] . "' limit 1 ");
            if ($oldrel) outData(2, "用户名已被注册过了");


            //验证电话
            $data['Mobile'] = trim($_POST['Mobile']);//电话
            if (empty($data['Mobile'])) outData(2, "手机号码不能为空");
            if (!preg_match('/^(((17[0-9]{1})|(13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/', $data['Mobile'])) {
                outData('2', '请输入正确的手机号码', '');
            }
            $oldrel2 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Mobile='" . $data['Mobile'] . "' limit 1 ");
            if ($oldrel2) outData(2, "该手机已被注册过了");

            //验证邮箱
            $data['Email'] = trim($_POST['Email']);//邮箱
            if (empty($data['Email'])) outData(2, "邮箱不能为空");
            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match($pattern, $data['Email'])) outData(2, "请输入正确的邮箱地址");
            $oldrel3 = $db->find("select uName,uPwd,uRole from " . DB_PREFIX . "usr  where Email='" . $data['Email'] . "' limit 1 ");
            if ($oldrel3) outData(2, "该邮箱已被注册过了");

            $data['uStatus'] = trim($_POST['uStatus']);//状态
            $data['Addr'] = trim($_POST['Addr']);//地址
            $data['level'] = trim($_POST['level']);//等级
            $data['qisId'] = $_SESSION['Id'];//用户的上级ID就是创建这个用户的ID
            $data['uPwd'] = md5(123456);//企业用户创建的用户密码默认为123456；
            $data['uRole'] = 0;//角色默认为0
            $data['RegTime'] = date('Y-m-d H:i:s');//注册时间为当前创建时间
            /*
				 * 没用到事务。。。
				 */
            //插入用户信息到bc_usr表
            $rel = $db->save("bc_usr", $data);
            if ($rel) outData(1, "成功增加用户，用户初始密码为:123456");
        } else {
            outData(2, "修改失败");
        }


    default:
        return;
}

?>