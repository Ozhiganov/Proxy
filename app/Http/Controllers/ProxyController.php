<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function proxyPage(Request $request, $password, $url)
    {
        $targetUrl = str_rot13(base64_decode($url));

        // Password already got checked by the middleware:

        $newPW   = md5(env('PROXY_PASSWORD') . date('dmy'));
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

        # IFrame URL
        $urlToProxy = $targetUrl;
        $urlToProxy = str_replace("=", "=3d", $urlToProxy);
        $urlToProxy = str_replace("?", "=3f", $urlToProxy);
        $urlToProxy = str_replace("%", "=25", $urlToProxy);
        $urlToProxy = str_replace("&", "=26", $urlToProxy);
        $urlToProxy = str_replace(";", "=3b", $urlToProxy);
        $urlToProxy = preg_replace('/^(http[s]{0,1}):\/\//', '$1/', $urlToProxy);

        $settings = "u0";
        if ($cookiesEnabled && !$scriptsEnabled) {
            $settings = "O0";
        } elseif (!$cookiesEnabled && $scriptsEnabled) {
            $settings = "e0";
        } elseif ($cookiesEnabled && $scriptsEnabled) {
            $settings = "80";
        }

        $urlToProxy = "/en/$settings/" . $urlToProxy;

        $params             = $request->all();
        $params['password'] = urlencode($newPW);
        $params['url']      = $urlToProxy;

        $iframeUrl = action('ProxyController@proxy', $params);

        return view('ProxyPage')
            ->with('iframeUrl', $iframeUrl)
            ->with('scriptsEnabled', $scriptsEnabled)
            ->with('scriptUrl', $scriptUrl)
            ->with('cookiesEnabled', $cookiesEnabled)
            ->with('cookieUrl', $cookieUrl)
            ->with('targetUrl', $targetUrl);
    }

    public function redir(Request $request, $password, $url)
    {
        $params = $request->all();

        $params['password'] = urlencode($password);
        $params['page']     = urlencode(base64_encode(str_rot13("/test/nph-proxy.cgi/" . $url)));
        $newUrl             = action('ProxyController@proxy', $params);
        return redirect($newUrl);
    }

    public function proxy(Request $request, $password, $url)
    {
        $proxyUrl = "https://proxy.suma-ev.de/test/nph-proxy.cgi/" . ltrim($url, "/");
        $r        = $this->getUrlContent($proxyUrl);
        $httpcode = 200;
        if (isset($r["http_code"]) && $r["http_code"] !== 0) {
            $httpcode = $r["http_code"];
        }

        $r["data"] = $this->parseProxyLink($r["data"], $password, $request);
        return response($r["data"], $httpcode)
            ->withHeaders($r["header"]);

        return var_dump($r);

    }

    private function parseProxyLink($data, $password, $request)
    {
        $result = $data;

        $proxyLink = env('PROXY_URL');
        $host      = $request->root();
        $result    = str_replace($proxyLink, $host, $data);
        $result    = str_replace("/test/nph-proxy.cgi", "/proxy/$password", $result);
        return $result;
    }

    private function getUrlContent($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $data = curl_exec($ch);

        $httpcode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($data, 0, $header_size);

        $data = substr($data, $header_size);

        curl_close($ch);
        $headerArray = [];
        foreach (explode(PHP_EOL, $header) as $index => $value) {
            if ($index > 0) {
                $ar = explode(': ', $value);
                if (sizeof($ar) === 2) {
                    $headerArray[trim($ar[0])] = trim($ar[1]);
                } else {
                    //die(var_dump($value) . var_dump($ar));
                }
            }
        }
        return ['header' => $headerArray, 'data' => $data, 'http_code' => $httpcode];
    }
}
