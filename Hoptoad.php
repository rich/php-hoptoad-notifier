<?php
if (!class_exists('HTTP_Request')) require_once('HTTP/Request.php');
if (!class_exists('Horde_Yaml')) require_once('Horde/Yaml.php');
if (!class_exists('Horde_Yaml_Dumper')) require_once('Horde/Yaml/Dumper.php');

class Hoptoad
{
  public static function errorHandler($code, $message, $file, $line)
  {
    if ($code == E_STRICT) return;
    
	  $trace = Hoptoad::tracer();
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $message, $file, $line, $trace, null);
  }
  
  public static function exceptionHandler($exception)
  {
  	$trace = Hoptoad::tracer($exception->getTrace());
  	
    Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $exception->getMessage(), $exception->getFile(), $exception->getLine(), $trace, null);
  }
  
  public static function notifyHoptoad($api_key, $message, $file, $line, $trace, $error_class=null)
  {
    $req =& new HTTP_Request("http://hoptoadapp.com/notices/", array("method" => "POST", "timeout" => 2));
    $req->addHeader('Accept', 'text/xml, application/xml');
    $req->addHeader('Content-type', 'application/x-yaml');

    array_unshift($trace, "$file:$line");
    
    if (isset($_SESSION)) {
      $session = array('key' => session_id(), 'data' => $_SESSION);
    } else {
      $session = array();
    }
        
    $body = array(
      'api_key'         => $api_key,
      'error_class'     => $error_class,
      'error_message'   => $message,
      'backtrace'       => $trace,
      'request'         => array("params" => $_REQUEST, "url" => "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"),
      'session'         => $session,
      'environment'     => $_SERVER
    );
    
    $req->setBody(Horde_Yaml::dump(array("notice" => $body)));
    $req->sendRequest();
  }
  
  public static function tracer($trace = NULL)
  {
    $lines = Array(); 

    $trace = $trace ? $trace : debug_backtrace();
    
    $indent = '';
    $func = '';
    
    foreach($trace as $val) {
      if (isset($val['class']) && $val['class'] == 'Hoptoad') continue;
      
      $file = isset($val['file']) ? $val['file'] : 'Unknown file';
      $line_number = isset($val['line']) ? $val['line'] : '';
      $func = isset($val['function']) ? $val['function'] : '';
      $class = isset($val['class']) ? $val['class'] : '';
      
      $line = $file;
      if ($line_number) $line .= ':' . $line_number;
      if ($func) $line .= ' in function ' . $func;
      if ($class) $line .= ' in class ' . $class;
      
      $lines[] = $line;
    }
    
    return $lines;
  }  
}