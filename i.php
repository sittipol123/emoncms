<?php



define('EMONCMS_EXEC', 1);
// 1) Load settings and core scripts
require "process_settings.php";
require "core.php";
require "Modules/log/EmonLogger.php";


if(!get('SN'))
{
    echo "Fail";
}

// 2) Database
$mysqli = @new mysqli($server, $username, $password, $database);
if (class_exists('Redis') && $redis_enabled) {
    $redis = new Redis();
    $connected = $redis->connect("127.0.0.1");
    if (!$connected) {
        //echo "Can't connect to redis database, it may be that redis-server is not installed or started see readme for redis installation";
        echo "Fail 501";
        die;
    }
} else {
    $redis = false;
}


if ($mysqli->connect_error) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ($display_errors) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}

if (!$mysqli->connect_error && $dbtest==true) {
    require "Lib/dbschemasetup.php";
    if (!db_check($mysqli,$database)) db_schema_setup($mysqli,load_db_schema(),true);
}

//global $mysqli, $redis, $user, $session, $route, $max_node_id_limit, $feed_settings;
$time = time();
$nodeid=$SN=get('SN');
include "Modules/device/Device.php";
$deviceModel = new Device($mysqli,$redis);


$Objectdevice =  $deviceModel->Get($SN);
if(!$Objectdevice)
{
    if(!(get("IP")&&get("N")))
    {
        echo "F 401-1";
        die();
    }
    $result =  $deviceModel->register(get("N"),$SN,get("IP"),"");
    if($result['success'])
    {
        $Objectdevice =  $deviceModel->Get($SN);

    }else{
        echo "F 401-2";
        die();
    }

}
    if(get("IP")&&get("N"))
    {
        $result = $deviceModel->updateInfo($Objectdevice->id,get("N"),get("IP"));
        if($result["success"])
        {
            //TODO ;
            $Objectdevice =  $deviceModel->find($Objectdevice->id);
        }else{
            //TODO Error do something
        }
    }
    // 3) User sessions
    require "Modules/user/rememberme_model.php";
    $rememberme = new Rememberme($mysqli);

    global $feed,$max_node_id_limit,$feed,$feed_settings;

    include "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis, $feed_settings);
    require("Modules/user/user_model.php");
    $user = new User($mysqli,$redis,$rememberme);
    require "Modules/input/input_model.php"; // 295
    $input = new Input($mysqli,$redis, $feed);
    $valid = true; $error = "";
    require "Modules/input/process_model.php"; // 886

    $process = new Process($mysqli,$input,$feed,$user->get_timezone(1));
    //$nodeid = preg_replace('/[^\w\s-.]/','',get('node'));
    $error = " old".$max_node_id_limit;

    if (!isset($max_node_id_limit))
    {
        $max_node_id_limit = 32;
    }

    $error .= " new".$max_node_id_limit;
    $datain = get('j');
    if ($datain!="") {

        $json = preg_replace('/[^\w\s-.:,]/','',$datain);
        $datapairs = explode(',', $json);

        $csvi = 0;
        for ($i=0; $i<count($datapairs); $i++) {

            $keyvalue = explode(':', $datapairs[$i]);

            if (isset($keyvalue[1])) {
                if ($keyvalue[0]=='') {$valid = false; $error = "Format error, json key missing or invalid character"; }
                if (!is_numeric($keyvalue[1])) {$valid = false; $error = "Format error, json value is not numeric"; }
                $data[$keyvalue[0]] = (float) $keyvalue[1];
            } else {
                if (!is_numeric($keyvalue[0])) {$valid = false; $error = "Format error: csv value is not numeric"; }
                $data[$csvi+1] = (float) $keyvalue[0];
                $csvi ++;
            }
        }
        $userid = 0;
        $dbinputs = $input->get_inputs($userid);
        $tmp = array();

        foreach ($data as $name => $value)
        {
            if (!isset($dbinputs[$nodeid][$name])) {

                $inputid = $input->create_input($userid, $nodeid, $name);
                $dbinputs[$nodeid][$name] = true;
                $dbinputs[$nodeid][$name] = array('id'=>$inputid);
                $input->set_timevalue($dbinputs[$nodeid][$name]['id'],$time,$value);
            } else {
                $inputid = $dbinputs[$nodeid][$name]['id'];
                $input->set_timevalue($dbinputs[$nodeid][$name]['id'],$time,$value);

                if ($dbinputs[$nodeid][$name]['processList']) $tmp[] = array('value'=>$value,'processList'=>$dbinputs[$nodeid][$name]['processList']);
            }
        }

        foreach ($tmp as $i) $process->input($time,$i['value'],$i['processList']);
    }
    else
    {
        $valid = false; $error = "Request contains no data via csv, json or data tag";
    }

    if ($valid)
        $result = 'ok-4';
    else
        $result = "Error: $error\n";

//dump($Objectdevice);
echo $result;
$mysqli->close();