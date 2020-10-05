<?php

namespace SilverStripe\Snapshots\Thirdparty;

use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

class EmbargoExpiryExtension extends DataExtension
{
    const EVENT_NAME = 'embargoExpiryJob';

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
