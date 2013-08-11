<?php

namespace Cybits;

/**
 * A simple class to redirect any request to another web server and get the result from web server,
 * In short a request proxy
 *
 * Living in Iran force me to drop the Proxy from the class repo and packagist. sorry.
 *
 * Class RequestProxy
 * @package Cybits
 */
class RequestProxy
{

    /**
     * @var string Target server
     */
    private $_target;

    /**
     * @var array of options
     */
    private $_options;

    private $_curl;

    private $_header;

    private $_content;

    private $_state;

    /**
     * Initialize object
     *
     * @param string $target target url
     * @param array $options
     */
    public function __construct($target, array $options = array())
    {
        $this->_target = $target;
        $this->_options = $options;
    }

    /**
     * Send request and serve the result.
     *
     * @throws \Exception
     */
    public function serve($return = false)
    {
        ob_start();
        $this->_initCurl();
        $this->_setUrl();
        $this->_setMethod();

        if ($this->getOption('send_headers', true) && !$return) {
            $this->_setHeaders();
        }
        if ($this->getOption('send_cookies', true) && !$return) {
            $this->_setCookies();
        }

        if ($this->_execCurl()) {
            if (!$return) {
                http_response_code($this->_state['http_code']);
                foreach ($this->_header as $header) {
                    header($header);
                }
            }
            echo $this->_content;
        } else {
            // What to do?
            throw new \Exception('Fail');
        }

        if ($return) {
            return ob_get_clean();
        } else {
            ob_end_flush();
        }
    }

    /**
     * Execute curl to get result
     *
     * @return bool
     */
    private function _execCurl()
    {
        $result = curl_exec($this->_curl);
        if (!$result) {
            return false;
        }
        $this->_state = curl_getinfo($this->_curl);
        list($header, $content) = preg_split('/([\r\n][\r\n])\\1/', $result, 2);
        $this->_header = preg_split('/[\r\n]+/', $header);;
        $this->_content = $content;

        return true;
    }

    /**
     * get options from array
     *
     * @param $option
     * @param $default
     * @return mixed
     */
    protected function getOption($option, $default)
    {
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
        return $default;
    }

    /**
     * A helper function to get all http-headers from current request
     * @return array
     */
    protected function getAllHeaders()
    {
        $result = array();
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == "HTTP_") {
                $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
                $result[$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }


    /**
     * Initialize curl object
     */
    private function _initCurl()
    {
        $this->_curl = curl_init();

        curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curl, CURLOPT_HEADER, true);
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Set url from current request, strip the extra part if any.
     */
    private function _setUrl()
    {
        $url = $_SERVER['REQUEST_URI'];
        $extraPath = $this->getOption('extra_path', '');
        if ($extraPath != '' && substr($url, 0, strlen($extraPath)) == $extraPath) {
            $url = substr($url, strlen($extraPath) - strlen($url));
        }
        $url = $this->_target . $url;
        curl_setopt($this->_curl, CURLOPT_URL, $url);
    }

    /**
     * Set request headers from original request to new request
     */
    private function _setHeaders()
    {
        //TODO Strip unused header like user agent and such.
        $headers = $this->getAllHeaders();
        curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Set cookies from request.
     */
    private function _setCookies()
    {
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = $name . '=' . $value;
        }

        $cookiesString = implode('; ', $cookies);
        curl_setopt($this->_curl, CURLOPT_COOKIE, $cookiesString);
    }

    /**
     * Set method and request data
     */
    private function _setMethod()
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        switch ($method) {
            case 'POST':
                curl_setopt($this->_curl, CURLOPT_POST, true);
                break;
            case 'GET':
                break;
            default:
                curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, $method);
        }
        if (count($_POST)) {
            // Ok this has a post field
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $_POST);
        }
        //TODO : Support for files
    }

}