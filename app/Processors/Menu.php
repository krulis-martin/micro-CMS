<?php

namespace uCMS\Processors;

use uCMS\Response;
use uCMS\Config;

/**
 * Handles the preparation of page's menu.
 */
class Menu implements IProcessor
{
    public function process(Response $response): bool
    {
        if ($response->latteTemplate) {
            $menuConfig = Config::loadYaml(__DIR__ . '/../../config/menu.yaml');
            $menu = [];
            $currentPath = implode('/', $response->app->path);
            foreach ($menuConfig->items as $item) {
                $caption = $item->value('caption', null);
                $url = $item->value('url', null);
                if (!$caption || !$url) {
                    continue;
                }

                $menu[] = (object)[
                    'caption' => $response->getLocalizedValue($caption),
                    'url' => $url,
                    'active' => $url === $currentPath,
                ];
            }

            $response->latteParameters['menu'] = $menu;
        }

        return false;
    }
}
