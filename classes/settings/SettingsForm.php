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

use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use TemplateManager;

class SettingsForm extends Form
{
    private OpenCitationsPlugin $plugin;
    private int $contextId;

    /** @var string[] Array of variables saved in the database. */
    private array $settings = [
        Constants::token
    ];

    /** @copydoc Form::__construct() */
    public function __construct(OpenCitationsPlugin &$plugin, int $contextId)
    {
        $this->plugin = &$plugin;
        $this->contextId = $contextId;
        parent::__construct($plugin->getTemplateResource('settings.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /** @copydoc Form::initData() */
    public function initData(): void
    {
        foreach ($this->settings as $key) {
            $this->setData($key, $this->plugin->getSetting($this->contextId, $key));
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
        foreach ($this->settings as $key) {
            $this->plugin->updateSetting($this->contextId, $key, $this->getData($key));
        }
        return parent::execute(...$functionArgs);
    }
}
