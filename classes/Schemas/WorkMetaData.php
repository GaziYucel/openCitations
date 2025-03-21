<?php

/**
 * @file classes/Schemas/WorkMetaData.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkMetaData
 *
 * @brief MetaData of citing and cited works.
 */

namespace APP\plugins\generic\openCitations\classes\Schemas;

class WorkMetaData
{
    public ?string $id = null;
    public ?string $title = null;
    public ?string $author = null;
    public ?string $pub_date = null;
    public ?string $venue = null;
    public ?string $volume = null;
    public ?string $issue = null;
    public ?string $page = null;
    public ?string $type = null;
    public ?string $publisher = null;
    public ?string $editor = null;
}
