<?php

namespace SilverStripe\Snapshots\Thirdparty;

use SilverStripe\Core\Extension;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataObject;
use Terraformers\EmbargoExpiry\Job\PublishTargetJob;
use Terraformers\EmbargoExpiry\Job\UnPublishTargetJob;

/**
 * Event hook for Embargo and expiry module
 *
 * @extends Extension<PublishTargetJob>
 * @extends Extension<UnPublishTargetJob>
 */
class EmbargoExpiryExtension extends Extension
{
    const string EVENT_NAME = 'embargoExpiryJob';

    /**
     * Extension point in @see PublishTargetJob::process()
     *
     * @param array $options
     */
    protected function afterPublishTargetJob(array $options): void
    {
        $owner = $this->getOwner();

        Dispatcher::singleton()->trigger(
            static::EVENT_NAME,
            Event::create(
                'publish',
                [
                    'record' => DataObject::get_by_id($owner->baseClass(), $owner->ID),
                    'options' => $options,
                ]
            )
        );
    }

    /**
     * Extension point in @see UnPublishTargetJob::process()
     *
     * @param array $options
     */
    protected function afterUnPublishTargetJob(array $options): void
    {
        $owner = $this->getOwner();

        Dispatcher::singleton()->trigger(
            static::EVENT_NAME,
            Event::create(
                'unpublish',
                [
                    'record' => DataObject::get_by_id($owner->baseClass(), $owner->ID),
                    'options' => $options,
                ]
            )
        );
    }
}
