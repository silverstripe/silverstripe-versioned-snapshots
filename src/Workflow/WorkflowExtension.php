<?php

namespace SilverStripe\Snapshots\Workflow;

use SilverStripe\Core\Extension;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataObject;
use Symbiote\AdvancedWorkflow\Actions\PublishItemWorkflowAction;
use Symbiote\AdvancedWorkflow\Jobs\WorkflowPublishTargetJob;

/**
 * Event hook for Advanced workflow module
 *
 * @extends Extension<PublishItemWorkflowAction>
 * @extends Extension<WorkflowPublishTargetJob>
 */
class WorkflowExtension extends Extension
{
    protected function onAfterWorkflowPublish(DataObject $target): void
    {
        $record = DataObject::get_by_id($target::class, $target->ID, false);
        Dispatcher::singleton()->trigger('workflowComplete', Event::create('publish', [
            'record' => $record,
        ]));
    }

    protected function onAfterWorkflowUnpublish(DataObject $target): void
    {
        $record = DataObject::get_by_id($target::class, $target->ID, false);
        Dispatcher::singleton()->trigger('workflowComplete', Event::create('publish', [
            'record' => $record,
        ]));
    }
}
