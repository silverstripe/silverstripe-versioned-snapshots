<?php


namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Block extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => BlockPage::class,
    ];

    private static $has_many = [
        'Galleries' => Gallery::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $owns = [ 'Galleries' ];

    private static $table_name = 'VersionedRelationsTest_Block';
}
