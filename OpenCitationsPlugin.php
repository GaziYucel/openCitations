<?php

/**
 * @file OpenCitationsPlugin.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenCitationsPlugin
 * @ingroup plugins_generic_opencitations
 *
 * @brief Plugin for depositing citations to Open Citations Crowdsourcing.
 */

namespace APP\plugins\generic\openCitations;

use APP\plugins\generic\openCitations\classes\jobs\DepositJob;
use APP\plugins\generic\openCitations\classes\PluginSchema;
use APP\plugins\generic\openCitations\classes\PluginConfig;
use APP\publication\Publication;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

class OpenCitationsPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled()) {
                $schema = new PluginSchema();
                Hook::add('Schema::get::publication', [$schema, 'addToPublication']);

                // add job when publication is published
                Hook::add('Publication::publish', [$this, 'addJob']);
            }
            return true;
        }
        return false;
    }

    /** @copydoc PKPPlugin::getDescription */
    public function getDescription(): string
    {
        return __('plugins.generic.openCitations.description');
    }

    /** @copydoc PKPPlugin::getDisplayName */
    public function getDisplayName(): string
    {
        return __('plugins.generic.openCitations.displayName');
    }

    /** @copydoc Plugin::getActions() */
    public function getActions($request, $actionArgs): array
    {
        $pluginConfig = new PluginConfig($this);
        return $pluginConfig->actions($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /** @copydoc Plugin::manage() */
    public function manage($args, $request): JSONMessage
    {
        $pluginConfig = new PluginConfig($this);
        return $pluginConfig->manage($args, $request);
    }

    /**
     * Add job for depositing to open citations.
     */
    public function addJob($hookName, $args): void
    {
        /** @var Publication $publication */
        $publication = $args[0];

        if (
            $publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED ||
            empty($publication->getDoi()) ||
            empty($publication->getData('citations'))
        ) {
            return;
        }

        dispatch(new DepositJob($publication->getId()));
    }
}
