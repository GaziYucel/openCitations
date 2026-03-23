<?php

/**
 * @file classes/jobs/Deposit.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Deposit
 * @ingroup plugins_generic_opencitations
 *
 * @brief Deposit class for OpenCitations
 */

namespace APP\plugins\generic\openCitations\classes\jobs;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\classes\Mapping;
use APP\publication\Publication;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use PKP\citation\Citation;
use PKP\config\Config;
use PKP\context\Context;
use PKP\file\FileManager;

class Deposit
{
    private int $publicationId;
    private string $apiToken;
    private bool $isSaveToFileOnly;

    private Publication $publication;
    private Context $context;
    private Issue $issue;
    private string $locale;

    private string $defaultArticleType = 'journal article';

    private string $publicationDoi;

    public function __construct(int $publicationId, string $apiToken, bool $isSaveToFileOnly = false)
    {
        $this->publicationId = $publicationId;
        $this->apiToken = $apiToken;
        $this->isSaveToFileOnly = $isSaveToFileOnly;

        $this->publication = Repo::publication()->get($this->publicationId);
        $this->context = Application::getContextDAO()->getById(
            Repo::submission()->get($this->publication->getData('submissionId'))->getData('contextId')
        );
        $this->issue = Repo::issue()->get($this->publication->getData('issueId'));
        $this->locale = $this->publication->getData('locale');

        $this->publicationDoi = $this->publication->getDoi();
    }

    /**
     * Process the publication deposit with the external service.
     * If sandbox enabled or issue body is empty or isSaveToFileOnly is true => save only to file.
     */
    public function execute(): bool
    {
        $issueTitle = sprintf('deposit %s doi:%s', $_SERVER['SERVER_NAME'], $this->publicationDoi);
        $issueBody = $this->prepareIssueBody();

        if (Config::getVar('general', 'sandbox', false) ||
            empty($issueBody) ||
            $this->isSaveToFileOnly
        ) {
            $this->saveToFile($issueTitle, $issueBody);
            return true;
        }

        $githubIssueId = $this->createGithubIssue($issueTitle, $issueBody);
        if ($githubIssueId) {
            $this->publication->setData(
                Constants::depositedUrlName,
                sprintf('%s/%s/%s/issues/%s',
                    Constants::githubUrl, Constants::owner, Constants::repository, $githubIssueId)
            );
            Repo::publication()->edit($this->publication, []);
            return true;
        }

        return false;
    }

    /**
     * Prepare body of the issue.
     */
    private function prepareIssueBody(): string
    {
        $citationMetadataList = [];
        $citingCitedIdsList = [];

        $citations = Repo::citation()->getByPublicationId($this->publication->getId());

        foreach ($citations as $citation) {
            if (empty($citation->getData('arxiv')) &&
                empty($citation->getData('doi')) &&
                empty($citation->getData('urn')) &&
                empty($citation->getData('wikidata'))
            ) {
                continue;
            }

            $citationMetadataList[] = '"' . implode('","', $this->getCitationMetadata($citation)) . '"';
            $citingCitedIdsList[] = '"' . implode('","', $this->getCitingCitedIds($citation)) . '"';
        }

        $body =
            '"' . implode('","', array_keys(Mapping::getPublication())) . '"' . PHP_EOL .
            '"' . implode('","', $this->getPublicationMetadata()) . '"' . PHP_EOL .
            implode(PHP_EOL, $citationMetadataList) . PHP_EOL .
            '===###===@@@===' . PHP_EOL .
            '"' . implode('","', array_keys(Mapping::getRelation())) . '"' . PHP_EOL .
            implode(PHP_EOL, $citingCitedIdsList) . PHP_EOL;

        return $body ?: '';
    }

    /**
     * Get publication metadata in a comma-separated format.
     */
    private function getPublicationMetadata(): array
    {
        $metadata = [];

        foreach (Mapping::getPublication() as $key => $mappedKey) {
            $newValue = '';
            switch ($key) {
                case 'id':
                    $newValue = 'doi:' . $this->publicationDoi;
                    break;
                case 'title':
                    $newValue = $this->publication->getData('title')[$this->locale];
                    break;
                case 'authors':
                    /** @var Author $author */
                    foreach ($this->publication->getData('authors') as $author) {
                        $newValue .= empty($author->getData('orcid'))
                            ? $author->getFamilyName($this->locale) . ', ' . $author->getGivenName($this->locale) . ';'
                            : $author->getFamilyName($this->locale) . ', ' . $author->getGivenName($this->locale) . '[orcid:' . $author->getData('orcid') . '];';
                    }
                    break;
                case 'pubDate':
                    $newValue = !empty($this->issue->getData('datePublished'))
                        ? date('Y-m-d', strtotime($this->issue->getData('datePublished')))
                        : '';
                    break;
                case 'venue':
                    $venueIds = !empty($this->context->getData('doi'))
                        ? 'doi:' . $this->context->getData('doi') . ' '
                        : '';
                    if (!empty($this->context->getData('onlineIssn'))) {
                        $venueIds .= 'issn:' . $this->context->getData('onlineIssn') . ' ';
                    } else if (!empty($this->context->getData('printIssn'))) {
                        $venueIds .= 'issn:' . $this->context->getData('printIssn') . ' ';
                    }
                    $newValue = !empty($venueIds)
                        ? $this->context->getData('name')[$this->locale] . ' [' . trim($venueIds) . ']'
                        : $this->context->getData('name')[$this->locale];
                    break;
                case 'type':
                    $newValue = $this->defaultArticleType;
                    break;
                default:
                    if (!empty($mappedKey) && is_array($mappedKey)) {
                        $newValue = !empty($this->{$mappedKey[0]}->getData($mappedKey[1]))
                            ? $this->{$mappedKey[0]}->getData($mappedKey[1])
                            : '';
                    }
                    break;
            }
            $metadata[] = trim($newValue, ',; ');
        }

        return $metadata;
    }

    /**
     * Get citations in a comma-separated format.
     */
    private function getCitationMetadata($citation): array
    {
        $metadata = [];

        foreach (Mapping::getCitation() as $key => $mappedKey) {
            $newValue = '';
            switch ($key) {
                case 'id':
                    foreach ($mappedKey as $idKey => $idMappedKey) {
                        $newValue .= !empty($citation->getData($idMappedKey))
                            ? $idKey . ':' . $citation->getData($idMappedKey) . ' '
                            : '';
                    }
                    break;
                case 'authors':
                    if (!empty($citation->getData('authors')) && $citation->getData('authors') !== null) {
                        foreach ($citation->getData('authors') as $author) {
                            $newValue .= empty($author['orcid'])
                                ? $author['familyName'] . ', ' . $author['givenName'] . ';'
                                : $author['familyName'] . ', ' . $author['givenName'] . '[orcid:' . $author['orcid'] . '];';
                        }
                    }
                    break;
                case 'venue':
                    $newValue = !empty($citation->getData('sourceIssn'))
                        ? $citation->getData('sourceName') . ' [issn:' . $citation->getData('sourceIssn') . ']'
                        : $citation->getData('sourceName');
                    break;
                case 'type':
                    $newValue = !empty($citation->getData('type'))
                        ? str_replace('-', ' ', $citation->getData('type'))
                        : '';
                    break;
                default:
                    $newValue = !empty($mappedKey) && !empty($citation->getData($mappedKey))
                        ? $citation->getData($mappedKey)
                        : '';
                    break;
            }
            $metadata[] = trim($newValue, ',; ');
        }

        return $metadata;
    }

    /**
     * Get relations between citing_id and cited_id.
     */
    private function getCitingCitedIds(Citation $citation): array
    {
        $relationData = [];

        foreach (Mapping::getRelation() as $key => $mappedKey) {
            $newValue = '';
            switch ($key) {
                case 'citing_id':
                    $newValue = 'doi:' . $this->publicationDoi;
                    break;
                case 'cited_id':
                    foreach ($mappedKey as $idKey => $idMappedKey) {
                        $newValue .= !empty($citation->getData($idMappedKey))
                            ? $idKey . ':' . $citation->getData($idMappedKey) . ' '
                            : '';
                    }
                    break;
            }
            $relationData[] = trim($newValue, ',; ');
        }

        return $relationData;
    }

    /**
     * Adds an issue to the repository and returns the issue ID.
     */
    private function createGithubIssue(string $title, string $body): string
    {
        try {
            $httpClient = Application::get()->getHttpClient();

            $response = $httpClient->request(
                'POST',
                Constants::apiUrl . '/' . Constants::owner . '/' . Constants::repository . '/issues',
                [
                    'headers' => [
                        'accept' => 'application/vnd.github.v3+json',
                        'authorization' => 'token ' . $this->apiToken,
                        'mailto:' . $this->context->getContactEmail(),
                    ],
                    'json' => [
                        'title' => $title,
                        'body' => $body,
                        'labels' => ['deposit']
                    ]
                ]
            );

            if (!str_contains('200,201,202', (string)$response->getStatusCode())) {
                return '';
            }

            $result = json_decode($response->getBody(), true);

            if (empty($result) || json_last_error() !== JSON_ERROR_NONE) {
                return '';
            }

            if (is_numeric($result['number']) && (string)$result['number'] !== '0') {
                return (string)$result['number'];
            }
        } catch (GuzzleException|Exception $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Saves the issue details to a file.
     */
    private function saveToFile(string $title, string $body): void
    {
        try {
            $fileManager = new FileManager();

            $fileName = Config::getVar('files', 'files_dir') . '/temp/' . 'OpenCitationsPlugin' . '-' .
                $this->context->getId() . '-' . $this->publicationId . '-' . date('Ymd-His') . '.txt';

            $fileContent = json_encode(['title' => $title, 'body' => $body], JSON_PRETTY_PRINT);

            $fileManager->writeFile($fileContent, $fileName);
        } catch (Exception $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }
    }
}
