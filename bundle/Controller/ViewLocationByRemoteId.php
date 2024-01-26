<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Controller;

use Netgen\IbexaSiteApi\API\LoadService;
use Symfony\Component\HttpFoundation\Response;

final class ViewLocationByRemoteId extends Controller
{
    private LoadService $loadService;

    public function __construct(LoadService $loadService) 
    {
        $this->loadService = $loadService;
    }

    public function __invoke(string $remoteId): Response
    {
        $content = $this->loadService->loadLocationByRemoteId($remoteId);

        return $this->redirectToRoute('ibexa.location.view', ['locationId' => $content->id]);
    }
}
