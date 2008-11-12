<?php
class Hoptoad
{
  public static function errorHandler($code, $message)
  {
    if ($code == E_STRICT) return;
    
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $message, null, 2);
  }
  
  public static function exceptionHandler($exception)
  {
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $exception->getMessage(), null, 2);
  }
  
  public static function notifyHoptoad($api_key, $message, $error_class=null, $offset=1)
  {
    $lines = array_slice(Hoptoad::tracer(), $offset);

    $req =& new HTTP_Request("http://hoptoadapp.com/notices/", array("method" => "POST", "timeout" => 1));
    $req->addHeader('Accept', 'text/xml, application/xml');
    $req->addHeader('Content-type', 'application/x-yaml');

    if (isset($_SESSION)) {
      $session = array('key' => session_id(), 'data' => $_SESSION);
    } else {
      $session = array();
    }

    $body = array(
      'api_key'         => $api_key,
      'error_class'     => $error_class,
      'error_message'   => $message,
      'backtrace'       => $lines,
      'request'         => array("params" => $_REQUEST, "url" => "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"),
      'session'         => $session,
      'environment'     => $_SERVER
    );

    $req->setBody(Horde_Yaml::dump(array("notice" => $body)));
    $req->sendRequest();
  }
  
  public static function tracer()
  {
    $lines = Array(); 

    $trace = debug_backtrace();
    
    $indent = '';
    $func = '';
    
    foreach($trace as $val) {
      if (!isset($val['file']) || !isset($val['line'])) continue;
      
      $line = $val['file'] . ' on line ' . $val['line'];
    
      if ($func) $line .= ' in function ' . $func;
      $func = $val['function'];
      $lines[] = $line;
    }
    return $lines;
  }  
}