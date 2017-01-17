<?php
namespace YesWiki;

require_once('includes/WikiLinkCompatibility.php');

class WikiLinksTrackingCompatibility extends WikiLinkCompatibility
{
    /**
     * LinkTrackink
     */
    public $isTrackingLinks = false;
    public $linktable = array();



    // LinkTracking management
    /**
     * Tracks the link to a given page (only if the LinkTracking is activated)
     *
     * @param string $tag
     *            The tag (name) of the page to track a link to.
     */
    public function trackLinkTo($tag)
    {
        if ($this->linkTracking()) {
            $this->linktable[] = $tag;
        }
    }

    /**
     * Clears the link tracking table
     */
    public function clearLinkTable()
    {
        $this->linktable = array();
    }

    /**
     * Starts the LinkTracking
     *
     * @return bool The previous state of the link tracking
     */
    public function startLinkTracking()
    {
        return $this->linkTracking(true);
    }

    /**
     * Stops the LinkTracking
     *
     * @return bool The previous state of the link tracking
     */
    public function stopLinkTracking()
    {
        return $this->linkTracking(false);
    }

    /**
     * Sets and/or retrieve the state of the LinkTracking
     *
     * @param bool $newStatus
     *            The new status of the LinkTracking
     *            (defaults to <tt>null</tt> which lets it unchanged)
     * @return bool The previous state of the link tracking
     */
    public function linkTracking($newStatus = null)
    {
        $old = $this->isTrackingLinks;
        if ($newStatus !== null) {
            $this->isTrackingLinks = $newStatus;
        }
        return $old;
    }

    public function writeLinkTable()
    {
        // delete old link table
        $tableLinks = $this->database->prefix . 'links';
        $tag = $this->database->escapeString($this->getPageTag());
        $this->database->query("DELETE FROM $tableLinks WHERE from_tag = '$tag'");

        if (!empty($this->linktable)) {
            $fromTag = $this->database->escapeString($this->getPageTag());
            foreach ($this->linktable as $toTag) {
                $lowerToTag = strtolower($toTag);
                if (! isset($written[$lowerToTag])) {
                    $toTag = $this->database->escapeString($toTag);
                    $this->database->query(
                        "INSERT INTO $tableLinks
                            SET from_tag = '$fromTag', to_tag = '$toTag'"
                    );
                    $written[$lowerToTag] = 1;
                }
            }
        }
    }

}
