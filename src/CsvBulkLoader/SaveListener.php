<?php

namespace SilverStripe\Snapshots\CsvBulkLoader;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataObject;

/**
 * Event hook for @see CsvBulkLoader
 *
 * @extends Extension<CsvBulkLoader>
 */
class SaveListener extends Extension
{
    /**
     * Extension point in @see CsvBulkLoader::processRecord()
     *
     * @param DataObject $obj
     * @param mixed $preview
     * @param mixed $isChanged
     */
    protected function onAfterProcessRecord(DataObject $obj, mixed $preview, mixed $isChanged): void
    {
        // No need tracking previews, since we don't expect any writes
        if ($preview) {
            return;
        }

        // Only record snapshot on changes.
        // All rows from a CSV import will come through this by default.
        if (!$isChanged) {
            return;
        }

        Dispatcher::singleton()->trigger(
            'csvBulkLoaderImport',
            Event::create($obj::class, [
                'record' => $obj,
            ])
        );
    }
}
