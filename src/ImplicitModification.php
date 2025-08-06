<?php

namespace SilverStripe\Snapshots;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\RelationDiffer\RelationDiffer;

/**
 * Represents a situation where snapshot origin itself is not being updated but rather a related model
 */
class ImplicitModification extends SnapshotEvent
{
    /**
     * @param RelationDiffer[] $diffs
     * @return $this
     * @throws NotFoundExceptionInterface
     */
    public function hydrateFromDiffs(array $diffs): ImplicitModification
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
     * @throws NotFoundExceptionInterface
     */
    private function getMessagesForDiff(RelationDiffer $diff): array
    {
        $relationType = $diff->getRelationType();
        $messages = [];
        $class = $diff->getRelationClass();
        $sng = Injector::inst()->get($class);
        $i18nGraph = [
            'added' => [
                'Added',
                'Created',
            ],
            'removed' => [
                'Removed',
                'Deleted',
            ],
            'changed' => [
                'Modified',
                'Modified',
            ],
        ];

        foreach ($i18nGraph as $category => $labels) {
            $getter = 'get' . ucfirst($category);
            // Number of records in 'added', or 'removed', etc.
            $ct = count($diff->$getter());
            // e.g. MANY_MANY, HAS_MANY
            $i18nRelationKey = strtoupper($relationType);
            // e.g. use "Added" for many_many, "Created" for has_many
            [
                $manyManyLabel,
                $hasManyLabel,
            ] = $labels;
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
                    $key = sprintf('%s.%s_%s_ONE', ImplicitModification::class, $i18nRelationKey, $i18nActionKey);
                    /** @phpstan-ignore translation.key (we need the key to be dynamic here) */
                    $messages[] = _t(
                        $key,
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
                $key = sprintf('%s.%s_%s_MANY', ImplicitModification::class, $i18nRelationKey, $i18nActionKey);
                /** @phpstan-ignore translation.key (we need the key to be dynamic here) */
                $messages[] = _t(
                    $key,
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
