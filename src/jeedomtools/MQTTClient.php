<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

namespace jeedomtools;
require_once '/var/www/html/core/php/core.inc.php';
//require_once __DIR__  . '/../../../../core/php/core.inc.php';

use \log as log;
use \jeedom as jeedom;
use \cache as cache;
use \config as config;
use \system as system;
use \network as network;
use \com_http as com_http;

class MQTTClient {

  private $_class = null;

  public function __construct($name) {
    $this->_class = $name;
  }

  public function stop() {
//  log::add($this->_class, 'debug', 'stopping '. $this->_class .'d');
    $pid_file = jeedom::getTmpFolder($this->_class) . '/mqttDeamon.pid';
    if (file_exists($pid_file)) {
	$pid = intval(trim(file_get_contents($pid_file)));
	system::kill($pid);
    }
    system::kill($this->_class . 'd.js');
    system::fuserk(config::byKey('socketport', $this->_class));
    cache::delete ($this->_class . '::settings');
    sleep(1);
    log::add($this->_class, 'debug', $this->_class .'d stopped');
  }

  public function start($mqttSettings) {
    if (! is_array($mqttSettings))
	throw new Exception("les settings du deamon doivent être renseignés");
    cache::set($this->_class . '::settings', $mqttSettings);

    log::add($this->_class, 'debug', 'starting ' . $this->_class.'d with settings: ' . json_encode($mqttSettings));
    $cbclass = '/core/php/' . $mqttSettings['cbclass'] . '.php';
    $rootdir = '/var/www/html/plugins/' . $this->_class . '/';
    $cbfile = $rootdir . $cbclass;
    if (file_exists($cbfile))
	$callback = '/plugins/' . $this->_class . $cbclass;
    else
	$callback = '/plugins/' . $this->_class . '/core/class/MQTTClient.php';

    $mqtt_dir = $rootdir .'resources/' . $this->_class .'d';
    chdir($mqtt_dir);
    $cmd = system::getCmdSudo() . ' /usr/bin/node ' . $mqtt_dir . '/' . $this->_class . 'd.js';
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel($this->_class));
    $cmd .= ' --socketport '. $mqttSettings['socket_port'];
    $cmd .= ' --mqtt_server mqtt://' . $mqttSettings['host'] . ':' . $mqttSettings['port'];
    $cmd .= ' --username "'. $mqttSettings['user'] .'"';
    $cmd .= ' --password "' . $mqttSettings['passwd'] .'"';
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'http:127.0.0.1:port:comp') . $callback;
    $cmd .= ' --apikey ' . jeedom::getApiKey($this->_class);
    $cmd .= ' --cycle 1';
    $cmd .= ' --pid ' . jeedom::getTmpFolder($this->_class) . '/mqttDeamon.pid';
    log::add($class, 'info',$this->_class . 'd started with command: ' . $cmd);
    exec($cmd . ' >> ' . log::getPathToLog($this->_class . 'd') . ' 2>&1 &');
  }

  public function isRunning () {
    $pid_file = jeedom::getTmpFolder($this->_class) . '/mqttDeamon.pid';
    $pid = trim(file_get_contents($pid_file));
//    log::add($this->_class, 'debug', '[' . __FUNCTION__ . ']  pid=' . $pid . ' pidfile=' . $pid_file);
    if (file_exists($pid_file) && $pid) {
	if (@posix_getsid((int) $pid))
	    return true;
        else
	    shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
    }
    return false;
  }

  public function send ($action, $topic, $message = '') {
    if (! is_string($message))
	$message = json_encode($message);

    log::add($this->_class, 'debug', '[' . __FUNCTION__ . '] action: ' . $action. ' topic: '. $topic . ' message: ' . $message);
    $mqttSettings = cache::byKey($this->_class . '::settings')->getValue();
    if (! is_array($mqttSettings))
	throw new Exception("les settings du deamon doivent être renseignés");

    if (($action != 'addTopic') && ($action != 'removeTopic') && ($action != 'publish'))
	throw new Exception(__FUNCTION__ .': unrecognized action: ' . $action);

    $port = $mqttSettings['socket_port'];
//  log::add($this->_class, 'debug', '[' . __FUNCTION__ . '] port=' . $port);

    $httpReq = new com_http('http://127.0.0.1:' . $port . '/' . $action . '?apikey=' . jeedom::getApiKey($this->_class));
    $httpReq->setHeader(array('Content-Type: application/json'));
    $httpReq->setPost(json_encode(array('topic' => $topic, 'message' => $message)));
    try {
	$result = json_decode($httpReq->exec(60,3), true);
    } catch(Exception $e) {
        sleep(3);
        $result = json_decode($httpReq->exec(60,3), true);
    }
    log::add($this->_class, 'debug', '[' . __FUNCTION__ . '] result: ' . json_encode($result));
    if ($result['state'] != 'ok')
	throw new Exception(json_encode($result));
  }
}

$message = json_decode(file_get_contents("php://input"), true);
log::add('plugin', 'debug', ' message non traité: ' . json_encode($message));

?>
