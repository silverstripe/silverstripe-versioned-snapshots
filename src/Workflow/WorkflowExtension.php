<?php


namespace SilverStripe\Snapshots\Workflow;

use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

class WorkflowExtension extends DataExtension
{
    public function onAfterWorkflowPublish(DataObject $target)
    {
        $record = DataObject::get_by_id(get_class($target), $target->ID, false);
        Dispatcher::singleton()->trigger('workflowComplete', new Event('publish', [
            'record' => $record,
        ]));
    }

    public function onAfterWorkflowUnpublish(DataObject $target)
    {
        $record = DataObject::get_by_id(get_class($target), $target->ID, false);
        Dispatcher::singleton()->trigger('workflowComplete', new Event('publish', [
            'record' => $record,
        ]));
    }

}
