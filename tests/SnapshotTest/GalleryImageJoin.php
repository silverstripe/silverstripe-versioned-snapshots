<?php


namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class GalleryImageJoin extends DataObject implements TestOnly
{
    private static $has_one = [
        'Gallery' => Gallery::class,
        'Image' => GalleryImage::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedRelationsTest_GalleryImageJoin';

}
