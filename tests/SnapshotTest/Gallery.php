<?php


namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Gallery extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Block' => Block::class,
    ];

    private static $many_many = [
        'Images' => [
            'through' => GalleryImageJoin::class,
            'from' => 'Gallery',
            'to' => 'Image',
        ]
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $owns = [ 'Images' ];

    private static $table_name = 'VersionedRelationsTest_Gallery';
}
