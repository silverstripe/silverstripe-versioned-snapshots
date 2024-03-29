<?php

namespace SilverStripe\Snapshots\Tests;

use Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Versioned\Versioned;

class SnapshotPublishableTest extends SnapshotTestAbstract
{
    /**
     * @throws ValidationException
     */
    public function testGetAtSnapshot(): void
    {
        $state = $this->buildState();
        /** @var BlockPage $a1 */
        $a1 = $state['a1'];

        $firstSnapshot = Snapshot::get()->sort('Created ASC')->first();
        $result = SnapshotPublishable::singleton()
            ->getAtSnapshotByClassAndId(BlockPage::class, $a1->ID, $firstSnapshot->Created);

        $param = $result->getSourceQueryParam('Versioned.date');
        $this->assertNotNull($param);
        $this->assertEquals($firstSnapshot->Created, $param);
    }

    /**
     * @throws ValidationException
     */
    public function testGetAtLastSnapshot(): void
    {
        $state = $this->buildState();
        /** @var BlockPage $a1 */
        $a1 = $state['a1'];

        $a1->Title = 'changed';
        $a1->write();

        $result = SnapshotPublishable::singleton()->getAtLastSnapshotByClassAndId(BlockPage::class, $a1->ID);
        $this->assertNotNull($result);
        $this->assertEquals('A1 Block Page', $result->Title);
    }

    /**
     * @throws ValidationException
     */
    public function testGetLastSnapshotItem(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|Versioned $a1 */
        $a1 = $state['a1'];
        $a1->Title = 'changed';
        $this->snapshot($a1);

        /** @var SnapshotItem $result */
        $result = SnapshotPublishable::singleton()->getLastSnapshotItemByClassAndId(BlockPage::class, $a1->ID);
        $this->assertNotNull($result);
        $this->assertEquals($a1->Version, $result->ObjectVersion);
    }

    /**
     * @throws ValidationException
     */
    public function testGetSnapshots(): void
    {
        $state = $this->buildState();
        $snapshots = SnapshotPublishable::singleton()->getSnapshots();
        $this->assertOrigins($snapshots, $state);
    }

    /**
     * @throws ValidationException
     */
    public function testGetRelevantSnapshots(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var BlockPage|SnapshotPublishable $a2 */
        $a2 = $state['a2'];
        /** @var Block $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block $a1Block2 */
        $a1Block2 = $state['a1Block2'];
        /** @var Block $a2Block1 */
        $a2Block1 = $state['a2Block1'];
        /** @var Gallery $gallery1 */
        $gallery1 = $state['gallery1'];
        /** @var Gallery $gallery2 */
        $gallery2 = $state['gallery2'];
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

    /**
     * @throws ValidationException
     */
    public function testGetSnapshotsSinceVersion(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var Block $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block $a1Block2 */
        $a1Block2 = $state['a1Block2'];
        /** @var Block $a2Block1 */
        $a2Block1 = $state['a2Block1'];
        /** @var Gallery $gallery1 */
        $gallery1 = $state['gallery1'];
        /** @var Gallery $gallery2 */
        $gallery2 = $state['gallery2'];
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

    /**
     * @throws ValidationException
     */
    public function testHasOwnedModifications(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var BlockPage|SnapshotPublishable $a2 */
        $a2 = $state['a2'];
        /** @var Block $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block $a1Block2 */
        $a1Block2 = $state['a1Block2'];
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

    /**
     * @throws ValidationException
     */
    public function testPublishableItems(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var BlockPage|SnapshotPublishable $a2 */
        $a2 = $state['a2'];
        /** @var Block $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block $a2Block1 */
        $a2Block1 = $state['a2Block1'];
        /** @var Gallery $gallery1 */
        $gallery1 = $state['gallery1'];
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

    /**
     * @throws ValidationException
     */
    public function testGetRelationTracking(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var Block|Versioned $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block|Versioned $a1Block2 */
        $a1Block2 = $state['a1Block2'];
        $this->assertEmpty($a1->getRelationTracking());
        Config::modify()->set(BlockPage::class, 'snapshot_relation_tracking', ['Blocks', 'Fail']);
        $result = $a1->getRelationTracking();

        $this->assertArrayHasKey('Blocks', $result);
        $this->assertArrayNotHasKey('Fail', $result);
        $this->assertEquals($a1Block1->Version, $result['Blocks'][$a1Block1->ID]);
        $this->assertEquals($a1Block2->Version, $result['Blocks'][$a1Block2->ID]);
    }

    /**
     * @throws ValidationException
     */
    public function testPreviousSnapshotItem(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        $a1->Title = 'changed';
        $this->snapshot($a1);
        $version = Versioned::get_versionnumber_by_stage(BlockPage::class, Versioned::DRAFT, $a1->ID);
        $item = $a1->getPreviousSnapshotItem();
        $this->assertEquals($version, $item->ObjectVersion);
        $this->assertHashCompare($a1, $item->getItem());
    }

    /**
     * @throws ValidationException
     */
    public function testPreviousSnapshot(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
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
    public function testIsModifiedSinceLastSnapshot(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        $this->assertFalse($a1->isModifiedSinceLastSnapshot());
        $a1->Title = 'changed';
        $a1->write();

        /** @var BlockPage|SnapshotPublishable $obj */
        $obj = DataObject::get_by_id(BlockPage::class, $a1->ID);

        $this->assertTrue($obj->isModifiedSinceLastSnapshot());
    }

    /**
     * @throws ValidationException
     */
    public function testGetIntermediaryObjects(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var Block|SnapshotPublishable $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Gallery|SnapshotPublishable $gallery1 */
        $gallery1 = $state['gallery1'];
        $objs = $gallery1->getIntermediaryObjects();
        $this->assertHashCompareList([$a1Block1, $a1], $objs);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function testGetRelationDiffs(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        /** @var Block|SnapshotPublishable $a1Block1 */
        $a1Block1 = $state['a1Block1'];
        /** @var Block|SnapshotPublishable $a1Block2 */
        $a1Block2 = $state['a1Block2'];
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
        $block1 = Block::create();
        $block1->Title = 'new one 1';
        $block1->ParentID = $a1->ID;
        $block1->write();
        $block2 = Block::create();
        $block2->Title = 'new one 2';
        $block2->ParentID = $a1->ID;
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

    /**
     * @throws ValidationException
     */
    public function testGetPreviousVersion(): void
    {
        $state = $this->buildState();
        /** @var BlockPage|SnapshotPublishable $a1 */
        $a1 = $state['a1'];
        $originalTitle = $a1->Title;
        $a1->Title = 'changed';
        $this->snapshot($a1);

        $this->assertEquals('changed', $a1->Title);
        $prevVer = $a1->getPreviousVersion();
        $this->assertNotNull($prevVer);
        $this->assertEquals($originalTitle, $prevVer->Title);
    }
}
