<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Controller;

use Netgen\Bundle\IbexaSiteApiBundle\View\ContentView;
use Netgen\Bundle\SiteBundle\Helper\RedirectHelper;
use Netgen\IbexaSiteApi\API\Values\Location;
use Symfony\Component\HttpFoundation\Response;

final class CheckRedirect extends Controller
{
    private RedirectHelper $redirectHelper;

    public function __construct(RedirectHelper $redirectHelper) {
        $this->redirectHelper = $redirectHelper;
    }

    /**
     * Action for viewing content which has redirect fields.
     */
    public function __invoke(ContentView $view)
    {
        $location = $view->getSiteLocation();
        if (!$location instanceof Location) {
            $location = $view->getSiteContent()->mainLocation;
        }

        if (!$location instanceof Location) {
            return $view;
        }

        $response = $this->redirectHelper->checkRedirect($location);
        if ($response instanceof Response) {
            return $response;
        }

        return $view;
    }
}
