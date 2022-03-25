<?php

namespace SilverStripe\Snapshots\CsvBulkLoader;

use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

class SaveListener extends DataExtension
{
    /**
     * Extension point in @see CsvBulkLoader::processRecord()
     *
     * @param DataObject $obj
     * @param mixed $preview
     * @param mixed $isChanged
     */
    public function onAfterProcessRecord(DataObject $obj, $preview, $isChanged): void
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
            Event::create(get_class($obj), ['record' => $obj])
        );
    }
}
