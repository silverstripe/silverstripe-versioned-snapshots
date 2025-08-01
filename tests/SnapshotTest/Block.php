<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\Versioned;

/**
 * @property int $ParentID
 * @method BlockPage Parent()
 * @method HasManyList<Gallery> Galleries()
 */
class Block extends DataObject implements TestOnly
{
    private static string $table_name = 'SnapshotTest_Block';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => BlockPage::class,
    ];

    private static array $has_many = [
        'Galleries' => Gallery::class,
    ];

    private static array $extensions = [
        Versioned::class,
    ];

    private static array $owns = [
        'Galleries',
    ];
}
