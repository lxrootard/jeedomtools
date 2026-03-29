# jeedom-tools
Tools and helper classes for Jeedom plugin development
<br>

![Logo Jeedom](./images/jeedom.png)



## MQTTClient deamon class

Sample MQTTClient usage in your plugin class:
```
use jeedomtools\MQTTClient as myMQTTClient;
$deamon = new myMQTTClient(__CLASS__);
$mqttSettings = '{"host":"192.168.1.1","port":"1883","user":"jeedom","passwd":"xxx","socket_port":"55555","cbclass":"jeeCallback"}'
$deamon->start ($mqttSettings);
$deamon->send ('addTopic', 'mytopic');
deamon->send ('publish', '{"key" : "value"}');
$deamon->stop();
```

jeeCallback handler:
```
$results = json_decode(file_get_contents("php://input"), true);
if (is_array($results)) {
    foreach ($results as $key => $value)
            myplugin::handleMessage(array($key => $value));
}
```
