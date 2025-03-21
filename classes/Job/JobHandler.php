<?php

/**
 * @file classes/Job/JobHandler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobHandler
 *
 * @brief Job handler for the plugin.
 */

namespace APP\plugins\generic\openCitations\classes\Job;

use APP\facades\Repo;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class JobHandler extends BaseJob
{
    protected OpenCitationsPlugin $plugin;

    protected int $publicationId;

    public function __construct(OpenCitationsPlugin &$plugin, int $publicationId)
    {
        parent::__construct();

        $this->plugin = &$plugin;

        $this->publicationId = $publicationId;
    }

    public function handle(): void
    {
        $publication = Repo::publication()->get($this->publicationId);

        if (!$this->publicationId || !$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $deposit = new Deposit($this->plugin, $this->publicationId);
        $deposit->execute();
    }
}
