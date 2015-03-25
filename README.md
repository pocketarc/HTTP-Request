[![Latest Version](https://img.shields.io/github/release/brunodebarros/http-request.svg?style=flat-square)](https://github.com/brunodebarros/http-request/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/brunodebarros/http-request.svg?style=flat-square)](https://packagist.org/packages/brunodebarros/http-request)

HTTP Request 1.0
================

HTTP Request is a web crawler which behaves just like a regular web browser, interpreting location redirects and storing cookies automatically.

Features
--------

* HTTP/HTTPS Requests
* Automatic cookie and redirect handling
* GET/POST Requests

How to use it
-------------

#### Install with Composer

Add `brunodebarros/http-request` to the contents of your composer.json:

```
{
    "require": {
        "brunodebarros/http-request": "dev-master"
    }
}
```

#### Then use it!

    $http = new HTTP_Request();
    $content = $http->request($url, $mode, $data, $save_to_file);

$url is the URL to access. HTTP Request supports HTTPS requests just as easily as HTTP, as long as you have OpenSSL enabled on your PHP server.

$mode is either GET or POST. If empty, a GET request will be made.

$data is an array of data to pass via GET or POST. Couldn't be simpler. If empty, no extra data will be sent in the request.

$save_to_file is the filename of the file where you want the output to be stored. If empty, the output will not be stored anywhere.

Examples
--------

	$http = new HTTP_Request();
	
	# Make a simple GET Request:
	$content = $http->request('http://website.com');
	
    # Make a POST request to a HTTPS website with some data:
	$content = $http->request('https://website.com/login', 'POST', array('user' => 'myusername', 'pass' => 'mypassword'));
	
    # Make a simple GET Request and store it in a file:
	$http->request('http://website.com', 'GET', array(), 'contents_of_website_dot_com.txt')

Why automatic cookie handling is awesome
----------------------------------------

The best part about HTTP Request is that it will automatically handle location redirects and cookies. So if you POST your login details to a website's login page, and then access another page on that website, the website will believe that you are logged in, because HTTP Request will have kept the cookies. This way, you can build truly human-like web crawlers that can easily perform pretty much any action a human can.

    $http = new HTTP_Request();

	# Login to website.com
    $content = $http->request('https://website.com/login', 'POST', array('user' => 'myusername', 'pass' => 'mypassword'));

	# Access restricted page (because of cookie handling, you will be logged in when you request this page, without any effort on your part)
	$http->request('http://website.com/page-for-logged-in-users-only');


What's coming.
--------------

I intend to make HTTP Request a whole lot more powerful. Here are some ideas I have:

1. Enhanced cookie handling (delete cookies when the server sends a request to delete the cookie, store cookies in files for future visits, etc.)
2. Enhanced redirect interpretation (make it work with HTML meta redirects, and even JavaScript redirects)
3. Chunk downloads to enable downloading very big files without going over PHP's memory_limit.
4. If you have any ideas, create an issue. If you create an issue, I'll do everything I can to resolve it.

Suggestions, questions and complaints.
--------------------------------------

If you've got any suggestions, questions, or anything you don't like about HTTP Request, create an issue. I'd really appreciate your feedback. Feel free to fork this project, if you want to contribute to it.
