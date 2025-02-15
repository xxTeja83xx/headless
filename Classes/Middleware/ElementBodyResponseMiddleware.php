<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final class ElementBodyResponseMiddleware implements MiddlewareInterface
{
    /**
     * @var TypoScriptFrontendController
     */
    private $tsfe;
    /**
     * @var JsonEncoder
     */
    private $jsonEncoder;

    public function __construct(
        TypoScriptFrontendController $typoScriptFrontendController = null,
        JsonEncoder $jsonEncoder = null
    ) {
        $this->tsfe = $typoScriptFrontendController ?? $GLOBALS['TSFE'];
        $this->jsonEncoder = $jsonEncoder ?? GeneralUtility::makeInstance(JsonEncoder::class);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!isset($this->tsfe->tmpl->setup['plugin.']['tx_headless.']['staticTemplate'])
            || (bool)$this->tsfe->tmpl->setup['plugin.']['tx_headless.']['staticTemplate'] === false
        ) {
            return $response;
        }

        $elementId = (int)($request->getParsedBody()['responseElementId'] ?? 0);

        if (!$elementId || !in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
            return $response;
        }

        $responseJson = json_decode($response->getBody()->__toString(), true);

        if ($responseJson === null) {
            return $response;
        }

        $stream = new Stream('php://temp', 'r+');
        $stream->write($this->jsonEncoder->encode($this->extractElement($responseJson['content'] ?? [], $elementId)));

        return $response->withBody($stream);
    }

    /**
     * @param array<string, mixed> $content
     * @param int $elementId
     * @return array<string, mixed>
     */
    private function extractElement(array $content, int $elementId): array
    {
        $body = [];

        foreach ($content as $colPos => $items) {
            foreach ($items as $item) {
                if ((int)$item['id'] === $elementId) {
                    return $item;
                }
            }
        }

        return $body;
    }
}
