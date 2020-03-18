<?php


namespace SilverStripe\Snapshots\Handler\GridField\Alteration;

use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Versioned\Versioned;

class Handler extends HandlerAbstract
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $action = $context->getAction();
        if ($action === null) {
            return null;
        }
        $args = $context->get('args');
        if (!$args) {
            return null;
        }
        // Warning: this relies on convention. There's no guarantee an action provider uses
        // "RecordID" as its argument name.
        $recordID = $args['RecordID'] ?? null;
        if ($recordID === null) {
            return null;
        }

        /* @var GridField $grid */
        $grid = $context->get('gridField');
        if (!$grid) {
            return null;
        }
        $class = $grid->getModelClass();
        if (!is_subclass_of($class, DataObject::class)) {
            return null;
        }

        if (!$class::singleton()->hasExtension(Versioned::class)) {
            return null;
        }

        $record = DataObject::get_by_id($class, $recordID);
        // @todo Move this to a proper archive handler
        if (!$record) {
            $record = $this->getDeletedVersion($class, $recordID);
            if (!$record) {
                return null;
            }
        }

        return Snapshot::singleton()->createSnapshot($record);
    }
}
