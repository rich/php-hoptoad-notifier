
<?php
require_once 'PHPUnit/Framework.php';
require_once 'Services/Hoptoad.php';
 
$_SERVER = array(
  'HTTP_HOST'    => 'localhost',
  'REQUEST_URI'  => '/example.php',
  'HTTP_REFERER' => 'http://localhost/reports/something',
);

$_SESSION = array(
  'var1' => 'val1',
  'var2' => 'val2',
);

$_GET = array(
  'get1' => 'val1',
  'get2' => 'val2',
);

$_POST = array(
  'post1' => 'val3',
  'post2' => 'val4',
);

$_REQUEST = array_merge($_GET, $_POST);

class HoptoadTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
		{
			$this->hoptoad = new Services_Hoptoad('myAPIKey', 'production', 'pear', false, 2);

      $trace = array(
        array(
          'class' => 'Hoptoad',
          'file'  => 'file.php',
          'line'  => 23,
          'function' => 'foo',
        ),
        array(
          'class' => 'Foo',
          'file'  => 'foo.php',
          'line'  => 242,
          'function' => 'foo',
        ),
        array(
          'class' => 'Bar',
          'file'  => 'bar.php',
          'line'  => 42,
          'function' => 'bar',
        ),
      );
      $this->hoptoad->setParamsForNotify('ERROR', 'Something went wrong', 'foo', 23, $trace);
    }
    
    public function testRequestURI()
    {
      // check protocol support
      $this->assertEquals('http://localhost/example.php', $this->hoptoad->request_uri());
      $_SERVER['SERVER_PORT'] = 443;
      $this->assertEquals('https://localhost/example.php', $this->hoptoad->request_uri());
      $_SERVER['SERVER_PORT'] = 80;
      
      // Check query string support.
      $_SERVER['QUERY_STRING'] = 'commit=true';
      $this->assertEquals('http://localhost/example.php?commit=true', $this->hoptoad->request_uri());
      $_SERVER['QUERY_STRING'] = '';
    }
  
    public function testXMLBacktrace()
    {
			$expected_xml = <<<XML
			<root>
				<backtrace>
		<line file="foo" number="23"/>
		<line file="file.php" number="23" method="foo"/>
		<line file="foo.php" number="242" method="foo"/>
		<line file="bar.php" number="42" method="bar"/>
				</backtrace>
			</root>
XML;
			$doc = new SimpleXMLElement('<root />');
			$this->hoptoad->addXmlBacktrace($doc);
			$this->assertXmlStringEqualsXmlString($expected_xml, $doc->asXML());
    }
    
    public function testXMLParams()
    {      
			$expected_xml = <<<XML
			<root>
        <params>
          <var key="get1">val1</var>
          <var key="get2">val2</var>
          <var key="post1">val3</var>
          <var key="post2">val4</var>
				</params>
			</root>
XML;
			$doc = new SimpleXMLElement('<root />');
			$this->hoptoad->addXmlVars($doc, 'params', $_REQUEST);
      $this->assertXmlStringEqualsXmlString($expected_xml, $doc->asXML());
    }

		public function testNotificationBody() 
		{
			$xmllint = popen('xmllint --noout --schema test/hoptoad_2_0.xsd - 2> /dev/null', 'w');
			if ($xmllint) {
				fwrite($xmllint, $this->hoptoad->buildXmlNotice());
				$status = pclose($xmllint);
				$this->assertEquals(0, $status, "XML output did not validate against schema.");
			} else {
				$this->fail("Couldn't run xmllint command.");
			}
		}
    
}
?>

