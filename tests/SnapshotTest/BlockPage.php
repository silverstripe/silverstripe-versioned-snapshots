<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\Versioned;

/**
 * @method HasManyList<Block> Blocks()
 */
class BlockPage extends DataObject implements TestOnly
{
    private static string $table_name = 'SnapshotTest_BlockPage';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_many = [
        'Blocks' => Block::class,
    ];

    private static array $owns = [
        'Blocks',
    ];

    private static array $extensions = [
        Versioned::class,
    ];
}
