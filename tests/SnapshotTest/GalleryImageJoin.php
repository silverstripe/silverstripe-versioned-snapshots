<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Versioned;

/**
 * @property int $GalleryID
 * @property int $ImageID
 * @method Gallery Gallery()
 * @method GalleryImage Image()
 */
class GalleryImageJoin extends BaseJoin implements TestOnly
{
    private static string $table_name = 'SnapshotTest_GalleryImageJoin';

    private static array $has_one = [
        'Gallery' => Gallery::class,
        'Image' => GalleryImage::class,
    ];

    private static array $extensions = [
        Versioned::class,
    ];
}
