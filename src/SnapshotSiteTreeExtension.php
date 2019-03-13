<?php


namespace SilverStripe\Snapshots;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;

class SnapshotSiteTreeExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }
        /* @var SnapshotPublishable|SiteTree $owner*/
        $owner = $this->owner;
        if ($owner->hasOwnedModifications()) {
            $fields->unshift(HeaderField::create('This page has owned modifications'));
        }
    }
}
