<?php


namespace SilverStripe\Snapshots\CsvBulkLoader;

use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

class SaveListener extends DataExtension
{
    public function onAfterProcessRecord(DataObject $obj, $preview, $isChanged)
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
            Event::create('import', ['obj' => $obj])
        );
    }
}
