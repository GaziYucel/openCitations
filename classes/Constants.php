<?php

/**
 * @file classes/Constants.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Constants
 *
 * @brief Constants class for OpenCitations
 */

namespace APP\plugins\generic\openCitations\classes;

class Constants
{
    /** GitHub handle / account */
    public const githubOwner = 'OpenCitationsPlugin_Owner';

    /** GitHub repository name */
    public const githubRepository = 'OpenCitationsPlugin_Repository';

    /** GitHub APi token */
    public const githubToken = 'OpenCitationsPlugin_Token';

    /** Url to the GitHub issue. */
    public const githubIssueUrl = 'OpenCitationsPlugin_GitHubIssueUrl';

    /** URL of GitHub */
    public const githubUrl = 'https://github.com';

    /** The base URL for API requests. */
    public const apiUrl = 'https://api.github.com/repos';

    /** Prefix for Doi */
    public const doiPrefix = 'https://doi.org/';

    /** Prefix for Arxiv */
    public const arxivPrefix = 'https://arxiv.org/abs/';

    /** Prefix for Handle*/
    public const handlePrefix = 'https://hdl.handle.net/';

    /** Prefix for Orcid*/
    public const orcidPrefix = 'https://orcid.org/';
}
