<?php

namespace SilverStripe\Snapshots\Tests;

use Exception;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Versioned\Versioned;

class SnapshotItemTest extends SnapshotTestAbstract
{
    /**
     * @throws ValidationException
     */
    public function testGetItem(): void
    {
        /** @var Block|Versioned $block */
        $block = Block::create();
        $block->write();
        $item = SnapshotItem::create();
        $item->ObjectClass = Block::class;
        $item->ObjectID = $block->ID;
        $item->ObjectVersion = $block->Version * 100;

        $this->assertNull($item->getItem());

        $item->ObjectVersion = $block->Version;

        $this->assertInstanceOf(Block::class, $item->getItem());
        $this->assertEquals($block->ID, $item->getItem()->ID);
        $this->assertEquals($block->Version, $item->getItem()->Version);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function testHydration(): void
    {
        /** @var Block|Versioned $block */
        $block = Block::create();
        $block->write();

        $item = SnapshotItem::create()->hydrateFromDataObject($block);
        $item->write();
        $this->assertEquals(Block::class, $item->ObjectClass);
        $this->assertEquals($block->ID, $item->ObjectID);
        $this->assertEquals($block->Version, $item->ObjectVersion);
        $this->assertEquals(SnapshotPublishable::singleton()->hashObjectForSnapshot($block), $item->ObjectHash);

        $this->assertTrue($item->WasDraft);
        $this->assertFalse($item->WasDeleted);
        $this->assertTrue($item->WasCreated);
        $this->assertFalse($item->WasUnpublished);
        $this->assertFalse($item->WasPublished);

        $block->publishSingle();
        $block->Title = 'foo';
        $block->write();

        $item = SnapshotItem::create()->hydrateFromDataObject($block);
        $this->assertTrue($item->WasDraft);
        $this->assertFalse($item->WasDeleted);
        $this->assertFalse($item->WasCreated);
        $this->assertFalse($item->WasUnpublished);
        $this->assertFalse($item->WasPublished);

        $block->doArchive();
        $item = SnapshotItem::create()->hydrateFromDataObject($block);
        $this->assertFalse($item->WasDraft);
        $this->assertTrue($item->WasDeleted);
        $this->assertFalse($item->WasCreated);
        $this->assertFalse($item->WasUnpublished);
        $this->assertFalse($item->WasPublished);
    }
}
