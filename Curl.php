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

    private $options;

    private $raw_options;

    private $request_sets;

    private $processed_sets;

    private $content_type;

    private $connect_timeout;

    private $timeout;

    private $debug;

    const CONTENT_TYPE_MAP = [
        'json' => 'application/json; charset=utf-8',
        'form' => 'application/x-www-form-urlencoded',
    ];

    public function __construct()
    {
        $this->ch = curl_init();
        $this->method = '';
        $this->allowed_methods = ['get', 'post', 'put', 'delete'];
        $this->url = '';
        $this->query = [];
        $this->content_type = null;
        $this->options = [];
        $this->raw_options = [];
        $this->request_sets = ['agent', 'referer', 'redirect', 'ssl', 'cookie'];
        $this->processed_sets = [];
        $this->connect_timeout = 1;
        $this->timeout = 30;
        $this->debug = false;
    }

    public function __destruct()
    {
        if (is_resource($this->ch)) {
            curl_close($this->ch);
        }
    }

    public function __call($name, $arguments)
    {
        if (!in_array($name, $this->allowed_methods)) {
            echo 'unsupported request method';
            return false;
        }
        $this->method($name);
        $this->url($arguments[0]);
        $this->setRequestLine();
        $this->setRequestHeader();
        $this->setRequestBody();
        return $this->request();
    }

    private function method($method)
    {
       $this->method = $method;
       return $this;
    }

    private function url($url)
    {
        $this->url = $url;
        return $this;
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
                $this->setOpt(CURLOPT_CUSTOMREQUEST, strtoupper($this->method));
        }
        $this->setOpt(CURLOPT_URL, $this->url);
        $this->setOpt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
    }

    private function setOpt($name, $value)
    {
        $this->options[$name] = $value;
    }

    private function setRequestHeader()
    {
        foreach ($this->request_sets as $set) {
            if (! in_array($set, $this->processed_sets)) {
                $this->{$set}();
            }
        }
    }

    private function setRequestBody()
    {
        if ($this->method == 'get') {
            if ($this->query) {
                $this->url .= (strpos($this->url, '?') ? '&' : '?') . http_build_query($this->query);
            }
            $this->setOpt(CURLOPT_URL, $this->url);
        } else {
            if ($this->query) {
                if ($this->content_type == 'json') {
                    $data = json_encode($this->query);
                    $this->setOpt(CURLOPT_POSTFIELDS, $data);
                    $this->setRawOpt(['Content-Type' => self::CONTENT_TYPE_MAP[$this->content_type]]);
                    $this->setRawOpt(['Content-Length' => strlen($data)]);
                } else {
                    $data = http_build_query($this->query);
                    $this->setOpt(CURLOPT_POSTFIELDS, $data);
                }
            }
        }
    }

    private function setRawOpt($option = [])
    {
        $this->raw_options[] = implode(': ', $option);
    }

    private function request()
    {
        curl_setopt_array($this->ch, $this->options);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->raw_options);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        if ($this->debug) {
            $output = fopen('php://temp', 'rw');
            curl_setopt($this->ch, CURLOPT_VERBOSE, true);
            curl_setopt($this->ch, CURLOPT_STDERR, $output);
        }
        $res = curl_exec($this->ch);
        if ($res === false) {
            if (curl_errno($this->ch) != 0) {
                echo 'curl error: ' . curl_error($this->ch);
                $res = '';
            }
        }
        if ($this->debug) {
            rewind($output);
            echo stream_get_contents($output), "\n\n";
            fclose($output);
        }
        return $res;
    }

    public function agent($agent = '')
    {
        if ($agent) {
            $this->setOpt(CURLOPT_USERAGENT, $agent);
        }
        $this->processed_sets[] = 'agent';
        return $this;
    }

    public function referer($referer = '')
    {
        if ($referer) {
            $this->setOpt(CURLOPT_REFERER, $referer);
        } else {
            $this->setOpt(CURLOPT_AUTOREFERER, true);
        }
        $this->processed_sets[] = 'referer';
        return $this;
    }

    public function redirect($follow = true, $max = 5)
    {
        $this->setOpt(CURLOPT_FOLLOWLOCATION, $follow);
        $this->setOpt(CURLOPT_MAXREDIRS, $max);
        $this->processed_sets[] = 'redirect';
        return $this;
    }

    public function ssl($is_ssl = false, $capath = '')
    {
        if ($is_ssl) {
            $this->setOpt(CURLOPT_SSL_VERIFYPEER, true);
            $this->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
            if ($capath) {
                $this->setOpt(CURLOPT_CAPATH, $capath);
            }
        } else {
            $this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
            $this->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        }
        $this->processed_sets[] = 'ssl';
        return $this;
    }

    public function cookie($cookie = [])
    {
        if (is_array($cookie) && !empty($cookie)) {
            $this->setOpt(CURLOPT_COOKIE, http_build_query($cookie, '', '; '));
        } else {
            $this->setOpt(CURLOPT_COOKIESESSION, true);
        }
        $this->processed_sets[] = 'cookie';
        return $this;
    }

    public function query(array $query, $content_type = '')
    {
        $this->query = $query;
        $this->content_type = $content_type;
        return $this;
    }

    public function debug($debug = false)
    {
        $this->debug = $debug;
        return $this;
    }
}
