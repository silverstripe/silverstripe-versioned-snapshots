<?php


namespace SilverStripe\Snapshots\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\ActivityEntry;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImageJoin;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

class SnapshotTest extends FunctionalTest
{

    protected $usesDatabase = true;

    protected $usesTransactions = false;

    protected static $extra_dataobjects = [
        BlockPage::class,
        Block::class,
        Gallery::class,
        GalleryImage::class,
        GalleryImageJoin::class,
        ChangeSetItem::class,
    ];

    public function testSnapshotFundamentals()
    {
        // Model:
        // BlockPage
        //  -> (has_many/owns) -> Blocks
        //      -> (has_many/owns) -> Gallery
        //          -> (many_many/owns) -> GalleryImage

        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $a1->write();
        $a1->publishRecursive();

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $a2->write();
        $a2->publishRecursive();

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        $a1->Title = 'A1 Block Page -- changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $a1Block1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will publish its two blocks
        $this->assertTrue($a1->hasOwnedModifications());

        // Since publishing:
        //  two new blocks created
        //  one of those was then modified.
        $activity = $a1->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
            ]
        );

        // Testing third level
        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will now publish two blocks and a gallery
        $this->assertTrue($a1->hasOwnedModifications());

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created.
        $activity = $a1->getActivityFeed();
        $this->assertCount(4, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
            ]
        );

        $gallery1->Title = 'Gallery 1 on Block 1 on A1 -- changed';
        $gallery1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will still publish two blocks and a gallery
        $this->assertTrue($a1->hasOwnedModifications());

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created
        //  one gallery was modified
        $activity = $a1->getActivityFeed();
        $this->assertCount(5, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
            ]
        );

        // Testing many_many
        /* @var DataObject|SnapshotPublishable $galleryItem1 */
        $galleryItem1 = new GalleryImage(['URL' => '/gallery/image/1']);
        /* @var DataObject|SnapshotPublishable $galleryItem2 */
        $galleryItem2 = new GalleryImage(['URL' => '/gallery/image/2']);

        $gallery1->Images()->add($galleryItem1);
        $gallery1->Images()->add($galleryItem2);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new) *
        //          image2 (draft, new) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $activity = $a1->getActivityFeed();
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1],
                [$galleryItem2, ActivityEntry::ADDED, $gallery1],
            ]
        );

        /* @var DataObject|SnapshotPublishable $gallery1a */
        $gallery1a = new Gallery(['Title' => 'Gallery 1 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery1a->write();
        $gallery1a->Images()->add($galleryItem1);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new)
        //          image2 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new) *
        //          image1 (draft, new) *

        $this->assertTrue($a2->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, ActivityEntry::CREATED],
                [$gallery1a, ActivityEntry::CREATED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1a],
            ]
        );

        $galleryItem1->URL = '/changed/url';
        $galleryItem1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, modified) *
        //          image2 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new)
        //          image1 (draft, modified) *
        $this->assertTrue($a2->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(4, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, ActivityEntry::CREATED],
                [$gallery1a, ActivityEntry::CREATED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1a],
                [$galleryItem1, ActivityEntry::MODIFIED],
            ]
        );

        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(8, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1],
                [$galleryItem2, ActivityEntry::ADDED, $gallery1],
                [$galleryItem1, ActivityEntry::MODIFIED],
            ]
        );
        // Publish, and clear the slate
        $a1->publishRecursive();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        $a2->publishRecursive();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());
    }

    public function testRevertChanges()
    {
        /* @var DataObject|Versioned|SnapshotPublishable $a1 */
        /* @var DataObject|Versioned|SnapshotPublishable $gallery1 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $gallery1->Title = 'Gallery 1 is changed';
        $gallery1->write();

        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$gallery1, ActivityEntry::MODIFIED],
        ]);

        $gallery1->doRevertToLive();

        $this->assertEmpty($a1->getActivityFeed());
    }

    public function testIntermediaryObjects()
    {
        /* @var DataObject|Versioned|SnapshotPublishable $a1 */
        /* @var DataObject|Versioned|SnapshotPublishable $a2 */
        /* @var DataObject|Versioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|Versioned|SnapshotPublishable $gallery1 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $gallery1->Title = 'Gallery 1 changed';
        $gallery1->write();
        // Intermediate ownership
        $this->assertTrue($a1Block1->hasOwnedModifications());

        $a1->Title = 'A1 changed';
        $a1->write();

        // Publish the intermediary block
        $a1Block1->publishRecursive();

        // Block no longer has modified state
        $this->assertFalse($a1Block1->hasOwnedModifications());
        // Nor does the gallery
        $this->assertFalse($gallery1->hasOwnedModifications());
        // Changed block page still does
        $this->assertTrue($a1->hasOwnedModifications());

        $a1->publishRecursive();
        $this->assertFalse($a1->hasOwnedModifications());

        $a1Block1->Title = "Don't blink. A1 block might change again.";
        $a1Block1->write();

        $this->assertTrue($a1->hasOwnedModifications());
    }

    public function testChangeOwnershipStructure()
    {
        /* @var DataObject|Versioned|SnapshotPublishable $a1 */
        /* @var DataObject|Versioned|SnapshotPublishable $a2 */
        /* @var DataObject|Versioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|Versioned|SnapshotPublishable $gallery1 */
        /* @var DataObject|Versioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->Title = 'Block 1 changed';
        $a1Block1->write();

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->ParentID = $a2->ID;
        $a1Block1->write();
        $blockMoved = $a1Block1;

        // Change of ownership. Both trees now show modification.
        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        // A1 doesn't have activity anymore.
        $activity = $a1->getActivityFeed();
        $this->assertEmpty($activity);

        // A2 has a modified block
        $activity = $a2->getActivityFeed();
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $a1->publishRecursive();
        $this->assertEmpty($a1->getActivityFeed());

        // A2 still has a modified block
        $activity = $a2->getActivityFeed();
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $a2->publishRecursive();
        $this->assertEmpty($a2->getActivityFeed());
        $this->assertFalse($a2->hasOwnedModifications());

        $blockMoved->Title = "The moved block is modified";
        $blockMoved->write();

        $gallery1->Title = "The gallery that belongs to the moved block is modified";
        $gallery1->write();

        $item = new GalleryImage(['URL' => '/belongs/to/moved/block']);
        $item->write();

        $gallery1->Images()->add($item);

        $this->assertTrue($a2->hasOwnedModifications());
        $this->assertFalse($a1->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
        ]);

        // Move the block back to A1
        // Refresh the block so that changed fields flushes
        $blockMoved = DataObject::get_by_id(Block::class, $blockMoved->ID, false);
        $blockMoved->ParentID = $a1->ID;
        $blockMoved->write();

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a2->getActivityFeed());

        $activity = $a1->getActivityFeed();
        $this->assertCount(4, $activity);
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $a2->publishRecursive();
        $a1->publishRecursive();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());
        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());

        // Move a many_many
        $gallery1->Images()->remove($item);

        $gallery2->Images()->add($item);

        $item->URL = '/new/url';
        $item->write();

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$item, ActivityEntry::REMOVED, $gallery1],
        ]);

        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$item, ActivityEntry::ADDED, $gallery2],
            [$item, ActivityEntry::MODIFIED],
        ]);
    }

    public function testDeletions()
    {
        /* @var DataObject|Versioned|SnapshotPublishable $a1 */
        /* @var DataObject|Versioned|SnapshotPublishable $a2 */
        /* @var DataObject|Versioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|Versioned|SnapshotPublishable $a1Block2 */
        /* @var DataObject|Versioned|SnapshotPublishable $a2Block1 */
        /* @var DataObject|Versioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->delete();

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block1, ActivityEntry::DELETED],
        ]);

        $a2Block1->Title = 'Change change change';
        $a2Block1->write();

        $gallery2->Title = 'Changey McChangerson';
        $gallery2->write();

        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$a2Block1, ActivityEntry::MODIFIED],
            [$gallery2, ActivityEntry::MODIFIED],
        ]);

        $a2Block1->delete();

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$a2Block1, ActivityEntry::MODIFIED],
            [$gallery2, ActivityEntry::MODIFIED],
            [$a2Block1, ActivityEntry::DELETED],
        ]);

    }

    /**
     * @param $activity
     * @param array $objs
     * @return bool
     */
    protected function assertActivityContains($activity, $objs = [])
    {
        $this->assertCount(sizeof($objs), $activity);
        foreach ($activity as $i => $entry) {
            if (!isset($objs[$i][2])) {
                $objs[$i][2] = null;
            }
            /* @var DataObject|SnapshotPublishable $obj */
            list ($obj, $action, $owner) = $objs[$i];
            $expectedHash = $obj->isInDB()
                ? SnapshotPublishable::hashObject($obj)
                : SnapshotPublishable::hash($obj->ClassName, $obj->OldID);
            $this->assertEquals(
                $expectedHash,
                SnapshotPublishable::hashObject($entry->Subject)
            );
            $this->assertEquals($action, $entry->Action);
            if ($owner) {
                $this->assertEquals(
                    SnapshotPublishable::hashObject($owner),
                    SnapshotPublishable::hashObject($entry->Owner)
                );
            }
        }
    }

    protected function buildState($publish = true)
    {
        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $a1->write();

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $a2->write();

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery2 = new Gallery(['Title' => 'Gallery 2 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery2->write();

        if ($publish) {
            $a1->publishRecursive();
            $a2->publishRecursive();
        }

        return [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2];
    }

    protected function debugActivity($activity)
    {
        $list = [];
        foreach ($activity as $entry) {
            $list[] = sprintf(
                '[%s] %s #%s (%s)',
                $entry->Action,
                $entry->Subject->ClassName,
                $entry->Subject->ID,
                $entry->Subject->getTitle()
            );
        }

        return implode("\n", $list);
    }
}
