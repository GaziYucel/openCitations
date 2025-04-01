<?php

/**
 * @file OpenCitationsPlugin.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenCitationsPlugin
 *
 * @brief Plugin for depositing citations to Open Citations Crowd Sourcing.
 */

namespace APP\plugins\generic\openCitations;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\classes\jobs\DepositJob;
use APP\plugins\generic\openCitations\classes\schemas\PluginSchema;
use APP\plugins\generic\openCitations\classes\settings\SettingsHandler;
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
        $actions = new SettingsHandler($this);
        return $actions->actions($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    /** @copydoc Plugin::manage() */
    public function manage($args, $request): JSONMessage
    {
        $manage = new SettingsHandler($this);
        return $manage->manage($args, $request);
    }

    /**
     * Add job for publication
     */
    public function addJob($hookName, $args): void
    {
        /** @var Publication $newPublication */
        $newPublication = $args[0];

        $contextId = Application::get()->getRequest()->getContext()?->getId();
        $owner = $this->getSetting($contextId, Constants::owner);
        $repository = $this->getSetting($contextId, Constants::repository);
        $token = $this->getSetting($contextId, Constants::token);

        if (
            empty($owner) ||
            empty($repository) ||
            empty($token) ||
            $newPublication->getData('status') !== PKPSubmission::STATUS_PUBLISHED ||
            !empty($newPublication->getData(Constants::depositedUrlName)) ||
            empty($newPublication->getStoredPubId('doi'))
        ) {
            if (empty($owner) || empty($repository) || empty($token)) {
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification(
                    Application::get()->getRequest()->getUser()->getId(),
                    Notification::NOTIFICATION_TYPE_SUCCESS,
                    array('contents' => __('plugins.generic.openCitations.settings.missing_logon_credentials')));
            }
            return;
        }

        dispatch(new DepositJob($newPublication->getId(), $owner, $repository, $token))
            ->delay(now()->addSeconds(60));
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\openCitations\OpenCitationsPlugin', '\OpenCitationsPlugin');
}
