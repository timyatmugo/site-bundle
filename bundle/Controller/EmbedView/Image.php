<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Controller\EmbedView;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Netgen\Bundle\IbexaSiteApiBundle\View\ContentView;
use Netgen\Bundle\SiteBundle\Controller\Controller;
use Netgen\IbexaSiteApi\API\Site;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

use function mb_stripos;
use function mb_substr;
use function sprintf;
use function trim;

final class Image extends Controller
{
    public function __construct(private Site $site, private LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * Action for viewing embedded content with image content type identifier.
     */
    public function __invoke(ContentView $view): ContentView
    {
        $parameters = $view->getParameters();
        $targetLink = !empty($parameters['objectParameters']['href']) ? trim($parameters['objectParameters']['href']) : null;

        if (!empty($targetLink)) {
            $location = null;
            $content = null;

            if (mb_stripos($targetLink, 'ezlocation://') === 0) {
                $locationId = (int) mb_substr($targetLink, 9);

                try {
                    $location = $this->site->getLoadService()->loadLocation($locationId);
                    $content = $location->content;
                } catch (NotFoundException) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to non existing location #%s', $locationId));
                } catch (UnauthorizedException) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to location #%s without read rights', $locationId));
                }
            } elseif (mb_stripos($targetLink, 'ezcontent://') === 0) {
                $linkedContentId = (int) mb_substr($targetLink, 11);

                try {
                    $content = $this->site->getLoadService()->loadContent($linkedContentId);
                } catch (NotFoundException) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to non existing content #%s', $linkedContentId));
                } catch (UnauthorizedException) {
                    $targetLink = null;

                    $this->logger->error(sprintf('Tried to generate link to content #%s without read rights', $linkedContentId));
                }
            }

            $directDownloadLink = null;
            if ($content !== null && !empty($parameters['objectParameters']['link_direct_download'])) {
                $fieldName = null;
                if ($content->hasField('file') && !$content->getField('file')->isEmpty()) {
                    $fieldName = 'file';
                } elseif ($content->hasField('image') && !$content->getField('image')->isEmpty()) {
                    $fieldName = 'image';
                }

                if ($fieldName !== null) {
                    $directDownloadLink = $this->generateUrl(
                        'ngsite_download',
                        [
                            'contentId' => $content->id,
                            'fieldId' => $content->getField($fieldName)->id,
                        ],
                    );
                }
            }

            if ($directDownloadLink !== null) {
                $targetLink = $directDownloadLink;
            } elseif (mb_stripos($targetLink, 'ezlocation://') === 0) {
                $targetLink = $this->generateUrl('', [RouteObjectInterface::ROUTE_OBJECT => $location]);
            } elseif (mb_stripos($targetLink, 'ezcontent://') === 0) {
                $targetLink = $this->generateUrl('', [RouteObjectInterface::ROUTE_OBJECT => $content]);
            }
        }

        $view->addParameters([
            'link_href' => $targetLink,
        ]);

        return $view;
    }
}
