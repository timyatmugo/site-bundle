<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Controller;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use Netgen\Bundle\EzPlatformSiteApiBundle\View\ContentView;
use Netgen\EzPlatformSiteApi\API\Site;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;

class EmbedViewController extends Controller
{
    protected $site;

    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(Site $site, RouterInterface $router, LoggerInterface $logger = null)
    {
        $this->site = $site;
        $this->router = $router;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Action for viewing embedded content with image content type identifier.
     */
    public function embedImage(ContentView $view): ContentView
    {
        $parameters = $view->getParameters();
        $targetLink = !empty($parameters['objectParameters']['href']) ? trim($parameters['objectParameters']['href']) : null;

        if (!empty($targetLink)) {
            if (mb_stripos($targetLink, 'eznode://') === 0) {
                $locationId = (int) mb_substr($targetLink, 9);

                try {
                    $location = $this->site->getLoadService()->loadLocation($locationId);
                    $content = $location->content;
                } catch (NotFoundException $e) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to non existing location #%s', $locationId));
                } catch (UnauthorizedException $e) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to location #%s without read rights', $locationId));
                }
            } elseif (mb_stripos($targetLink, 'ezobject://') === 0) {
                $linkedContentId = (int) mb_substr($targetLink, 11);

                try {
                    $content = $this->site->getLoadService()->loadContent($linkedContentId);
                } catch (NotFoundException $e) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to non existing content #%s', $linkedContentId));
                } catch (UnauthorizedException $e) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to content #%s without read rights', $linkedContentId));
                }
            }

            $directDownloadLink = null;
            if (!empty($content) && !empty($parameters['objectParameters']['link_direct_download'])) {
                $fieldName = null;
                if ($content->hasField('file') && !$content->getField('file')->isEmpty()) {
                    $fieldName = 'file';
                } elseif ($content->hasField('image') && !$content->getField('image')->isEmpty()) {
                    $fieldName = 'image';
                }

                if ($fieldName !== null) {
                    $directDownloadLink = $this->router->generate(
                        'ngsite_download',
                        [
                            'contentId' => $content->id,
                            'fieldId' => $content->getField($fieldName)->id,
                        ]
                    );
                }
            }

            if ($directDownloadLink !== null) {
                $targetLink = $directDownloadLink;
            } elseif (mb_stripos($targetLink, 'eznode://') === 0) {
                $targetLink = $this->router->generate($location);
            } elseif (mb_stripos($targetLink, 'ezobject://') === 0) {
                $targetLink = $this->router->generate($content);
            }
        }

        $view->addParameters([
            'link_href' => $targetLink,
        ]);

        return $view;
    }
}
