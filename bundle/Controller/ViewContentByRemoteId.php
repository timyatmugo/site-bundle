<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Controller;

use Netgen\IbexaSiteApi\API\LoadService;
use Symfony\Component\HttpFoundation\Response;

final class ViewContentByRemoteId extends Controller
{

    private LoadService $loadService;

    public function __construct(LoadService $loadService) 
    {
        $this->loadService = $loadService;
    }

    public function __invoke(string $remoteId): Response
    {
        $content = $this->loadService->loadContentByRemoteId($remoteId);

        return $this->redirectToRoute('ibexa.content.view', ['contentId' => $content->id]);
    }
}
