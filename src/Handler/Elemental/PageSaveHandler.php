<?php


namespace SilverStripe\Snapshots\Handler\Elemental;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\Handler\Form\Handler;
use SilverStripe\Snapshots\Snapshot;
use DNADesign\Elemental\ElementalEditor;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Handles elemental changes at the *page* level, e.g. one or many inline edits saved with Page form.
 */
class PageSaveHandler extends Handler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        // Wonky check for elemental 4.
        // Elemental < 4 should leave this to the standard page save, which is a separate handler.
        if (class_exists(ElementalEditor::class)) {
            return null;
        }

        $changedElements = array_filter($context->get('elements'), function ($element) {
           /* @var SnapshotPublishable|Versioned $element */
           return $element->isModifiedSinceLastSnapshot() && $element->Version > 1;
        });

        // Defer to page save
        if (empty($changedElements)) {
            return null;
        }

        if (count($changedElements) === 1) {
            return Snapshot::singleton()->createSnapshot($changedElements[0]);
        }

        $message = _t(
            __CLASS__ . '.BLOCK_UPDATED_MANY',
            'Updated {count} {type}',
            [
                'count' => count($changedElements),
                'type' => BaseElement::singleton()->i18n_plural_name(),
            ]
        );

        $extraObjects = [];

        // Build a list of all the elements with distinct parents. In theory, more than one editor
        // could have been saved.
        $areas = [];
        foreach ($changedElements as $e) {
            $areas[$e->ParentID] = $e;
        }
        /* @var SnapshotPublishable|BaseElement $block */
        foreach ($areas as $block) {
            $extraObjects = array_merge($extraObjects, $block->getIntermediaryObjects());
        }
        $snapshot = Snapshot::singleton()->createSnapshotEvent(
            $message,
            array_merge($changedElements, $extraObjects)
        );

        return $snapshot;
    }
}
