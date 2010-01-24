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

	protected $error_class;
	protected $message;
	protected $file;
	protected $line;
	protected $trace;

	/**
	 * Report E_STRICT
	 *
	 * @var bool $reportESTRICT
	 * @todo Implement set!
	 */
	protected $reportESTRICT;

	/**
	 * Timeout for cUrl.
	 * @var int $timeout
	 */
	protected $timeout;

	public $client; // pear, curl or zend

	/**
	 * @var mixed $apiKey
	 */
	public $apiKey;

	/**
	 * @var string
	 **/
	public $environment;

	/**
	 * Initialize the chosen notifier and install the error
	 * and exception handlers that connect to Hoptoad
	 *
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public static function installHandlers($apiKey=NULL, $environment=NULL, $client=NULL, $class='Services_Hoptoad')
	{
		$hoptoad = new $class($apiKey, $environment, $client);
		$hoptoad->installNotifierHandlers();
	}

	/**
	 * Hook's this notifier to PHP error and exception handlers
	 * @return void
	 * @author Rich Cavanaugh
	 **/
	public function installNotifierHandlers()
	{
		set_error_handler(array($this, "errorHandler"));
		set_exception_handler(array($this, "exceptionHandler"));		
	}

	/**
	 * Initialize the Hoptad client
	 *
	 * @param string $apiKey
	 * @param string $environment
	 * @param string $client
	 * @param string $reportESTRICT
	 * @param int $timeout
	 * @return void
	 * @author Rich Cavanaugh
	 */	
	function __construct($apiKey, $environment='production', $client='pear', $reportESTRICT=false, $timeout=2)
	{
		$this->apiKey = $apiKey;
		$this->environment = $environment;
		$this->client = $client;
		$this->reportESTRICT = $reportESTRICT;
		$this->timeout = $timeout;
		$this->setup();
	}

	/**
	 * A method meant specifically for subclasses to override so they don't need
	 * to handle the constructor
	 * @return void
	 * @author Rich Cavanaugh
	 **/
	public function setup()
	{
		// we don't do anything here in the base class
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
	public function errorHandler($code, $message, $file, $line)
	{
		if ($code == E_STRICT && $this->reportESTRICT === false) return;

		$this->notify($code, $message, $file, $line, debug_backtrace());
	}

	/**
	 * Handle a raised exception
	 *
	 * @param Exception $exception 
	 * @return void
	 * @author Rich Cavanaugh
	 */
	public function exceptionHandler($exception)
	{
		$this->notify(get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTrace());
	}

	/**
	 * Set the values to be used for the next notice sent to Hoptoad
	 * @return void
	 * @author Rich Cavanaugh
	 **/
	public function setParamsForNotify($error_class, $message, $file, $line, $trace, $component=NULL)
	{
		$this->error_class = $error_class;
		$this->message = $message;
		$this->file = $file;
		$this->line = $line;
		$this->trace = $trace;
		$this->component = $component;
	}

	/**
	 * Pass the error and environment data on to Hoptoad
	 *
	 * @param mixed  $error_class
	 * @param string $message
	 * @param string $file
	 * @param string $line
	 * @param array  $trace
	 * @param string $environment
	 *
	 * @author Rich Cavanaugh
	 * @todo   Handle response (e.g. errors)
	 */
	function notify($error_class, $message, $file, $line, $trace, $component=NULL)
	{
		$this->setParamsForNotify($error_class, $message, $file, $line, $trace, $component);

		$url = "http://hoptoadapp.com/notifier_api/v2/notices";
		$headers = array(
			'Accept'				=> 'text/xml, application/xml',
			'Content-Type'	=> 'text/xml'
		);
		$body = $this->buildXmlNotice();

		try {
			$status = call_user_func_array(array($this, $this->client . 'Request'), array($url, $headers, $body));
			if ($status != 200) $this->handleErrorResponse($status);
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
	function buildXmlNotice()
	{
		$doc = new SimpleXMLElement('<notice />');
		$doc->addAttribute('version', self::NOTIFIER_API_VERSION);
		$doc->addChild('api-key', $this->apiKey);

		$notifier = $doc->addChild('notifier');
		$notifier->addChild('name', self::NOTIFIER_NAME);
		$notifier->addChild('version', self::NOTIFIER_VERSION);
		$notifier->addChild('url', self::NOTIFIER_URL);

		$error = $doc->addChild('error');
		$error->addChild('class', $this->error_class);
		$error->addChild('message', $this->message);
		$this->addXmlBacktrace($error);

		$request = $doc->addChild('request');
		$request->addChild('url', $this->request_uri());
		$request->addChild('component', $this->component());
		$request->addChild('action', $this->action());

		if (isset($_REQUEST)) $this->addXmlVars($request, 'params', $this->params());
		if (isset($_SESSION)) $this->addXmlVars($request, 'session', $this->session());
		if (isset($_SERVER)) $this->addXmlVars($request, 'cgi-data', $this->cgi_data());

		$env = $doc->addChild('server-environment');
		$env->addChild('project-root', $this->project_root());
		$env->addChild('environment-name', $this->environment());

		return $doc->asXML();
	}

	/**
	 * Add a Hoptoad var block to the XML
	 * @return void
	 * @author Rich Cavanaugh
	 **/
	function addXmlVars($parent, $key, $source)
	{
		if (empty($source)) return;

		$node = $parent->addChild($key);
		foreach ($source as $key => $val) {
			$var_node = $node->addChild('var', $val);
			$var_node->addAttribute('key', $key);
		}
	}

	/**
	 * Add a Hoptoad backtrace to the XML
	 * @return void
	 * @author Rich Cavanaugh
	 **/
	function addXmlBacktrace($parent)
	{
		$backtrace = $parent->addChild('backtrace');
		$line_node = $backtrace->addChild('line');
		$line_node->addAttribute('file', $this->file);
		$line_node->addAttribute('number', $this->line);

		foreach ($this->trace as $entry) {
			if (isset($entry['class']) && $entry['class'] == 'Services_Hoptoad') continue;

			$line_node = $backtrace->addChild('line');
			$line_node->addAttribute('file', $entry['file']);
			$line_node->addAttribute('number', $entry['line']);
			$line_node->addAttribute('method', $entry['function']);
		}
	}

	/**
	 * params
	 * @return array
	 * @author Scott Woods
	 **/
	function params() {
		return $_REQUEST;
	}

	/**
	 * session
	 * @return array
	 * @author Scott Woods
	 **/
	function session() {
		return $_SESSION;
	}

	/**
	 * cgi_data
	 * @return array
	 * @author Scott Woods
	 **/
	function cgi_data() {
		if (isset($_ENV) && !empty($_ENV)) {
			return array_merge($_SERVER, $_ENV);
		}
		return $_SERVER;
	}

	/**
	 * component
	 * @return mixed
	 * @author Scott Woods
	 **/
	function component() {
		return $this->component;
	}

	/**
	 * action
	 * @return mixed
	 * @author Scott Woods
	 **/
	function action() {
		return '';
	}

	/**
	 * environment
	 * @return string
	 * @author Rich Cavanaugh
	 **/
	function environment() {
		return $this->environment;
	}
	
	/**
	 * project_root
	 * @return string
	 * @author Scott Woods
	 **/
	function project_root() {
		if (isset($_SERVER['DOCUMENT_ROOT'])) {
			return $_SERVER['DOCUMENT_ROOT'];
		} else {
			return dirname(__FILE__);
		}
	}


	/**
	 * get the request uri
	 * @return string
	 * @author Scott Woods
	 **/
	function request_uri() {
		if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
			$protocol = 'https';
		} else {
			$protocol = 'http';
		}
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		$path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$query_string = isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
		return "{$protocol}://{$host}{$path}{$query_string}";
	}

	/**
	 * @param mixed $code The HTTP status code from Hoptoad.
	 *
	 * @return void
	 * @throws RuntimeException Error message from hoptoad, translated to a RuntimeException.
	 */
	protected function handleErrorResponse($code)
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
	public function pearRequest($url, $headers, $body)
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
	public function curlRequest($url, $headers, $body)
	{
		$header_strings = array();
		foreach ($headers as $key => $val) {
			$header_strings[] = "{$key}: {$val}";
		}

		$curlHandle = curl_init();
		curl_setopt($curlHandle, CURLOPT_URL,            $url);
		curl_setopt($curlHandle, CURLOPT_POST,           1);
		curl_setopt($curlHandle, CURLOPT_HEADER,         0);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT,        $this->timeout);
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
	public function zendRequest($url, $headers, $body)
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
