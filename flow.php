<?php
/**
 * Flow Platform: PHP Client Library
 *
 * Copyright (c) 2010-2011, Flow Search Corporation
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *  * Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 *  * Neither the name of the Flow Platform nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if(!function_exists('curl_init')) {
  throw new Exception('`flow` requires the CURL PHP extension.');
}

if(!function_exists('mb_strlen')) {
  function mb_strlen($str, $encoding='UTF-8') {
    return strlen(utf8_decode($str));
  }
}

/**
 * Simple logging utility to log to a file or stdout
 */
class Flow_Logger {
  const DEBUG   = 1;
  const INFO    = 2;
  const NOTICE  = 3;
  const WARN    = 4;
  const ERROR   = 5;

  private $file, $level, $levels = array();

  static $dateformat = 'Y-m-d H:i:s.u';

  function __construct($filename, $level) {
    $reflector = new ReflectionObject($this);
    $this->file = fopen($filename, 'a');
    $this->level = $level;
    $this->levels = $reflector->getConstants();
  }

  function __destruct() {
    if($this->file) fclose($this->file);
  }

  /**
   * Write to the log by invoking a method with the
   * name of the desired log level
   *
   *    $logger->info('Lorem ipsum');
   *    $logger->debug('Lorem ipsum');
   */
  function __call($method, array $params) {
    $level_key = strtoupper($method);

    if(array_key_exists($level_key, $this->levels)) {
      array_unshift($params, $this->levels[$level_key]);
      return call_user_func_array(array($this, 'log'), $params);
    } else {
      throw new BadMethodCallException();
    }
  }

  /**
   * Write to the log with the specified log level
   */
  function log($level, $msg) {
    if($level >= $this->level) {
      $date_stamp   = '[' . date(self::$dateformat) . ']';
      $level_stamp  = '[' . key($this->level, $this->levels) . ']';

      fwrite($this->file, "$datestamp $levelstamp $msg \n");
    }
  }

  function get_level() {
    return $this->level;
  }

  function set_level($level) {
    $this->level = $level;
  }
}

/**
 * Simple base exception for client library errors
 */
class Flow_Rest_Client_Exception extends Exception {
  function __construct($msg) {
    parent::__construct($msg, 0);
  }
}

/**
 * A handle to the Flow Platform's RESTful API
 *
 * The client is responsible for authenticating 
 * and executing requests (via CURL). 
 */
class Flow_Rest_Client {
  const HOST    = 'api.flow.net';
  const PORT    = 80;

  const MIME_XML  = 'text/xml';
  const MIME_JSON = 'application/json';

  const GET     = 0;
  const POST    = 1;
  const PUT     = 2;
  const DELETE  = 3;

  private $key, $secret, $actor, $logger;

  private $query_params = array(), $headers = array(
    self::GET     => array('Accept' => self::MIME_JSON),
    self::POST    => array('Accept' => self::MIME_JSON, 'Content-type' => self::MIME_JSON),
    self::PUT     => array('Accept' => self::MIME_JSON, 'Content-type' => self::MIME_JSON),
    self::DELETE  => array('Accept' => self::MIME_JSON)
  );

  function __construct(array $opts) {
    if(!isset($opts['key']) || !isset($opts['secret']))
      throw new Flow_Rest_Client_Exception('Key and secret are required.');

    foreach($opts as $k => $v) switch($k) {
      case 'headers':
        $this->$k = array_merge_recursive($this->$k, $v);
        break;
      default:
        $this->$k = $v;
        break;
    }

    $this->logger = new Flow_Logger("php://stdout", Flow_Logger::WARN);
  }

  function set_key($key) {
    $this->key = $key;
    return $this;
  }

  function set_secret($secret) {
    $this->secret = $secret;
    return $this;
  }

  function set_actor($actor) {
    $this->actor = $actor;
    return $this;
  }

  function set_log_file($filename) {
    $level = isset($this->logger) ? $this->logger->get_level() : Flow_Logger::WARN;
    $this->logger = new Flow_Logger($filename, $level);
    return $this;
  }

  function set_log_level($level) {
    $this->logger->set_level($level);
    return $this;
  }

  /**
   * Query parameters to be applied to every request.
   */
  function set_global_query_params(array $query_params) {
    $this->query_params = $query_params;
    return $this;
  }

  /**
   * Headers to be applied to every request
   */
  function set_global_headers(array $headers) {
    $this->headers = $headers;
    return $this;
  }

  /**
   * CURL invocation
   */
  protected function exec_request($uri, array $co=array()) {
    $uri = self::HOST . ':' . self::PORT . $uri;

    switch(TRUE) {
    case $co[CURLOPT_HTTPGET]:
      $method = 'GET';
      break;
    case $co[CURLOPT_POST]:
      $method = 'POST';
      break;
    case $co[CURLOPT_CUSTOMREQUEST] == 'PUT':
      $method = 'PUT';
      break;
    case $co[CURLOPT_CUSTOMREQUEST] == 'DELETE':
      $method = 'DELETE';
      break;
    case isset($co[CURLOPT_CUSTOMREQUEST]):
      $method = $co[CURLOPT_CUSTOMREQUEST];
      break;
    }

    $log_msg .= "\n-- Begin REST Request --\nHTTP 1.1 $method $uri\n";
    $log_msg .= "-H " . implode(",", $co[CURLOPT_HTTPHEADER]) . "\n-d " . $co[CURLOPT_POSTFIELDS];

    $co[CURLOPT_HTTPHEADER][]   = 'Expect:';
    $co[CURLOPT_RETURNTRANSFER] = TRUE;
    $co[CURLOPT_CONNECTTIMEOUT] = 10;
    $co[CURLOPT_TIMEOUT]        = 60;
    $co[CURLOPT_USERAGENT]      = 'flow-php-client_0.1';

    $ch = curl_init($fq_uri);
    curl_setopt_array($ch, $co);
    $response = curl_exec($ch);
    curl_close($ch);

    $log_msg .= "\n$response\n-- End REST Request --\n";
    $this->logger->debug($log_msg);

    return $response;
  }

  /**
   * Prepare a request for invocation
   */
  protected function request($uri, array $query_params=array(), array $headers=array(), $copts) {
    $this->headers_to_CURLOPTS($headers, &$copts);

    $uri_parts  = explode("?", $uri);
    $uri_base   = $uri_parts[0];
    $query_str  = count($uri_parts) > 1 ? $uri_parts[1] : NULL;

    if($query_str != NULL && count($query_params) > 0) {
      $uri = $uri_base . '?' . $this->query_params_to_query_string($query_params) . '&' . $query_str;
    } else if(count($query_params) > 0) {
      $uri = $uri_base . '?' . $this->query_params_to_query_string($query_params);
    }

    return $this->exec_request($uri, $copts);
  }

  /**
   * Generate authentication headers
   */
  protected function credentials() {
    $signature = '';
    $credentials = array(
      'X-Actor'     => $this->actor,
      'X-Key'       => $this->key,
      'X-Timestamp' => time() * 1000
    );

    foreach($credentials as $k => $v) {
      $signature .= sprintf('%s:%s', strtolower($k), $v);
    }

    $credentials['X-Signature'] = sha1($signature .= $this->secret);

    return $credentials;
  }

  protected function headers_to_CURLOPTS(array $headers, array $co) {
    switch(TRUE) {
    case $co[CURLOPT_HTTPGET]:
      $headers += $this->headers[self::GET];
      break;
    case $co[CURLOPT_POST]:
      $headers += $this->headers[self::POST];
      break;
    case $co[CURLOPT_CUSTOMREQUEST] == 'PUT':
      $headers += $this->headers[self::PUT];
      break;
    case $co[CURLOPT_CUSTOMREQUEST] == 'DELETE':
      $headers += $this->headers[self::DELETE];
      break;
    }

    foreach($this->credentials() + $headers as $k => $v)
      $co[CURLOPT_HTTPHEADER][] = "$k:$v";
  }

  protected function query_params_to_query_string(array $params) {
    return http_build_query($params + $this->query_params, NULL, '&');
  }

  /**
   * Execute a HTTP GET request
   */
  function http_get($uri, array $query_params=array(), array $headers=array()) {
    $copts = array(CURLOPT_HTTPGET => TRUE);
    return $this->request($uri, $query_params, $headers, $copts);
  }

  /**
   * Execute a HTTP POST request
   */
  function http_post($uri, $data, array $query_params=array(), array $headers=array()) {
    $copts = array(
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $data
    );

    return $this->request($uri, $query_params, $headers, $copts);
  }

  /**
   * Execute a HTTP PUT request
   */
  function http_put($uri, $data, array $query_params=array(), array $headers=NULL) {
    $copts = array(
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_POSTFIELDS => $data
    );

    return $this->request($uri, $query_params, $headers, $copts);
  }

  /**
   * Execute a HTTP DELETE request
   */
  function http_delete($uri, $data=NULL, array $query_params=array(), array $headers=array()) {
    $copts = array(CURLOPT_CUSTOMREQUEST => 'DELETE');
    return $this->request($uri, $query_params, $headers, $copts);
  }
}

/**
 * A decorator around the Flow_Rest_Client
 * to parse and deliver Flow Platform requests
 * as a specified format
 */
abstract class Flow_Marshaling_Rest_Client {
  protected $mime_type;
  protected $client;
  protected $marshaler;

  function __construct(array $opts) {
    $client = new Flow_Rest_Client($opts);
    $client->set_global_query_params(array('hints' => 1));
    $client->set_global_headers(array(
      $client::DELETE => array('Accept' => $this->mime_type),
      $client::GET    => array('Accept' => $this->mime_type),
      $client::POST   => array(
        'Accept'        => $this->mime_type,
        'Content-type'  => $this->mime_type),
      $client::PUT    => array(
        'Accept'        => $this->mime_type,
        'Content-type'  => $this->mime_type)));

    $this->client = $client;
  }

  /**
   * Proxy method calls to the Flow_Rest_Client
   */
  function __call($method, array $args) {
    return call_user_func_array(array($this->client, $method), $args);
  }

  /**
   * Was the request successful?
   */
  abstract protected function response_ok($response);

  /**
   * Response metadata
   */
  abstract protected function response_head($response);

  /**
   * Response data
   */
  abstract protected function response_body($response);

  /**
   * Convert a value to the client's data format
   */
  function marshal($obj) {
    return $this->marshaler->dumps($obj);
  }

  /**
   * Parse a value from the client's data format
   */
  function unmarshal($str, $obj=NULL) {
    return $this->marshaler->loads($str, $obj);
  }

  /**
   * Execute a HTTP request that will hit a
   * Flow Platform endpoint to create a domain object
   */
  function create($class, $uri, $data) {
    $response = $this->http_post($uri, $data);
    return new $class($this->response_body($response));
  }

  /**
   * Execute a HTTP request that will hit a
   * Flow Platform endpoint to update a domain object
   */
  function update($class, $uri, $data) {
    $response = $this->http_put($uri, $data);
    return new $class($this->response_body($response));
  }

  /**
   * Execute a HTTP request that will hit a
   * Flow Platform endpoint to delete a domain object
   */
  function delete($class, $uri, $data=NULL) {
    $headers = $data != NULL ? array('Content-type' => $this->mime_type) : array();
    $response = $this->http_delete($uri, $data, array(), $headers);
    return $this->response_ok($response);
  }

  /**
   * Execute a HTTP request that will hit a
   * Flow Platform endpoint to retrieve a single
   * domain object
   */
  function find_one($class, $uri) {
    $response = $this->http_get($uri);
    return new $class($this->response_body($response));
  }

  /**
   * Execute a HTTP request that will hit a
   * Flow Platform endpoint to retrieve many domain objects
   */
  function find_many($class, $uri, array $criteria=array(), array $query_params=array()) {
    if(count($criteria)) {
      if(array_key_exists('filter', $query_params))
        $this->logger->warn('`filter` and `criteria` strategies are mutually exclusive');

      $query_params['criteria'] = $this->marshal($criteria);
    }

    $results = $this->response_body($this->http_get($uri, $query_params));

    foreach($results as $i => $result)
      $result[$i] = new $class($result);

    return $results;
  }
}

/**
 * A marshaling Flow_Rest_Client that uses JSON 
 * as its data interchange format
 */
class Flow_Json_Rest_Client extends Flow_Marshaling_Rest_Client {
  function __construct(array $opts) {
    $this->mime_type = Flow_Rest_Client::MIME_JSON;
    $this->marshaler = new Flow_Json_Marshaler();
    parent::__construct($opts);
  }

  protected function response_ok($json_response) {
    return (isset($json_response['head'])
      && isset($json_response['body'])
      && isset($json_response['head']['ok'])
      && $json_response['head']['ok']);
  }

  protected function response_head($json_response_string) {
    $json_response = json_decode($json_response_string, TRUE);

    if(isset($json_response['head'])) {
      return $json_response['head'];
    } else {
      throw new Exception('No response head present.');
    }
  }

  protected function response_body($json_response_string) {
    $json_response = json_decode($json_response_string, TRUE);

    if($this->response_ok($json_response)) {
      return $json_response['body'];
    } else {
      throw new Exception('Response is not ok.');
    }
  }
}

/**
 * A marshaling Flow_Rest_Client that uses XML 
 * as its data interchange format
 */
class Flow_Xml_Rest_Client extends Flow_Marshaling_Rest_Client {
  function __construct(array $opts) {
    $this->mime_type = Flow_Rest_Client::MIME_XML;
    $this->marshaler = new Flow_Xml_Marshaler();
    parent::__construct($opts);
  }

  protected function response_ok($xml_response) {
    // method not yet implemented
  }

  protected function response_head($xml_response) {
    // method not yet implemented
  }

  protected function response_body($xml_response) {
    // method not yet implemented
  }
}

/**
 * A base class for providing a means to take arbitrary objects
 * and serialize and deseriale them to and from a specified
 * data format
 */
abstract class Flow_Marshaler { 
  abstract function from_string($str);
  abstract function to_string($obj);

  protected function is_labelled($obj) {
    return is_array($obj) && count($obj) == 2 && array_key_exists(0, $obj) && is_string($obj[0]);
  }

  protected function is_typed($obj) {
    return is_array($obj) && array_key_exists('type', $obj) && array_key_exists('value', $obj);
  }

  protected function is_primitive($obj) {
    return is_bool($obj) || is_int($obj) || is_float($obj) || is_double($obj) || is_string($obj);
  }

  protected function is_associative(array $a) {
    return (bool) count(array_filter(array_keys($a), 'is_string'));
  }
}

/**
 * Serialize and deserialize arbitrary values to and from JSON
 */
class Flow_Json_Marshaler extends Flow_Marshaler {
  function from_string($str) {
    return $this->from_json(json_decode($str, TRUE));
  }

  function from_json($json) {
    if(func_num_args() == 2)
      $type = func_get_arg(1);

    else if($this->is_typed($json)) {
      $type = $json['type'];
      $json = $json['value'];
    }

    else
      $type = NULL;

    if($type) {
      if(in_array($type, Flow_Domain_Object::$types))
        return Flow_Domain_Object::factory($type, $this->from_array_to_json($json));

      else if(in_array($type, Flow_Domain_Object_Member::$complex_types)) {
        $instance = Flow_Domain_Object_Member::factory(NULL, $type);
        return $instance['value']->from_json($json);
      }

      else if($type == 'map' || $type == 'sortedMap' || $type == 'set' || $type == 'sortedSet' || $type == 'list')
        return $this->from_array_to_json($json);

      else if(is_bool($json) || is_int($json) || is_float($json) || is_double($json) || is_string($json))
        return $json;

      else
        return array('type' => $type, 'value' => $json);
    }
  }

  function to_string($obj) {
    $json = $this->to_json($obj);

    if(is_array($json))
      return json_encode($json);
    else
      return strval($json);
  }

  function to_json($obj) {
    if($obj instanceof Flow_Marshalable)
      return $obj->to_json();

    else if(is_array($obj)) {
      foreach($obj as $k => $v)
        $obj[$k] = $this->to_json($v);

      return $obj;
    }

    else if(is_bool($obj) || is_int($obj) || is_float($obj) || is_double($obj) || is_string($obj))
      return $obj;

    else throw new Exception('Cannot marshal object ' . strval($obj));
  }

  private function from_array_to_json(array $a) {
    $b = array();

    foreach($a as $k => $v)
      $b[$k] = $this->is_typed($v) ? $this->from_json($v) : $v;

    return $b;
  }
}

/**
 * Serialize and deserialize arbitrary values to and from XML
 */
class Flow_Xml_Marshaler extends Flow_Marshaler {
  function from_string($str) {
    $doc = new DOMDocument();
    $doc->loadXML($str);
    return $this->from_xml($doc->documentElement);
  }

  function from_xml($xml) {
    if(func_num_args() == 2)
      $type = func_get_arg(1);
    else if($xml->nodeType == XML_ELEMENT_NODE && $xml->hasAttribute('type'))
      $type = $xml->getAttribute('type');
    else
      $type = NULL;
   

    if($type) {
      if(in_array($type, Flow_Domain_Object::$types)) {
        $members = $this->from_xml($xml, 'map');
        return Flow_Domain_Object::factory($type, $members['value']);
      }

      if(in_array($type, Flow_Domain_Object_Member::$complex_types)) {
        $instance = Flow_Domain_Object_Member::factory(NULL, $type);
        return $instance['value']->from_xml($xml);
      }

      else if($type == 'map' || $type == 'sortedMap')
        return $this->from_xml_to_assoc($xml, $type);

      else if($type == 'set' || $type == 'sortedSet' || $type == 'list')
        return $this->from_xml_to_array($xml, $type);

      else if($type == 'float')
        return (float) $this->concat_text_nodes($xml);

      else if($type == 'integer')
        return (int) $this->concat_text_nodes($xml);

      else if($type == 'boolean')
        return (bool) $this->concat_text_nodes($xml);

      else if($type == 'string')
        return $this->concat_text_nodes($xml);

      else if($type == 'permissions')
        return array();

      else if($xml->hasChildNodes()) {
        $xml->normalize();
        $parse_as_type = $this->has_child_elements($xml) ? 'map' : 'string';
        $value = $this->from_xml($xml, $parse_as_type);

        return Flow_Domain_Object_Member::factory($value, $type);
      }
    }
    
    // if no type attr but has nested elements, parse as assoc array
    else if($xml->hasChildNodes() && $this->has_child_elements($xml))
      return $this->from_xml_to_assoc($xml);

    // if no type attr but only has text nodes, parse as string
    else if($xml->hasChildNodes())
      return $this->from_xml($xml, 'string');
  }

  function to_string($obj) {
    if($obj instanceof Flow_Marshalable)
      return $obj->to_xml()->ownerDocument->saveXML();
    else {
      $doc = new DOMDocument('1.0', 'UTF-8');
      return $this->to_xml($obj, $doc)->saveXML();
    }
  }

  function to_xml($obj, $xml_doc) {
    if($this->is_labelled($obj) && $this->is_typed($obj[1]))
      return $this->to_xml_from_typed_tuple($obj, $xml_doc);

    else if($this->is_labelled($obj))
      return $this->to_xml_from_untyped_tuple($obj, $xml_doc);

    else if($obj instanceof Flow_Marshalable)
      return $xml_doc->importNode($obj->to_xml(), TRUE);

    else if(is_array($obj) && $this->is_associative($obj))
      return $this->to_xml_from_assoc($obj, $xml_doc);

    else if(is_array($obj))
      return $this->to_xml_from_array($obj, $xml_doc);

    else if($this->is_primitive($obj)) {
      $e = $xml_doc->createElement('item');
      $f = is_bool($obj) ? ($obj ? 'true' : 'false') : (string) $obj;
      $e->appendChild($xml_doc->createTextNode($f));

      return $e;
    }
    
    else throw new Exception('Cannot marshal object ' . strval($obj));
  }

  private function from_xml_to_assoc($xml) {
    $type = func_num_args() == 2 ? func_get_arg(1) : 'map';
    $return = array();

    foreach($xml->childNodes as $e) if($e->nodeName != '#text') {
      $k = $e->nodeName;
      $v = $this->from_xml($e);

      if(!in_array($k, $return))
        $return[$k] = $v;
      else {
        if(is_array($return[$k]) && count($return[$k]) == 0) 
          array_push($return[$k], $v);
        else
          $return[$k] = array($return[$k], $v);
      }
    }

    return array('type' => $type, 'value' => $return);
  }

  private function from_xml_to_array($xml) {
    $type = func_num_args() == 2 ? func_get_arg(1) : 'list';
    $values = array();

    foreach($xml->childNodes as $e) if($e->nodeName != '#text')
      array_push($values, $this->from_xml($e));

    return array('type' => $type, 'value' => $values);
  }

  private function to_xml_from_typed_tuple($tuple, $xml_doc) {
    list($label, $struct) = $tuple;
    $type  = $struct['type'];
    $value = $struct['value'];

    $e = $this->to_xml($value, $xml_doc);
    $e = $this->set_tag_name($e, $label);
    $e->setAttribute('type', $type);

    return $e;
  }

  private function to_xml_from_untyped_tuple($tuple, $xml_doc) {
    list($label, $value) = $tuple;

    if($value instanceof Flow_Domain_Object)
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => $value->resolver->type_hint, 'value' => $value)), $xml_doc);

    else if($value instanceof Flow_Domain_Object_Member)
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => $value->type_hint, 'value' => $value)), $xml_doc);

    else if(is_array($value) && $this->is_associative($value)) 
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'map', 'value' => $value)), $xml_doc);

    else if(is_array($value)) 
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'list', 'value' => $value)), $xml_doc);

    else if(is_bool($value))
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'boolean', 'value' => $value)), $xml_doc);

    else if(is_float($value) || is_double($value)) 
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'float', 'value' => $value)), $xml_doc);

    else if(is_int($value))
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'integer', 'value' => $value)), $xml_doc);

    else if(is_string($value))
      return $this->to_xml_from_typed_tuple(
        array($label, array('type' => 'string', 'value' => $value)), $xml_doc);

    else throw new Exception('Cannot convert value of unknown type ' . strval($value));
  }

  private function to_xml_from_assoc(array $a, $xml_doc) {
    $e = $xml_doc->createElement('items');

    foreach($a as $k => $v) {
      $f = $this->to_xml(array($k, $v), $xml_doc);
      $e->appendChild($f);
      $e->setAttribute('type', 'map');
    }

    return $e;
  }

  private function to_xml_from_array(array $a, $xml_doc) {
    $e = $xml_doc->createElement('items');

    foreach($a as $i) {
      $f = $this->to_xml(array('item', $i), $xml_doc);
      $e->appendChild($f);
      $e->setAttribute('type', 'list');
    }

    return $e;
  }

  private function has_child_elements($node) {
    foreach($node->childNodes as $e) if($e->nodeName != "#text")
      return TRUE;
    return FALSE;
  }

  private function set_tag_name($node, $name) {
    $doc = $node->ownerDocument;
    $new_node = $doc->createElement($name);

    foreach($node->attributes as $value)
      $new_node->setAttribute($value->nodeName, $value->value);
           
    foreach($node->childNodes as $child)
      if($child->nodeName == '#text')
        $new_node->appendChild($doc->createTextNode($child->nodeValue));
      else
        $new_node->appendChild($this->set_tag_name($child, $child->nodeName));
           
    return $new_node;
  }

  private function concat_text_nodes($node) {
    $node->normalize();
    $text = '';

    foreach($node->childNodes as $child) if($child->nodeName == '#text')
      $text .= $child->nodeValue;

    return $text;
  }
}

/**
 * A means to provide an arbitrary object
 * with behaviors to serialize and deserialize
 * itself to and from the Flow Platform's accepted
 * data interchange formats
 */
interface Flow_Marshalable {
 function from_json($str);
 function to_json();

 function from_xml($xml_doc);
 function to_xml();
}

/**
 * A flyweight for generating utility classes
 * to generate the proper API endpoint URLs for 
 * Flow Platform domain objects
 */
class Flow_Path_Resolver {
  private function __construct($classname) {
    $this->type_hint = strtolower(str_replace('Flow_', '', $classname));
  }

  function class_path() {
    return "/$this->type_hint";
  }

  function context_path($context=NULL) {
    return $context == NULL
      ? $this->class_path()
      : "/$this->type_hint/$context";
  }

  function instance_path($id) {
    return "/$this->type_hint/$id";
  }

  private static $instances = array();

  static function get_instance($classname) {
    if(!isset(self::$instances[$classname])) {
      $class = __CLASS__;
      $instance = new $class($classname);
      self::$instances[$classname] = $instance;
    }

    return self::$instances[$classname];
  }
}

/**
 * A base class for creating complex members or wrappers
 * around primitive values for use as properties of Flow Domain objects
 */
abstract class Flow_Domain_Object_Member implements Flow_Marshalable {
  static $marshalers = array();

  static $complex_types = array(
    'permissions',
    'applicationTemplate',
    'flowTemplate',
    'trackTemplate',
    'dropTemplate',
    'constraints',
    'constraint'
  );

  static function init_marshalers() {
    if(empty(self::$marshalers)) {
      self::$marshalers['json'] = new Flow_Json_Marshaler();
      self::$marshalers['xml']  = new Flow_Xml_Marshaler();
    }
  }

  static function is_domain_object($value) {
    return $value instanceof Flow_Domain_Object;
  }

  static function is_typed($obj) {
    return is_array($obj) && array_key_exists('type', $obj) && array_key_exists('value', $obj);
  }

  static function factory($value, $type=NULL) {
    if($type == NULL) switch(TRUE) {
      case $this->is_domain_object($value):
        $type = $value->resolver->type_hint;
        break;

      case is_bool($value):
        $type = 'boolean';
        break;

      case is_int($value):
        $type = 'integer';
        break;

      case is_float($value) || is_double($value):
        $type = 'float';
        break;

      case is_string($value):
        $type = 'string';
        break;

      default:
        throw new Exception("Type-hint cannot be inferred from $value.");
    }

    else if(in_array($type, self::$complex_types)) {
      if(is_array($value))
        $value = self::complex_instance_from_array($type, $value);

      else
        $value = self::complex_instance_from_string($type);
    }

    return array('type' => $type, 'value' => $value);
  }

  static function permissions() {
    return self::complex_instance_from_array(new Flow_Permissions(), func_get_args());
  }

  static function applicationTemplate() {
    return self::complex_instance_from_array(new Flow_Application_Template(), func_get_args());
  }

  static function flowTemplate() {
    return self::complex_instance_from_array(new Flow_Flow_Template(), func_get_args());
  }

  static function trackTemplate() {
    return self::complex_instance_from_array(new Flow_Track_Template(), func_get_args());
  }

  static function dropTemplate() {
    return self::complex_instance_from_array(new Flow_Drop_Template(), func_get_args());
  }

  static function constraints() {
    return self::complex_instance_from_array(new Flow_Constraints(), func_get_args());
  }

  static function constraint() {
    return self::complex_instance_from_array(new Flow_Constraint(), func_get_args());
  }

  private static function complex_instance_from_string($type) {
    return call_user_func("Flow_Domain_Object_Member::$type");
  }

  private static function complex_instance_from_array($instance, array $args) {
    call_user_func_array(array($instance, '__construct'), $args);
    return $instance;
  }

  function __construct() {
    self::init_marshalers();
  }
}

/**
 * The permissions member type of a Flow Domain object
 */
class Flow_Permissions extends Flow_Domain_Object_Member {
  static $type_hint = 'permissions';

  function __construct($readers=array(), $writers=array(), $deleters=array()) {
    parent::__construct();
    $this->flags = func_num_args() == 4 ? func_get_arg(3) : array(FALSE, FALSE, FALSE);
    $this->readers  = $readers;
    $this->writers  = $writers;
    $this->deleters = $deleters;
  }

  function add_reader($id) {
    if(!in_array($id, $this->readers)) array_push($this->readers, $id);
  }

  function add_writer($id) {
    if(!in_array($id, $this->writers)) array_push($this->writers, $id);
  }

  function add_deleter($id) {
    if(!in_array($id, $this->deleters)) array_push($this->deleters, $id);
  }

  function rm_reader($id) {
    $index = array_search($id, $this->readers);
    if($index) array_splice($this->readers, $index, 1);
  }

  function rm_writer($id) {
    $index = array_search($id, $this->writers);
    if($index) array_splice($this->writers, $index, 1);
  }

  function rm_deleter($id) {
    $index = array_search($id, $this->deleters);
    if($index) array_splice($this->deleters, $index, 1);
  }

  function set_inclusive_access($read, $write, $delete) {
    $this->flags = array($read, $write, $delete);
  }

  function from_json($json) {
    $marshaler = new Flow_Json_Marshaler();

    if(isset($json['read'])) {
      $this->readers = $marshaler->from_json($json['read']);
      $this->flags[0] = $json['read']['access'];
    }

    if(isset($json['write'])) {
      $this->writers = $marshaler->from_json($json['write']);
      $this->flags[1] = $json['write']['access'];
    }

    if(isset($json['delete'])) {
      $this->deleters = $marshaler->from_json($json['delete']);
      $this->flags[2] = $json['delete']['access'];
    }

    return $this;
  }

  function to_json() {
    $marshaler = new Flow_Json_Marshaler();
    $json = array();

    $json['read'] = array(
      'type'    => 'set',
      'access'  => $marshaler->to_json($this->flags[0]),
      'value'   => $marshaler->to_json($this->readers));

    $json['write'] = array(
      'type'    => 'set',
      'access'  => $marshaler->to_json($this->flags[1]),
      'value'   => $marshaler->to_json($this->writers));

    $json['delete'] = array(
      'type'    => 'set',
      'access'  => $marshaler->to_json($this->flags[2]),
      'value'   => $marshaler->to_json($this->deleters));

    return array('type' => self::$type_hint, 'value' => $json);
  }

  function from_xml($xml) {
    $marshaler = new Flow_Xml_Marshaler();

    foreach($xml->childNodes as $e) if($e->nodeName != '#text') {
      if($e->nodeName == 'read') {
        $this->readers  = $marshaler->from_xml($e);
        $this->flags[0] = $e->getAttribute('access') == 'include' ? TRUE : FALSE;
      }

      if($e->nodeName == 'write') {
        $this->readers  = $marshaler->from_xml($e);
        $this->flags[1] = $e->getAttribute('access') == 'include' ? TRUE : FALSE;
      }

      if($e->nodeName == 'delete') {
        $this->readers  = $marshaler->from_xml($e);
        $this->flags[2] = $e->getAttribute('access') == 'include' ? TRUE : FALSE;
      }
    }

    return $this;
  }

  function to_xml() {
    $marshaler = new Flow_Xml_Marshaler();
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('permissions', NULL);
    $root->setAttribute('type', self::$type_hint);

    $r_node = $marshaler->to_xml(array('read', array('type' => 'set', 'value' => $this->readers)), $doc);
    $r_node->setAttribute('access', $this->flags[0] ? 'include' : 'exclude');

    $w_node = $marshaler->to_xml(array('read', array('type' => 'set', 'value' => $this->writers)), $doc);
    $w_node->setAttribute('access', $this->flags[0] ? 'include' : 'exclude');

    $d_node = $marshaler->to_xml(array('read', array('type' => 'set', 'value' => $this->deleters)), $doc);
    $d_node->setAttribute('access', $this->flags[0] ? 'include' : 'exclude');

    $root->appendChild($r_node);
    $root->appendChild($w_node);
    $root->appendChild($d_node);

    return $root;
  }
}

class Flow_Application_Template extends Flow_Domain_Object_Member {
  static $type_hint = 'applicationTemplate';

  function __construct($flowTemplates=array(), $trackTemplates=array()) {
    parent::__construct();
    $this->userFlows  = $flowTemplates;
    $this->userTracks = $trackTemplates;
  }

  function from_json($json) {
    $marshaler = self::$marshalers['json'];
    return $this;
  }

  function to_json() {
    $marshaler = self::$marshalers['json'];
    $json = array();

    $json['userFlows']  = $marshaler->to_json(array('type' => 'list', 'value' => $this->userFlows));
    $json['userTracks'] = $marshaler->to_json(array('type' => 'list', 'value' => $this->userTracks));

    return $json;
  }

  function from_xml($xml) {
    $marshaler = self::$marshalers['xml'];
    return $this;
  }

  function to_xml() {
    $marshaler = self::$marshalers['xml'];
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('applicationTemplate', NULL);
    $root->setAttribute('type', self::$type_hint);

    $flows_with_type  = array('type' => 'list', 'value' => $this->userFlows);
    $flows_with_label = array('userFlows', $flows_with_type);
    $flows_as_xml     = $marshaler->to_xml($flows_with_label, $doc);

    $tracks_with_type   = array('type' => 'list', 'value' => $this->userTracks);
    $tracks_with_label  = array('userTracks', $tracks_with_type);
    $tracks_as_xml      = $marshaler->to_xml($tracks_with_label, $doc);

    $root->appendChild($flows_as_xml);
    $root->appendChild($tracks_as_xml);

    return $root;
  }
}

class Flow_Flow_Template extends Flow_Domain_Object_Member {
  static $type_hint = 'flowTemplate';

  function __construct($name, $displayName, $dropTemplates=array()) {
    parent::__construct();
    $this->name = $name;
    $this->displayName = $displayName;
    $this->dropElements = $dropTemplates;
  }

  function from_json($json) {
    $marshaler = self::$marshaler['json'];
    return $this;
  }

  function to_json() {
    $marshaler = self::$marshaler['json'];
    $json = array();
    return $json;
  }

  function from_xml($xml) {
    $marshaler = self::$marshaler['xml'];
    return $this;
  }

  function to_xml() {
    $marshaler = self::$marshalers['xml'];
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('flowTemplate', NULL);
    $root->setAttribute('type', self::$type_hint);

    $name_node = array('name', array('type' => 'string', 'value' => $this->name));
    $display_name_node = array('name', array('type' => 'string', 'value' => $this->displayName));

    $drops_with_type = array('type' => 'list', 'value' => $this->dropElements);
    $drops_with_label = array('dropElements', $drops_with_type);
    $drops_as_xml = $marshaler->to_xml($drops_with_label, $doc);

    $root->appendChild($name_node);
    $root->appendChild($display_name_node);
    $root->appendChild($drops_as_xml);

    return $root;
  }
}

class Flow_Track_Template extends Flow_Domain_Object_Member {
  static $type_hint = 'trackTemplate';

  function __construct($to, $from) {
    parent::__construct();
    $this->to = $to;
    $this->from = $from;
  }

  function from_json($json) {
    return $this;
  }

  function to_json() {
    $json = array();
    return $json;
  }

  function from_xml($xml) {
    return $this;
  }
  
  function to_xml() {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('trackTemplate', NULL);
    $root->setAttribute('type', self::$type_hint);

    return $root;
  }
}

class Flow_Drop_Template extends Flow_Domain_Object_Member {
  static $type_hint = 'dropTemplate';

  function __construct() {
    parent::__construct();
  }

  function from_json($json) {
    return $this;
  }
  
  function to_json() {
    $json = array();
    return $json;
  }

  function from_xml($xml) {
    return $this;
  }

  function to_xml() {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('dropTemplate', NULL);
    $root->setAttribute('type', self::$type_hint);

    return $root;
  }
}

class Flow_Constraints extends Flow_Domain_Object_Member {
  static $type_hint = 'constraints';

  function __construct($constraints=array()) {
    parent::__construct();
    $this->constraints = $constraints;
  }

  function from_json($json) {
    return $this;
  }
 
  function to_json() {
    $json = array();
    return $json;
  }

  function from_xml($xml) {
    return $this;
  }

  function to_xml() {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('constraints', NULL);
    $root->setAttribute('type', self::$type_hint);

    return $root;
  }
}

class Flow_Constraint extends Flow_Domain_Object_Member {
  static $type_hint = 'constraint';

  function __construct() {
    parent::__construct();
  }

  function from_json($json) {
    return $this;
  }
 
  function to_json() {
    $json = array();
    return $json;
  }

  function from_xml($xml) {
    return $this;
  }

  function to_xml() {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement('constraint', NULL);
    $root->setAttribute('type', self::$type_hint);

    return $root;
  }
}

/**
 * The base class for all top-level Flow Platform domain objects
 */
abstract class Flow_Domain_Object implements Flow_Marshalable {
  public $resolver;

  protected $members = array();

  function __construct(array $members=NULL) {
    $this->resolver = Flow_Path_Resolver::get_instance(get_class($this));
    $this->members += array(
      'id'            => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'creator'       => Flow_Domain_Object_Member::factory(NULL, 'map'),
      'creationDate'  => Flow_Domain_Object_Member::factory(NULL, 'date'),
      'lastEditDate'  => Flow_Domain_Object_Member::factory(NULL, 'date'));

    if($members != NULL) $this->set_members($members);
  }

  function __get($key) {
    if(array_key_exists($key, $this->members))
      return $this->members[$key]['value'];
    else
      return NULL;
  }

  function __set($key, $value) {
    if(array_key_exists($key, $this->members))
      if(Flow_Domain_Object_Member::is_typed($value))
        $this->members[$key] = $value;
      else
        $this->members[$key]['value'] = $value;
  }

  function get_members() {
    return $this->members;
  }

  function set_members(array $members) {
    foreach($members as $k => $v) $this->$k = $v; 
  }

  function uid() {
    return $this->id;
  }

  function from_json($str) {
    $this->set_members(json_decode($str, TRUE));
    return $this;
  }

  function to_json() {
    $marshaler = new Flow_Json_Marshaler();
    $json = array();

    foreach($this->members as $k => $v) if($v['value'] != NULL) {
      if($v['value'] instanceof Flow_Marshalable)
        $json[$k] = $marshaler->to_json($v['value']);
      else
        $json[$k] = $marshaler->to_json($v);
    }

    return array('type' => $this->resolver->type_hint, 'value' => $json);
  }

  function from_xml($node) {
    $marshaler = new Flow_Xml_Marshaler();

    foreach($node->childNodes as $e)
      if($e->nodeName != '#text' && array_key_exists($e->tagName, $this->members))
        $this->$e->nodeName = $marshaler->from_xml($e);

    return $this;
  }

  function to_xml() {
    $marshaler = new Flow_Xml_Marshaler();
    $doc = new DOMDocument('1.0', 'UTF-8');
    $root = $doc->createElement($this->resolver->type_hint, NULL);
    $root->setAttribute('type', $this->resolver->type_hint);
    $doc->appendChild($root);

    foreach($this->members as $k => $v) if($v['value'] != NULL)
      $root->appendChild($marshaler->to_xml(array($k, $v), $doc));

    return $root;
  }

  function save(Flow_Marshaling_Rest_Client $client) {
    $uid = $this->uid();
    $id = $this->id;
    $this->id = NULL;
    $data = $client->marshal($this);
    $this->id = $id;

    if($uid == NULL) {
      $uri = $this->resolver->class_path();
      $new = $client->create(get_class($this), $uri, $data);
    } else {
      $uri = $this->resolver->instance_path($uid);
      $new = $client->update(get_class($this), $uri, $data); 
    }

    $this->set_members($new->get_members());
    return $this;
  }

  function update(Flow_Marshaling_Rest_Client $client, $member=NULL) {
    $uid = $this->uid();

    if($uid == NULL) throw new Exception('Cannot update Domain Object without id member set.'); 

    if($member == NULL) {
      return $this->save($client);
    } else {
      $uri = $this->resovler->instance_path($uid) . "/$member";
      $data = $client->marshal($this);
      $new = $client->update(get_class($this), $uri, $data);
      $this->set_members($new->get_members());
      return $this;
    }
  }

  function delete(Flow_Marshaling_Rest_Client $client, $member=NULL) {
    $uid = $this->uid();

    if($uid == NULL) throw new Exception('Cannot delete Domain Object without id member set.');

    if($member == NULL) {
      $uri = $this->resolver->instance_path($uid);
      return $client->delete(get_class($this), $uri);
    } else {
      $uri = $this->resolver->instance_path($uid) + "/$member";
      $data = $client->marshals($this);
      return $client->delete(get_class($this), $uri, $data);
    }
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type) {
    $resolver = Flow_Path_Resolver::get_instance($return_type);

    if(isset($argv['id'])) {
      if(isset($argv['flowId'])) {
        $id = $argv['flowId'] . "/" . $argv['id'];
        $uri = $resolver->instance_path($id);
      } else {
        $uri = $resolver->instance_path($argv['id']);
      }

      return $client->find_one($return_type, $uri);
    } else {
      if(isset($argv['flowId'])) {
        $uri = $resolver->context_path($argv['flowId']);
        unset($argv['flowId']);
      } else {
        $uri = $resolver->class_path();
      }

      $query_opts_keys  = array('filter', 'start', 'limit', 'sort', 'order');
      $query_opts       = array();
      $criteria         = array();
      $typed_criteria   = array();

      foreach($argv as $k => $v)
        in_array($k, $query_opts_keys) ? $query_opts[$k] = $v : $criteria[$k] = $v;

      $prototype = new $return_type($criteria);

      foreach($prototype->get_members() as $k => $v) if(isset($criteria[$k]))
        $typed_criteria[$k] = $v;

      return $client->find_many($return_type, $uri, $typed_criteria, $query_opts);
    }
  }

  static $types = array('application', 'flow', 'comment', 'drop', 'file', 'group', 'identity', 'track', 'user');

  static function factory($type, $data=array()) {
    $classname = 'Flow_' . ucfirst($type);
    return new $classname($data);
  }
}

class Flow_Application extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $permissions = new Flow_Permissions();
    $permissions->set_inclusive_access(TRUE, TRUE, TRUE);

    $this->members += array(
      'name'            => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'displayName'     => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'description'     => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'email'           => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'url'             => Flow_Domain_Object_Member::factory(NULL, 'url'),
      'icon'            => Flow_Domain_Object_Member::factory(NULL, 'url'),
      'isDiscoverable'  => Flow_Domain_Object_Member::factory(NULL, 'boolean'),
      'isInviteOnly'    => Flow_Domain_Object_Member::factory(NULL, 'boolean'),

      'applicationTemplate' => Flow_Domain_Object_Member::factory(NULL, 'applicationTemplate'),
      'flowRefs'            => Flow_Domain_Object_Member::factory(NULL, 'set'),
      'permissions'         => array('type' => 'permissions', 'value' => $permissions));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Flow extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'name'            => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'description'     => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'path'            => Flow_Domain_Object_Member::factory(NULL, 'path'),
      'filter'          => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'location'        => Flow_Domain_Object_Member::factory(NULL, 'location'),
      'local'           => Flow_Domain_Object_Member::factory(NULL, 'boolean'),
      'template'        => Flow_Domain_Object_Member::factory(NULL, 'constraints'),
      'icon'            => Flow_Domain_Object_Member::factory(NULL, 'url'),
      'permissions'     => Flow_Domain_Object_Member::factory(NULL, 'permissions'),
      'dropPermissions' => Flow_Domain_Object_Member::factory(NULL, 'permissions'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Comment extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'title'       => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'description' => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'text'        => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'flowId'      => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'dropId'      => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'parentId'    => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'topParentId' => Flow_Domain_Object_Member::factory(NULL, 'id'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Drop extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'flowId'    => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'path'      => Flow_Domain_Object_Member::factory(NULL, 'path'),
      'elems'     => Flow_Domain_Object_Member::factory(NULL, 'map'));

    parent::__construct($members);
  }

  function uid() {
    return $this->flowId != NULL && $this->id != NULL ? "$this->flowId/$this->id" : NULL;
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_File extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'name'      => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'mimeType'  => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'contents'  => Flow_Domain_Object_Member::factory(NULL, 'bytes'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Group extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'name'                => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'displayName'         => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'identities'          => Flow_Domain_Object_Member::factory(NULL, 'list'),
      'permissions'         => Flow_Domain_Object_Member::factory(NULL, 'permissions'),
      'identityPermissions' => Flow_Domain_Object_Member::factory(NULL, 'permissions'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Identity extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'firstName'   => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'lastName'    => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'alias'       => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'avatar'      => Flow_Domain_Object_Member::factory(NULL, 'fileRef'),
      'groupIds'    => Flow_Domain_Object_Member::factory(NULL, 'list'),
      'userId'      => Flow_Domain_Object_Member::factory(NULL, 'id'),
      'appIds'      => Flow_Domain_Object_Member::factory(NULL, 'list'),
      'permissions' => Flow_Domain_Object_Member::factory(NULL, 'permissions'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_Track extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'from'              => Flow_Domain_Object_Member::factory(NULL, 'path'),
      'to'                => Flow_Domain_Object_Member::factory(NULL, 'path'),
      'filterString'      => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'transformFunction' => Flow_Domain_Object_Member::factory(NULL, 'string'),
      'permissions'       => Flow_Domain_Object_Member::factory(NULL, 'permissions'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

class Flow_User extends Flow_Domain_Object {
  function __construct(array $members=NULL) {
    $this->members += array(
      'email'           => Flow_Domain_Object_Member::factory(NULL, 'email'),
      'password'        => Flow_Domain_Object_Member::factory(NULL, 'password'),
      'defaultIdentity' => Flow_Domain_Object_Member::factory(NULL, 'identity'),
      'permissions'     => Flow_Domain_Object_Member::factory(NULL, 'permissions'));

    parent::__construct($members);
  }

  static function find(Flow_Marshaling_Rest_Client $client, array $argv, $return_type=NULL) {
    return parent::find($client, $argv, __CLASS__);
  }
}

