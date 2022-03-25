<?php

namespace SilverStripe\Snapshots\Tests;

use DateTime;
use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\BaseJoin;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImageJoin;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

class SnapshotTestAbstract extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        SnapshotItem::class,
        Snapshot::class,
        BlockPage::class,
        Block::class,
        ElementalArea::class,
        Gallery::class,
        GalleryImage::class,
        BaseJoin::class,
        GalleryImageJoin::class,
        SnapshotEvent::class,
    ];

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|Snapshot
     */
    protected function mockSnapshot()
    {
        $mock = $this->getMockBuilder(Snapshot::class)
            ->setMethods([
                'createSnapshotEvent',
                'createSnapshot',
                'addOwnershipChain',
                'applyOrigin',
            ])
            ->getMock();
        Injector::inst()->registerService($mock, Snapshot::class);

        return $mock;
    }

    /**
     * @param DataObject $obj
     * @return Snapshot
     * @throws ValidationException
     * @throws Exception
     */
    protected function createHistory(DataObject $obj): Snapshot
    {
        $snapshot = Snapshot::create();
        $snapshot->addObject($obj);
        $snapshot->applyOrigin($obj);
        $snapshot->write();
        $this->sleep(3);

        return $snapshot;
    }

    /**
     * Virtual "sleep" that doesn't actually slow execution, only advances DBDateTime::now()
     *
     * @param int $minutes
     * @return string
     * @throws Exception
     */
    protected function sleep(int $minutes): string
    {
        $now = DBDatetime::now();
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $now->getValue());
        $date->modify(sprintf('+%d minutes', $minutes));
        $dateTime = $date->format('Y-m-d H:i:s');
        DBDatetime::set_mock_now($dateTime);

        return $dateTime;
    }

    /**
     * @param DataObject $obj
     * @param array $extraObjects
     * @return Snapshot
     * @throws ValidationException
     * @throws Exception
     */
    protected function snapshot(DataObject $obj, array $extraObjects = []): Snapshot
    {
        $obj->write();
        $obj = DataObject::get_by_id($obj->ClassName, $obj->ID, false);
        $snapshot = Snapshot::singleton()->createSnapshot($obj, $extraObjects);
        $snapshot->write();
        $this->sleep(3);

        return $snapshot;
    }

    /**
     * @param DataObject|RecursivePublishable $obj
     * @param array $extraObjects
     * @return Snapshot
     * @throws ValidationException
     */
    protected function publish(DataObject $obj, array $extraObjects = []): Snapshot
    {
        $obj->publishRecursive();
        $obj = DataObject::get_by_id($obj->ClassName, $obj->ID, false);
        $snapshot = Snapshot::singleton()->createSnapshot($obj, $extraObjects);

        foreach ($snapshot->Items() as $item) {
            $item->WasPublished = 1;
        }

        $snapshot->write();

        return $snapshot;
    }

    /**
     * @return array
     * @throws ValidationException
     */
    protected function buildState(): array
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = BlockPage::create();
        $a1->Title = 'A1 Block Page';
        $this->snapshot($a1);

        /** @var BlockPage|SnapshotPublishable $a2 */
        $a2 = BlockPage::create();
        $a2->Title = 'A2 Block Page';
        $this->snapshot($a2);

        /** @var Block|SnapshotPublishable $a1Block1 */
        $a1Block1 = Block::create();
        $a1Block1->Title = 'Block 1 on A1';
        $a1Block1->ParentID = $a1->ID;
        $this->snapshot($a1Block1);

        $a1Block2 = Block::create();
        $a1Block2->Title = 'Block 2 on A1';
        $a1Block2->ParentID = $a1->ID;
        $this->snapshot($a1Block2);

        /** @var Block|SnapshotPublishable $a2Block1 */
        $a2Block1 = Block::create();
        $a2Block1->Title = 'Block 1 on A2';
        $a2Block1->ParentID = $a2->ID;
        $this->snapshot($a2Block1);

        /** @var Gallery|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = Gallery::create();
        $gallery1->Title = 'Gallery 1 on Block 1 on A1';
        $gallery1->BlockID = $a1Block1->ID;
        $this->snapshot($gallery1);

        /** @var Gallery|SnapshotPublishable|Versioned $gallery1 */
        $gallery2 = Gallery::create();
        $gallery2->Title = 'Gallery 2 on Block 1 on A2';
        $gallery2->BlockID = $a2Block1->ID;
        $this->snapshot($gallery2);

        return [
            'a1' => $a1,
            'a2' => $a2,
            'a1Block1' => $a1Block1,
            'a1Block2' => $a1Block2,
            'a2Block1' => $a2Block1,
            'gallery1' => $gallery1,
            'gallery2' => $gallery2,
        ];
    }

    protected function assertItems(SS_List $result, array $objects, string $column = 'ObjectHash'): void
    {
        $hashes = array_unique($result->column($column));
        $this->assertCount(count($objects), $hashes);

        foreach ($objects as $o) {
            $hash = SnapshotHasher::hashObjectForSnapshot($o);
            $this->assertTrue(in_array($hash, $hashes));
        }
    }

    protected function assertObjects(SS_List $result, array $objects): void
    {
        $this->assertItems($result, $objects, 'ObjectHash');
    }

    protected function assertOrigins(SS_List $result, array $objects): void
    {
        $this->assertItems($result, $objects, 'OriginHash');
    }

    protected function assertHashCompare(DataObject $obj1, DataObject $obj2): void
    {
        $this->assertTrue(SnapshotHasher::hashSnapshotCompare($obj1, $obj2));
    }

    protected function assertHashCompareList(array $objs1, array $objs2): void
    {
        $hashes1 = array_map(static function ($o) {
            return SnapshotHasher::hashObjectForSnapshot($o);
        }, $objs1);
        $hashes2 = array_map(static function ($o) {
            return SnapshotHasher::hashObjectForSnapshot($o);
        }, $objs2);
        sort($hashes1);
        sort($hashes2);

        $this->assertEquals($hashes1, $hashes2);
    }
}
