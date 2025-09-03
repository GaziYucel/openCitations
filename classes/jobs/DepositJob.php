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
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class DepositJob extends BaseJob
{
    protected int $publicationId;
    public string $token;

    public function __construct(int $publicationId, string $token)
    {
        parent::__construct();

        $this->publicationId = $publicationId;
        $this->token = $token;
    }

    public function handle(): void
    {
        $publication = Repo::publication()->get($this->publicationId);

        if (!$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $deposit = new Deposit($this->publicationId, $this->token);
        $deposit->execute();
    }
}
