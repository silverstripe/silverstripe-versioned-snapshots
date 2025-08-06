<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

/**
 * Clear in-memory cache when DB update is executed
 *
 * @extends Extension<DataObject>
 * @extends Extension<UpdateCacheExtension>
 */
class UpdateCacheExtension extends Extension
{
    protected function onAfterWrite(): void
    {
        RelationDiffCache::reset();
    }

    /**
     * @throws Exception
     */
    protected function onAfterPublishRecursive(): void
    {
        $owner = $this->getOwner();

        RelationDiffCache::singleton()->markAsPublished($owner);
    }
}
