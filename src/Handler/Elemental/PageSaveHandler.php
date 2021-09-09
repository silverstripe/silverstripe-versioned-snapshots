<?php


namespace SilverStripe\Snapshots\Handler\Elemental;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\Form\SaveHandler;
use SilverStripe\Snapshots\RelationDiffer;
use SilverStripe\Snapshots\Snapshot;
use DNADesign\Elemental\ElementalEditor;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\ORM\ValidationException;

/**
 * Handles elemental changes at the *page* level, e.g. one or many inline edits saved with Page form.
 */
class PageSaveHandler extends SaveHandler
{
    /**
     * @param EventContextInterface $context
     * @return Snapshot|null
     * @throws ValidationException
     */
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        // Wonky check for elemental 4.
        // Elemental < 4 should leave this to the standard page save, which is a separate handler.
        if (class_exists(ElementalEditor::class)) {
            return null;
        }

        $record = $this->getRecordFromContext($context);
        if (!$record) {
            return null;
        }
        if (!$record->hasExtension(ElementalAreasExtension::class)) {
            return parent::createSnapshot($context);
        }
        $changedElements = [];
        /* @var SnapshotPublishable|ElementalAreasExtension $record */
        foreach ($record->getElementalRelations() as $areaName) {
            $diffs = $record->$areaName()->getRelationDiffs();
            $elementDiffs = array_filter($diffs, function (RelationDiffer $diff) {
                return $diff->getRelationClass() === BaseElement::class;
            });
            if (!empty($elementDiffs)) {
                /* @var RelationDiffer $diff */
                foreach ($elementDiffs as $diff) {
                    $changedElements = array_merge($changedElements, $diff->getChanged());
                }
            }
        }
        // Defer to page save
        if (empty($changedElements)) {
            return parent::createSnapshot($context);
        }
        $elements = BaseElement::get()->byIDs($changedElements);
        if (count($changedElements) === 1) {
            return Snapshot::singleton()->createSnapshot($elements->first());
        }

        $message = _t(
            __CLASS__ . '.BLOCK_UPDATED_MANY',
            'Updated {count} {type}',
            [
                'count' => count($changedElements),
                'type' => BaseElement::singleton()->i18n_plural_name(),
            ]
        );
        $snapshot = Snapshot::singleton()->createSnapshotEvent($message);
        foreach ($elements as $e) {
            $snapshot->addOwnershipChain($e);
        }

        return $snapshot;
    }
}
