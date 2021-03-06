<?php

/**
 * Class CloudFlareExt
 */
class CloudFlareExt extends SiteTreeExtension
{

    /**
     * Extension Hook
     *
     * @param \SiteTree $original
     */
    public function onAfterPublish(&$original)
    {
        // if the page was just created, then there is no cache to purge and $original doesn't actually exist so bail out - resolves #3
        // we don't purge anything if we're operating on localhost
        if (CloudFlare::hasCFCredentials() && strlen($original->URLSegment)) {

            $urls = array(DataObject::get_by_id("SiteTree", $this->owner->ID)->Link());
            $top = $this->getTopLevelParent();

            if (
                $this->owner->URLSegment != $original->URLSegment || // the slug has been altered
                $this->owner->MenuTitle != $original->MenuTitle || // the navigation label has been altered
                $this->owner->Title != $original->Title // the title has been altered
            ) {
                // purge everything
                CloudFlare::purgeAll("A critical element has changed in this page (url, menu label, or page title) as a result; everything was purged");
            }

            if ($this->owner->URLSegment != $top->URLSegment) {
                $this->getChildrenRecursive($top->ID, $urls);
            }

            if (count($urls) === 1) {
                CloudFlare::purgeSingle($urls[0]);
            }

            // phpmd will insult me if I use else :'(
            if (count($urls) > 1) {
                CloudFlare::purgeMany($urls);
            }

        }

        parent::onAfterPublish($original);
    }

    /**
     * If something gets unpublished we purge EVERYTHING just to be safe (ie nav menus etc)
     */
    public function onAfterUnpublish()
    {

        if (CloudFlare::hasCFCredentials()) {
            CloudFlare::purgeAll('CloudFlare: All cache has been purged as a result of unpublishing a page.');
        }

        parent::onBeforeUnpublish();
    }

    /**
     * Determines if the current owner or given page ID is a parent
     *
     * @param null|int $id SiteTree.ID
     *
     * @return bool
     */
    public function isParent($id = NULL)
    {
        return ($this->getChildren($id)->count()) ? TRUE : FALSE;
    }

    /**
     * Gets the immediate children of a Page (doesn't care if those children are parents themselves - see getChildrenRecursive instead)
     *
     * @param null|int $parentID SiteTree.ParentID
     *
     * @return \DataList
     */
    public function getChildren($parentID = NULL)
    {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        return SiteTree::get()->filter("ParentID", $id);
    }

    /**
     * Traverses through the SiteTree hierarchy until it reaches the top level parent
     *
     * @return \DataObject|Object
     */
    public function getTopLevelParent() {
        $obj = $this->owner;

        while ((int)$obj->ParentID) {
            $obj = SiteTree::get()->filter("ID", $obj->ParentID)->first();
        }

        return $obj;
    }

    /**
     * Recursively fetches all children of the given page ID
     *
     * @param null $parentID SiteTree.ParentID
     * @param      $output
     */
    public function getChildrenRecursive($parentID = NULL, &$output) {
        $id = (is_null($parentID)) ? $this->owner->ID : $parentID;

        if (!is_array($output)) { $output = array(); }

        $children = $this->getChildren($id);

        foreach ($children as $child) {
            if ($this->isParent($child->ID)) {
                $this->getChildrenRecursive($child->ID, $output);
            }

            $output[] = ltrim(DataObject::get_by_id('SiteTree', $child->ID)->Link(), "/");
        }
    }


}