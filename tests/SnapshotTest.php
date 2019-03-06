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

    public function testHistoryIncludesOwnedObjects()
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

        // Starting point. An entry for draft and publish
        $historyA1 = 2;
        $historyA2 = 2;

        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        // A new entry for each block added.
        $historyA1 += 2;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        // A new entry for the one block added to the SIBLING.
        $historyA2 += 1;
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());


        $a1->Title = 'A1 Block Page -- changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified BlockPage
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $a1Block1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified Block <- BlockPage
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

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
        // A new entry for the Gallery <- Block <- BlockPage
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

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

        // A new entry for the modified Gallery <- Block <- BlockPage
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

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

        // Two new entries for the new GalleryItem <- Gallery <- Block <- BlockPage
        $historyA1 += 2;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

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

        // New gallery, new image
        $historyA2 += 2;
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());

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
        $historyA2 += 1;
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());

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
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

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

        // New new live, new draft versions
        $historyA1 = 4;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        $a2->publishRecursive();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());

        // Make sure A2 didn't get hit with any collateral damage.
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());
    }

    public function testRevertChanges()
    {
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $historyA1 = $a1->getHistoryIncludingOwned()->count();
        $gallery1->Title = 'Gallery 1 is changed';
        $gallery1->write();


        $this->assertCount($historyA1 + 1, $a1->getHistoryIncludingOwned());
        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$gallery1, ActivityEntry::MODIFIED],
        ]);

        $gallery1->doRevertToLive();

        // The rollback removes the draft entry from the "owned" history
        $historyA1 -= 1;

        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $this->assertEmpty($a1->getActivityFeed());
    }

    public function testIntermediaryObjects()
    {
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $historyA1 = $a1->getHistoryIncludingOwned()->count();
        $historyBlock = $a1Block1->getHistoryIncludingOwned()->count();

        $gallery1->Title = 'Gallery 1 changed';
        $gallery1->write();
        $historyBlock += 1;
        // Intermediate ownership
        $this->assertTrue($a1Block1->hasOwnedModifications());
        $this->assertCount($historyBlock, $a1Block1->getHistory());

        $a1->Title = 'A1 changed';
        $a1->write();

        // Publish the intermediary block
        $a1Block1->publishRecursive();
        $historyBlock += 2;
        $this->assertCount($historyBlock, $a1Block1->getHistory());

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
        $historyBlock += 1;

        $this->assertCount($historyBlock, $a1Block1->getHistory());
        $this->assertTrue($a1->hasOwnedModifications());
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
    }

    public function testChangeOwnershipStructure()
    {
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $historyA1 = $a1->getHistoryIncludingOwned()->count();
        $historyA2 = $a2->getHistoryIncludingOwned()->count();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->Title = 'Block 1 changed';
        $a1Block1->write();

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->ParentID = $a2->ID;
        $a1Block1->write();
        $blockMoved = $a1Block1;

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        $historyA2 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());

        $a2->publishRecursive();
        $historyA2 += 2;
        $a1->publishRecursive();
        // No bump for A1. Has no modifications.

        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());
        $this->assertFalse($historyA1, $a1->hasOwnedModifications());
        $this->assertFalse($historyA2, $a2->hasOwnedModifications());

        $blockMoved->Title = "The moved block is modified";
        $blockMoved->write();
        $historyA2 += 1;

        $gallery1->Title = "The gallery that belongs to the moved block is modified";
        $gallery1->write();
        $historyA2 += 1;

        $item = new GalleryImage(['URL' => '/belongs/to/moved/block']);
        $item->write();

        $gallery1->Images()->add($item);
        $historyA2 += 1;

        $this->assertTrue($a2->hasOwnedModifications());
        $this->assertCount($historyA2, $a2->getHistory());
        $this->assertFalse($a1->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
        ]);

        // Move the block back to A1

        $blockMoved->ParentID = $a1->ID;
        $blockMoved->write();
        $historyA2 -= 3;
        $historyA1 += 3;

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());
        $this->assertCount($historyA2, $a2->getHistory());
        $this->assertCount($historyA1, $a1->getHistory());

        $this->assertEmpty($a2->getActivityFeed());

        $activity = $a1->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
        ]);

        $a1->publishRecursive();
        $historyA1 += 2;
        $a2->publishRecursive();
        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());
        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());
        $this->assertCount($historyA1, $a1->getHistory());

        // Move a many_many
        $gallery1->Images()->remove($item);
        $historyA1 += 1;
        $gallery2->Images()->add($item);
        $historyA2 += 1;

        $item->URL = '/new/url';
        $item->write();
        $historyA2 += 1;

        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());

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
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();
        $historyA1 = $a1->getHistoryIncludingOwned()->count();
        $historyA2 = $a2->getHistoryIncludingOwned()->count();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->delete();
        $historyA1 += 1;
        $this->assertCount($historyA1, $a1->getHistoryIncludingOwned());
        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block1, ActivityEntry::DELETED],
        ]);

        $a2Block1->Title = 'Change change change';
        $a1Block2->write();
        $historyA2 += 1;

        $gallery2->Title = 'Changey McChangerson';
        $gallery2->write();
        $historyA2 += 1;

        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());
        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$a2Block1, ActivityEntry::MODIFIED],
            [$gallery2, ActivityEntry::MODIFIED],
        ]);

        $a2Block1->delete();
        $historyA2 -= 2;

        $this->assertCount($historyA2, $a2->getHistoryIncludingOwned());
        $activity = $a2->getActivityFeed();
        $this->assertEmpty($activity);
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
            list ($obj, $action, $owner) = $objs[$i];
            $this->assertEquals(
                SnapshotPublishable::hashObject($obj),
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

    protected function buildState()
    {
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

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery2 = new Gallery(['Title' => 'Gallery 2 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery2->write();

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
