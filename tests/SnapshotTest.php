<?php


namespace SilverStripe\Snapshots\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;

require_once(__DIR__ . '/SnapshotTestAbstract.php');
class SnapshotTest extends SnapshotTestAbstract
{
    public function testGetOriginItem()
    {
        $snapshot = Snapshot::create();
        $snapshot->write();
        $item = SnapshotItem::create([
            'ObjectClass' => Block::class,
            'ObjectID' => 5,
            'SnapshotID' => $snapshot->ID,
        ]);
        $item->write();
        $item = SnapshotItem::create([
            'ObjectClass' => Block::class,
            'ObjectID' => 7,
            'SnapshotID' => $snapshot->ID,
        ]);
        $item->write();
        $snapshot->OriginClass = $item->ObjectClass;
        $snapshot->OriginID = $item->ObjectID;
        $snapshot->write();

        $this->assertCount(2, $snapshot->Items());
        $origin = $snapshot->getOriginItem();
        $this->assertNotNull($origin);
        $this->assertEquals($item->ObjectClass, $origin->ObjectClass);
        $this->assertEquals($item->ObjectID, $origin->ObjectID);
    }

    public function testAddObjectLimit()
    {
        Config::modify()->set(Snapshot::class, 'item_limit', 5);
        $snapshot = Snapshot::create();
        for ($i = 0; $i < 10; $i++) {
            $b = Block::create();
            $b->write();
            $snapshot->addObject($b);
        }
        $this->assertCount(5, $snapshot->Items());
    }

    public function testAddObjectDuplication()
    {
        $snapshot = Snapshot::create();
        $block1 = Block::create();
        $block1->write();
        $block2 = Block::create();
        $block2->write();
        $block3 = Block::create();
        $block3->write();

        $snapshot->addObject($block1);
        $snapshot->addObject($block1);
        $snapshot->addObject($block2);
        $snapshot->addObject($block3);
        $snapshot->addObject($block2);
        $snapshot->write();

        $this->assertCount(3, $snapshot->Items());
        $ids = $snapshot->Items()->column('ObjectID');
        sort($ids);
        $expected = [$block1->ID, $block2->ID, $block3->ID];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    public function testAddObjectAsSnapshotItem()
    {
        $snapshot = Snapshot::create();
        $block1 = Block::create();
        $block1->write();
        $item1 = SnapshotItem::create()->hydrateFromDataObject($block1);
        $block2 = Block::create();
        $block2->write();
        $item2 = SnapshotItem::create()->hydrateFromDataObject($block2);
        $block3 = Block::create();
        $block3->write();
        $item3 = SnapshotItem::create()->hydrateFromDataObject($block3);

        $snapshot->addObject($item1);
        $snapshot->addObject($item1);
        $snapshot->addObject($item2);
        $snapshot->addObject($item3);
        $snapshot->addObject($item2);
        $snapshot->write();

        $this->assertCount(3, $snapshot->Items());
        $ids = $snapshot->Items()->column('ObjectID');
        sort($ids);
        $expected = [$block1->ID, $block2->ID, $block3->ID];
        sort($expected);
        $this->assertEquals($expected, $ids);
    }

    public function testGetOriginVersion()
    {
        $snapshot = Snapshot::create();
        $snapshot->write();
        $block1 = Block::create();
        $block1->write();
        $item = SnapshotItem::create([
            'ObjectClass' => $block1->baseClass(),
            'ObjectID' => $block1->ID,
            'SnapshotID' => $snapshot->ID,
            'Version' => $block1->Version,
        ]);
        $item->write();
        $block2 = Block::create(['Title' => 'Original title']);
        $block2->write();
        $expectedVersion = $block2->Version;
        $block2->Title = 'changed title';
        $block2->write();

        $item = SnapshotItem::create([
            'ObjectClass' => $block2->baseClass(),
            'ObjectID' => $block2->ID,
            'SnapshotID' => $snapshot->ID,
            'Version' => $expectedVersion,
        ]);
        $item->write();
        $snapshot->OriginClass = $item->ObjectClass;
        $snapshot->OriginID = $item->ObjectID;
        $snapshot->write();

        $v = $snapshot->getOriginVersion();
        $this->assertNotNull($v);
        $this->assertEquals('changed title', $block2->Title);
        $this->assertEquals('Original title', $v->Title);
    }

    public function testCreateSnapshotNoRelations()
    {
        list($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $gallery1->Title = 'changed';
        $snapshot = $this->snapshot($gallery1);
        $this->assertCount(3, $snapshot->Items());
        $this->assertHashCompare($gallery1, $snapshot->getOriginItem()->getItem());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $gallery1,
                $a1Block1,
                $a1,
            ]
        );
        $a2Block1->Title = 'changed';
        $snapshot = $this->snapshot($a1Block1);
        $this->assertCount(2, $snapshot->Items());
        $this->assertHashCompare($a1Block1, $snapshot->getOriginItem()->getItem());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $a1Block1,
                $a1,
            ]
        );
        $a1->Title = 'changed';
        $snapshot = $this->snapshot($a1);
        $this->assertCount(1, $snapshot->Items());
        $this->assertHashCompare($a1, $snapshot->getOriginItem()->getItem());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $a1,
            ]
        );
        $a1->Title = 'changed again';
        $extraObject = BlockPage::create(['Title' => 'Extra page']);
        $extraObject->write();
        $snapshot = $this->snapshot($a1, [$extraObject]);
        $this->assertCount(2, $snapshot->Items());
        $this->assertHashCompare($a1, $snapshot->getOriginItem()->getItem());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $a1,
                $extraObject,
            ]
        );
    }

    public function testCreateSnapshotWithImplicitModifications()
    {
        list($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $image = GalleryImage::create();
        $image->write();

        $gallery1->Images()->add($image);
        $snapshot = $this->snapshot($gallery1);

        $this->assertInstanceOf(SnapshotItem::class, $snapshot->getOriginItem());
        $this->assertCount(3, $snapshot->Items());

        Config::modify()->set(Gallery::class, 'snapshot_relation_tracking', ['Images']);
        $image1 = GalleryImage::create();
        $image1->write();
        $image2 = GalleryImage::create();
        $image2->write();

        $gallery1->Images()->add($image1);
        $gallery1->Images()->add($image2);

        $snapshot = $this->snapshot($gallery1);

        $event = $snapshot->getOriginItem()->getItem();
        $this->assertInstanceOf(SnapshotEvent::class, $event);
        $this->assertCount(6, $snapshot->Items());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $image1,
                $image2,
                $gallery1,
                $a1Block1,
                $a1,
                $event,
            ]
        );
        $this->assertRegExp('/^Added 2/', $event->Title);

        $gallery1->Images()->removeByID($image2->ID);

        $snapshot = $this->snapshot($gallery1);
        $event = $snapshot->getOriginItem()->getItem();
        $this->assertInstanceOf(SnapshotEvent::class, $event);
        $this->assertCount(5, $snapshot->Items());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $image2,
                $gallery1,
                $a1Block1,
                $a1,
                $event,
            ]
        );
        $name = $gallery1->i18n_singular_name();
        $this->assertRegExp('/^Removed ' . $name . '/', $event->Title);

        // Mixed changes
        $gallery1->Title = 'a whole new gallery';
        $image = GalleryImage::create();
        $image->write();
        $gallery1->Images()->add($image);
        $snapshot = $this->snapshot($gallery1);
        $origin = $snapshot->getOriginItem()->getItem();
        $this->assertInstanceOf(SnapshotEvent::class, $origin);
        $this->assertRegExp('/Added ' . $name . '/', $origin->Title);
    }

    public function testCreateSnapshotEvent()
    {
        $snapshot = Snapshot::singleton()->createSnapshotEvent('test event');
        $snapshot->write();
        $event = $snapshot->getOriginItem()->getItem();
        $this->assertInstanceOf(SnapshotEvent::class, $event);
        $this->assertEquals('test event', $event->Title);
        $this->assertCount(1, $snapshot->Items());

        $block1 = Block::create();
        $block1->write();
        $block2 = Block::create();
        $block2->write();

        $snapshot = Snapshot::singleton()->createSnapshotEvent('test event 2', [$block1, $block2]);
        $snapshot->write();
        $event = $snapshot->getOriginItem()->getItem();
        $this->assertInstanceOf(SnapshotEvent::class, $event);
        $this->assertEquals('test event 2', $event->Title);
        $this->assertCount(3, $snapshot->Items());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $event,
                $block1,
                $block2
            ]
        );
    }

    public function testAddOwnershipChain()
    {
        list($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $snapshot = Snapshot::create();
        $this->assertEmpty($snapshot->Items());
        $snapshot->addOwnershipChain($gallery1);
        $snapshot->write();
        $this->assertCount(3, $snapshot->Items());
        $this->assertObjects(
            $snapshot->Items(),
            [
                $gallery1,
                $a1Block1,
                $a1,
            ]
        );
    }

    public function testApplyOrigin()
    {
        $snapshot = Snapshot::create();
        $this->assertFalse($snapshot->getOriginItem());
        $block = Block::create();
        $block->write();
        $snapshot->applyOrigin($block);
        $this->assertNotFalse($snapshot->getOriginItem());
        $item = $snapshot->getOriginItem()->getItem();
        $this->assertNotNull($item);
        $this->assertHashCompare($item, $block);
    }
}
