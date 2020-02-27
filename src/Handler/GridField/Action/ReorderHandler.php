<?php

namespace SilverStripe\Snapshots\Handler\GridField\Action;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;

class ReorderHandler extends Handler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $snapshot = parent::createSnapshot($context);
        if (!$snapshot) {
            return null;
        }
        /* @var GridField $grid */
        $grid = $context->get('gridField');
        if (!$grid) {
            return null;
        }
        $model = $grid->getModelClass();
        $pluralName = $model::singleton()->i18n_plural_name();
        $event = SnapshotEvent::create([
            'Title' => _t(
                __CLASS__ . '.REORDER_ROWS',
                'Reordered {title}',
                ['title' => $pluralName]
            ),
        ]);
        $event->write();
        return $snapshot
            ->applyOrigin($event);
    }
}
