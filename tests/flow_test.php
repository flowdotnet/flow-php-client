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

$GLOBALS['example_comment'] = new Flow_Comment(array(
  "text" => "Lorem ipsum dolor sit amet"
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
      $GLOBALS['example_comment'],
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

