<?php 
// register Services_Hoptoad for php errors and raised exceptions
require_once 'Services/Hoptoad.php';
Services_Hoptoad::installHandlers("YOUR_HOPTOAD_API_KEY");
?>

<?php 
// register Services_Hoptoad for php errors and raised exceptions
// when used in your staging environment
require_once 'Services/Hoptoad.php';
Services_Hoptoad::installHandlers("YOUR_HOPTOAD_API_KEY", 'staging');
?>

<?php 
// register Services_Hoptoad for php errors and raised exceptions
// when used in production and using the Curl transport
require_once 'Services/Hoptoad.php';
Services_Hoptoad::installHandlers("YOUR_HOPTOAD_API_KEY", 'production', 'curl');
?>
 
<?php
// standalone
require_once 'Services/Hoptoad.php';
 
Services_Hoptoad::$apiKey = "YOUR_HOPTOAD_API_KEY";
 
$exception = new Custom_Exception('foobar');
Services_Hoptoad::handleException($exception);
?>
 
<?php
// use Zend_Http_Client
require_once 'Services/Hoptoad.php';
 
Services_Hoptoad::$apiKey = "YOUR_HOPTOAD_API_KEY";
Services_Hoptoad::$client = "zend";
 
$exception = new Custom_Exception('foobar');
Services_Hoptoad::handleException($exception);
?>

