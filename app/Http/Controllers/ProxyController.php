<?php

namespace App\Http\Controllers;

use App\CssDocument;
use App\HtmlDocument;
use Cache;
use finfo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use URL;

class ProxyController extends Controller
{
    public function proxyPage(Request $request, $password, $url)
    {
        $targetUrl = str_replace("<<SLASH>>", "/", $url);
        $targetUrl = str_rot13(base64_decode($targetUrl));

        if (strpos($targetUrl, URL::to('/')) === 0) {
            return redirect($targetUrl);
        }

        // Password already got checked by the middleware:

        $newPW = md5(env('PROXY_PASSWORD') . date('dmy'));

        # Check For URL-Parameters that don't belong to the Proxy but to the URL that needs to be proxied
        $params = $request->except(['enableJS', 'enableCookies']);
        if (sizeof($params) > 0) {
            # There are Params that need to be passed to the page
            # Most of the times this happens due to forms that are submitted on a proxied page
            # Let's redirect to the correct URI
            $proxyParams   = $request->except(array_keys($params));
            $redirProxyUrl = $targetUrl;
            $redirParams   = [];
            if (strpos($redirProxyUrl, "?") === false) {
                $redirProxyUrl .= "?";
            } else {
                # There are already Params for this site which need to get updated
                $tmpParams = substr($redirProxyUrl, strpos($redirProxyUrl, "?") + 1);
                $tmpParams = explode("&", $tmpParams);
                foreach ($tmpParams as $param) {
                    $tmp = explode("=", $param);
                    if (sizeof($tmp) === 2) {
                        $redirParams[$tmp[0]] = $tmp[1];
                    }
                }
            }

            foreach ($params as $key => $value) {
                $redirParams[$key] = $value;
            }

            foreach ($redirParams as $key => $value) {
                $redirProxyUrl .= $key . "=" . urlencode($value) . "&";
            }

            $redirProxyUrl = rtrim($redirProxyUrl, "&");

            $pw = md5(env('PROXY_PASSWORD') . $redirProxyUrl);

            $redirProxyUrl = base64_encode(str_rot13($redirProxyUrl));
            $redirProxyUrl = urlencode(str_replace("/", "<<SLASH>>", $redirProxyUrl));

            $proxyParams['url']      = $redirProxyUrl;
            $proxyParams['password'] = $pw;

            $newLink = action('ProxyController@proxyPage', $proxyParams);
            return redirect($newLink);
        }

        $toggles = "111000A";

        # Script Toggle Url:
        $params         = $request->all();
        $scriptsEnabled = false;
        if ($request->has('enableJS')) {
            $scriptsEnabled = true;
            array_forget($params, 'enableJS');
        } else {
            $toggles[1]         = "0";
            $params['enableJS'] = "true";
        }

        $params['password'] = $password;
        $params['url']      = $url;
        $scriptUrl          = action('ProxyController@proxyPage', $params);
        //$scriptUrl          = "javascript:alert('Diese Funktion wurde auf Grund von technischen Problemen vorerst deaktiviert. Sie können JavaScript wieder aktivieren sobald diese behoben wurden.');";

        # Cookie Toggle Url:
        $params         = $request->all();
        $cookiesEnabled = false;
        if ($request->has('enableCookies')) {
            $cookiesEnabled = true;
            array_forget($params, 'enableCookies');
        } else {
            $toggles[0]              = "0";
            $params['enableCookies'] = "true";
        }

        $params['password'] = $password;
        $params['url']      = $url;
        $cookieUrl          = action('ProxyController@proxyPage', $params);

        $settings = "u0";
        if ($cookiesEnabled && !$scriptsEnabled) {
            $settings = "O0";
        } elseif (!$cookiesEnabled && $scriptsEnabled) {
            $settings = "e0";
        } elseif ($cookiesEnabled && $scriptsEnabled) {
            $settings = "80";
        }

        $urlToProxy = $this->proxifyUrl($targetUrl, $newPW, false);

        return view('ProxyPage')
            ->with('iframeUrl', $urlToProxy)
            ->with('scriptsEnabled', $scriptsEnabled)
            ->with('scriptUrl', $scriptUrl)
            ->with('cookiesEnabled', $cookiesEnabled)
            ->with('cookieUrl', $cookieUrl)
            ->with('targetUrl', $targetUrl);
    }

    public function proxy(Request $request, $password, $url)
    {

        $supportedContentTypes = [
            'text/html',
        ];

        $targetUrl      = str_replace("<<SLASH>>", "/", $url);
        $targetUrl      = str_rot13(base64_decode($targetUrl));
        $this->password = $password;
        // Hash Value under which a possible cached file would've been stored
        $hash     = md5($targetUrl);
        $result   = [];
        $httpcode = 200;

        if (!Cache::has($hash) || 1 == 1) {
            // Inits the Curl connection for being able to preload multiple URLs while using a keep-alive connection
            $this->initCurl();
            $result = $this->getUrlContent($targetUrl, false);

            # $result can be null if the File Size exeeds the maximum cache size defined in .env
            # In this case
            if ($result === null) {
                return $this->streamFile($targetUrl);
            } else {

                $httpcode = $result["http_code"];

                extract(parse_url($targetUrl));
                $base = $scheme . "://" . $host;

                # We will parse whether we have a parser for this document type.
                # If not, we will not Proxy it:
                $contentType = strpos($result["header"]["content-type"], ";") !== false ? trim(substr($result["header"]["content-type"], 0, strpos($result["header"]["content-type"], ";"))) : trim($result["header"]["content-type"]);
                switch ($contentType) {
                    case 'text/html':
                        # It's a html Document
                        $htmlDocument = new HtmlDocument($password, $targetUrl, $result["data"]);
                        $htmlDocument->proxifyContent();
                        $result["data"] = $htmlDocument->getResult();
                        break;
                    case 'image/png':
                    case 'image/jpeg':
                    case 'image/gif':
                    case 'application/font-woff':
                    case 'application/x-font-woff':
                    case 'application/x-empty':
                    case 'font/woff2':
                    case 'image/svg+xml':
                    case 'application/octet-stream':
                    case 'text/plain':
                    case 'image/x-icon':
                    case 'font/eot':
                    case 'image/vnd.microsoft.icon':
                    case 'application/vnd.ms-fontobject':
                    case 'application/x-font-ttf':
                        # Nothing to do with Images: Just return them
                        break;
                    case 'text/css':
                        # Css Documents might contain references to External Documents that need to get Proxified
                        $cssDocument = new CssDocument($password, $targetUrl, $result["data"]);
                        $cssDocument->proxifyContent();
                        $result["data"] = $cssDocument->getResult();
                        break;
                    default:
                        # We have no Parser for this one. Let's respond:
                        abort(500, $contentType . " " . $targetUrl);
                        break;
                }
            }

            curl_close($this->ch);
            # We are gonna cache all files for 60 Minutes to reduce
            # redundant file transfers:
            $val = base64_encode(serialize($result));

            Cache::put($hash, $val, 60);
        } else {
            $result = Cache::get($hash);
            // Base64 decode:
            $result = base64_decode($result);
            // Unserialize
            $result = unserialize($result);
            if (isset($result["http_code"]) && $result["http_code"] !== 0) {
                $httpcode = $result["http_code"];
            }
        }

        return response($result["data"], $httpcode)
            ->withHeaders($result["header"]);

    }

    private function initCurl()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:45.0) Gecko/20100101 Firefox/45.0');
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
    }

    private function streamFile($url)
    {
        $headers = get_headers($url, 1);

        $filename = basename($url);

        # From the headers we need to remove the first Element since it's the status code:
        $status = $headers[0];
        $status = intval(preg_split("/\s+/si", $status)[1]);
        array_forget($headers, 0);

        # Add the Filename if it's not set:
        if (!isset($headers["Content-Disposition"])) {
            $headers["Content-Disposition"] = "inline; filename=\"" . $filename . "\"";
        }

        $response = new StreamedResponse(function () use ($url) {
            # We are gonna stream a large file
            $wh = fopen('php://output', 'r+');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 256);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FILE, $wh); // Data will be sent to our stream ;-)

            curl_exec($ch);

            curl_close($ch);

            // Don't forget to close the "file" / stream
            fclose($wh);
        }, 200, $headers);
        $response->send();
        return $response;
    }

    private function getUrlContent($url, $withCookies)
    {
        $url = htmlspecialchars_decode($url);
        curl_setopt($this->ch, CURLOPT_URL, "$url");
        curl_setopt($this->ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($this->ch, CURLOPT_PROGRESSFUNCTION, 'self::downloadProgress');

        $data = curl_exec($this->ch);

        # If the requested File is too big for this Process to cache then we are gonna handle this File download later
        # in another way.
        if (curl_errno($this->ch) === CURLE_ABORTED_BY_CALLBACK) {
            # In this case the download was aborted because of the FileSize
            # We have no headers or anything like that
            # so we will return null and handle this case in the calling function
            return null;
        } else {

            $httpcode = intval(curl_getinfo($this->ch, CURLINFO_HTTP_CODE));

            $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
            $header      = substr($data, 0, $header_size);

            $data        = substr($data, $header_size);
            $headerArray = [];
            foreach (explode(PHP_EOL, $header) as $index => $value) {
                if ($index > 0) {
                    $ar = explode(': ', $value);
                    if (sizeof($ar) === 2) {
                        if ($withCookies && (strtolower($ar[0]) === "content-type" || strtolower($ar[0]) === "set-cookie")) {
                            $headerArray[trim($ar[0])] = trim($ar[1]);
                        } elseif (!$withCookies && strtolower($ar[0]) === "content-type") {
                            $headerArray[strtolower(trim($ar[0]))] = strtolower(trim($ar[1]));
                        } elseif (strtolower($ar[0]) === "location") {
                            $headerArray[trim($ar[0])] = $this->proxifyUrl(trim($ar[1]), null, false);
                        } else {
                            #$headerArray[trim($ar[0])] = trim($ar[1]);
                        }
                    }
                }
            }

            # It might happen that a server doesn't give Information about file Type.
            # Let's try to generate one in this case
            if (!isset($headerArray["content-type"])) {
                $finfo                       = new finfo(FILEINFO_MIME);
                $headerArray["content-type"] = $finfo->buffer($data);
            }

            $headerArray["Content-Security-Policy"] = "default-src 'self' data: 'unsafe-inline' http://localhost";
            # Charset-Fix for people who forget to declare charset:
            # If this won't work the default charset UTF-8 is set by laravel:
            foreach ($headerArray as $key => $value) {
                if (strtolower($key) === "content-type" && strpos(strtolower($value), "charset") === false) {
                    # We will see if there is a content-type with charset declared in the document:
                    if (preg_match("/<\s*meta[^>]+http-equiv=[\'\"]\s*content-type\s*[\'\"][^>]+?>/si", $data, $match)) {
                        if (strpos($match[0], "charset") !== false && preg_match("/content=[\'\"]([^\'\"]+)/si", $match[0], $contentType)) {
                            $headerArray[$key] = $contentType[1];
                            break;
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }

                }

            }

            if (!isset($httpcode) || !$httpcode || $httpcode === 0) {
                $httpcode = 200;
            }

            return ['header' => $headerArray, 'data' => $data, 'http_code' => $httpcode];
        }
    }

    private function downloadProgress($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {
        # The Memory Cache:
        # Every file that our Proxy parses has to lie in the memory Cache of PHP
        # If you would download a 5GB File then our PHP Process would need 5GB min RAM
        # We are gonna handle Files bigger then our defined maximum Cache Size in another way and break the conection at this point.
        if ($download_size > intval(env('PROXY_MEMORY_CACHE')) || $downloaded > intval(env('PROXY_MEMORY_CACHE'))) {
            return 1;
        }
    }

    public function proxifyUrl($url, $password = null, $topLevel)
    {
        // Only convert valid URLs
        $url = trim($url);
        if (strpos($url, "http") !== 0 || strpos($url, URL::to('/')) === 0) {
            return $url;
        }

        if (!$password) {
            $password = urlencode(\Request::route('password'));
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
            $params['password'] = $password;
            $params['url']      = $urlToProxy;

            $iframeUrl = action('ProxyController@proxy', $params);

        }

        return $iframeUrl;
    }
}
