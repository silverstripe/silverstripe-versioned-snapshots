<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @property int $BlockID
 * @method ManyManyThroughList|Image[] Images()
 */
class Gallery extends DataObject implements TestOnly
{
    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Block' => Block::class,
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Images' => [
            'through' => GalleryImageJoin::class,
            'from' => 'Gallery',
            'to' => 'Image',
        ],
    ];

    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Images',
    ];

    /**
     * @var string
     */
    private static $table_name = 'SnapshotTest_Gallery';
}
