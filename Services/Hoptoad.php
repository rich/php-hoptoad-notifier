<?php
/**
 * Services_Hoptoad
 *
 * @category error
 * @package  Services_Hoptoad
 * @author   Rich Cavanaugh <no@email>
 * @author   Till Klampaeckel <till@php.net>
 * @license  
 * @version  GIT: $Id$
 * @link     http://github.com/till/php-hoptoad-notifier
 */


/**
 * Services_Hoptoad
 *
 * @category error
 * @package  Services_Hoptoad
 * @author   Rich Cavanaugh <no@email>
 * @author   Till Klampaeckel <till@php.net>
 * @license  
 * @version  Release: @package_version@
 * @link     http://github.com/rich/php-hoptoad-notifier
 * @todo     This class shouldn't be all static.
 * @todo     Add a unit test, or two.
 * @todo     Allow injection of Zend_Http_Client or HTTP_Request2
 * @todo		 Refactor the mess of tracer() and extractLineMethodAndClass()
 */
class Services_Hoptoad
{
	const NOTIFIER_NAME = 'php-hoptoad-notifier';
	const NOTIFIER_VERSION = '0.2.0';
	const NOTIFIER_URL = 'http://github.com/rich/php-hoptoad-notifier';
	const NOTIFIER_API_VERSION = '2.0';

	/**
	 * Report E_STRICT
	 *
	 * @var bool $reportESTRICT
	 * @todo Implement set!
	 */
	protected static $reportESTRICT = false;

	/**
	 * Timeout for cUrl.
	 * @var int $timeout
	 */
	protected static $timeout = 2;

	public static $client = 'pear'; // pear, curl or zend

	/**
	 * @var mixed $apiKey
	 */
	public static $apiKey = null;

	/**
	 * @var string
	 **/
	public static $environment = 'production';

	/**
	 * Install the error and exception handlers that connect to Hoptoad
	 *
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function installHandlers($api_key=NULL, $environment=NULL, $client=NULL)
	{
		if (isset($api_key)) self::$apiKey = $api_key;
		if (isset($environment)) self::$environment = $environment;
		if (isset($client)) self::$client = $client;

		set_error_handler(array("Services_Hoptoad", "errorHandler"));
		set_exception_handler(array("Services_Hoptoad", "exceptionHandler"));
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
		if ($code == E_STRICT && self::$reportESTRICT === false) return;

		$trace = self::tracer();
		self::notify(
			$message, 
			$file, 
			$line, 
			$trace
		);
	}

	/**
	 * Handle a raised exception
	 *
	 * @param Exception $exception 
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function exceptionHandler($exception)
	{
		$trace = self::tracer($exception->getTrace());

		self::notify(
			$exception->getMessage(), 
			$exception->getFile(), 
			$exception->getLine(), 
			$trace, 
			get_class($exception)
		);
	}

	/**
	 * Extract the line, method and class from a trace line
	 * @param string $line
	 * @return array
	 * @author Rich Cavanaugh
	 **/
	public static function extractLineMethodAndClass($line)
	{
		if (preg_match('/^([0-9]+)\sin\sfunction\s(.*)\sin\sclass\s(.*)$/', $line, $matches)) {
			return array($matches[1], $matches[2], $matches[3]);
		} else {
			return array($line, NULL, NULL);
		}
	}

	/**
	 * Pass the error and environment data on to Hoptoad
	 *
	 * @param string $api_key
	 * @param string $message
	 * @param string $file
	 * @param string $line
	 * @param array  $trace
	 * @param mixed  $error_class
	 * @param string $environment
	 * 
	 * @author Rich Cavanaugh
   * @todo   Handle response (e.g. errors)
	 */
	public static function notify($message, $file, $line, $trace, $error_class=null)
	{
		array_unshift($trace, "$file:$line");

		$url = "http://hoptoadapp.com/notifier_api/v2/notices";
		$headers = array(
			'Accept'				=> 'text/xml, application/xml',
			'Content-Type'	=> 'text/xml'
		);
		$body = self::buildXmlNotice($message, $trace, $error_class);

		try {
			$status = call_user_func_array(array('Services_Hoptoad', self::$client . 'Request'), array($url, $headers, $body));
			if ($status != 200) self::handleErrorResponse($status);
		} catch (RuntimeException $e) {
			// TODO do something reasonable with the runtime exception.
			// we can't really throw our runtime exception since we're likely in
			// an exception handler. Punt on this for now and come back to it.
		}
	}

	/**
	 * Build up the XML to post according to the documentation at:
	 * http://help.hoptoadapp.com/faqs/api-2/notifier-api-v2
	 * @return string
	 * @author Rich Cavanaugh
	 **/
	public static function buildXmlNotice($message, $trace, $error_class, $component='')
	{
		$doc = new SimpleXMLElement('<notice />');
		$doc->addAttribute('version', self::NOTIFIER_API_VERSION);
		$doc->addChild('api-key', self::$apiKey);

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
			list($number, $method, $class) = self::extractLineMethodAndClass($number);
			$line_node->addAttribute('number', $number);
			$line_node->addAttribute('method', $method);
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

		if (isset($_ENV) && !empty($_ENV)) {
			foreach ($_ENV as $key => $val) {
				$var_node = $cgi_data->addChild('var', $val);
				$var_node->addAttribute('key', $key);			
			}
		}

		$env = $doc->addChild('server-environment');
		$env->addChild('project-root', $_SERVER['DOCUMENT_ROOT']);
		$env->addChild('environment-name', self::$environment);

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
			if (isset($val['class']) && $val['class'] == 'Services_Hoptoad') continue;

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

	/**
	 * @param mixed $code The HTTP status code from Hoptoad.
	 *
	 * @return void
	 * @throws RuntimeException Error message from hoptoad, translated to a RuntimeException.
	 */
	protected static function handleErrorResponse($code)
	{
		switch ($code) {
		case '403':
			$msg = 'The requested project does not support SSL - resubmit in an http request.';
			break;
		case '422':
			$msg = 'The submitted notice was invalid - check the notice xml against the schema.';
			break;
		case '500':
			$msg = 'Unexpected errors - submit a bug report at http://help.hoptoadapp.com.';
			break;
		default:
			$msg = 'Unknown error code from Hoptoad\'s API: ' . $code;
			break;
		}

		throw new RuntimeException($msg, $code);
	}
	
	/**
	 * Send the request to Hoptoad using PEAR
	 * @return integer
	 * @author Rich Cavanaugh
	 **/
	public static function pearRequest($url, $headers, $body)
	{
		if (!class_exists('HTTP_Request2')) require_once('HTTP/Request2.php');
		if (!class_exists('HTTP_Request2_Adapter_Socket')) require_once 'HTTP/Request2/Adapter/Socket.php';
		
		$adapter = new HTTP_Request2_Adapter_Socket;
		$req = new HTTP_Request2($url, HTTP_Request2::METHOD_POST);
		$req->setAdapter($adapter);
		$req->setHeader($headers);
		$req->setBody($body);
		return $req->send()->getStatus();
	}

	/**
	 * Send the request to Hoptoad using Curl
	 * @return integer
	 * @author Rich Cavanaugh
	 **/
	public static function curlRequest($url, $headers, $body)
	{
		$header_strings = array();
		foreach ($headers as $key => $val) {
			$header_strings[] = "{$key}: {$val}";
		}

		$curlHandle = curl_init();
		curl_setopt($curlHandle, CURLOPT_URL,            $url);
		curl_setopt($curlHandle, CURLOPT_POST,           1);
		curl_setopt($curlHandle, CURLOPT_HEADER,         0);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT,        self::$timeout);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS,     $body);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER,     $header_strings);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		curl_exec($curlHandle);
		$status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		curl_close($curlHandle);
		return $status;
	}

	/**
	 * Send the request to Hoptoad using Zend
	 * @return integer
	 * @author Rich Cavanaugh
	 **/
	public static function zendRequest($url, $headers, $body)
	{
		$header_strings = array();
		foreach ($headers as $key => $val) {
			$header_strings[] = "{$key}: {$val}";
		}

		$client = new Zend_Http_Client($url);
		$client->setHeaders($header_strings);
		$client->setRawData($body, 'text/xml');

		$response = $client->request('POST');

		return $response->getStatus();
	}
}
