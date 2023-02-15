<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Versioned;

class GalleryImageJoin extends BaseJoin implements TestOnly
{
    /**
     * @var array
     */
    private static $has_one = [
        'Gallery' => Gallery::class,
        'Image' => GalleryImage::class,
    ];

    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var string
     */
    private static $table_name = 'SnapshotTest_GalleryImageJoin';
}
