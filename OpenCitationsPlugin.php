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
use APP\plugins\generic\openCitations\classes\settings\SettingsForm;
use APP\publication\Publication;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
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
        $actions = parent::getActions($request, $actionArgs);

        if (!$this->getEnabled()) {
            return $actions;
        }

        $url = $request->getRouter()->url(
            $request, null, null, 'manage', null,
            [
                'verb' => 'settings',
                'plugin' => $this->getName(),
                'category' => 'generic'
            ]
        );

        array_unshift($actions,
            new LinkAction(
                'settings',
                new AjaxModal($url, $this->getDisplayName()),
                __('manager.plugins.settings')
            )
        );

        return $actions;
    }

    /** @copydoc Plugin::manage() */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new SettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }

    /**
     * Add job for depositing to open citations.
     */
    public function addJob($hookName, $args): void
    {
        /** @var Publication $newPublication */
        $newPublication = $args[0];

        $token = $this->getSetting(
            $this->request->getContext()->getId(),
            Constants::token
        );

        if (empty($token)) {
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification(
                Application::get()->getRequest()->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                array('contents' => __('plugins.generic.openCitations.settings.missing_logon_credentials'))
            );
            return;
        }

        if (
            $newPublication->getData('status') !== PKPSubmission::STATUS_PUBLISHED ||
            !empty($newPublication->getData(Constants::depositedUrlName)) ||
            empty($newPublication->getStoredPubId('doi'))
        ) {
            return;
        }

        dispatch(new DepositJob($newPublication->getId(), $token))
            ->delay(now()->addSeconds(60));
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\openCitations\OpenCitationsPlugin', '\OpenCitationsPlugin');
}
