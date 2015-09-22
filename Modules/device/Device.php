<?php
/**
 * Created by PhpStorm.
 * User: Fresh
 * Date: 17/6/2558
 * Time: 14:49
 */
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
class Device
{
    private $mysqli;
    private $rememberme;
    private $enable_rememberme = false;
    private $redis;
    private $log;
    public $object;

    public function __construct($mysqli, $redis)
    {
        //copy the settings value, otherwise the enable_rememberme will always be false.
        global $enable_rememberme;
        $this->enable_rememberme = $enable_rememberme;

        $this->mysqli = $mysqli;

        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function isArrive($SN)
    {
        $result = $this->mysqli->query("SELECT * FROM device WHERE SN = '$SN'");
        if ($result->num_rows < 1) {
            return false;
        }

        return true;
    }

    public function Get($SN)
    {


        $result = $this->mysqli->query("SELECT * FROM device WHERE SN = '$SN'");
        if ($result->num_rows < 1) {
            return null;
        }
        $this->object = $result->fetch_object();

        return $this->object;
    }

    public function find($id)
    {
        $result = $this->mysqli->query("SELECT * FROM device WHERE id = '$id'");
        if ($result->num_rows < 1) {
            return null;
        }
        $this->object = $result->fetch_object();

        return $this->object;
    }

    public function register($name, $SN, $ip, $key)
    {
        if (!$name || !$SN || !$ip) {
            return array('success' => false, 'message' => _("Missing name, SN or ip"));
        }

        $now = $updatetime = date("Y-n-j H:i:s", time());

        if (!$this->mysqli->query("INSERT INTO device(name,SN,type,ip,create_at,update_at)VALUES('$name','$SN',1,'$ip','$now','$now');")) {
            return array('success' => false, 'message' => _("Error creating device"));
        }

        $id = $this->mysqli->insert_id;
        $result = $this->mysqli->query("SELECT * FROM device WHERE id = '$id'");
        $this->object = $result->fetch_object();

        return array('success' => true, 'id' => $id = $this->mysqli->insert_id);
    }

    /*
     * UPDATE `emoncms`.`device`
    SET
    `id` = <{id: }>,
    `name` = <{name: }>,
    `SN` = <{SN: }>,
    `type` = <{type: }>,
    `ip` = <{ip: }>,
    `key` = <{key: }>,
    `description` = <{description: }>,
    `create_at` = <{create_at: }>,
    `update_at` = <{update_at: }>
    WHERE `id` = <{expr}>;

     * */
    public function updateInfo($id, $name, $ip)
    {
        $now = $updatetime = date("Y-n-j H:i:s", time());
        if (!$this->mysqli->query("UPDATE device SET name='$name',ip='$ip', update_at='$now' where id=$id")) {
            return array('success' => false, 'message' => _("Error creating device"));
        }
        return array('success' => true);
    }

    public function getlist()
    {
        return $this->mysql_getlist();
    }

    public function get_liststatus()
    {
          return $this->mysql_getlist_status();
    }
    private function mysql_getlist_status()
    {
        $result = $this->mysqli->query("SELECT device.SN, device.id,device.name,device.ip,device.description,input.id as input_id,input.name as input_name,input.description as input_description,input.value FROM device
                                        inner join input on device.SN = input.nodeid
                                        order by SN");

        $devices = array();
        while ($row = (array)$result->fetch_object())
        {
            if ($row['SN']==null) continue;

            if (!isset($devices[$row['SN']]))
            {
                $devices[$row['SN']] = array(   'SN' =>$row['SN'],
                                                'id'=>$row['id'],
                                                'name'=>$row['name'],
                                                //'type'=>$row['type'],
                                                'ip'=>$row['ip'],
                                                'description'=>$row['description'],
                                                //'time'=>$row['time'],
                                                'input'=>[]
                );
            }
            $devices[$row['SN']]['input'][$row['input_name']] = array(
                'id'=>$row['input_id'],
                'name'=>$row['input_name'],
                //'description'=>$row['input_description'],
                'value'=>$row['value']
            );
        }

        return $devices;

    }

    public function mysql_getlist()
    {

        $devices = array();
        $result = $this->mysqli->query("SELECT * FROM device");
        while ($row = (array)$result->fetch_object())
        {
            $devices[] = $row;
        }
        return $devices;

    }


}