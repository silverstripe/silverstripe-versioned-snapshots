<?php

namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\Form\SaveHandler;
use SilverStripe\Snapshots\RelationDiffer\RelationDiffer;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

/**
 * Handles elemental changes at the *page* level, e.g. one or many inline edits saved with Page form.
 */
class PageSaveHandler extends SaveHandler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     * @throws Exception
     * @throws NotFoundExceptionInterface
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        $record = $this->getRecordFromContext($context);

        if (!$record) {
            return null;
        }

        if (!$record->hasExtension(ElementalAreasExtension::class)) {
            return parent::createSnapshot($context);
        }

        $changedElements = [];

        /** @var SnapshotPublishable|ElementalAreasExtension $record */
        foreach ($record->getElementalRelations() as $areaName) {
            $diffs = $record->$areaName()->getRelationDiffs();
            $elementDiffs = array_filter($diffs, static function (RelationDiffer $diff) {
                return $diff->getRelationClass() === BaseElement::class;
            });

            /** @var RelationDiffer $diff */
            foreach ($elementDiffs as $diff) {
                $changedElements = array_merge($changedElements, $diff->getChanged());
            }
        }

        // Defer to page save
        if (count($changedElements) === 0) {
            return parent::createSnapshot($context);
        }

        $elements = BaseElement::get()->byIDs($changedElements);

        if (count($changedElements) === 1) {
            return Snapshot::singleton()->createSnapshot($elements->first());
        }

        $message = _t(
            PageSaveHandler::class . '.BLOCK_UPDATED_MANY',
            'Updated {count} {type}',
            [
                'count' => count($changedElements),
                'type' => BaseElement::singleton()->i18n_plural_name(),
            ]
        );
        $snapshot = Snapshot::singleton()->createSnapshotEvent($message);

        foreach ($elements as $element) {
            $snapshot->addOwnershipChain($element);
        }

        return $snapshot;
    }
}
