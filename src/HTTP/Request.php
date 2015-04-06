<?php

/**
 * HTTP Request
 *
 * A web crawler which behaves just like a regular web browser, interpreting
 * location redirects and storing cookies automatically.
 *
 * LICENSE
 *
 * Copyright (c) 2015 Bruno De Barros <bruno@terraduo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author     Bruno De Barros <bruno@terraduo.com>
 * @copyright  Copyright (c) 2015 Bruno De Barros <bruno@terraduo.com>
 * @license    http://opensource.org/licenses/mit-license     MIT License
 * @version    1.0.2
 *
 */
class HTTP_Request {

    public $cookies          = array();
    public $user_agent       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/536.26.17 (KHTML, like Gecko) Version/6.0.2 Safari/536.26.17';
    public $last_url         = '';
    public $multipart        = false;
    public $redirections     = 0;
    public $max_redirections = 10;
    public $header           = array();

    protected $last_request = null;

    function __construct() {

    }

    function getLastRequest() {
        return $this->last_request;
    }

    function request($url, $mode = 'GET', $data = array(), $save_to_file = false) {
        if (!stristr($url, 'http://') and !stristr($url, 'https://')) {
            $url = 'http://' . $url;
        }
        $original = $url;
        $url      = parse_url($url);
        if (!isset($url['host'])) {
            print_r($url);
            throw new HTTP_Request_Exception("Failed to parse the given URL correctly.");
        }
        if (!isset($url['path'])) {
            $url['path'] = '/';
        }
        if (!isset($url['query'])) {
            $url['query'] = '';
        }

        if (!isset($url['port'])) {
            $url['port'] = ($url['scheme'] == 'https') ? 443 : 80;
        }

        $errno   = 0;
        $errstr  = '';
        $port    = $url['port'];
        $sslhost = (($url['scheme'] == 'https') ? 'tls://' : '') . $url['host'];
        $fp      = @fsockopen($sslhost, $port, $errno, $errstr, 30);
        if (!$fp) {
            throw new HTTP_Request_Exception("Failed to connect to {$url['host']}.");
        } else {
            $url['query'] = '?' . ((empty($url['query']) and $mode == 'GET') ? http_build_query($data) : $url['query']);
            $out          = "$mode {$url['path']}{$url['query']} HTTP/1.0\r\n";
            $out .= "Host: {$url['host']}\r\n";
            $out .= "User-Agent: {$this->user_agent}\r\n";
            if (count($this->cookies) > 0) {
                $out .= "Cookie: ";
                $i = 0;
                foreach ($this->cookies as $name => $cookie) {
                    if ($i == 0) {
                        $out .= "$name=$cookie";
                        $i = 1;
                    } else {
                        $out .= "; $name=$cookie";
                    }
                }
                $out .= "\r\n";
            }
            if (!empty($this->last_url)) {
                $out .= "Referer: " . $this->last_url . "\r\n";
            }
            $out .= "Connection: Close\r\n";

            if ($mode == "POST") {
                if (!$this->multipart) {
                    $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
                    $post = self::urlencodeArray($data, $this->multipart);
                } else {
                    $out .= "Content-Type: multipart/form-data; boundary=AaB03x\r\n";
                    $post = self::urlencodeArray($data, $this->multipart, 'AaB03x');
                }
                $out .= "Content-Length: " . strlen($post) . "\r\n";
                $out .= "\r\n";
                $out .= $post;
            } else {
                $out .= "\r\n";
            }

            $content       = '';
            $header        = '';
            $header_passed = false;

            $this->last_request = $out;

            if (fwrite($fp, $out)) {

                if (stristr($original, '://')) {
                    $this->last_url = $original;
                } else {
                    $this->last_url = "://" . $original;
                }

                if ($save_to_file) {
                    $fh = fopen($save_to_file, 'w+');
                }

                while (!feof($fp)) {
                    if ($header_passed) {
                        $line = fread($fp, 1024);
                    } else {
                        $line = fgets($fp);
                    }

                    if ($line == "\r\n" and !$header_passed) {
                        $header_passed = true;
                        $line          = "";

                        $header = self::parseHeaders($header);

                        if (isset($header['Set-Cookie'])) {
                            if (is_array($header['Set-Cookie'])) {
                                foreach ($header['Set-Cookie'] as $cookie) {
                                    $cookie                    = explode(';', $cookie);
                                    $cookie                    = explode('=', $cookie[0], 2);
                                    $this->cookies[$cookie[0]] = $cookie[1];
                                }
                            } else {
                                $header['Set-Cookie']                    = explode(';', $header['Set-Cookie']);
                                $header['Set-Cookie']                    = explode('=', $header['Set-Cookie'][0], 2);
                                $this->cookies[$header['Set-Cookie'][0]] = $header['Set-Cookie'][1];
                            }
                        }

                        $this->header = $header;

                        if (isset($header['Location']) and $this->redirections < $this->max_redirections) {

                            $location    = parse_url($header['Location']);
                            $custom_port = ($url['port'] == 80 or $url['port'] == 443) ? '' : ':' . $url['port'];

                            if (!isset($location['host'])) {

                                if (substr($header['Location'], 0, 1) == '/') {
                                    # It's an absolute URL.
                                    $header['Location'] = $url['scheme'] . '://' . $url['host'] . $custom_port . $header['Location'];
                                } else {
                                    # It's a relative URL, let's take care of it.
                                    $path = explode('/', $url['path']);
                                    array_pop($path);
                                    $header['Location'] = $url['scheme'] . '://' . $url['host'] . $custom_port . implode('/', $path) . '/' . $header['Location'];
                                }
                            }
                            $this->redirections++;
                            $content = $this->request($header['Location'], $mode, $data, $save_to_file);
                            break;
                        }
                    }
                    if ($header_passed) {
                        if (!$save_to_file) {
                            $content .= $line;
                        } else {
                            fwrite($fh, $line);
                        }
                    } else {
                        $header .= $line;
                    }
                }
                fclose($fp);
                if ($save_to_file) {
                    fclose($fh);
                }

                return $content;
            } else {
                throw new HTTP_Request_Exception("Failed to send request headers to $url.");
            }
        }
    }

    public static function urlencodeArray($data, $multipart = false, $boundary = '') {
        $return = "";
        $i      = 0;

        if ($multipart) {
            $return = '--' . $boundary;

            foreach ($data as $key => $value) {
                $return .= "\r\n" . 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n" . "\r\n";
                $return .= $value . "\r\n";
                $return .= '--' . $boundary;
            }

            $return .= '--';
        } else {
            $return = http_build_query($data);
        }

        return $return;
    }

    public static function GetBetween($content, $start, $end) {
        $r = explode($start, $content, 2);
        if (isset($r[1])) {
            $r = explode($end, $r[1], 2);

            return $r[0];
        }

        return '';
    }

    public static function parseHeaders($headers) {
        $return           = array();
        $headers          = explode("\r\n", $headers);
        $response         = explode(" ", $headers[0]);
        $return['STATUS'] = $response[1];
        unset($headers[0]);
        foreach ($headers as $header) {
            $header = explode(": ", $header, 2);
            if (!isset($return[$header[0]])) {
                if (isset($header[1])) {
                    $return[$header[0]] = $header[1];
                }
            } else {
                if (!is_array($return[$header[0]])) {
                    $return[$header[0]] = array($return[$header[0]]);
                }
                $return[$header[0]][] = $header[1];
            }
        }

        return $return;
    }

}

class HTTP_Request_Exception extends Exception {

}
