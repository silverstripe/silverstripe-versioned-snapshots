<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class SimpleBlock extends DataObject implements TestOnly
{
    private static string $table_name = 'SnapshotTest_SimpleBlock';

    private static array $db = [
        'Title' => 'Varchar',
    ];
}
