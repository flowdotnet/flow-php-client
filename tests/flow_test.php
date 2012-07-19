<?php

require "../flow.php";

$GLOBALS['example_identity'] = new Flow_Identity(array(
  "alias" => "foobarbaz"
));

$GLOBALS['example_user'] = new Flow_User(array(
  "email" => "foobarbaz@example.com",
  "password" => "p4ssw0rd",
  "defaultIdentity" => $GLOBALS['example_identity']
));

$GLOBALS['example_application'] = new Flow_Application(array(
  "name"  => "flow_php_client_example_application",
  "email" => "jeff@flow.net",
));

$GLOBALS['example_application']->url = "http://flow.net";

$GLOBALS['example_bucket_1'] = new Flow_Flow(array(
  "name" => "flow_php_client_example_bucket_1",
  "path" => "/test/flow_php_client_example_bucket_1"
));

$GLOBALS['example_bucket_2'] = new Flow_Flow(array(
  "name" => "flow_php_client_example_bucket_2",
  "path" => "/test/flow_php_client_example_bucket_2"
));

$GLOBALS['example_bucket_3'] = new Flow_Flow(array(
  "name" => "flow_php_client_example_bucket_3",
  "path" => "/test/flow_php_client_example_bucket_3"
));

$GLOBALS['example_bucket_4'] = new Flow_Flow(array(
  "name" => "flow_php_client_example_bucket_4",
  "path" => "/test/flow_php_client_example_bucket_4"
));

$GLOBALS['example_bucket_5'] = new Flow_Flow(array(
  "name" => "flow_php_client_example_bucket_5",
  "path" => "/test/flow_php_client_example_bucket_5"
));

$GLOBALS['example_drop'] = new Flow_Drop(array(
  "elems" => array(
    "foo" => array("type" => "string", "value" => "Lorem ipsum"),
    "bar" => array("type" => "integer", "value" => 12)
  )
));

class Flow_Marshaler_Test extends PHPUnit_Framework_TestCase {
  public $dumpable = array();

  public $loadable = array();

  public $marshaler = null;

  function __construct() {
    $this->dumpable = array(
      $GLOBALS['example_identity'],
      $GLOBALS['example_user'],
      $GLOBALS['example_application'],
      $GLOBALS['example_bucket_1'],
      $GLOBALS['example_bucket_2'],
      $GLOBALS['example_bucket_3'],
      $GLOBALS['example_bucket_4'],
      $GLOBALS['example_bucket_5'],
      $GLOBALS['example_drop']
    );
  }

  function test_dumps() {
    if($this->marshaler != null) foreach($this->dumpable as $i) {
      $str = $this->marshaler->to_string($i);
      echo $str, "\n";
    }
  }

  /**
   * @depends test_dumps
   */
  function test_loads() {
    if($this->marshaler != null) {
      foreach($this->dumpable as $i) {
        $str = $this->marshaler->to_string($i);
        array_push($this->loadable, $str);
      }

      foreach($this->loadable as $i) {
        $obj = $this->marshaler->from_string($i);
        var_dump($obj);
      }
    }
  }
}

class Flow_Json_Marshaler_Test extends Flow_Marshaler_Test {
  function __construct() {
    parent::__construct();
    $this->marshaler = new Flow_Json_Marshaler();
  }
}

class Flow_Xml_Marshaler_Test extends Flow_Marshaler_Test {
  function __construct() {
    parent::__construct();
    $this->marshaler = new Flow_Xml_Marshaler();
  }
}

class Flow_Rest_Client_Test extends PHPUnit_Framework_TestCase {
  function __construct() {
    parent::__construct();

    $key    = getenv('FLOW_API_KEY');
    $secret = getenv('FLOW_API_SECRET');
    $actor  = getenv('FLOW_API_ACTOR');

    if(empty($key) || empty($secret) || empty($actor)) {
      throw new Exception("FLOW_API_KEY, FLOW_API_SECRET, and FLOW_API_ACTOR must be defined in the system env.");
    }

    $this->client = new Flow_Rest_Client(array('key' => $key, 'secret' => $secret, 'actor' => $actor));
    $this->client->set_log_level(Flow_Logger::DEBUG);
  }

  function test_http_post() {
    $this->client->http_post('/foo', 'test');  
  }

  /**
   * @depends test_http_post
   */
  function test_http_get() {
    $this->client->http_get('/foo');  
  }

  /**
   * @depends test_http_post
   */
  function test_http_put() {
    $this->client->http_put('/foo', 'test');
  }

  /**
   * @depends test_http_post
   */
  function test_http_delete() {
    $this->client->http_delete('/foo');  
    $this->client->http_delete('/foo', 'test');  
  }

  function test_http_options() {
    $this->client->http_request('OPTIONS', '/foo');  
    $this->client->http_request('OPTIONS', '/foo', 'test');  
  }
}
