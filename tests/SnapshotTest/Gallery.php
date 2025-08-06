<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Versioned\Versioned;

/**
 * @property int $BlockID
 * @method Block Block()
 * @method ManyManyThroughList<Image> Images()
 */
class Gallery extends DataObject implements TestOnly
{
    private static string $table_name = 'SnapshotTest_Gallery';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Block' => Block::class,
    ];

    private static array $many_many = [
        'Images' => [
            'through' => GalleryImageJoin::class,
            'from' => 'Gallery',
            'to' => 'Image',
        ],
    ];

    private static array $extensions = [
        Versioned::class,
    ];

    private static array $owns = [
        'Images',
    ];
}
