<?php


namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class BlockPage extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Blocks' => Block::class,
    ];

    private static $owns = [ 'Blocks' ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'SnapshotTest_BlockPage';
}
