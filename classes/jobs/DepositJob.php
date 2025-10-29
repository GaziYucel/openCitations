<?php

/**
 * @file classes/jobs/DepositJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositJob
 * @ingroup plugins_generic_opencitations
 *
 * @brief Job for depositing.
 */

namespace APP\plugins\generic\openCitations\classes\jobs;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\openCitations\classes\Constants;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;
use PKP\plugins\PluginRegistry;

class DepositJob extends BaseJob
{
    protected int $publicationId;
    protected object $plugin;

    public function __construct(int $publicationId)
    {
        parent::__construct();
        $this->publicationId = $publicationId;
        $this->plugin = PluginRegistry::getPlugin('generic', 'opencitationsplugin');
    }

    /**
     * Handle the queue job execution process
     *
     * @throws JobException
     */
    public function handle(): void
    {
        if (!$this->plugin->getEnabled()) {
            return;
        }

        $publication = Repo::publication()->get($this->publicationId);

        if (!$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $context = Application::getContextDAO()->getById(
            Repo::submission()->get($publication->getData('submissionId'))->getData('contextId'));

        $apiToken = $this->plugin->getSetting($context->getId(), Constants::token);

        if (!$apiToken) {
            throw new JobException(__('plugins.generic.openCitations.settings.missingCredentials'));
        }

        $deposit = new Deposit($this->publicationId, $apiToken);
        $success = $deposit->execute();

        if (!$success) {
            throw new JobException(__('plugins.generic.openCitations.job.failed', [
                'publicationId' => $this->publicationId
            ]));
        }
    }
}
