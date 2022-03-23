<?php

namespace SilverStripe\Snapshots\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Versioned\Versioned;

class SnapshotPublishableTest extends SnapshotTestAbstract
{
    public function testGetAtSnapshot(): void
    {
        /** @var BlockPage $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $firstSnapshot = Snapshot::get()->sort('Created ASC')->first();
        $result = SnapshotPublishable::get_at_snapshot(BlockPage::class, $a1->ID, $firstSnapshot->Created);
        $param = $result->getSourceQueryParam('Versioned.date');
        $this->assertNotNull($param);
        $this->assertEquals($firstSnapshot->Created, $param);
    }

    /**
     * @throws ValidationException
     */
    public function testGetAtLastSnapshot(): void
    {
        /** @var BlockPage $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $a1->Title = 'changed';
        $a1->write();

        $result = SnapshotPublishable::get_at_last_snapshot(BlockPage::class, $a1->ID);
        $this->assertNotNull($result);
        $this->assertEquals('A1 Block Page', $result->Title);
    }

    public function testGetLastSnapshotItem(): void
    {
        /** @var BlockPage|Versioned $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $a1->Title = 'changed';
        $this->snapshot($a1);

        /** @var DataObject|Versioned $result */
        $result = SnapshotPublishable::get_last_snapshot_item(BlockPage::class, $a1->ID);
        $this->assertNotNull($result);
        $this->assertEquals($a1->Version, $result->Version);
    }

    public function testGetSnapshots(): void
    {
        $state = $this->buildState();
        $snapshots = SnapshotPublishable::getSnapshots();
        $this->assertOrigins($snapshots, $state);
    }

    public function testGetRelevantSnapshots(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var BlockPage|SnapshotPublishable $a2 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $a1->Title = 'changed';
        $this->snapshot($a1);

        $a1->Title = 'changed again';
        $this->snapshot($a1);

        $a2->Title = 'a2 changed';
        $this->snapshot($a2);

        $result = $a1->getRelevantSnapshots();
        $this->assertOrigins(
            $result,
            [
                $a1,
                $a1Block1,
                $a1Block2,
                $gallery1,
            ]
        );

        $result = $a2->getRelevantSnapshots();
        $this->assertOrigins(
            $result,
            [
                $a2,
                $a2Block1,
                $gallery2,
            ]
        );
    }

    public function testGetSnapshotsSinceVersion(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var Block $a1Block1 */
        /** @var Block $a1Block2 */
        /** @var Block $a2Block1 */
        /** @var Gallery $gallery1 */
        /** @var Gallery $gallery2 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $this->publish($a1);
        $fromVersion = Versioned::get_versionnumber_by_stage(BlockPage::class, Versioned::LIVE, $a1->ID);

        $a1Block1->Title = 'changed';
        $this->snapshot($a1Block1);

        $a1Block2->Title = 'changed';
        $this->snapshot($a1Block2);

        $this->publish($a1);

        $gallery1->Title = 'changed';
        $this->snapshot($gallery1);

        $a2Block1->Title = 'changed';
        $this->snapshot($a2Block1);

        $gallery2->Title = 'changed';
        $this->snapshot($gallery2);

        $result = $a1->getSnapshotsSinceVersion($fromVersion);
        $this->assertOrigins(
            $result,
            [
                $a1,
                $a1Block1,
                $a1Block2,
                $gallery1,
            ]
        );

        $result = $a1->getSnapshotsSinceLastPublish();
        $this->assertOrigins(
            $result,
            [
                $gallery1,
            ]
        );
    }

    public function testHasOwnedModifications(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var BlockPage|SnapshotPublishable $a2 */
        /** @var Block $a1Block1 */
        /** @var Block $a1Block2 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $this->publish($a1);
        $this->publish($a2);

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->Title = 'changed';
        $this->snapshot($a1Block1);

        $a1Block2->Title = 'changed';
        $this->snapshot($a1Block2);

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());
    }

    public function testPublishableItems(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var BlockPage|SnapshotPublishable $a2 */
        /** @var Block $a1Block1 */
        /** @var Block $a2Block1 */
        /** @var Gallery $gallery1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $this->publish($a1);
        $this->publish($a2);

        $this->assertEquals(0, $a1->getPublishableItemsCount());
        $this->assertEquals(0, $a2->getPublishableItemsCount());
        $this->assertEquals(0, $a1->getPublishableObjects()->count());
        $this->assertEquals(0, $a2->getPublishableObjects()->count());

        $a1Block1->Title = 'changed';
        $this->snapshot($a1Block1);

        $gallery1->Title = 'changed';
        $this->snapshot($gallery1);

        $this->assertEquals(2, $a1->getPublishableItemsCount());
        $this->assertEquals(2, $a1->getPublishableObjects()->count());
        $ids = [$a1Block1->ID, $gallery1->ID];
        $classes = [$a1Block1->ClassName, $gallery1->ClassName];
        $this->assertEquals($ids, $a1->getPublishableObjects()->column('ID'));
        $this->assertEquals($classes, $a1->getPublishableObjects()->column('ClassName'));
        $this->assertEquals(0, $a2->getPublishableItemsCount());
        $this->assertEquals(0, $a2->getPublishableObjects()->count());

        $a2Block1->Title = 'changed';
        $this->snapshot($a2Block1);

        $this->assertEquals(2, $a1->getPublishableItemsCount());
        $this->assertEquals(2, $a1->getPublishableObjects()->count());
        $ids = [$a1Block1->ID, $gallery1->ID];
        $classes = [$a1Block1->ClassName, $gallery1->ClassName];
        $this->assertEquals($ids, $a1->getPublishableObjects()->column('ID'));
        $this->assertEquals($classes, $a1->getPublishableObjects()->column('ClassName'));

        $this->assertEquals(1, $a2->getPublishableItemsCount());
        $this->assertEquals(1, $a2->getPublishableObjects()->count());
        $ids = [$a2Block1->ID];
        $classes = [$a2Block1->ClassName];
        $this->assertEquals($ids, $a2->getPublishableObjects()->column('ID'));
        $this->assertEquals($classes, $a2->getPublishableObjects()->column('ClassName'));
    }

    public function testGetRelationTracking(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var Block|Versioned $a1Block1 */
        /** @var Block|Versioned $a1Block2 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $this->assertEmpty($a1->getRelationTracking());
        Config::modify()->set(BlockPage::class, 'snapshot_relation_tracking', ['Blocks', 'Fail']);
        $result = $a1->getRelationTracking();

        $this->assertArrayHasKey('Blocks', $result);
        $this->assertArrayNotHasKey('Fail', $result);
        $this->assertEquals($a1Block1->Version, $result['Blocks'][$a1Block1->ID]);
        $this->assertEquals($a1Block2->Version, $result['Blocks'][$a1Block2->ID]);
    }

    public function testPreviousSnapshotItem(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $a1->Title = 'changed';
        $this->snapshot($a1);
        $version = Versioned::get_versionnumber_by_stage(BlockPage::class, Versioned::DRAFT, $a1->ID);
        $item = $a1->getPreviousSnapshotItem();
        $this->assertEquals($version, $item->Version);
        $this->assertHashCompare($a1, $item->getItem());
    }

    /**
     * @throws ValidationException
     */
    public function testPreviousSnapshot(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $a1->Title = 'changed';
        $this->snapshot($a1);

        $a1->Title = 'changed again';
        $a1->write();

        $result = $a1->atPreviousSnapshot(function ($date) use ($a1) {
            $this->assertNotNull($date);

            return DataObject::get_by_id(BlockPage::class, $a1->ID);
        });

        $this->assertNotNull($result);
        $this->assertEquals('changed', $result->Title);

        $result = $a1->getPreviousSnapshotVersion();
        $this->assertNotNull($result);
        $this->assertEquals('changed', $result->Title);
    }

    /**
     * @throws ValidationException
     */
    public function testIsModifiedSinceLastSnapshot()
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $this->assertFalse($a1->isModifiedSinceLastSnapshot());
        $a1->Title = 'changed';
        $a1->write();

        /** @var BlockPage|SnapshotPublishable $obj */
        $obj = DataObject::get_by_id(BlockPage::class, $a1->ID);

        $this->assertTrue($obj->isModifiedSinceLastSnapshot());
    }

    public function testGetIntermediaryObjects(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var Block|SnapshotPublishable $a1Block1 */
        /** @var Gallery|SnapshotPublishable $gallery1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $objs = $gallery1->getIntermediaryObjects();
        $this->assertHashCompareList([$a1Block1, $a1], $objs);
    }

    /**
     * @throws ValidationException
     */
    public function testGetRelationDiffs(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        /** @var Block|SnapshotPublishable $a1Block1 */
        /** @var Block|SnapshotPublishable $a1Block2 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        Config::modify()->set(BlockPage::class, 'snapshot_relation_tracking', ['Blocks']);

        $this->assertCount(1, $a1->getRelationDiffs());

        // Change one
        $a1Block1->Title = 'changed';
        $a1Block1->write();

        $diffs = $a1->getRelationDiffs(false);
        $diff = $diffs[0];
        $this->assertTrue($diff->hasChanges());
        $this->assertTrue($a1->hasRelationChanges());
        $this->assertEmpty($diff->getAdded());
        $this->assertEmpty($diff->getRemoved());
        $this->assertCount(1, $diff->getChanged());
        $this->assertEquals([$a1Block1->ID], $diff->getChanged());

        // Add two
        $block1 = Block::create(['Title' => 'new one 1', 'ParentID' => $a1->ID]);
        $block1->write();
        $block2 = Block::create(['Title' => 'new one 2', 'ParentID' => $a1->ID]);
        $block2->write();

        $diffs = $a1->getRelationDiffs(false);
        $diff = $diffs[0];
        $this->assertTrue($diff->hasChanges());
        $this->assertTrue($a1->hasRelationChanges());
        $this->assertCount(2, $diff->getAdded());
        $this->assertEquals([$block1->ID, $block2->ID], $diff->getAdded());
        $this->assertEmpty($diff->getRemoved());
        $this->assertCount(1, $diff->getChanged());
        $this->assertEquals([$a1Block1->ID], $diff->getChanged());

        // Remove one
        $id = $a1Block2->ID;
        $a1Block2->delete();

        $diffs = $a1->getRelationDiffs(false);
        $diff = $diffs[0];
        $this->assertTrue($diff->hasChanges());
        $this->assertTrue($a1->hasRelationChanges());
        $this->assertCount(2, $diff->getAdded());
        $this->assertEquals([$block1->ID, $block2->ID], $diff->getAdded());
        $this->assertCount(1, $diff->getRemoved());
        $this->assertEquals([$id], $diff->getRemoved());
        $this->assertCount(1, $diff->getChanged());
        $this->assertEquals([$a1Block1->ID], $diff->getChanged());
    }

    public function testGetPreviousVersion(): void
    {
        /** @var BlockPage|SnapshotPublishable $a1 */
        [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2] = $this->buildState();
        $originalTitle = $a1->Title;
        $a1->Title = 'changed';
        $this->snapshot($a1);

        $this->assertEquals('changed', $a1->Title);
        $prevVer = $a1->getPreviousVersion();
        $this->assertNotNull($prevVer);
        $this->assertEquals($originalTitle, $prevVer->Title);
    }
}
