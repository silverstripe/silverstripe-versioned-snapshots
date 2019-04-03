<?php


namespace SilverStripe\Snapshots;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

/**
 * @property SnapshotPublishable|SnapshotVersioned|DataObject $owner
 */
class SnapshotVersioned extends Versioned
{

    public function doRollbackToSnapshot($snapshot)
    {
        /* @var SnapshotVersioned|SnapshotPublishable|DataObject $rolledBack */
        $rolledBack = $this->owner->getAtSnapshot($snapshot);
        $rolledBack->copyVersionToStage($rolledBack->Version, Versioned::DRAFT);
        $rolledBack->rollbackOwned($snapshot);

        $rolledBack->unlinkDisownedObjects($rolledBack, Versioned::DRAFT);

        return $rolledBack->getAtVersion(Versioned::DRAFT);
    }

}