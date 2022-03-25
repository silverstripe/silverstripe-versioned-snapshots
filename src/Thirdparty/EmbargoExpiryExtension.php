<?php

namespace SilverStripe\Snapshots\Thirdparty;

use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use Terraformers\EmbargoExpiry\Job\PublishTargetJob;
use Terraformers\EmbargoExpiry\Job\UnPublishTargetJob;

class EmbargoExpiryExtension extends DataExtension
{
    const EVENT_NAME = 'embargoExpiryJob';

    /**
     * Extension point in @see PublishTargetJob::process()
     *
     * @param array $options
     */
    public function afterPublishTargetJob(array $options): void
    {
        Dispatcher::singleton()->trigger(
            static::EVENT_NAME,
            Event::create(
                'publish',
                [
                    'record' => DataObject::get_by_id($this->owner->baseClass(), $this->owner->ID),
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
    public function afterUnPublishTargetJob(array $options): void
    {
        Dispatcher::singleton()->trigger(
            static::EVENT_NAME,
            Event::create(
                'unpublish',
                [
                    'record' => DataObject::get_by_id($this->owner->baseClass(), $this->owner->ID),
                    'options' => $options,
                ]
            )
        );
    }
}
