<?php

namespace SilverStripe\Snapshots\Handler;

use Page;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Path;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;

/**
 * Trait PageContextProvider
 *
 * @package SilverStripe\Snapshots\Listener
 */
class PageContextProvider
{
    use Injectable;

    /**
     * @var HTTPRequest|null
     */
    private $request;

    public function __construct(?HTTPRequest $request = null)
    {
        $this->request = $request;
    }

    /**
     * Provides the ability to detect current page from controller
     * note that this can be only used for actions that are aware of the current page
     *
     * @param mixed $controller
     * @return SiteTree|null
     */
    public function getCurrentPageFromController($controller): ?DataObject
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

        if ($page === null || !$page instanceof SiteTree) {
            return null;
        }

        return $page;
    }

    /**
     * Provides the ability to detect current page from URL
     * this is useful for actions that have no explicit awareness of the current page
     *
     * @param string|null $url
     * @return SiteTree|null
     */
    public function getCurrentPageFromRequestUrl(?string $url): ?SiteTree
    {
        $url = trim($url, '/ ');
        if (!$url) {
            return null;
        }

        $adminSegment = AdminRootController::get_admin_route();
        $controller = CMSPageEditController::singleton();

        $urlBase = $controller->config()->get('url_segment');
        $baseURL = Path::join($adminSegment, $urlBase);
        $pattern = '#^' . $baseURL .'#';
        if (!preg_match($pattern, $url)) {
            return null;
        }
        $slug = preg_replace($pattern, '', $url);
        $request = new HTTPRequest('GET', $slug);
        $params = $request->match($controller->config()->get('url_rule'));
        $pageId = $params['ID'] ?? null;

        if (!$pageId) {
            return null;
        }

        // find page by ID
        $page = DataObject::get_by_id(SiteTree::class, (int) $pageId);
        if (!$page) {
            return null;
        }
        // re-fetch the page with proper type
        $page = DataObject::get_by_id($page->ClassName, $pageId);

        if (!$page instanceof SiteTree) {
            return null;
        }

        return $page;
    }

    /**
     * @return HTTPRequest|null
     */
    public function getRequest(): ?HTTPRequest
    {
        if ($this->request) {
            return $this->request;
        }

        return Controller::has_curr() ? Controller::curr()->getRequest() : null;
    }

    /**
     * @param HTTPRequest $request
     * @return $this
     */
    public function setRequest(HTTPRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return SiteTree|null
     */
    public function getPageFromReferrer(): ?SiteTree
    {
        $request = $this->getRequest();
        if (!$request) {
            return null;
        }
        $url = $request->getHeader('referer');
        $url = parse_url($url, PHP_URL_PATH);
        $url = ltrim($url, '/');
        return $this->getCurrentPageFromRequestUrl($url);
    }
}
