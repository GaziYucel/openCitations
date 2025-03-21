<?php

/**
 * @file classes/Schemas/WorkCitingCited.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkCitingCited
 *
 * @brief Relation Citing and Cited work.
 */

namespace APP\plugins\generic\openCitations\classes\Schemas;

class WorkCitingCited
{
    public ?string $citing_id = null;
    public ?string $cited_id = null;
}
