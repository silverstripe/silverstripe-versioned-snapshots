<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

/**
 * Clear in-memory cache when DB update is executed
 *
 * @method DataObject|$this getOwner()
 */
class ClearCacheExtension extends Extension
{
    public function onAfterWrite(): void
    {
        RelationDiffCache::reset();
    }
}
