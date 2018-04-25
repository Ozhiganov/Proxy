<?php

namespace App;

class CssDocument extends Document
{

    private $styleString;

    public function __construct($password, $base, $styleString)
    {
        parent::__construct($password, $base);
        $this->styleString = $styleString;
    }

    public function proxifyContent()
    {
        # All Resources that I know, that are included within an CSS Stylesheet must have the url() functional quoting
        # We're gonna replace all URL's that we find within this document
        # First with Quotation Marks:
        $this->styleString = preg_replace_callback("/(url\()([\"\']{1})([^\\2]+?)\\2/si", "self::pregReplaceUrl", $this->styleString);
        # And then the ones without Quotation Marks
        $this->styleString = preg_replace_callback("/(url\()([^\"\'][^\)]+?)(\))/si", "self::pregReplaceUrlNoQuotes", $this->styleString);

        # Replace @imports without url()
        $this->styleString = preg_replace_callback("/(@import\s+)([\"\'])(.*?)(\\2)/si", "self::pregReplaceImport", $this->styleString);
    }

    private function pregReplaceImport($matches)
    {
        $url = $matches[3];
        # Relative to Absolute
        $url = $this->convertRelativeToAbsoluteLink($url);
        # Proxify Url
        $url = $this->proxifyUrl($url, false);

        $replacement = $matches[1] . $matches[2] . $url . $matches[4];

        return $replacement;
    }

    private function pregReplaceUrl($matches)
    {
        $url = $matches[3];
        # Relative to Absolute
        $url = $this->convertRelativeToAbsoluteLink($url);
        # Proxify Url
        $url = $this->proxifyUrl($url, false);

        $replacement = $matches[1] . $matches[2] . $url . $matches[2];

        return $replacement;
    }

    private function pregReplaceUrlNoQuotes($matches)
    {
        $url = $matches[2];
        # Relative to Absolute
        $url = $this->convertRelativeToAbsoluteLink($url);
        # Proxify Url
        $url = $this->proxifyUrl($url, false);

        $replacement = $matches[1] . $url . $matches[3];

        return $replacement;
    }

    public function getResult()
    {
        return $this->styleString;
    }
}
