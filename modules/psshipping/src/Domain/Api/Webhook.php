<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\Psshipping\Domain\Api;

use Context as LegacyContext;
use GuzzleHttp\Psr7\Request;
use PrestaShop\Module\Psshipping\Domain\Accounts\AccountsService;
use PrestaShop\Module\Psshipping\Exception\PsshippingException;
use Prestashop\ModuleLibGuzzleAdapter\ClientFactory;
use Prestashop\ModuleLibGuzzleAdapter\Interfaces\HttpClientInterface;
use PrestaShop\PrestaShop\Adapter\Configuration;
use Psshipping;

class Webhook
{
    /** @var Psshipping */
    private $module;

    public function __construct(Psshipping $module)
    {
        $this->module = $module;
    }

    /**
     * @see https://docs.guzzlephp.org/en/stable/quickstart.html-
     *
     * @return HttpClientInterface
     */
    private function getClient()
    {
        return (new ClientFactory())->getClient([
            'allow_redirects' => true,
            'connect_timeout' => 10,
            'http_errors' => false,
            'timeout' => 10,
        ]);
    }

    /**
     * @param string $svixSecret
     *
     * @return void
     *
     * @throws PsshippingException
     */
    public function createSvixEndpoint(string $svixSecret)
    {
        $jwt = (new AccountsService())->getPsAccountToken($this->module);
        $configuration = new Configuration();
        $context = LegacyContext::getContext();

        if (!empty($context) && !empty($context->shop)) {
            $configuration->restrictUpdatesTo($context->shop);
        }

        $response = $this->getClient()->sendRequest(
            new Request(
                'POST',
                $this->module->getApiUrl() . '/shipment-status/webhook',
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json',
                ],
                '{"svixSecret": "' . $svixSecret . '"}'
            )
        );

        if (substr((string) $response->getStatusCode(), 0, 1) !== '2') {
            throw new PsshippingException('An error occured while sending the secret to the API.', 400);
        }

        $configuration->set('PS_SHIPPING_WEBHOOK_SECRET', $svixSecret);
    }

    public function deleteSvixEndpoint(): void
    {
        $jwt = (new AccountsService())->getPsAccountToken($this->module);

        if (empty($jwt)) {
            return;
        }

        $configuration = new Configuration();
        $context = LegacyContext::getContext();

        if (!empty($context) && !empty($context->shop)) {
            $configuration->restrictUpdatesTo($context->shop);
        }

        $response = $this->getClient()->sendRequest(
            new Request(
                'DELETE',
                $this->module->getApiUrl() . '/shipment-status/webhook',
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json',
                ]
            )
        );

        if (substr((string) $response->getStatusCode(), 0, 1) !== '2') {
            throw new PsshippingException('An error occured while removing the svix endpoint to the API.', 400, false);
        }
    }

    private function generateSvixSecret(): string
    {
        return 'whsec_' . base64_encode(random_bytes(24));
    }

    /**
     * @throws PsshippingException
     */
    public function saveSvixSecret(): void
    {
        $svixSecret = $this->generateSvixSecret();

        $this->createSvixEndpoint(
            $svixSecret
        );
    }
}
