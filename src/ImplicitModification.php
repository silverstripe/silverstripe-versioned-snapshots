<?php

namespace SilverStripe\Snapshots;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\RelationDiffer\RelationDiffer;

class ImplicitModification extends SnapshotEvent
{
    /**
     * @param RelationDiffer[] $diffs
     * @return $this
     */
    public function hydrateFromDiffs(array $diffs): self
    {
        $messages = [];

        foreach ($diffs as $diff) {
            $messages = array_merge($messages, $this->getMessagesForDiff($diff));
        }

        $this->Title = implode(PHP_EOL, $messages);

        return $this;
    }

    /**
     * @param RelationDiffer $diff
     * @return array
     */
    private function getMessagesForDiff(RelationDiffer $diff): array
    {
        $relationType = $diff->getRelationType();
        $messages = [];
        $class = $diff->getRelationClass();
        $sng = Injector::inst()->get($class);
        $i18nGraph = [
            'added' => ['Added', 'Created'],
            'removed' => ['Removed', 'Deleted'],
            'changed' => ['Modified', 'Modified'],
        ];

        foreach ($i18nGraph as $category => $labels) {
            $getter = 'get' . ucfirst($category);
            // Number of records in 'added', or 'removed', etc.
            $ct = count($diff->$getter());
            // e.g. MANY_MANY, HAS_MANY
            $i18nRelationKey = strtoupper($relationType);
            // e.g. use "Added" for many_many, "Created" for has_many
            [$manyManyLabel, $hasManyLabel] = $labels;
            $action = $relationType === 'many_many'
                ? $manyManyLabel
                : $hasManyLabel;
            // e.g. ADDED, for MANY_MANY_ADDED
            $i18nActionKey = strtoupper($action);

            if ($ct === 1) {
                // If singular, be specific with the record
                $map = $diff->$getter();
                $id = $map[0] ?? 0;
                $record = DataObject::get_by_id($class, $id);

                if ($record) {
                    $messages[] = _t(
                        self::class . '.' . $i18nRelationKey . '_' . $i18nActionKey . '_ONE',
                        $action . ' {type} {title}',
                        [
                            'type' => $sng->singular_name(),
                            'title' => $record->getTitle()
                                ? '"' . $record->getTitle() . '"'
                                : '',
                        ]
                    );
                }
            } elseif ($ct > 1) {
                // Otherwise, just give a count
                $messages[] = _t(
                    self::class . '.' . $i18nRelationKey . '_' . $i18nActionKey . '_MANY',
                    $action . ' {count} {name}',
                    [
                        'count' => $ct,
                        'name' => $sng->plural_name(),
                    ]
                );
            }
        }

        return $messages;
    }
}
