<?php


namespace SilverStripe\Snapshots\Tests\Handler\Form;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Snapshots\Handler\Form\PublishHandler;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;
use SilverStripe\Versioned\Versioned;

class PublishHandlerTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        BlockPage::class,
        Block::class,
        Gallery::class,
        GalleryImage::class,
    ];

    protected $usesTransactions = false;

    public function testItOnlyCreatesASnapshotWhenContentHasChanged()
    {
        $handler = new PublishHandler();
        $blockPage = BlockPage::create(['Title' => 'Test']);
        $this->assertTrue($blockPage->hasExtension(Versioned::class));
        $blockPage->write();
        $blockPage->publishRecursive();
        $id = $blockPage->ID;
        $handler->fire(Event::create('test', [
            'record' => $blockPage,
        ]));

        $this->assertCount(1, $blockPage->getRelevantSnapshots());
        // make a change
        $blockPage = BlockPage::get()->byID($id);
        $blockPage->Title = 'Test -- changed';
        $blockPage->write();
        $blockPage->publishRecursive();
        $handler->fire(Event::create('test', [
            'record' => $blockPage,
        ]));

        $this->assertCount(2, $blockPage->getRelevantSnapshots());
        $latest = $blockPage->getRelevantSnapshots()->sort('ID DESC')->first();
        foreach ($latest->Items() as $item) {
            $this->assertEquals(1, $item->WasPublished);
        }

        $blockPage = BlockPage::get()->byID($id);
        $blockPage->write();
        $blockPage->publishRecursive();
        $handler->fire(Event::create('test', [
            'record' => $blockPage,
        ]));

        $this->assertCount(2, $blockPage->getRelevantSnapshots());
    }
}
