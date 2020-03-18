<?php


namespace SilverStripe\Snapshots\Tests\Handler\GraphQL;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Snapshots\Handler\PageContextProvider;

class FakePageContextProvider extends PageContextProvider
{
    private $page;

    public function setPage(SiteTree $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getPageFromReferrer(): ?SiteTree
    {
        return $this->page;
    }
}
