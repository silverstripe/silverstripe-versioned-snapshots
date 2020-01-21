<?php

namespace SilverStripe\Snapshots\Handler;

use Page;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;

/**
 * Trait CurrentPage
 *
 * @package SilverStripe\Snapshots\Listener
 */
trait CurrentPage
{
    /**
     * Provides the ability to detect current page from controller
     * note that this can be only used for actions that are aware of the current page
     *
     * @param mixed $controller
     * @return SiteTree|null
     */
    protected function getCurrentPageFromController($controller): ?SiteTree
    {
        while ($controller && ($controller instanceof Form || $controller instanceof GridFieldDetailForm_ItemRequest)) {
            $controller = $controller->getController();
        }

        if (!$controller) {
            return null;
        }

        if (!$controller instanceof CMSMain) {
            return null;
        }

        $page = $controller->currentPage();

        if ($page === null) {
            return null;
        }

        return $page;
    }

    /**
     * Provides the ability to detect current page from URL
     * this is useful for actions that have no explicit awareness of the current page
     *
     * @param string|null $url
     * @return Page|null
     */
    protected function getCurrentPageFromRequestUrl(?string $url): ?Page
    {
        $url = trim($url);

        if (!$url) {
            return null;
        }

        $adminSegment = AdminRootController::get_admin_route();
        $controller = CMSPageEditController::singleton();
        $controllerSegment = $controller->config()->get('url_segment');
        $editForm = $controller->getEditForm();
        $formSegment = $editForm->getName();
        $viewSegment = 'show';

        foreach ([$adminSegment, $controllerSegment, $formSegment, $viewSegment] as $segment) {
            $segment .= '/';

            if (mb_strpos($url, $segment) !== 0) {
                continue;
            }

            $url = str_replace($segment, '', $url);
        }

        $url = explode('/', $url);

        if (count($url) === 0) {
            return null;
        }

        $pageId = (int) array_shift($url);

        if (!$pageId) {
            return null;
        }

        // find page by ID
        $page = DataObject::get_by_id(Page::class, $pageId);

        // re-fetch the page with proper type
        $page = DataObject::get_by_id($page->ClassName, $pageId);

        if (!$page instanceof Page) {
            return null;
        }

        return $page;
    }

    /**
     * @return HTTPRequest|null
     */
    protected function getCurrentRequest(): ?HTTPRequest
    {
        return Controller::has_curr() ? Controller::curr()->getRequest() : null;
    }

    /**
     * @return SiteTree|null
     */
    protected function getPageFromReferrer(): ?SiteTree
    {
        $request = $this->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $url = $request->getHeader('referer');
        $url = parse_url($url, PHP_URL_PATH);
        $url = ltrim($url, '/');
        return $this->getCurrentPageFromRequestUrl($url);
    }
}
