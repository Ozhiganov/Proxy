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

        $this->writeLog($targetUrl, $request->ip());

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

        if (!Cache::has($hash) || 1 === 1) {
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
                    case 'application/pdf':
                        if (!isset($result["header"]["content-disposition"])) {
                            $name     = "document.pdf";
                            $basename = basename($targetUrl);
                            if (stripos($basename, ".pdf") !== false) {
                                $name = $basename;
                            }
                            $result["header"]["content-disposition"] = "attachment; filename=$name";
                        }
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
                # We are gonna cache all files for 60 Minutes to reduce
                # redundant file transfers:
                $val = base64_encode(serialize($result));

                #Cache::put($hash, $val, 60);
            }

            curl_close($this->ch);

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

        if ($result["data"] === false) {
            $result["data"] = "";
        }

        return response($result["data"], $httpcode)
            ->withHeaders($result["header"]);

    }

    private function initCurl()
    {
        $this->ch = curl_init();
        $useragent=$_SERVER['HTTP_USER_AGENT'];
        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))){
            // Mobile Browser Dummy Mobile Useragent
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Mobile Safari/537.36');
        }else{
            // Not Mobile Dummy Desktop useragent
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:45.0) Gecko/20100101 Firefox/45.0');
        }
            
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
                        } elseif (strtolower($ar[0]) === "content-disposition") {
                            $headerArray[strtolower(trim($ar[0]))] = strtolower(trim($ar[1]));
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

    private function writeLog($targetUrl, $ip)
    {
        $logFile = env('PROXY_LOG_LOCATION');

        $dateString = date('D M d H:i:s Y');

        $logString = $dateString . "\t" . $targetUrl . "\t" . $ip . "\n";
        if(file_exists($logFile)){
            file_put_contents($logFile, $logString, FILE_APPEND);
        }
    }
}
