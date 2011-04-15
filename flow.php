<?php

if(!function_exists('curl_init')) {
  throw new Exception('Flow needs the CURL PHP extension.');
}

class Flow_Exception extends Exception {
  function __construct($msg) {
    parent::__construct($msg, 0);
  }
}

class Flow {
  const HOST    = 'api.flow.net';
  const FS_HOST = 'file.flow.net';
  const PORT    = 80;

  const MIME_XML  = 'text/xml';
  const MIME_JSON = 'application/json';

  const GET     = 0;
  const POST    = 1;
  const PUT     = 2;
  const DELETE  = 3;

  private $key, $secret, $actor, $params = array(), $headers = array(
    self::GET     => array('Accept' => self::MIME_JSON),
    self::POST    => array('Accept' => self::MIME_JSON, 'Content-type' => self::MIME_JSON),
    self::PUT     => array('Accept' => self::MIME_JSON, 'Content-type' => self::MIME_JSON),
    self::DELETE  => array('Accept' => self::MIME_JSON)
  );

  function __construct(array $opts) {
    if(!isset($opts['key']) || !isset($opts['secret']))
      throw new Flow_Exception('Key and secret are required.');

    foreach($opts as $k => $v) switch($k) {
      case 'headers':
        $this->$k = array_merge_recursive($this->$k, $v);
        break;
      default:
        $this->$k = $v;
        break;
    }
  }

  protected function exec_request($url, array $co=array()) {
    $co[CURLOPT_HTTPHEADER][]   = 'Expect:';
    $co[CURLOPT_RETURNTRANSFER] = TRUE;
    $co[CURLOPT_CONNECTTIMEOUT] = 10;
    $co[CURLOPT_TIMEOUT]        = 60;
    $co[CURLOPT_USERAGENT]      = 'flow-php-client_0.1A';

    $ch = curl_init(self::HOST.':'.self::PORT.$url);
    curl_setopt_array($ch, $co);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
  }

  protected function request($url, $opts, $copts) {
    if(!isset($opts['headers'])) $opts['headers'] = array();
    if(!isset($opts['params'])) $opts['params'] = array();

    $this->headers_to_CURLOPTS($opts['headers'], &$copts);
    $url .= $this->params_to_query_str($opts['params']);

    return $this->exec_request($url, $copts);
  }

  protected function credentials() {
    $out = '';
    $credentials = array(
      'X-Actor'     => $this->actor,
      'X-Key'       => $this->key,
      'X-Timestamp' => time() * 1000
    );

    foreach($credentials as $k => $v) {
      $out .= sprintf('%s:%s', strtolower($k), $v);
    }

    $credentials['X-Signature'] = sha1($out .= $this->secret);

    return $credentials;
  }

  protected function headers_to_CURLOPTS(array $headers, array $co) {
    switch(true) {
    case $co[CURLOPT_HTTPGET]:
      $headers += $this->headers[self::GET];
      break;
    case $co[CURLOPT_POST]:
      $headers += $this->headers[self::POST];
      break;
    case $co[CURLOPT_PUT]:
      $headers += $this->headers[self::PUT];
      break;
    case $co[CURLOPT_CUSTOMREQUEST] == 'DELETE':
      $headers += $this->headers[self::DELETE];
      break;
    }

    foreach($this->credentials() + $headers as $k => $v)
      $co[CURLOPT_HTTPHEADER][] = "$k:$v";
  }

  protected function params_to_query_str(array $params) {
    return '?'.http_build_query($params + $this->params, NULL, '&');
  }

  function set_global_actor($id) {
    $this->actor = $id;
    return $this;
  }

  function set_global_params(array $params) {
    $this->params = $params;
    return $this;
  }

  function set_global_headers(array $headers) {
    $this->headers = $headers;
    return $this;
  }

  function get($url, array $opts=array()) {
    $copts = array(CURLOPT_HTTPGET => TRUE);
    return $this->request($url, $opts, $copts);
  }

  function post($url, $data, array $opts=array()) {
    $copts = array(
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $data
    );

    return $this->request($url, $opts, $copts);
  }

  function put($url, $data, array $opts=null) {
    $copts = array(
      CURLOPT_PUT => TRUE,
      CURLOPT_POSTFIELDS => $data
    );

    return $this->request($url, $opts, $copts);
  }

  function delete($url, array $opts=array()) {
    $copts = array(CURLOPT_CUSTOMREQUEST => 'DELETE');
    return $this->request($url, $opts, $copts);
  }

  function oauth_uri($path, array $params=null) {
    $uri = sprintf('http://%s:%s/oauth', self::HOST, self::PORT);
    $uri .= $path;

    if($params != null) 
      return $uri . $this->params_to_query_str($params);
    else
      return $uri;
  }
}

/* TEST */
/*
$api = new Flow(array(
  'key'     => $argv[1],
  'secret'  => $argv[2],
  'actor'   => '000000000000000000000001',
  'params'  => array('hints' => 0)
));

echo $api->get('/user'), "\n";
echo $api->oauth_uri('/token', array(
  'key' => $argv[1],
  'response_type' => 'code'
)), "\n";
*/
