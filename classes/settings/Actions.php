<?php

/**
 * @file classes/Settings/Actions.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Actions
 * @ingroup plugins_generic_opencitations
 *
 * @brief Actions on the settings page
 */

namespace APP\plugins\generic\openCitations\classes\settings;

use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class Actions
{
    public OpenCitationsPlugin $plugin;

    public function __construct(OpenCitationsPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    public function execute($request, $actionArgs, $parentActions): array
    {
        if (!$this->plugin->getEnabled()) {
            return $parentActions;
        }

        $router = $request->getRouter();

        $linkAction[] = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->plugin->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->plugin->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        array_unshift($parentActions, ...$linkAction);

        return $parentActions;
    }
}
