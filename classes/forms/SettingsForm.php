<?php

/**
 * @file classes/forms/SettingsForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsForm
 * @ingroup plugins_generic_opencitations
 *
 * @brief SettingsForm for journal managers to configure the plugin
 */

namespace APP\plugins\generic\openCitations\classes\forms;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SettingsForm extends Form
{
    public OpenCitationsPlugin $plugin;

    private array $settings = [
        Constants::token
    ];

    public function __construct(OpenCitationsPlugin $plugin)
    {
        parent::__construct($plugin->getTemplateResource('settings.tpl'));

        $this->plugin = $plugin;

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        $context = Application::get()->getRequest()->getContext();

        $contextId = $context
            ? $context->getId()
            : Application::SITE_CONTEXT_ID;

        foreach ($this->settings as $key) {
            $this->setData($key,
                $this->plugin->getSetting($contextId, $key)
            );
        }

        parent::initData();
    }

    public function readInputData(): void
    {
        foreach ($this->settings as $key) {
            $this->readUserVars([$key]);
        }

        parent::readInputData();
    }

    public function fetch($request, $template = null, $display = false): ?string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs): mixed
    {
        $context = Application::get()->getRequest()->getContext();

        $contextId = $context
            ? $context->getId()
            : Application::SITE_CONTEXT_ID;

        foreach ($this->settings as $key) {
            $this->plugin->updateSetting(
                $contextId,
                $key,
                $this->getData($key)
            );
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
