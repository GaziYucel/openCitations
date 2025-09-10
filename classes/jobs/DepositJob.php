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

use APP\facades\Repo;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class DepositJob extends BaseJob
{
    public OpenCitationsPlugin $plugin;
    protected int $publicationId;
    private string $token;
    private int $daysToRetry = 14;

    public function __construct(OpenCitationsPlugin &$plugin, int $publicationId, string $token)
    {
        parent::__construct();
        $this->plugin = $plugin;
        $this->publicationId = $publicationId;
        $this->token = $token;
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

        if (empty($this->token)) {
            error_log(__('plugins.generic.openCitations.settings.missingCredentials', [
                'days' => $this->daysToRetry,
            ]));
            dispatch(
                new DepositJob($this->plugin, $this->publicationId, $this->token))
                ->delay(now()->addDays($this->daysToRetry));
            return;
        }

        $deposit = new Deposit($this->publicationId, $this->token);
        $result = $deposit->execute();

        if (!$result) {
            dispatch(
                new DepositJob($this->plugin, $this->publicationId, $this->token))
                ->delay(now()->addDays($this->daysToRetry));
        }
    }
}
