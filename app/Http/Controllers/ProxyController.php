<?php

namespace App\Http\Controllers;

use Cache;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function proxyPage(Request $request, $password, $url)
    {
        $targetUrl = str_replace("<<SLASH>>", "/", $url);
        $targetUrl = str_rot13(base64_decode($targetUrl));
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
            if (strpos($redirProxyUrl, "?") === false) {
                $redirProxyUrl .= "?";
            }

            foreach ($params as $key => $value) {
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
        $targetUrl = str_replace("<<SLASH>>", "/", $url);
        $targetUrl = str_rot13(base64_decode($targetUrl));
        $this->password = $password;
        // Hash Value under which a possible cached file would've been stored
        $hash     = md5($targetUrl);
        $result   = [];
        $httpcode = 200;

        if (!Cache::has($hash) || 1 == 1) {
            // Inits the Curl connection for being able to preload multiple URLs while using a keep-alive connection
            $this->initCurl();
            if ($request->has("enableCookies")) {
                $result = $this->getUrlContent($targetUrl, true);
            } else {
                $result = $this->getUrlContent($targetUrl, false);
            }
            # Für alle weiteren Aktonen auf der URL benötigen wir die URL-Parameter nicht mehr. Wir entfernen diese:
            $targetUrl = preg_replace("/\?.*/si", "", $targetUrl);

            if (isset($result["http_code"]) && $result["http_code"] !== 0) {
                $httpcode = $result["http_code"];
            }

            if (!$request->has('enableJS')) {
                $result["data"] = $this->removeJavaScript($result["data"]);
            }

            extract(parse_url($targetUrl));
            $this->base = $scheme . "://" . $host;
            if (isset($path)) {
                $this->base .= $path;
            }
            if (isset($result["header"]["Content-Type"]) && stripos($result["header"]["Content-Type"], "text/html") !== false) {
                $result["data"] = $this->convertTargetAttributes($result["data"]);
            }
            // First of all we will handle the URLs in the META refresh tag
            /*die(var_dump($result["data"]));
            $result["data"] = preg_replace_callback("/(<meta\b[^>]*?url=)([^\s\"\']*)([^>]*?>)/si", "self::regRel2AbsMeta", $result["data"]);
            die("test");*/
            // We define the "tag" that encloses possible URLS that are needed to be parsed
            // Every tag is seperated by a "|" and needs to be regexp escaped
            $tagsToMatch = "href=|src=|action=|background=";
            // We have to match all Links enclosed within Quotes
            $result["data"] = preg_replace_callback("/(<[^>]+)($tagsToMatch)\s*([\"\'])((?!\\\\3).*?)(\\3.*?>)/si", "self::regRel2AbsQuotes", $result["data"]);
            // Ommitting Quotes is valid too so we match all Links matching this here
            $result["data"] = preg_replace_callback("/(<[^>]+?)($tagsToMatch)([^\"\'\s][^\s\"\/>]*?)(\s[^>]+?>|>)/si", "self::regRel2AbsNoQuotes", $result["data"]);
            // srcsets can contain multiple URLs so we handle them here srcset=
            $result["data"] = preg_replace_callback("/(<[^>]+)(srcset=)\s*([\"\'])((?!\\\\3).*?)(\\3.*?>)/s", "self::regRel2AbsSrcSet", $result["data"]);

            // You can define resources in your css files that will make the browser load that resources
            // We need to Proxify them, too.
            // Option one url(...)
            $result["data"] = preg_replace_callback("/(url\(\s*[\"\']{0,1})([^\'\"\)]*?)([\"\']{0,1}\))/si", "self::regCssRel2Abs", $result["data"]);

            // Now we need replace all of the absolute Links
            // We have to distinct whether the target of the Link is _blank|_top or not

            #$result["data"] = $this->parseProxyLink($result["data"], $password, $request);
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
    private function convertTargetAttributes($data)
    {
        $result = $data;

        # The following html Elements can define a target attribute which will set the way in which Links are gonna be opened
        # a, area, base, form (https://wiki.selfhtml.org/wiki/Referenz:HTML/Attribute/target)
        # If the target is _blank we don't need to worry It'll be correct then
        # We should make all other options Reference to _top because the link outside of the iframe needs to be changed then:

        # First change the ones that already have an target tag
        $result = preg_replace("/(<\s*(?:a|area|base|form)[^>]+\starget\s*=\s*[\"\']{0,1}\s*)(?:(?!_blank|\s|\"|\').)+/si", "$1_top", $result);
        # Now the ones that haven't got one
        $result = preg_replace("/(<\s*(?:a|area|base|form)(?:(?!target=).)*?)([<>])/", "$1 target=_top $2", $result);
        return $result;
    }
    private function removeJavaScript($html)
    {
        // We could use DOMDocument from PHP but
        // that would suggest that the given HTML is well formed which
        // we cannot guarantee so we will use regex
        $result = $html;
        // We will simply remove every single script tag and it's contents
        $result = preg_replace("/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i", "", $result);
        # Remove all Javascript that is placed in a href
        $result = preg_replace('/(href=)(["\'])\s*javascript:[^\\2]*?(\\2)/si', "$1$2$3", $result);
        # Remove all HTMl Event Handler
        $result = preg_replace_callback("/<[^>]*?\bon[^=]+?=([\"\']).*?\\1[^>]*?>/si", "self::removeJsAttributes", $result);
        # Remove all autofocus attributes:
        $result = preg_replace("/(<[^>]*?)autofocus[=\"\']+([^>]*>)/si", "$1$2", $result);

        // The rest of a potentional JavaScript code will be blocked by our IFrame. It would be a waste of resources to remove them

        return $result;
    }

    private function removeJsAttributes($match)
    {
        # This funktion gets a HTML Tag in $match[0] with javascript HTML Attributes in it (onclick=, etc...)
        # This funktion schouls remove these from the string and return the replacement
        $string = $match[0];

        $string = preg_replace("/\bon[^=]+?=\s*([\"\']).*?\\1/si", "", $string);
        return $string;
    }

    private function regRel2AbsMeta($match){
        die(var_dump($match));
    }

    private function regRel2AbsNoQuotes($match){
        $top = false;
        if(preg_match("/target=[\"\']{0,1}\s*(_blank|_top)/si", $match[0]) === 1){
            $top = true;
        }
        
        $pre = $match[1] . $match[2];
        $post = $match[4];
        $link = $match[3];

        $link = $this->parseRelativeToAbsolute($link);

        // We will Proxify this URL
        $link = $this->proxifyUrl($link, $this->password, $top);
        return $pre . $link . $post;
    }

    private function regRel2AbsQuotes($match){
        $top = false;
        if(preg_match("/target=[\"\']{0,1}\s*(_blank|_top)/si", $match[0]) === 1){
            $top = true;
        }

        $pre = $match[1] . $match[2] . $match[3];
        $post = $match[5];
        $link = htmlspecialchars_decode($match[4]);

        $link = $this->parseRelativeToAbsolute($link);

        // We will Proxify this URL
        $link = $this->proxifyUrl($link, $this->password, $top);
        return $pre . $link . $post;
    }

    private function regCssRel2Abs($match){
        $top = false;
        $pre = $match[1];
        $post = $match[3];
        $link = htmlspecialchars_decode($match[2]);

        $link = $this->parseRelativeToAbsolute($link);
        if(strpos($link, "data:") !== 0){
          #  die($link);
        }
        // We will Proxify this URL
        $link = $this->proxifyUrl($link, $this->password, $top);

        return $pre . $link . $post;
    }

    private function regRel2AbsSrcSet($match){
        $top = false;
        if(preg_match("/target=[\"\']{0,1}\s*(_blank|_top)/si", $match[0]) === 1){
            $top = true;
        }

        $pre = $match[1] . $match[2] . $match[3];
        $post = $match[5];

        $links = explode(",", $match[4]);
        $result = $match[4];
        foreach($links as $link){
            preg_match_all("/[\S]+/", $link, $matches);
            if(isset($matches[0]) && isset($matches[0][0])){
                // In the srcset the Link would be the first match
                $rel = $matches[0][0];
                $absLink = $this->parseRelativeToAbsolute($rel, "", $rel, "");

                // We will Proxify this URL
                $absLink = $this->proxifyUrl($absLink, $this->password, $top);

                $result = str_replace($rel, $absLink, $result);
            }
        }
        return $pre . $result . $post;
    }

    private function parseRelativeToAbsolute($link)
    {
        // When the Link is already absolute then do not convert anything
        if(preg_match("/(?:(?!:\/\/).)+?:\/\//si", $link) === 1){
            return $link;
        // If the Link starts with "//" that means it already is an absolute Link
        // But it needs to get the current protocol added
        }elseif (preg_match("/^\s*?\/\//si", $link) === 1) {
            $scheme = parse_url($this->base)["scheme"] . "://";
            $abs = preg_replace("/^\s*?\/\//si", "$scheme", $link);
            return $abs;
        // The Link that is following here is not absolute. But it can be invalid:
        }else{
            $absLink = $this->rel2abs($link, $this->base);
            return $absLink;
        }

        # Convert every Link that starts with [ . | / ] but not  with [ // ]        

        
        while (preg_match("/(href=|src=|url\(|action=|srcset=|@import |background=)(\s*[\"\']{0,1}\s*)((:?\.|\/[^\/])[^\"\'\s]+)([\"\'\s])/si", $result, $matches) === 1) {
            $absoluteLink = $this->rel2abs($matches[3], $base);
            $result       = str_replace($matches[0], $matches[1] . $matches[2] . $absoluteLink . $matches[5], $result, $count);
        }
        # Convert every Link that starts with a path and not with a slash
        preg_match_all("/(href=|src=|url\(|action=|srcset=|@import |background=)\s*([\"\']{0,1})\s*([\w]+?[^\"\'\s>]+)/si", $result, $matches);
        foreach ($matches[0] as $index => $value) {
            $absoluteLink = $this->rel2abs($matches[3][$index], $base);
            $result       = str_replace($value, $matches[1][$index] . $matches[2][$index] . $absoluteLink, $result);
        }

        $scheme = parse_url($base)["scheme"] . "://";

        while (preg_match("/(href=|src=|url\(|action=|srcset=|@import |background=)([\"\']{0,1})\/{2}/si", $result, $matches) === 1) {
            $result = str_replace($matches[0], $matches[1] . $matches[2] . $scheme, $result, $count);
        }

        # Form tags that do not define a action will automatically target the same site we are on.
        # To make it link to the correct page in the end we need to add an specific action:
        $result = preg_replace("/(<\s*form(?:(?!action=).)+?)(>)/si", "$1 action=\"$base\"$2", $result);

        return $result;
    }

    private function parseProxyLink($data, $password, $request)
    {
        $result = $data;
        $count  = 1;
        # Zunächst ersetzen wir alle externen Links
        preg_match_all("/<\s*(?:a|area|form)[^>]+?target=[\"\']{0,1}\s*(?:_top|_blank)\s*[^>]*?>/si", $result, $matches);
        foreach ($matches[0] as $tag) {
            $tmp    = preg_replace_callback("/((?:href|action)=[\"\']{0,1})([^\"\'\s>]+)/si", "self::pregProxifyUrlTop", $tag);
            $result = str_replace($tag, $tmp, $result, $count);
        }
        # Jetzt alle internen Links mit einem anderen target:
        preg_match_all("/<\s*(?:a|area|form)[^>]+?target=[\"\']{0,1}\s*(?:(?!_top|_blank|>).)+?>/si", $result, $matches);
        foreach ($matches[0] as $tag) {
            $tmp    = preg_replace_callback("/((?:href|action)=[\"\']{0,1})([^\"\'\s>]+)/si", "self::pregProxifyUrl", $tag);
            $result = str_replace($tag, $tmp, $result, $count);
        }
        $result = preg_replace_callback("/((?:href=|src=|action=|url\(|srcset=|@import |background=)\s*[\"\']{0,1}\s*)([^\"\'\s\)>]+)/si", "self::pregProxifyUrl", $result);

        return $result;
    }

    private function pregProxifyUrl($matches)
    {

        $current   = \Request::root();
        $iframeUrl = $matches[2];

        if (strpos(strtolower($iframeUrl), "http") === 0 && strpos(strtolower($iframeUrl), $current) !== 0) {
            $iframeUrl = $this->proxifyUrl($matches[2], null, false);
        }

        return $matches[1] . $iframeUrl;
    }
    private function pregProxifyUrlTop($matches)
    {
        $current   = \Request::root();
        $iframeUrl = $matches[2];
        if (strpos($iframeUrl, "http") === 0 && strpos($iframeUrl, $current) !== 0) {
            $iframeUrl = $this->proxifyUrl($matches[2], null, true);
        }
        return $matches[1] . $iframeUrl;
    }

    private function initCurl()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
    }

    private function getUrlContent($url, $withCookies)
    {
        $url = htmlspecialchars_decode($url);
        curl_setopt($this->ch, CURLOPT_URL, "$url");

        $data = curl_exec($this->ch);
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
                        $headerArray[trim($ar[0])] = trim($ar[1]);
                    } elseif (strtolower($ar[0]) === "location") {
                        $headerArray[trim($ar[0])] = $this->proxifyUrl(trim($ar[1]), null, false);
                    } else {
                        #$headerArray[trim($ar[0])] = trim($ar[1]);
                    }
                }
            }
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

        return ['header' => $headerArray, 'data' => $data, 'http_code' => $httpcode];
    }

    public function proxifyUrl($url, $password = null, $topLevel)
    {
        // Only convert valid URLs
        $url = trim($url);
        if(strpos($url, "http") !== 0){
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

    private function rel2abs($rel, $base)
    {

        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '' || strlen(trim($rel)) <= 0 ) {
            return ($rel);
        }

        /* queries and anchors */
        if ($rel[0] == '#' || $rel[0] == '?') {
            return ($base . $rel);
        }

        /* parse base URL and convert to local variables:
        $scheme, $host, $path */
        extract(parse_url($base));

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
}
