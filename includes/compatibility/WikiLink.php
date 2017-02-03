<?php
namespace YesWiki\Compatibility;

require_once('includes/compatibility/WikiPage.php');

use YesWiki\Link;

class WikiLink extends WikiPage
{
    public function link($tag, $method = "", $text = "")
    {
        return new Link($tag, $method, $text);
    }

    public function composeLinkToPage($tag, $method = "", $text = "")
    {
        return new Link($tag, $method, $text);
    }

    // returns just PageName[/method].
    public function miniHref($method = '', $tag = '')
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }

        return $tag . ($method ? '/' . $method : '');
    }

    // returns the full url to a page/method.
    public function href($method = '', $tag = '', $params = '')
    {
        if (! $tag = trim($tag)) {
            $tag = $this->tag;
        }
        $link = new Link($tag, $method, null, $params);
        return $link->href();
    }
}
