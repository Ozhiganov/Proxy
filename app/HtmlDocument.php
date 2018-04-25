<?php

namespace App;

use DomDocument;

class HtmlDocument extends Document
{

    private $htmlString;

    public function __construct($password, $baseUrl, $htmlString, $encoding)
    {
        parent::__construct($password, $baseUrl);
        $this->htmlString = $this->convertEncoding($htmlString, $encoding);
    }

    public function convertEncoding($htmlString, $encoding){
        # If the site sets the document encoding in the "content-type" Header the $encoding variable is not null
        if($encoding == null){
            # Otherwise we will try to extract the correct encoding from the meta tag
            # Let's create a new DOM
            libxml_use_internal_errors(true);
            $dom = new DomDocument();
            $dom->loadHtml($htmlString);
            
            foreach($dom->getElementsByTagName("meta") as $meta){                
                # If there is a Content-Type Meta Tag
                if($meta->hasAttribute("http-equiv") && strtolower($meta->getAttribute("http-equiv")) === "content-type" && $meta->hasAttribute("content")){
                    $contentType = $meta->getAttribute("content");
                    $encoding = stripos($contentType, "charset=") !== false ? trim(substr($contentType, stripos($contentType, "charset=")+8)) : null;
                    if($encoding !== null) break;
                }
                # If there is a Charset Meta Tag
                if($meta->hasAttribute("charset")){
                    $encoding = $meta->getAttribute("charset");
                    break;
                }
            }
            if($encoding === null) $encoding = "UTF-8"; # Default Fallback
        }
        return mb_convert_encoding($htmlString, 'HTML-ENTITIES', $encoding);
    }

    public function getResult()
    {
        return $this->htmlString;
    }

    /**
     * Function proxifyContent
     * This method parses the given String and Proxifies all Links/Urls in it so it's targetting this Proxy Server
     **/
    public function proxifyContent()
    {
        if (trim($this->htmlString) === "") {
            return;
        }

        # Let's create a new DOM
        libxml_use_internal_errors(true);
        $dom = new DomDocument();
        $dom->loadHtml($this->htmlString);

        foreach ($dom->getElementsByTagName('base') as $base) {
            if ($base->hasAttribute("href")) {
                $href = $base->getAttribute('href');
                # Convert all relative Links to absolute Ones
                $href          = $this->convertRelativeToAbsoluteLink($href);
                $this->baseUrl = $href;
                # Delete Base Tag
            }
            $base->parentNode->removeChild($base);
        }

        # First things first. Let's change all a Tags that can define a target Attribute
        foreach ($dom->getElementsByTagName('a') as $link) {
            if ($link->hasAttribute("href")) {
                if (stripos($link->getAttribute("href"), "#") === 0) {
                    continue;
                } elseif (stripos($link->getAttribute("href"), "javascript:") === 0) {
                    $link->setAttribute("href", "");
                } else {
                    # All Links within a "a" Tag need to target the top level because they change the site on click
                    $this->convertTargetAttribute($link, "_top");
                    # Convert all relative Links to absolute Ones
                    $link->setAttribute("href", $this->convertRelativeToAbsoluteLink($link->getAttribute("href")));
                    # Convert all Links to the proxified Version
                    # All of this Links should target to the top Level
                    $link->setAttribute("href", $this->proxifyUrl($link->getAttribute("href"), true));
                }
            }

        }

        # All Buttons
        foreach ($dom->getElementsByTagName('button') as $button) {
            if ($button->hasAttribute("formtarget")) {
                $button->setAttribute("formtarget", "_top");
            }
            if ($button->hasAttribute("formaction")) {
                $formaction = $button->getAttribute("formaction");
                # Rel to abs
                $formaction = $this->convertRelativeToAbsoluteLink($formaction);
                # Abs to proxified
                $formaction = $this->proxifyUrl($formaction, true);
                # And replace
                $button->setAttribute("formaction", $formaction);
            }
            # Since when are buttons allowed to have a href?
            # Youtube has such on it's site so we are converting it anyways
            if ($button->hasAttribute("href")) {
                $href = $button->getAttribute("href");
                # Rel to abs
                $href = $this->convertRelativeToAbsoluteLink($href);
                # Abs to proxified
                $href = $this->proxifyUrl($href, true);
                # And replace
                $button->setAttribute("href", $href);
            }

        }

        foreach ($dom->getElementsByTagName('area') as $area) {
            # All Links within a "a" Tag need to target the top level because they change the site on click
            $this->convertTargetAttribute($area, "_top");
            if ($area->hasAttribute("href")) {
                $href = $area->getAttribute("href");
                # Rel to abs
                $href = $this->convertRelativeToAbsoluteLink($href);
                # Abs to proxified
                $href = $this->proxifyUrl($href, true);
                # And replace
                $area->setAttribute("href", $href);
            }
        }

        foreach ($dom->getElementsByTagName('form') as $form) {
            # All Links within a "a" Tag need to target the top level because they change the site on click
            $this->convertTargetAttribute($form, "_top");
            # If a Form doesn't define a action It references itself but we need to set the link then
            $action = $form->getAttribute("action");
            if ($action === "") {
                $action = $this->baseUrl;
            } else {
                # Otherwise the Link could be relative and we need to change it:
                # Convert all relative Links to absolute Ones
                $action = $this->convertRelativeToAbsoluteLink($action);
            }
            #
            # And finally Proxify the Url
            $action = $this->proxifyUrl($action, true);
            $form->setAttribute("action", $action);
        }

        # Alle Link Tags
        foreach ($dom->getElementsByTagName('link') as $link) {
            # Convert all relative Links to absolute Ones
            $link->setAttribute("href", $this->convertRelativeToAbsoluteLink($link->getAttribute("href")));
            # Convert all Links to the proxified Version
            # All of this Links should NOT target to the top Level
            $link->setAttribute("href", $this->proxifyUrl($link->getAttribute("href"), false));
        }

        # All Iframes
        foreach ($dom->getElementsByTagName('iframe') as $iframe) {
            # There can be 2 Possible sources
            # A - The src Attribute defines a Url that the Iframe loads
            $src = $iframe->getAttribute("src");
            if ($src !== "") {
                # Make the Link absolute
                $src = $this->convertRelativeToAbsoluteLink($src);
                # Proxify the Link
                $src = $this->proxifyUrl($src, false);
                # Replace the old Link
                $iframe->setAttribute("src", $src);
            }
            # B - The srcdoc Attribute defines Html-Code that should be displayed in the frame
            $srcdoc = $iframe->getAttribute("srcdoc");
            if ($srcdoc !== "") {
                # The srcdoc should be a HTML String so we are gonna make a new HTML-Document Element
                $htmlDoc = new HtmlDocument($this->password, $this->baseUrl, $srcdoc);
                $htmlDoc->proxifyContent();
                $srcdoc = $htmlDoc->getResult();
                # Replace the Old HTML Code
                $iframe->setAttribute("srcdoc", $srcdoc);
            }
        }

        # All Image Tags
        foreach ($dom->getElementsByTagName('img') as $img) {
            # Convert all Image src's to Absolute Links
            $img->setAttribute("src", $this->convertRelativeToAbsoluteLink($img->getAttribute("src")));
            # Convert all Image Sources to proxified Versions
            $img->setAttribute("src", $this->proxifyUrl($img->getAttribute("src"), false));
            # Some Images might contain a srcset (Different Images for different resolutions)
            # Syntax would be i.e. srcset="medium.jpg 1000w, large.jpg 2000w"
            $srcset = $img->getAttribute("srcset");
            if ($srcset !== "") {
                $images = explode(",", $srcset);
                foreach ($images as $index => $set) {
                    $set   = trim($set);
                    $parts = preg_split("/\s+/si", $set);
                    # $parts[0] is the Image Path
                    # It could be relative so convert that one:
                    $parts[0] = $this->convertRelativeToAbsoluteLink($parts[0]);

                    # And now Proxify it:
                    $parts[0]       = $this->proxifyUrl($parts[0], false);
                    $images[$index] = implode(" ", $parts);
                }
                $srcset = implode(",", $images);
                $img->setAttribute("srcset", $srcset);
            }
        }

        # All Input Elements
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ($input->hasAttribute("src")) {
                # Convert all Image src's to Absolute Links
                $input->setAttribute("src", $this->convertRelativeToAbsoluteLink($input->getAttribute("src")));
                # input all Image Sources to proxified Versions
                $input->setAttribute("src", $this->proxifyUrl($input->getAttribute("src"), false));
            }
        }

        # All Source Tags
        foreach ($dom->getElementsByTagName('source') as $img) {
            if ($img->hasAttribute("src")) {
                # Convert all Image src's to Absolute Links
                $img->setAttribute("src", $this->convertRelativeToAbsoluteLink($img->getAttribute("src")));
                # Convert all Image Sources to proxified Versions
                $img->setAttribute("src", $this->proxifyUrl($img->getAttribute("src"), false));

            }
            # Some Images might contain a srcset (Different Images for different resolutions)
            # Syntax would be i.e. srcset="medium.jpg 1000w, large.jpg 2000w"
            $srcset = $img->getAttribute("srcset");
            if ($srcset !== "") {
                $images = explode(",", $srcset);
                foreach ($images as $index => $set) {
                    $set   = trim($set);
                    $parts = preg_split("/\s+/si", $set);
                    # $parts[0] is the Image Path
                    # It could be relative so convert that one:
                    $parts[0] = $this->convertRelativeToAbsoluteLink($parts[0]);

                    # And now Proxify it:
                    $parts[0]       = $this->proxifyUrl($parts[0], false);
                    $images[$index] = implode(" ", $parts);
                }
                $srcset = implode(",", $images);
                $img->setAttribute("srcset", $srcset);
            }
        }

        # Alle Meta Tags
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if ($meta->hasAttribute("href")) {
                # Convert all relative Links to absolute Ones
                $meta->setAttribute("href", $this->convertRelativeToAbsoluteLink($meta->getAttribute("href")));
                # Convert all Links to the proxified Version
                # All of this Links should NOT target to the top Level
                $meta->setAttribute("href", $this->proxifyUrl($meta->getAttribute("href"), false));
            }
            if ($meta->hasAttribute("http-equiv") && $meta->getAttribute("http-equiv") === "refresh") {
                # We should refresh the site with a meta tag
                # But not before profifying the new URL
                $content = $meta->getAttribute("content");
                $url     = substr($content, stripos($content, "url=") + 4);
                # Convert all relative Links to absolute Ones
                $url = $this->convertRelativeToAbsoluteLink($url);

                # Convert all Links to the proxified Version
                # All of this Links should NOT target to the top Level
                $url = $this->proxifyUrl($url, false);

                $content = substr($content, 0, stripos($content, "url=") + 4) . $url;

                $meta->setAttribute("content", $content);
            }
        }

        # Alle Script Tags
        foreach ($dom->getElementsByTagName('script') as $script) {
            $script->nodeValue = "";
            $script->setAttribute("src", "");
            $script->setAttribute("type", "");
        }

        # Alle Style Blöcke
        # Werden extra geparsed
        foreach ($dom->getElementsByTagName('style') as $style) {
            $styleString = $style->nodeValue;
            $cssElement  = new CssDocument($this->password, $this->baseUrl, $styleString);
            $cssElement->proxifyContent();
            $style->nodeValue = $cssElement->getResult();
        }

        foreach ($dom->getElementsByTagName("noscript") as $noscript) {
            $this->DOMRemove($noscript);

        }

        # Nun alle Video Tags
        foreach ($dom->getElementsByTagName("video") as $video) {
            if ($video->hasAttribute("src")) {
                # Convert all relative Links to absolute Ones
                $video->setAttribute("src", $this->convertRelativeToAbsoluteLink($video->getAttribute("src")));
                # Convert all Links to the proxified Version
                # All of this Links should NOT target to the top Level
                $video->setAttribute("src", $this->proxifyUrl($video->getAttribute("src"), false));
            }
            if ($video->hasAttribute("poster")) {
                # Convert all relative Links to absolute Ones
                $video->setAttribute("poster", $this->convertRelativeToAbsoluteLink($video->getAttribute("poster")));
                # Convert all Links to the proxified Version
                # All of this Links should NOT target to the top Level
                $video->setAttribute("poster", $this->proxifyUrl($video->getAttribute("poster"), false));
            }
        }

        # Abschließend gehen wir noch einmal alle Tags durch
        foreach ($dom->getElementsByTagName('*') as $el) {
            if ($el->getAttribute("style") !== "") {
                $styleString = $el->getAttribute("style");
                $cssElement  = new CssDocument($this->password, $this->baseUrl, $styleString);
                $cssElement->proxifyContent();
                $el->setAttribute("style", $cssElement->getResult());
            }

            # Some old sites might use the background attribute Let's parse them, too
            if ($el->hasAttribute("background")) {
                # Convert all relative Links to absolute Ones
                $el->setAttribute("background", $this->convertRelativeToAbsoluteLink($el->getAttribute("background")));
                # Convert all Links to the proxified Version
                # All of this Links should NOT target to the top Level
                $el->setAttribute("background", $this->proxifyUrl($el->getAttribute("background"), false));
            }

            # We Will Remove all Javascript Event attributes
            # To keep things simple we're gonna remove all Attributes which names start with "on"
            foreach ($el->attributes as $attr) {
                if (stripos($attr->name, "on") === 0) {
                    $el->removeAttribute($attr->name);
                }
            }
        }

        $this->htmlString = $dom->saveHtml();

        # Remove all now empty script Tags
        $this->htmlString = preg_replace("/<\s*[\/]{0,1}\s*script[^>]*?>/si", "", $this->htmlString);

        libxml_use_internal_errors(false);
    }

/**
 * This function changes the current Target Attribute on the link to given new target Attribute
 */
    private function convertTargetAttribute($link, $newTarget)
    {
        $link->setAttribute("target", $newTarget);
    }

    private function DOMRemove(\DOMNode $from)
    {
        $sibling = $from->firstChild;
        do {
            $next = $sibling->nextSibling;
            $from->parentNode->insertBefore($sibling, $from);
        } while ($sibling = $next);
        $from->parentNode->removeChild($from);
    }
}
