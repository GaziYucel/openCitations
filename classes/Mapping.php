<?php

/**
 * @file classes/Mapping.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mapping
 * @ingroup plugins_generic_opencitations
 *
 * @brief Mapping class for OpenCitations
 *
 * @see https://github.com/opencitations/crowdsourcing
 * @see https://ceur-ws.org/Vol-3220/invited-talk2.pdf
 */

namespace APP\plugins\generic\openCitations\classes;

class Mapping
{
    /**
     * Mapping publication
     */
    public static function getPublication(): array
    {
        return [
            'id' => '', // custom transformation
            'title' => '', // custom transformation
            'authors' => '', // custom transformation
            'pubDate' => '', // custom transformation
            'venue' => '', // custom transformation
            'volume' => ['issue', 'volume'],
            'issue' => ['issue', 'number'],
            'page' => '',
            'type' => '', // custom transformation
            'publisher' => ['context', 'publisherInstitution'],
            'editor' => ''
        ];
    }

    /**
     * Mapping Citations
     */
    public static function getCitation(): array
    {
        return [
            'id' => self::getId(),
            'title' => '', // custom transformation
            'authors' => '', // custom transformation
            'pubDate' => 'publicationDate',
            'venue' => '', // custom transformation
            'volume' => 'volume',
            'issue' => 'issue',
            'page' => '',
            'type' => '', // custom transformation
            'publisher' => 'sourceHost',
            'editor' => ''
        ];
    }

    /**
     * Mapping Relations: citing <> cited
     */
    public static function getRelation(): array
    {
        return [
            'citing_id' => '', // custom transformation
            'cited_id' => self::getId()
        ];
    }

    /**
     * Mapping to OpenCitations id
     */
    public static function getId(): array
    {
        return [
            'arxiv' => 'arxiv',
            'doi' => 'doi',
            'urn' => 'urn',
            'wikidata' => 'wikidata'
        ];
    }
}
