#!/usr/bin/php
<?php
/*
    Plugin for icinga2 - compatible with Nagios
    Check update status for OPNsense routers

    Usage: check_opnsense_update -H host -K key -S secret [-s] [-p port] [-d]
           -s use https
           -p port number
           -d debug mode
*/

$path = "/api/core/firmware/status";


define('STATE_OK', 0);
define('STATE_WARNING', 1);
define('STATE_CRITICAL', 2);
define('STATE_UNKNOWN', 3);
define('STATE_DEPENDENT', 4);

function terminate($status, $msg) {
  echo($msg . "\n");
  exit($status);
}

$log = "";
foreach ($argv as $arg) {
  $log = $log . $arg . " ";
}
// syslog(LOG_INFO, $log);

$options = getopt("H:u:K:S:sp:d");
$debug = array_key_exists("d", $options);

if ($debug) print_r($options);
//$debug = true;

if (array_key_exists("s", $options)) {
  $http = "https://";
} else {
  $http = "http://";
}

if (array_key_exists("H", $options)) {
  $host = $options["H"];
} else { terminate(STATE_UNKNOWN, "-H is mandatory");}

$port = "";
if (array_key_exists("p", $options)) {
  $port = ":" . $options["p"];
}

$url = $http . $host . $port . $path;

$key = "";
if (array_key_exists("K", $options)) {
  $key = $options["K"];
} else { terminate(STATE_UNKNOWN, "-K is mandatory");}

$secret = "";
if (array_key_exists("S", $options)) {
  $secret = $options["S"];
} else { terminate(STATE_UNKNOWN, "-S is mandatory");}

if ($debug) echo $url . "\n";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERPWD, "$key:$secret");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$plugin_status = STATE_UNKNOWN;
$plugin_message = "Unknown status";

$return = curl_exec($ch);

if  ($return === false) {
  terminate(STATE_UNKNOWN, "Status fetch failed: ". curl_error($ch));
}
else {
//  echo($return . "\n");
//  file_put_contents("/tmp/aaa.json", $return);
  $resp = json_decode($return, true);
  if (is_null($resp)) {
    terminate(STATE_UNKNOWN, "Status fetch failed: ". $return);
  }
//var_dump($resp);
  if (array_key_exists("upgrade_major_version", $resp) && !empty($resp['upgrade_major_version'])) {
    terminate(STATE_WARNING, "WARNING: Major upgrade available: " . $resp['upgrade_major_version']);
  }
  if ($resp["updates"] == "0") {
    $ver = explode("-", $resp["product_version"]);
    terminate(STATE_OK, "OK: No updates available - Current version: " . $ver[0]);
  }
  else {
    if ($resp["updates"] == "1") {
      terminate(STATE_WARNING, "WARNING: There is " . $resp['updates'] . " update available");
    }
    else {
      terminate(STATE_WARNING, "WARNING: There are " . $resp['updates'] . " updates available");
    }
  }
}

terminate($plugin_status, $plugin_message);
