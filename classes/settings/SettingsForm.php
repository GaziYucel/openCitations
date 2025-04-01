<?php

/**
 * @file classes/settings/SettingsForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsForm
 *
 * @brief Form for settings for the Open Citations Plugin
 */

namespace APP\plugins\generic\openCitations\classes\settings;

use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use APP\core\Application;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use TemplateManager;

class SettingsForm extends Form
{
    /** @var OpenCitationsPlugin */
    public OpenCitationsPlugin $plugin;

    /** @var string[] Array of variables saved in the database. */
    private array $settings = [
        Constants::owner,
        Constants::repository,
        Constants::token
    ];

    /** @copydoc Form::__construct() */
    public function __construct(OpenCitationsPlugin &$plugin)
    {
        $this->plugin = &$plugin;

        // Always add POST and CSRF validation to secure your form.
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));

        parent::__construct($plugin->getTemplateResource('settings.tpl'));
    }

    /** @copydoc Form::initData() */
    public function initData(): void
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        $contextId = $context
            ? $context->getId()
            : Application::SITE_CONTEXT_ID;

        foreach ($this->settings as $key) {
            $this->setData(
                $key,
                $this->plugin->getSetting($contextId, $key));
        }

        parent::initData();
    }

    /** @copydoc Form::readInputData() */
    public function readInputData(): void
    {
        foreach ($this->settings as $key) {
            $this->readUserVars([$key]);
        }
        parent::readInputData();
    }

    /** @copydoc Form::fetch() */
    public function fetch($request, $template = null, $display = false): ?string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /** @copydoc Form::execute() */
    public function execute(...$functionArgs)
    {
        $context = Application::get()
            ->getRequest()
            ->getContext();

        $contextId = $context
            ? $context->getId()
            : Application::SITE_CONTEXT_ID;

        foreach ($this->settings as $key) {
            $this->plugin->updateSetting(
                $contextId,
                $key,
                $this->getData($key));
        }

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }
}
