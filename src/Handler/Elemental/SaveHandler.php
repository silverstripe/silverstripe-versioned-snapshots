<?php


namespace SilverStripe\Snapshots\Handler\Elemental;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\Snapshots\ActivityEntry;
use SilverStripe\Snapshots\Handler\Form\Handler as FormHandler;
use SilverStripe\Snapshots\Snapshot;
use DNADesign\Elemental\ElementalEditor;

class SaveHandler extends FormHandler
{
    protected function createSnapshot(EventContextInterface $context): ?Snapshot
    {
        // Wonky check for elemental 4
        if (class_exists(ElementalEditor::class)) {
            return null;
        }

        $page = $this->getPage($context);

        if (!$page) {
            return null;
        }

        $changedElements = array_filter($context->get('elements'), function ($element) {
           return $element->isModifiedOnDraft() && $element->Version > 1;
        });
        $implicitChangedElements = array_map(function ($elem) {
            return [
                'record' => $elem,
                'type' => ActivityEntry::MODIFIED,
            ];
        }, $changedElements);
        $newElements = array_filter($context->get('elements'), function ($element) {
            return $element->isModifiedOnDraft() && $element->Version == 1;
        });
        $implicitCreatedElements = array_map(function ($elem) {
            return [
                'record' => $elem,
                'type' => ActivityEntry::CREATED,
            ];
        }, $changedElements);

        if (empty($changedElements) && empty($newElements)) {
            return null;
        }

        $messages = [];
        if (count($changedElements) === 1) {
            /* @var BaseElement $elem */
            $elem = $changedElements[0];
            $messages[] = _t(
                __CLASS__ . '.BLOCK_UPDATED_ONE',
                'Updated {type} "{name}"',
                [
                    'type' => $elem->i18n_singular_name(),
                    'name' => $elem->getTitle(),
                ]
            );
        } else if (count($changedElements) > 1) {
            $messages[] = _t(
                __CLASS__ . '.BLOCK_UPDATED_MANY',
                'Updated {count} {type}',
                [
                    'count' => count($changedElements),
                    'type' => BaseElement::singleton()->i18n_plural_name(),
                ]
            );
        }

        if (count($newElements) === 1) {
            /* @var BaseElement $elem */
            $elem = $newElements[0];
            $messages[] = _t(
                __CLASS__ . '.BLOCK_CREATED_ONE',
                'Created {type} "{name}"',
                [
                    'type' => $elem->i18n_singular_name(),
                    'name' => $elem->getTitle(),
                ]
            );
        } else if (count($newElements) > 1) {
            $messages[] = _t(
                __CLASS__ . '.BLOCK_CREATED_MANY',
                'Updated {count} {type}',
                [
                    'count' => count($newElements),
                    'type' => BaseElement::singleton()->i18n_plural_name(),
                ]
            );
        }
        $allElements = array_merge($newElements, $changedElements);

        // The areas should be in the snapshot, too, even though it rarely changes. Insure uniqueness
        // (in most cases, this should just be one area)
        $elementalAreaMap = [];
        foreach ($allElements as $elem) {
            $elementalAreaMap[$elem->ParentID] = $elem->Parent();
        }
        $elementalAreas = array_values($elementalAreaMap);

        $snapshot = Snapshot::singleton()->createSnapshotFromAction(
            $page,
            $page,
            implode("\n", $messages),
            array_merge($allElements, $elementalAreas)
        );

        $implicitObjects = array_merge($implicitChangedElements, $implicitCreatedElements);

        $snapshot->applyImplicitObjects($implicitObjects);

        return $snapshot;
    }
}
