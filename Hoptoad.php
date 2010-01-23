<?php
if (!class_exists('HTTP_Request2')) require_once('HTTP/Request2.php');
if (!class_exists('HTTP_Request2_Adapter_Socket')) require_once 'HTTP/Request2/Adapter/Socket.php';

class Hoptoad
{
	const NOTIFIER_NAME = 'php-hoptoad-notifier';
	const NOTIFIER_VERSION = '0.2.0';
	const NOTIFIER_URL = 'http://github.com/rich/php-hoptoad-notifier';
	const NOTIFIER_API_VERSION = '2.0';

	/**
	 * Install the error and exception handlers that connect to Hoptoad
	 *
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function installHandlers($api_key=NULL, $environment=NULL)
	{
		if (isset($api_key)) define('HOPTOAD_API_KEY', $api_key);
		if (isset($environment)) define('HOPTOAD_APP_ENVIRONMENT', $environment);

		set_error_handler(array("Hoptoad", "errorHandler"));
		set_exception_handler(array("Hoptoad", "exceptionHandler"));
	}

	/**
	 * Handle a php error
	 *
	 * @param string $code 
	 * @param string $message 
	 * @param string $file 
	 * @param string $line 
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function errorHandler($code, $message, $file, $line)
	{
		if ($code == E_STRICT) return;

		$trace = Hoptoad::tracer();
		Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $message, $file, $line, $trace, null, HOPTOAD_APP_ENVIRONMENT);
	}

	/**
	 * Handle a raised exception
	 *
	 * @param string $exception 
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function exceptionHandler($exception)
	{
		$trace = Hoptoad::tracer($exception->getTrace());

		Hoptoad::notifyHoptoad(HOPTOAD_API_KEY, $exception->getMessage(), $exception->getFile(), $exception->getLine(), $trace, null, HOPTOAD_APP_ENVIRONMENT);
	}

	/**
	 * Pass the error and environment data on to Hoptoad
	 *
	 * @package default
	 * @author Rich Cavanaugh
	 */
	public static function notifyHoptoad($api_key, $message, $file, $line, $trace, $error_class=null, $environment='production')
	{
		array_unshift($trace, "$file:$line");

		$adapter = new HTTP_Request2_Adapter_Socket;
		$req = new HTTP_Request2("http://hoptoadapp.com/notifier_api/v2/notices", HTTP_Request2::METHOD_POST);
		$req->setAdapter($adapter);
		$req->setHeader(array(
			'Accept'				=> 'text/xml, application/xml',
			'Content-Type'	=> 'text/xml'
		));
		$req->setBody(self::buildXmlNotice($api_key, $message, $trace, $error_class, $environment));
		echo $req->send()->getBody();
	}

	/**
	 * Build up the XML to post according to the documentation at:
	 * http://help.hoptoadapp.com/faqs/api-2/notifier-api-v2
	 * @return string
	 * @author Rich Cavanaugh
	 **/
	public static function buildXmlNotice($api_key, $message, $trace, $error_class, $environment, $component='')
	{
		$doc = new SimpleXMLElement('<notice />');
		$doc->addAttribute('version', self::NOTIFIER_API_VERSION);
		$doc->addChild('api-key', $api_key);
		
		$notifier = $doc->addChild('notifier');
		$notifier->addChild('name', self::NOTIFIER_NAME);
		$notifier->addChild('version', self::NOTIFIER_VERSION);
		$notifier->addChild('url', self::NOTIFIER_URL);

		$error = $doc->addChild('error');
		$error->addChild('class', $error_class);
		$error->addChild('message', $message);
		
		$backtrace = $error->addChild('backtrace');
		foreach ($trace as $line) {
			$line_node = $backtrace->addChild('line');
			list($file, $number) = explode(':', $line);
			$line_node->addAttribute('file', $file);
			$line_node->addAttribute('number', $number);
			$line_node->addAttribute('method', '');
		}

		$request = $doc->addChild('request');
		$request->addChild('url', "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
		$request->addChild('component', $component);
		
		if (isset($_REQUEST) && !empty($_REQUEST)) {
			$params = $request->addChild('params');
			foreach ($_REQUEST as $key => $val) {
				$var_node = $params->addChild('var', $val);
				$var_node->addAttribute('key', $key);
			}
		}

		if (isset($_SESSION) && !empty($_SESSION)) {
			$session = $request->addChild('session');
			foreach ($_SESSION as $key => $val) {
				$var_node = $session->addChild('var', $val);
				$var_node->addAttribute('key', $key);			
			}			
		}

		$cgi_data = $request->addChild('cgi-data');
		foreach ($_SERVER as $key => $val) {
			$var_node = $cgi_data->addChild('var', $val);
			$var_node->addAttribute('key', $key);			
		}

		$env = $doc->addChild('server-environment');
		$env->addChild('project-root', $_SERVER['DOCUMENT_ROOT']);
		$env->addChild('environment-name', $environment);

		return $doc->asXML();
	}

	/**
	 * Build a trace that is formatted in the way Hoptoad expects
	 *
	 * @param string $trace 
	 * @return void
	 * @author Rich Cavanaugh
	 */
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
