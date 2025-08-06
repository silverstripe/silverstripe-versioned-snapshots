<?php

namespace SilverStripe\Snapshots\Handler\GridField\Action;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;

/**
 * Event hook for @see GridField
 */
class ReorderHandler extends Handler
{
    /**
     * @throws ValidationException
     * @throws Exception
     * @throws NotFoundExceptionInterface
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $snapshot = parent::createSnapshot($context);

        if (!$snapshot) {
            return null;
        }

        /** @var GridField $grid */
        $grid = $context->get('gridField');

        if (!$grid) {
            return null;
        }

        $model = $grid->getModelClass();
        $pluralName = DataObject::singleton($model)->i18n_plural_name();
        $event = SnapshotEvent::create();
        $event->Title = _t(
            ReorderHandler::class . '.REORDER_ROWS',
            'Reordered {title}',
            [
                'title' => $pluralName,
            ]
        );
        $event->write();

        return $snapshot->applyOrigin($event);
    }
}
