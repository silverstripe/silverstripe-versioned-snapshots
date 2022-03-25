<?php

namespace SilverStripe\Snapshots\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\RelationDiffer;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Versioned\Versioned;

class RelationDifferTest extends SapphireTest
{
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        Block::class,
    ];

    public function testDiffRemoved(): void
    {
        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [
                '1' => 5,
                '2' => 12,
                '5' => 8,
            ],
            []
        );
        $this->assertTrue($differ->hasChanges());
        $this->assertEmpty($differ->getChanged());
        $this->assertEmpty($differ->getAdded());
        $this->assertEquals(['1','2','5'], $differ->getRemoved());
    }

    public function testDiffAdded(): void
    {
        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [],
            [
                '1' => 5,
                '2' => 12,
                '5' => 8,
            ]
        );
        $this->assertTrue($differ->hasChanges());
        $this->assertEmpty($differ->getChanged());
        $this->assertEmpty($differ->getRemoved());
        $this->assertEquals(['1','2','5'], $differ->getAdded());
    }

    public function testDiffChanged(): void
    {
        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [
                '1' => 5,
                '2' => 12,
                '5' => 8,
                '9' => 3,
                '16' => 20,
            ],
            [
                '5' => 8,
                '2' => 11,
                '1' => 6,
                '16' => 21,
                '9' => 4,
            ]
        );
        $this->assertTrue($differ->hasChanges());
        $this->assertEmpty($differ->getAdded());
        $this->assertEmpty($differ->getRemoved());
        $changed = $differ->getChanged();
        sort($changed);
        $this->assertEquals(['1','9','16'], $changed);
    }

    public function testDiffMixed(): void
    {
        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [
                '1' => 5,
                '2' => 12,
                '5' => 8,
                '9' => 3,
                '16' => 20,
            ],
            [
                '5' => 9,
                '2' => 11,
                '11' => 55,
                '9' => 4,
                '44' => 3,
            ]
        );
        $this->assertTrue($differ->hasChanges());
        $added = $differ->getAdded();
        sort($added);
        $this->assertEquals(['11', '44'], $added);
        $this->assertTrue($differ->isAdded(11));
        $this->assertFalse($differ->isAdded(1));
        $removed = $differ->getRemoved();
        sort($removed);
        $this->assertEquals(['1', '16'], $removed);
        $this->assertTrue($differ->isRemoved(16));
        $this->assertFalse($differ->isRemoved(11));

        $changed = $differ->getChanged();
        sort($changed);
        $this->assertEquals(['5', '9'], $changed);
        $this->assertTrue($differ->isChanged(5));
        $this->assertFalse($differ->isChanged(16));
    }

    public function testNoChanges(): void
    {
        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [
                '1' => 5,
                '2' => 12,
                '5' => 8,
                '11' => 55,
                '9' => 3,
                '44' => 3,
                '16' => 20,
            ],
            [
                '5' => 8,
                '2' => 12,
                '11' => 55,
                '16' => 20,
                '1' => 5,
                '9' => 3,
                '44' => 3,
            ]
        );

        $this->assertFalse($differ->hasChanges());
        $this->assertEmpty($differ->getAdded());
        $this->assertEmpty($differ->getChanged());
        $this->assertEmpty($differ->getRemoved());
    }

    /**
     * @throws ValidationException
     */
    public function testGetRecords(): void
    {
        /** @var Block|Versioned $block1 */
        $block1 = Block::create();
        $block1->write();
        /** @var Block|Versioned $block2 */
        $block2 = Block::create();
        $block2->write();
        /** @var Block|Versioned $block3 */
        $block3 = Block::create();
        $block3->write();

        $differ = RelationDiffer::create(
            Block::class,
            'has_many',
            [
                $block3->ID => $block3->Version,
            ],
            [
                $block1->ID => $block1->Version,
                $block2->ID => $block2->Version,
            ]
        );

        $this->assertTrue($differ->hasChanges());
        $this->assertCount(3, $differ->getRecords());
        $expected = [$block1->ID, $block2->ID, $block3->ID];
        sort($expected);

        $actual = array_map(static function ($record) {
            return $record->ID;
        }, $differ->getRecords());
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function testException(): void
    {
        $this->expectException('InvalidArgumentException');
        RelationDiffer::create(static::class, 'test');
    }
}
