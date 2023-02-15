<?php

namespace SilverStripe\Snapshots\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Snapshots\Handler\PageContextProvider;

class PageContextProviderTest extends SapphireTest
{
    /**
     * @var SiteTree|null
     */
    private $page;

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        SiteTree::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $page = SiteTree::create();
        $page->write();
        $this->page = $page;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testPageFromURL(string $testURL, bool $shouldSucceed): void
    {
        $provider = PageContextProvider::create();
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

    public function dataProvider(): array
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
