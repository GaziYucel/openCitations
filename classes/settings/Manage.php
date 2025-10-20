<?php

/**
 * @file classes/Settings/Manage.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Manage
 * @ingroup plugins_generic_opencitations
 *
 * @brief Manage settings page
 */

namespace APP\plugins\generic\openCitations\classes\settings;

use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\core\JSONMessage;

class Manage
{
    public OpenCitationsPlugin $plugin;

    public function __construct(OpenCitationsPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function execute($args, $request): JSONMessage
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                // Load the custom form
                $form = new Form($this->plugin);

                // Fetch the form the first time it loads, before the user has tried to save it
                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }

                // Validate and save the form data
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
                break;
            default:
                break;
        }

        return new JSONMessage(false);
    }
}
