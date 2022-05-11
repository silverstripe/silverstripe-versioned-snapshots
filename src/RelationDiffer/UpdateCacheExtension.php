<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

/**
 * Clear in-memory cache when DB update is executed
 *
 * @method DataObject|$this getOwner()
 */
class UpdateCacheExtension extends Extension
{
    public function onAfterWrite(): void
    {
        RelationDiffCache::reset();
    }

    /**
     * @throws Exception
     */
    public function onAfterPublishRecursive(): void
    {
        $owner = $this->getOwner();

        RelationDiffCache::singleton()->markAsPublished($owner);
    }
}
