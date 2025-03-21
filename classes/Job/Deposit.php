<?php

/**
 * @file classes/Job/Deposit.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Deposit
 *
 * @brief Deposit class for OpenCitations
 */

namespace APP\plugins\generic\openCitations\classes\Job;

use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\openCitations\classes\Constants;
use APP\plugins\generic\openCitations\classes\Schemas\WorkCitingCited;
use APP\plugins\generic\openCitations\classes\Schemas\WorkMetaData;
use APP\plugins\generic\openCitations\OpenCitationsPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Author;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PKP\context\Context;
use PKP\submission\PKPSubmission;
use ReflectionClass;
use ReflectionProperty;

class Deposit
{
    protected OpenCitationsPlugin $plugin;

    public ?string $githubOwner = null;
    public ?string $githubToken = null;
    public ?string $githubRepository = null;

    protected ?int $publicationId = null;
    protected ?Publication $publication = null;
    protected ?string $publicationLocale = null;

    protected ?int $submissionId = null;
    protected ?Submission $submission = null;

    protected ?int $contextId = null;
    protected ?Context $context = null;

    protected ?int $issueId = null;
    protected ?Issue $issue = null;

    protected ?array $citations = null;

    /** Default article type */
    protected string $defaultType = 'journal article';

    /** @copydoc InboundAbstract::__construct */
    public function __construct(OpenCitationsPlugin &$plugin, int $publicationId)
    {
        $this->plugin = &$plugin;
        $this->publicationId = $publicationId;
    }

    /** Process this external service */
    public function execute(): bool
    {
        // abort if credentials missing
        if (empty($this->githubOwner) || empty($this->githubRepository) || empty($this->githubToken)) {
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification(
                Application::get()->getRequest()->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                array('contents' => __('plugins.generic.openCitations.settings.missing_logon_credentials')));

            return false;
        }

        $this->publication = Repo::publication()->get($this->publicationId);

        // abort if not published or already deposited
        if (
            $this->publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED ||
            !empty($this->publication->getData(Constants::githubIssueUrl))
        ) {
            return true;
        }

        $this->publicationLocale = $this->publication->getData('locale');
        $this->submissionId = $this->publication->getData('submissionId');
        $this->submission = Repo::submission()->get($this->submissionId);
        $this->contextId = $this->submission->getData('contextId');
        $this->context = Application::getContextDAO()->getById($this->contextId);
        $this->issueId = $this->submission->getData('issueId');
        $this->issue = Repo::issue()->get($this->publication->getData('issueId'));
        $this->githubOwner = $this->plugin->getSetting($this->contextId, Constants::githubOwner);
        $this->githubRepository = $this->plugin->getSetting($this->contextId, Constants::githubRepository);
        $this->githubToken = $this->plugin->getSetting($this->contextId, Constants::githubToken);
        $this->citations = Repo::citation()->getByPublicationId($this->publicationId);

        $githubIssueId = $this->addIssue();

        if ($githubIssueId) {
            $this->publication->setData(
                Constants::githubIssueUrl,
                Constants::githubUrl . "/$this->githubOwner/$this->githubRepository/issues/$githubIssueId"
            );

            Repo::publication()->edit($this->publication, []);

            return true;
        }

        return false;
    }

    /** Get Work as publication metadata in comma separated format. */
    private function getPublicationCsv(): string
    {
        $context = Application::getContextDAO()->getById($this->contextId);
        $work = new WorkMetaData();

        $locale = $this->publication->getData('locale');

        // id
        $work->id = 'doi:' . str_replace(Constants::doiPrefix, '', $this->publication->getStoredPubId('doi'));

        // title
        $work->title = $this->publication->getData('title')[$locale];

        // familyName, givenNames [orcid: 0000]
        /** @var Author $author */
        foreach ($this->publication->getData('authors') as $index => $author) {
            if (!empty($author->getFamilyName($locale)))
                $work->author .= $author->getFamilyName($locale) . ', ';
            if (!empty($author->getGivenName($locale)))
                $work->author .= $author->getGivenName($locale);
            if (!empty($author->getData('orcid')))
                $work->author .= ' [orcid:' . str_replace(Constants::orcidPrefix, '', $author->getData('orcid')) . ']';
            $work->author .= '; ';
        }
        $work->author = trim($work->author, '; ');

        // pub_date
        $work->pub_date = '';
        if (!empty($this->issue->getData('datePublished')))
            $work->pub_date = date('Y-m-d', strtotime($this->issue->getData('datePublished')));

        // venue
        $work->venue = $context->getData('name')[$locale];
        $venueIds = '';
        if (!empty($context->getData('onlineIssn')))
            $venueIds .= 'issn:' . $context->getData('onlineIssn') . ' ';
        if (!empty($context->getData('printIssn')))
            $venueIds .= 'issn:' . $context->getData('printIssn') . ' ';
        if (!empty($this->issue->getStoredPubId('doi')))
            $venueIds .= 'doi:' . $this->issue->getStoredPubId('doi') . ' ';
        if (!empty($venueIds))
            $work->venue = trim($work->venue) . ' ' . '[' . trim($venueIds) . ']';

        // volume
        $work->volume = '';
        if (!empty($this->issue->getData('volume'))) $work->volume = $this->issue->getData('volume');

        // issue
        $work->issue = '';
        if (!empty($this->issue->getData('number'))) $work->issue = $this->issue->getData('number');

        // page
        $work->page = '';

        // type
        $work->type = $this->defaultType;
        if (!empty($context->getData('publisherInstitution')))
            $work->publisher = $context->getData('publisherInstitution');

        // editor
        $work->editor = '';

        $values = '';
        foreach ($work as $name => $value) {
            $values .= '"' . str_replace('"', '\"', $value) . '",';
        }

        return trim($values, ',');
    }

    /** Get Citations as citations in comma separated format. */
    private function getCitationsCsv(): string
    {
        $values = '';

        foreach ($this->citations as $citation) {
            $workMetaData = new WorkMetaData();

            if (!empty($citation->getData('doi'))) $workMetaData->id .= 'doi:' . str_replace(Constants::doiPrefix, '', $citation->getData('doi')) . ' ';
            if (!empty($citation->getData('url'))) $workMetaData->id .= $this->getUrl($citation->getData('url')) . ' ';
            if (!empty($citation->getData('urn'))) $workMetaData->id .= 'urn:' . str_replace(' ', '', $citation->getData('urn')) . ' ';
            $workMetaData->id = trim($workMetaData->id);

            $workMetaData->title = $citation->getData('title');

            $workMetaData->author = '';
            if (!empty($citation->getData('authors'))) {
                foreach ($citation->authors as $author) {
                    if (empty($author->getData('orcid'))) {
                        $workMetaData->author .= $author->getData('displayName');
                    } else {
                        $workMetaData->author .= $author->getData('familyName') . ', ' . $author->getData('givenName');
                    }
                    $workMetaData->author .= ' [orcid:' . $author->getData('orcid') . ']';
                    $workMetaData->author .= '; ';
                }
                $workMetaData->author = trim($workMetaData->author, '; ');
            }

            $workMetaData->pub_date = $citation->getData('publicationDate');

            $workMetaData->venue = $citation->getData('journalName');
            if (!empty($citation->getData('journalIssnL'))) $workMetaData->venue .= ' [issn:' . $citation->getData('journalIssnL') . ']';

            $workMetaData->volume = $citation->getData('volume');
            $workMetaData->issue = $citation->getData('issue');
            $workMetaData->page = '';
            $workMetaData->type = str_replace('-', ' ', $citation->getData('type'));
            $workMetaData->publisher = $citation->getData('journalPublisher');
            $workMetaData->editor = '';

            if (!empty($workMetaData->id)) {
                foreach ($workMetaData as $name => $value) {
                    $values .= '"' . str_replace('"', '\"', $value) . '",';
                }
                $values = trim($values, ',');
                $values = $values . PHP_EOL;
            }
        }

        return trim($values, PHP_EOL);
    }

    /** Get Citations in comma separated format. */
    private function getRelationsCsv(): string
    {
        $doi = $this->publication->getStoredPubId('doi');

        $values = '';
        foreach ($this->citations as $index => $citation) {
            $workCitingCited = new WorkCitingCited();

            $workCitingCited->citing_id = 'doi:' . $doi;

            $workCitingCited->cited_id = '';
            if (!empty($citation->getData('doi'))) $workCitingCited->cited_id
                .= 'doi:' . $citation->getData('doi') . ' ';
            if (!empty($citation->getData('url'))) $workCitingCited->cited_id
                .= $this->getUrl($citation->getData('url')) . ' ';
            if (!empty($citation->urn)) $workCitingCited->cited_id
                .= 'urn:' . str_replace(' ', '', $citation->getData('urn')) . ' ';
            $workCitingCited->cited_id = trim($workCitingCited->cited_id);

            if (!empty($workCitingCited->cited_id)) {
                foreach ($workCitingCited as $name => $value) {
                    $values .= '"' . str_replace('"', '\"', $value) . '",';
                }
                $values = trim($values, ',');
                $values = $values . PHP_EOL;
            }
        }

        return trim($values, PHP_EOL);
    }

    /** Get url as arxiv, handle or url */
    private function getUrl(string $url): string
    {
        if (str_contains($url, Constants::arxivPrefix)) {
            return 'arxiv:' . str_replace(Constants::arxivPrefix, '', $url) . ' ';
        } else if (str_contains($url, Constants::handlePrefix)) {
            return 'handle:' . str_replace(Constants::handlePrefix, '', $url) . ' ';
        } else {
            return 'url:' . str_replace(' ', '', $url) . ' ';
        }
    }

    /** Adds issue to a given repository and returns the issue ID. */
    private function addIssue(): string
    {
        $title =
            'deposit' . ' ' .
            $_SERVER['SERVER_NAME'] . ' ' .
            'doi:' . $this->publication->getStoredPubId('doi');

        $body =
            $this->getClassPropertiesAsCsv(new WorkMetaData()) . PHP_EOL .
            $this->getPublicationCsv() . PHP_EOL .
            $this->getCitationsCsv() . PHP_EOL .
            '===###===@@@===' . PHP_EOL .
            $this->getClassPropertiesAsCsv(new WorkCitingCited()) . PHP_EOL .
            $this->getRelationsCsv() . PHP_EOL;

        $result = $this->apiRequest(
            'POST',
            Constants::apiUrl . "/$this->githubOwner/$this->githubRepository/issues",
            [
                'json' =>
                    [
                        'title' => $title,
                        'body' => $body,
                        'labels' => ['Deposit']
                    ]
            ]);

        if (is_numeric($result['number'] && (string)$result['number'] !== '0')) {
            return $result['number'];
        }

        return '';
    }

    /** Makes HTTP request to the API and returns the response as an array. */
    public function apiRequest(string $method, string $url, array $options): array
    {
        if ($method !== 'POST' && $method !== 'GET') return [];

        $httpClient = new Client(
            [
                'headers' => [
                    'User-Agent' => Application::get()->getName(),
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => 'token ' . $this->githubToken
                ],
                'verify' => false
            ]
        );

        try {
            $response = $httpClient->request($method, $url, $options);

            if (!str_contains('200,201,202', (string)$response->getStatusCode())) {
                return [];
            }

            $result = json_decode($response->getBody(), true);

            if (empty($result) || json_last_error() !== JSON_ERROR_NONE) return [];

            return $result;

        } catch (GuzzleException $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }

        return [];
    }

    /** Get class public properties as a csv, e.g. "id","title","pub_date" */
    public function getClassPropertiesAsCsv(object $class, ?string $separator = ','): string
    {
        $result = '';

        $reflect = new ReflectionClass($class);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $result .= '"' . $property->getName() . '"' . $separator;
        }

        return trim($result, $separator);
    }
}
