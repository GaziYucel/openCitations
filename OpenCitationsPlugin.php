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

use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\classes\jobs\DepositJob;
use APP\plugins\generic\openCitations\classes\schemas\PluginSchema;
use APP\plugins\generic\openCitations\classes\settings\Actions;
use APP\plugins\generic\openCitations\classes\settings\Manage;
use APP\publication\Publication;
use PKP\core\JSONMessage;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

class OpenCitationsPlugin extends GenericPlugin
{
    private int $contextId;

    public function register($category, $path, $mainContextId = null): bool
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled()) {
                $this->contextId = ($mainContextId === null) ? $this->getCurrentContextId() : $mainContextId;

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
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /** @copydoc Plugin::manage() */
    public function manage($args, $request): JSONMessage
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    /**
     * Add job for depositing to open citations.
     */
    public function addJob($hookName, $args): void
    {
        /** @var Publication $newPublication */
        $newPublication = $args[0];

        $token = $this->getSetting($this->contextId, Constants::token);

        if (empty($token)) {
            error_log(__('plugins.generic.openCitations.settings.missingCredentials'));
            return;
        }

        if (
            $newPublication->getData('status') !== PKPSubmission::STATUS_PUBLISHED ||
            !empty($newPublication->getData(Constants::depositedUrlName)) ||
            empty($newPublication->getStoredPubId('doi')) ||
            empty($newPublication->getData('citations'))
        ) {
            return;
        }

        dispatch(new DepositJob($newPublication->getId(), $token));
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\openCitations\OpenCitationsPlugin', '\OpenCitationsPlugin');
}
