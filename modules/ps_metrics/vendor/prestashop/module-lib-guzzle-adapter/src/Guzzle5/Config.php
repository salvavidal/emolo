<?php

namespace ps_metrics_module_v4_0_6\Prestashop\ModuleLibGuzzleAdapter\Guzzle5;

use ps_metrics_module_v4_0_6\Prestashop\ModuleLibGuzzleAdapter\Interfaces\ConfigInterface;
class Config implements ConfigInterface
{
    /**
     * {@inheritdoc}
     */
    public static function fixConfig(array $config)
    {
        if (isset($config['timeout'])) {
            $config['defaults']['timeout'] = $config['timeout'];
            unset($config['timeout']);
        }
        if (isset($config['headers'])) {
            $config['defaults']['headers'] = $config['headers'];
            unset($config['headers']);
        }
        if (isset($config['http_errors'])) {
            $config['defaults']['exceptions'] = $config['http_errors'];
            unset($config['http_errors']);
        }
        if (isset($config['base_uri'])) {
            $config['base_url'] = $config['base_uri'];
            unset($config['base_uri']);
        }
        return $config;
    }
}
