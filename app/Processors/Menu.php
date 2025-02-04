<?php

namespace uCMS\Processors;

use uCMS\Response;
use uCMS\Config;

/**
 * Handles the preparation of page's menu.
 */
class Menu implements IProcessor
{
    private function loadItems(Response $response, Config $items, int $level = 0): array
    {
        $menu = [];

        foreach ($items as $item) {
            $caption = $item->value('caption', null);
            $url = $item->value('url', null);
            if (!$caption) {
                continue;
            }

            $menuObj = (object)[
                'caption' => $response->getLocalizedValue($caption),
                'url' => $url,
                'active' => $url === $response->app->relativePath,
                'subitems' => null,
            ];

            if ($level === 0 && isset($item->subitems)) {
                $menuObj->subitems = $this->loadItems($response, $item->subitems, $level + 1);
                foreach ($menuObj->subitems as $sub) {
                    if ($sub->active) {
                        $menuObj->active = true;
                    }
                }
            }

            $menu[] = $menuObj;
        }
        return $menu;
    }

    public function process(Response $response): bool
    {
        if ($response->latteTemplate) {
            $menuConfig = Config::loadYaml(__DIR__ . '/../../config/menu.yaml');
            $response->latteParameters['menu'] = $this->loadItems($response, $menuConfig->items);
        }

        return false;
    }
}
