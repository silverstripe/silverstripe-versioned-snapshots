<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $URL
 */
class GalleryImage extends DataObject implements TestOnly
{
    private static string $table_name = 'SnapshotTest_GalleryImage';

    private static array $db = [
        'URL' => 'Varchar',
    ];

    private static array $belongs_many_many = [
        'Gallery' => Gallery::class,
    ];

    private static array $extensions = [
        Versioned::class,
    ];
}
