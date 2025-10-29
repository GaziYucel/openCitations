<?php

/**
 * @file classes/Constants.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Constants
 * @ingroup plugins_generic_opencitations
 *
 * @brief Constants class for OpenCitations
 */

namespace APP\plugins\generic\openCitations\classes;

class Constants
{
    /** GitHub handle / account */
    public const owner = 'opencitations';

    /** GitHub repository name */
    public const repository = 'crowdsourcing';

    /** GitHub APi token */
    public const token = 'openCitations::token';

    /** Url to the GitHub issues. */
    public const depositedUrlName = 'openCitations::depositedUrl';

    /** URL of GitHub */
    public const githubUrl = 'https://github.com';

    /** The base URL for API requests. */
    public const apiUrl = 'https://api.github.com/repos';
}
