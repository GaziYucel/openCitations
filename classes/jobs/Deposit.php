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

use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\publication\Publication;
use APP\submission\Submission;
use Author;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PKP\citation\Citation;
use PKP\context\Context;
use PKP\job\exceptions\JobException;

class Deposit
{
    protected ?int $publicationId = null;
    protected ?Publication $publication = null;
    protected ?Submission $submission = null;
    protected ?Context $context = null;
    protected ?Issue $issue = null;
    protected ?array $citations = null;
    protected string $domain = '';
    protected ?string $locale = null;
    protected string $publicationDoi = '';

    protected string $token;

    protected string $defaultArticleType = 'journal article';
    protected array $metaDataSchema = ['id', 'title', 'authors', 'pubDate', 'venue', 'volume', 'issue', 'page', 'type', 'publisher', 'editor'];
    protected array $relationsSchema = ['citing_id', 'cited_id'];

    public function __construct(int $publicationId, string $token)
    {
        $this->publicationId = $publicationId;
        $this->token = $token;

        $this->publication = Repo::publication()->get($this->publicationId);
        $this->publicationDoi = $this->publication->getStoredPubId('doi');
    }

    /**
     * Process this external service
     */
    public function execute(): void
    {
        $this->submission = Repo::submission()->get($this->publication->getData('submissionId'));
        $this->context = Application::getContextDAO()->getById($this->submission->getData('contextId'));
        $this->issue = Repo::issue()->get($this->publication->getData('issueId'));
        $this->citations = Repo::citation()->getByPublicationId($this->publicationId);
        $this->domain = $_SERVER['SERVER_NAME'];
        $this->locale = $this->publication->getData('locale');

        $title = 'deposit' . ' ' . $this->domain . ' ' . 'doi:' . $this->publicationDoi;

        $body =
            '"' . implode('","', $this->metaDataSchema) . '"' . PHP_EOL .
            '"' . implode('","', $this->getPublication()) . '"' . PHP_EOL .
            implode(PHP_EOL, $this->getCitations()) . PHP_EOL .
            '===###===@@@===' . PHP_EOL .
            '"' . implode('","', $this->relationsSchema) . '"' . PHP_EOL .
            implode(PHP_EOL, $this->getRelations()) . PHP_EOL;

        $githubIssueId = $this->addIssue($title, $body);

        if ($githubIssueId) {
            $this->publication->setData(
                Constants::depositedUrlName,
                Constants::githubUrl . '/' . Constants::owner . '/' . Constants::repository . '/issues/' . $githubIssueId
            );
            Repo::publication()->edit($this->publication, []);
        }
    }

    /**
     * Get Work as publication metadata in comma separated format.
     */
    private function getPublication(): array
    {
        $id = 'doi:' . str_replace(Constants::pidPrefix['doi'], '', $this->publicationDoi);

        $title = $this->publication->getData('title')[$this->locale];

        $authors = '';
        foreach ($this->publication->getData('authors') as $author) {
            /** @var Author $author */
            $authors .= !empty($author->getFamilyName($this->locale)) ? $author->getFamilyName($this->locale) . ', ' : '';
            $authors .= !empty($author->getGivenName($this->locale)) ? $author->getGivenName($this->locale) : '';
            $authors .= !empty($author->getData('orcid')) ? ' [orcid:' . str_replace(Constants::pidPrefix['orcid'], '', $author->getData('orcid')) . ']' : '';
            $authors .= '; ';
        }
        $authors = trim($authors, '; ');

        $pubDate = !empty($this->issue->getData('datePublished')) ? date('Y-m-d', strtotime($this->issue->getData('datePublished'))) : '';

        $venueName = !empty($this->context->getData('name')[$this->locale]) ? $this->context->getData('name')[$this->locale] : '';
        $venueIds = !empty($this->context->getData('onlineIssn')) ? 'issn:' . $this->context->getData('onlineIssn') . ' ' : '';
        $venueIds .= !empty($this->context->getData('printIssn')) ? 'issn:' . $this->context->getData('printIssn') . ' ' : '';
        $venueIds .= !empty($this->issue->getStoredPubId('doi')) ? 'issn:' . $this->context->getData('doi') . ' ' : '';
        $venue = !empty($venueIds) ? trim($venueName) . ' ' . '[' . trim($venueIds) . ']' : trim($venueName);

        $volume = !empty($this->issue->getData('volume')) ? $this->issue->getData('volume') : '';

        $issue = !empty($this->issue->getData('number')) ? $this->issue->getData('number') : '';

        $page = '';

        $type = !empty($this->context->getData('publisherInstitution')) ? $this->context->getData('publisherInstitution') : $this->defaultArticleType;

        $publisher = '';

        $editor = '';

        return [$id, $title, $authors, $pubDate, $venue, $volume, $issue, $page, $type, $publisher, $editor];

    }

    /**
     * Get Citations as citations in comma separated format.
     */
    private function getCitations(): array
    {
        $rows = [];

        foreach ($this->citations as $citation) {
            /** @var Citation $citation */
            $id = !empty($citation->getData('doi')) ? 'doi:' . str_replace(Constants::pidPrefix['doi'], '', $citation->getData('doi')) . ' ' : '';
            $id .= !empty($citation->getData('url')) ? $this->getUrl($citation->getData('url')) . ' ' : '';
            $id .= !empty($citation->getData('urn')) ? 'urn:' . str_replace(' ', '', $citation->getData('urn')) . ' ' : '';
            $id = trim($id);

            if (empty($id)) {
                continue;
            }

            $title = !empty($citation->getData('title')) ? $citation->getData('title') : '';

            $authors = '';
            if (!empty($citation->getData('authors')) && $citation->getData('authors') !== null) {
                foreach ($citation->getData('authors') as $author) {
                    $authors .= $author['familyName'] . ' ' . $author['givenName'];
                    $authors .= !empty($author['orcid']) ? ' ' . '[orcid:' . str_replace(Constants::pidPrefix['orcid'], '', $author['orcid']) . ']' : '';
                    $authors .= '; ';
                }
            }
            $authors = trim($authors, '; ');

            $pubDate = $citation->getData('publicationDate');

            $venue = !empty($citation->getData('sourceIssn'))
                ? $citation->getData('sourceName') . ' [issn:' . $citation->getData('sourceIssn') . ']'
                : $citation->getData('sourceName');

            $volume = $citation->getData('volume');

            $issue = $citation->getData('issue');

            $page = '';

            $type = str_replace('-', ' ', $citation->getData('type'));

            $publisher = $citation->getData('sourcePublisher');

            $editor = '';

            $rows[] = '"' . implode('","', [$id, $title, $authors, $pubDate, $venue, $volume, $issue, $page, $type, $publisher, $editor]) . '"';
        }

        return $rows;
    }

    /**
     * Get relations publication <> citations.
     */
    private function getRelations(): array
    {
        $rows = [];

        foreach ($this->citations as $citation) {
            /** @var Citation $citation */
            $citingId = 'doi:' . $this->publicationDoi;

            $citedId = !empty($citation->getData('doi')) ? 'doi:' . $citation->getData('doi') . ' ' : '';
            $citedId .= !empty($citation->getData('url')) ? $this->getUrl($citation->getData('url')) . ' ' : '';
            $citedId .= !empty($citation->getData('urn')) ? 'urn:' . str_replace(' ', '', $citation->getData('urn')) . ' ' : '';
            $citedId = trim($citedId);

            if (empty($citingId) || empty($citedId)) {
                continue;
            }

            $rows[] = '"' . implode('","', [$citingId, $citedId]) . '"';
        }

        return $rows;
    }

    /**
     * Get url as arxiv, handle or url.
     */
    private function getUrl(string $url): string
    {
        if (str_contains($url, Constants::pidPrefix['arxiv'])) {
            return 'arxiv:' . str_replace(Constants::pidPrefix['arxiv'], '', $url) . ' ';
        } else if (str_contains($url, Constants::pidPrefix['handle'])) {
            return 'handle:' . str_replace(Constants::pidPrefix['handle'], '', $url) . ' ';
        } else {
            return 'url:' . str_replace(' ', '', $url) . ' ';
        }
    }

    /**
     * Adds issue to a given repository and returns the issue ID.
     */
    private function addIssue(string $title, string $body): string
    {
        try {
            $client = new Client(
                [
                    'headers' => [
                        'User-Agent' => Application::get()->getName(),
                        'Accept' => 'application/vnd.github.v3+json',
                        'Authorization' => 'token ' . $this->token
                    ],
                    'verify' => false
                ]
            );

            $response = $client->request(
                'POST',
                Constants::apiUrl . '/' . Constants::owner . '/' . Constants::repository . '/issues',
                [
                    'json' =>
                        [
                            'title' => $title,
                            'body' => $body,
                            'labels' => ['Deposit']
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
                return $result['number'];
            }

        } catch (GuzzleException|Exception $e) {
            throw new JobException($e->getMessage());
        }

        return '';
    }
}
