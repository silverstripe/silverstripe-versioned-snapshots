<?php


namespace SilverStripe\Snapshots\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Snapshots\Handler\PageContextProvider;

require_once(__DIR__ . '/SnapshotTest/BlockPage.php');
class PageContextProviderTest extends SapphireTest
{
    private $page;

    protected static $extra_dataobjects = [
        SiteTree::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        $page = SiteTree::create();
        $page->write();
        $this->page = $page;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testPageFromURL(string $testURL, bool $shouldSucceed)
    {
        $provider = new PageContextProvider();
        // Strip out the placeholder and use the real ID.
        $url = str_replace('[ID]', $this->page->ID, $testURL);

        $result = $provider->getCurrentPageFromRequestUrl($url);
        if ($shouldSucceed) {
            $this->assertInstanceOf(SiteTree::class, $result);
            $this->assertEquals($this->page->ID, $result->ID);
        } else {
            $this->assertNull($result);
        }
    }

    public function dataProvider()
    {
        // This is called before the database is ready, so [ID] is used as a placeholder
        return [
            ['/fail/[ID]', false],
            ['/admin/[ID]', false],
            ['admin/pages/[ID]', false],
            ['/admin/pages/edit/anything/[ID]', true],
            ['/admin/pages/edit/show/[ID]', true],
            ['/admin/pages/edit/EditForm/[ID]', true],
            ['admin/pages/edit/show/[ID]', true],
            ['admin/pages/edit/EditForm/[ID]', true],
            ['/admin/pages/edit/show/[ID]/', true],
            ['/admin/pages/edit/EditForm/[ID]/', true],
            ['admin/pages/edit/show/[ID]/', true],
            ['admin/pages/edit/EditForm/[ID]/', true],
            ['/admin/pages/edit/[ID]', false],
            ['/admin/pages/EditForm/[ID]', false],
            ['admin/pages/edit/show/[ID]/something', true],
            ['admin/pages/edit/show/[ID]/something/another-thing', true],
            ['admin/pages/edit/show/something/another-thing/[ID]', false],
        ];
    }
}
