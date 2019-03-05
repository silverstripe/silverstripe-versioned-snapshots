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

        // Starting point. An entry for draft and publish, plus a snapshot (empty)
        $this->assertCount(3, $a1->getHistoryIncludingOwned());

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        // A new entry for each block added.
        $this->assertCount(5, $a1->getHistoryIncludingOwned());

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        // A new entry for the one block added to the SIBLING.
        $this->assertCount(4, $a2->getHistoryIncludingOwned());


        $a1->Title = 'A1 Block Page -- changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified BlockPage
        $this->assertCount(6, $a1->getHistoryIncludingOwned());

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $a1Block1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified Block <- BlockPage
        $this->assertCount(7, $a1->getHistoryIncludingOwned());

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
                [$a1Block1, ActivityEntry::MODIFIED],
                [$a1Block2, ActivityEntry::CREATED]
            ]
        );

        // Testing third level
        /* @var DataObject|SnapshotPublishable $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();
        // A new entry for the Gallery <- Block <- BlockPage
        $this->assertCount(8, $a1->getHistoryIncludingOwned());

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
                [$a1Block1, ActivityEntry::MODIFIED],
                [$a1Block2, ActivityEntry::CREATED],
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
        $this->assertCount(9, $a1->getHistoryIncludingOwned());

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
                [$a1Block1, ActivityEntry::MODIFIED],
                [$a1Block2, ActivityEntry::CREATED],
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
                [$a1Block1, ActivityEntry::MODIFIED],
                [$a1Block2, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1],
                [$galleryItem2, ActivityEntry::ADDED, $gallery1],
            ]
        );

        // Two new entries for the new GalleryItem <- Gallery <- Block <- BlockPage
        $this->assertCount(11, $a1->getHistoryIncludingOwned());

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
        $this->assertCount(6, $a2->getHistoryIncludingOwned());

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

        $this->assertCount(7, $a2->getHistoryIncludingOwned());

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

        $this->assertCount(12, $a1->getHistoryIncludingOwned());

        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(8, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$a1Block2, ActivityEntry::CREATED],
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
        $this->assertCount(14, $a1->getHistoryIncludingOwned());

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        $a2->publishRecursive();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());

        $gallery1->Title = 'Gallery 1 is changed again';
        $gallery1->write();

        $this->assertCount(14, $a1->getHistoryIncludingOwned());
        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$gallery1, ActivityEntry::MODIFIED],
        ]);

        // ROLLBACK $gallery1
        /// Assert history decremements by 1
        /// OR history incremements by 1
        // Assert unpublished owned is EMPTY
        // assert changes between $a1[version 2] and $a1[current] is EMPTY

        // Intermediate ownership
        // Change gallery1
        // assert BlockPage has unpublished owned
        // assert Block has unpublished owned
        // Does this item belong to a snapshot that has unpublished changes

        // Change A1 BlockPage
        // Publish Block
        // assert Block has unpublished owned EMPTY
        // BlockPage has unpublished owned NOT EMPTY
        // assert gallery1 has unpublished owned EMPTY

        // Make sure siblings weren't affected by all this.
        $this->assertCount(4, $a2->getHistoryIncludingOwned());


        // Publish A1
        // modify block 1
        // assert A1 has unpublished owned $block1
        // assert A1 history increment
        // move block 1 to A2
        // assert A2 has unpublished owned contains $block1
        // assert A1 has unpublished owned EMPTY
        // assert A2 history increment
        // assert A1 history decrement

        // ------------ reset state -----------//
        // change Block1

        $a1Block1->delete();
        // Assert A1 history is UNCHANGED
        // assert a1 unpublished owned is EMPTY

        // assert changes between $a1[version 1] and $a1[current] is
        //  $a1Block1 CREATED
        //  $a1Block1 MODIFIED
        //  $a1Block1 DELETED

        // Add block2
        // publish block 2
        // unpublish block 2
        // assert A1 history is increment by 3
        // assert A1 unpublished owned is $block2

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
            list ($obj, $action, $owner) = $entry;
            $this->assertEquals(
                SnapshotPublishable::hashObject($obj),
                SnapshotPublishable::hashObject($activity->Subject)
            );
            $this->assertEquals($action, $activity->Action);
            if ($owner) {
                $this->assertEquals(
                    SnapshotPublishable::hashObject($owner),
                    SnapshotPublishable::hashObject($activity->Owner)
                );
            }
        }
    }
}