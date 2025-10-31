<?php

namespace OCA\FlattenPDF\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Security\CSP\ContentSecurityPolicy;
use OCP\Util;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {

    public function __construct() {
        parent::__construct('flattenpdf');
    }

    public function register(IRegistrationContext $context): void {
        // Nothing to register yet
    }

    public function boot(IBootContext $context): void {
        $logger = $context->getAppContainer()->get(LoggerInterface::class);

        // Load JS into the Files app
        Util::addScript('flattenpdf', 'flattenpdf');

        // Extend the default Content Security Policy
        $policy = new ContentSecurityPolicy();
        $policy->addAllowedConnectDomain('*'); // allow websocket, webpack, ajax, etc.
        $policy->addAllowedFrameDomain('*');
        $policy->addAllowedImageDomain('*');
        $policy->addAllowedMediaDomain('*');
        $policy->addAllowedFontDomain('*');
        $policy->addAllowedScriptDomain('*');
        $policy->addAllowedStyleDomain('*');

        $context->getAppContainer()->getServer()
            ->getContentSecurityPolicyManager()
            ->addDefaultPolicy($policy);

        $logger->debug('FlattenPDF CSP policy applied and JS loaded', ['app' => 'flattenpdf']);
    }
}

