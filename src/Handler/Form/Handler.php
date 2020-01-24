<?php


namespace SilverStripe\Snapshots\Handler\Form;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Handler\HandlerAbstract;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;
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

        $page = $this->getPage($context);
        $record = null;
        if ($form = $context->get('form')) {
            $record = $form->getRecord();
        }

        if ($page === null || $record === null) {
            return null;
        }

        $message = $this->getMessage($action);

        $intermediaryObjects = [];
        $implicitObjects = [];
        $previous = $this->getPreviousVersion($record);
        /* @var SnapshotPublishable|Versioned|DataObject $record */
        if ($record->hasExtension(SnapshotPublishable::class)) {
            // Get all the owners that aren't the page
            $intermediaryObjects = $record->findOwners()->filterByCallback(function ($owner) use ($page) {
                return !SnapshotHasher::hashSnapshotCompare($page, $owner);
            })->toArray();
            $manyManyDiff = $record->diffManyMany();
            if (!empty($manyManyDiff)) {
                $message = $this->getMessageForManyMany($manyManyDiff);
                foreach ($manyManyDiff as $childClass => $details) {
                    // Any new many_many objects that were added should be tracked in the snapshot
                    foreach (['added', 'removed'] as $type) {
                        foreach ($details[$type] as $id) {
                            $obj = DataObject::get_by_id($childClass, $id);
                            if ($obj) {
                                $implicitObjects[] = ['type' => $type, 'record' => $obj];
                            }
                        }
                    }
                }
            }
        }
        $extraObjects = [];

        foreach ($intermediaryObjects as $extra) {
            $extraObjects[SnapshotHasher::hashObjectForSnapshot($extra)] = $extra;
        }
        foreach ($implicitObjects as $spec) {
            $extraObjects[SnapshotHasher::hashObjectForSnapshot($spec['record'])] = $spec['record'];
        }

        $snapshot = Snapshot::singleton()->createSnapshotFromAction($page, $record, $message, $extraObjects);
        if ($snapshot && !empty($implicitObjects)) {
            $parentItem = $snapshot->Items()->filter(
                'ObjectHash',
                SnapshotHasher::hashObjectForSnapshot($record)
            )->first();
            if ($parentItem) {
                foreach ($implicitObjects as $spec) {
                    $obj = $spec['record'];
                    $type = $spec['type'];
                    $item = $snapshot->Items()->filter(
                        'ObjectHash',
                        SnapshotHasher::hashObjectForSnapshot($obj)
                    )->first();
                    if ($item) {
                        $item->ParentID = $parentItem->ID;
                        $item->WasDeleted = $type === 'removed';
                        $item->write();
                    }
                }
            }
        }

        $record->reconcileOwnershipChanges($previous);

        return $snapshot;
    }

    /**
     * @param EventContextInterface $context
     * @return DataObject|null
     */
    protected function getPage(EventContextInterface $context): ?DataObject
    {
        $page = $context->get('page');
        if ($page) {
            return $page;
        }

        /* @var HTTPRequest $request */
        $request = $context->get('request');
        $url = $request->getURL();
        return $this->getCurrentPageFromRequestUrl($url);
    }

    private function getMessageForManyMany(array $manyManyDiff): string
    {
        $messages = [];
        foreach ($manyManyDiff as $childClass => $details) {
            $sng = Injector::inst()->get($childClass);
            $ct = count($details['added']);
            if ($ct) {
                $messages[] = _t(
                    __CLASS__ . '.MANY_MANY_ADD',
                    'Added {count} {name}',
                    [
                        'count' => $ct,
                        'name' => $ct > 1 ? $sng->plural_name() : $sng->singular_name()
                    ]
                );
            }
            $ct = count($details['removed']);
            if ($ct) {
                $messages[] = _t(
                    __CLASS__ . '.MANY_MANY_REMOVE',
                    'Removed {count} {name}',
                    [
                        'count' => $ct,
                        'name' => $ct > 1 ? $sng->plural_name() : $sng->singular_name()
                    ]
                );
            }
        }

        return implode("\n", $messages);
    }

    /**
     * @param DataObject $record
     * @param null $version
     * @return DataObject|null
     */
    private function getPreviousVersion(DataObject $record, $version = null): ?DataObject
    {
        $previous = null;
        if ($record->Version == 1) {
            $previous = Injector::inst()->create(get_class($record));
        } else {
            if ($version === null) {
                $version = $record->Version - 1;
            }

            $previous = $record->getAtVersion($version);
        }

        return $previous;
    }
}
