<?php


namespace SilverStripe\Snapshots\Elemental;

use DNADesign\Elemental\Forms\ElementalAreaField;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;

class SaveListener extends DataExtension
{
    public function onSaveInto(array $elements)
    {
        /* @var ElementalAreaField $owner */
        $owner = $this->getOwner();
        $page = $owner->getArea()->getOwnerPage();
        Dispatcher::singleton()->trigger(
            'elementalAreaUpdated',
            Event::create(
                $owner->getName(),
                [
                    'elements' => $elements,
                    'elementalArea' => $owner,
                    'page' => $page,
                ]
            )
        );
    }
}
