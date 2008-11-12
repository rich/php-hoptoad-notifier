<?php 
  require_once('Hoptoad.php');
  
  define("HOPTOAD_API_KEY", "YOUR_HOPTOAD_API_KEY");
  set_error_handler(array("Hoptoad", "errorHandler"));
  set_exception_handler(array("Hoptoad", "exceptionHandler"));
