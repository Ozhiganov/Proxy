<?php

namespace App;

use Illuminate\Http\Request;
use URL;

abstract class Document
{

    protected $password;
    protected $baseUrl;

    public function __construct($password, $base)
    {
        $this->password = $password;
        $this->baseUrl  = $base;
    }

    public function proxifyUrl($url, $topLevel)
    {
        // Only convert valid URLs
        $url = trim($url);
        if (strpos($url, "http") !== 0 || strpos($url, URL::to('/')) === 0) {
            return $url;
        }

        $urlToProxy = base64_encode(str_rot13($url));
        $urlToProxy = str_replace("/", "<<SLASH>>", $urlToProxy);
        $urlToProxy = urlencode($urlToProxy);

        if ($topLevel) {
            $params = \Request::all();

            # Password
            $pw         = md5(env('PROXY_PASSWORD') . $url);
            $urlToProxy = base64_encode(str_rot13($url));
            $urlToProxy = urlencode(str_replace("/", "<<SLASH>>", $urlToProxy));

            # Params
            $params['password'] = $pw;
            $params['url']      = $urlToProxy;

            $iframeUrl = action('ProxyController@proxyPage', $params);
        } else {
            $params             = \Request::all();
            $params['password'] = $this->password;
            $params['url']      = $urlToProxy;

            $iframeUrl = action('ProxyController@proxy', $params);

        }

        return $iframeUrl;
    }

    protected function convertRelativeToAbsoluteLink($rel)
    {
        if (strpos($rel, "//") === 0) {
            $rel = parse_url($this->baseUrl, PHP_URL_SCHEME) . ":" . $rel;
        }

        /* return if already absolute URL or empty URL */
        if (parse_url($rel, PHP_URL_SCHEME) != ''
            || strlen(trim($rel)) <= 0
            || preg_match("/^\s*mailto:/si", $rel)) {
            return ($rel);
        }

        /* queries and anchors */
        if ($rel[0] == '#' || $rel[0] == '?') {
            return ($this->baseUrl . $rel);
        }

        /* parse base URL and convert to local variables:
        $scheme, $host, $path */
        extract(parse_url($this->baseUrl));

        /* remove non-directory element from path */
        if (isset($path)) {
            $path = preg_replace('#/[^/]*$#', '', $path);
        }

        /* destroy path if relative url points to root */
        if ($rel[0] == '/') {
            $path = '';
        }

        /* dirty absolute URL */
        $abs = '';

        /* do we have a user in our URL? */
        if (isset($user)) {
            $abs .= $user;

            /* password too? */
            if (isset($pass)) {
                $abs .= ':' . $pass;
            }

            $abs .= '@';
        }

        $abs .= $host;
        /* did somebody sneak in a port? */
        if (isset($port)) {
            $abs .= ':' . $port;
        }

        if (isset($path)) {
            $abs .= $path;
        }
        if (isset($rel)) {
            $abs .= "/" . ltrim($rel, "/");
        }
        /* replace '//' or '/./' or '/foo/../' with '/' */
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

        /* absolute URL is ready! */
        return ($scheme . '://' . $abs);
    }

    abstract public function proxifyContent();
}
