<?php

namespace SilverStripe\Snapshots\Elemental;

use DNADesign\Elemental\Forms\ElementalAreaField;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;

/**
 * Event hook for @see ElementalAreaField
 *
 * @extends Extension<ElementalAreaField>
 * @deprecated Elemental no longer uses save into but rather executes save on individual blocks
 */
class SaveListener extends Extension
{
    /**
     * Extension point in @see ElementalAreaField::saveInto()
     *
     * @param array $elements
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ValidationException
     * @deprecated Elemental no longer uses save into but rather executes save on individual blocks
     */
    protected function onSaveInto(array $elements): void
    {
        $owner = $this->getOwner();
        $page = $owner
            ->getArea()
            ->getOwnerPage();

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
