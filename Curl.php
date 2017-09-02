<?php 

/**
 * cURL Library
 * Based on RFC 2616 HTTP/1.1 Hypertext Transfer Protocol
 */
class Curl
{
	private $ch;

	private $method;

	private $allowed_methods;

	private $url;

	private $query;

	private $query_format;

	private $options;

	private $raw_options;

	private $request_sets;

	private $processed_sets;

	private $connect_timeout;

	private $timeout;

	private $debug;

	public function __construct()
	{
		$this->ch = curl_init();
		$this->method = '';
		$this->allowed_methods = ['get', 'post', 'put', 'delete'];
		$this->url = '';
		$this->query = [];
		$this->query_format = '';
		$this->options = [];
		$this->raw_options = [];
		$this->request_sets = ['agent', 'referer', 'redirect', 'ssl', 'cookie'];
		$this->processed_sets = [];
		$this->connect_timeout = 1;
		$this->timeout = 30;
		$this->debug = true;
	}

	public function __destruct()
	{
		curl_close($this->ch);
	}

	private function setRequestLine()
	{
		switch ($this->method) {
			case 'get':
				$this->setOpt(CURLOPT_HTTPGET, true);
				break;
			case 'post':
				$this->setOpt(CURLOPT_POST, true);
				break;
			default:
				$this->setOpt(CURLOPT_CUSTOMREQUEST, strtoupper($method));
				break;
		}
		$this->setOpt(CURLOPT_URL, $this->url);
		$this->setOpt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
	}

	public function setRequestHeader()
	{
		foreach ($this->request_sets as $set) {
			if (! in_array($set, $this->processed_sets)) {
				if (method_exists($this, $set)) {
					$this->{$set}();
				}
			}
		}
	}

	public function setRequestBody()
	{
		if ($this->query) {
			if ($this->method == 'get') {
				$this->url .= (strpos($this->url, '?') ? '&' : '?').http_build_query($this->query);
				$this->setOpt(CURLOPT_URL, $this->url);
			} else {
				if ($this->query_format == 'json') {
					$data = json_encode($this->query);
					$this->setOpt(CURLOPT_POSTFIELDS, $data);
					$this->setRawOpt('Content-Type: application/json; charset=utf-8');
					$this->setRawOpt('Content-Length: '.strlen($data));
				} else {
					$data = http_build_query($this->query);
					$this->setOpt(CURLOPT_POSTFIELDS, $data);
				}		
			}
		}
	}

	private function request()
	{
		curl_setopt_array($this->ch, $this->options);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->raw_options);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
		if ($this->debug) {
			curl_setopt($this->ch, CURLOPT_VERBOSE, true);
			curl_setopt($this->ch, CURLOPT_HEADER, true);
			curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
		}
		$res = curl_exec($this->ch);
		if ($res === false) {
			if (curl_errno($this->ch) != 0) {
				echo 'curl error: '.curl_error($this->ch);
				$res = '';
			}
		}
		if ($this->debug) {
			print_r(curl_getinfo($this->ch));
		}
		return $res;
	}


	public function __call($name, $arguments)
	{
		if (! in_array($name, $this->allowed_methods)) {
			exit('unspported request method');
		}
		$this->method($name);
		$this->url(array_shift($arguments));
		$this->setRequestLine();
		$this->setRequestHeader();
		$this->setRequestBody();
		return $this->request();
	}

	public function setOpt($name, $value)
	{
		$this->options[$name] = $value;
	}

	public function setRawOpt($option)
	{
		$this->raw_options[] = $option;
	}

	public function agent($agent = '')
	{
		if ($agent) {
			$this->setRawOpt("User-Agent: $agent");
		}
		$this->processed_sets[] ='agent';
		return $this;
	}

	public function referer($referer = '')
	{
		if ($referer) {
			$this->setRawOpt("Referer: $referer");
		} else {
			$this->setOpt(CURLOPT_AUTOREFERER, true);
		}
		$this->processed_sets[] ='referer';
		return $this;
	}

	public function redirect($follow = true, $max = 5)
	{
		$this->setOpt(CURLOPT_FOLLOWLOCATION, $follow);
		$this->setOpt(CURLOPT_MAXREDIRS, $max);
		$this->processed_sets[] ='redirect';
		return $this;
	}

	public function ssl($is_ssl = false, $capath = '')
	{
		if ($is_ssl) {
			$this->setOpt(CURLOPT_SSL_VERIFYPEER, true);
			$this->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
			if (!empty($cap)) $this->setOpt(CURLOPT_CAPATH, $capath);
		} else {
			$this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
			$this->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
		}
		$this->processed_sets[] ='ssl';
		return $this;
	}

	public function cookie($cookie = [])
	{
		if (is_array($cookie)) {
			if (! empty($cookie)) {
				$this->setOpt(CURLOPT_COOKIE, http_build_query($cookie, '', '; '));
			}
		} else {
			$this->setOpt(CURLOPT_COOKIESESSION, true);
		}
		$this->processed_sets[] = 'cookie';
		return $this;
	}

	public function query($query = [], $format = '')
	{
		$this->query = $query;
		$this->query_format = $format;
		return $this;
	}

	public function method($name)
	{
		$this->method = $name;
		return $this;
	}

	public function url($url)
	{
		$this->url = $url;
		return $this;
	}
}

$instance = new Curl();
$res = $instance->query(['wd'=>'你好啊'])->get('http://www.baidu.com/s');
var_dump($res);