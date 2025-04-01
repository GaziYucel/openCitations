<?php

/**
 * @file classes/settings/SettingsHandler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsHandler
 *
 * @brief Settings handler for the Open Citations Plugin
 */

namespace APP\plugins\generic\openCitations\classes\settings;

use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class SettingsHandler
{
    /** @var OpenCitationsPlugin */
    public OpenCitationsPlugin $plugin;

    public function __construct(OpenCitationsPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }
    public function actions($request, $actionArgs, $parentActions): array
    {
        if (!$this->plugin->getEnabled()) return $parentActions;

        $router = $request->getRouter();

        $linkAction[] = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request, null, null, 'manage', null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->plugin->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->plugin->getDisplayName()),
            __('manager.plugins.settings'),
            null);

        array_unshift($parentActions, ...$linkAction);

        return $parentActions;
    }

    /** @copydoc Plugin::manage() */
    public function manage($args, $request): JSONMessage
    {
        $context = $request->getContext();
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new SettingsForm($this->plugin);
                // Fetch the form the first time it loads, before the user has tried to save it
                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }
                // Validate and save the form data
                $form->readInputData();
                if ($form->validate()) $form->execute();
                return new JSONMessage(true);
        }

        return new JSONMessage(false);
    }
}
