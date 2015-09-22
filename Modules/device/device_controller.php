<?php
/**
 * Created by PhpStorm.
 * User: Fresh
 * Date: 3/7/2558
 * Time: 17:11
 */

defined('EMONCMS_EXEC') or die('Restricted access');

function device_controller()
{

    global $mysqli, $redis, $user, $session, $route, $max_node_id_limit, $feed_settings;

    // There are no actions in the input module that can be performed with less than write privileges
    if (!$session['write']) return array('content'=>false);
    include "Modules/device/Device.php";
    $device = new Device($mysqli,$redis);

    if ($route->format == 'json') {
        if ($route->action == "list") $result = $device->getlist();
        if ($route->action == "getStatus") $result = $device->get_liststatus();
        return array('content'=>$result);
    }


}