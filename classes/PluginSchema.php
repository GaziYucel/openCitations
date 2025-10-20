<?php

/**
 * @file classes/PluginSchema.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginSchema
 * @ingroup plugins_generic_opencitations
 *
 * @brief Schema class for Publication
 */

namespace APP\plugins\generic\openCitations\classes;

class PluginSchema
{
    /**
     * This method adds properties to the schema of a publication.
     */
    public function addToPublication(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        $schema->properties->{Constants::depositedUrlName} = (object)[
            'type' => 'string',
            'multilingual' => false,
            'apiSummary' => true,
            'validation' => ['nullable']
        ];

        return false;
    }
}
