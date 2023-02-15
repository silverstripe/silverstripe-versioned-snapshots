<?php

namespace SilverStripe\Snapshots\Tests\SnapshotTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @property int $ParentID
 * @method HasManyList|Gallery[] Galleries()
 */
class Block extends DataObject implements TestOnly
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
        'Parent' => BlockPage::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Galleries' => Gallery::class,
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
        'Galleries',
    ];

    /**
     * @var string
     */
    private static $table_name = 'SnapshotTest_Block';
}
