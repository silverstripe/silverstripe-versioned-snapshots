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
    /**
     * @var array
     */
    private static $db = [
        'URL' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'Gallery' => Gallery::class,
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
    private static $table_name = 'SnapshotTest_GalleryImage';
}
