<?php

/**
 * @file plugins/generic/issuePreselection/classes/SubmissionManagement.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionManagement
 * @brief Handles submission-related functionality for the Issue Preselection plugin
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\plugins\generic\issuePreselection\IssuePreselectionPlugin;
use APP\submission\Submission;
use Exception;
use PKP\context\Context;

class SubmissionManagement extends BaseManagement
{
    /**
     * Form handler for submission form modifications
     *
     * @var SubmissionFormHandler
     */
    private SubmissionFormHandler $formHandler;

    /**
     * Service for managing editor assignments
     *
     * @var EditorAssignmentService
     */
    private EditorAssignmentService $editorService;

    /**
     * Constructor
     *
     * Initializes the form handler and editor assignment service.
     *
     * @param IssuePreselectionPlugin $plugin Reference to the main plugin instance
     */
    public function __construct(IssuePreselectionPlugin &$plugin)
    {
        parent::__construct($plugin);
        $this->formHandler = new SubmissionFormHandler();
        $this->editorService = new EditorAssignmentService($this);
    }

    /**
     * Add issue preselection field to submission schema
     *
     * Adds the preselectedIssueId property to the submission schema,
     * allowing submissions to store their preselected issue assignment.
     *
     * @hook Schema::get::submission
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [&$schema]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function addToSubmissionSchema(string $hookName, array $params): bool
    {
        $params[0]->properties->{Constants::SUBMISSION_PRESELECTED_ISSUE_ID} = (object) [
            "type" => "integer",
            "apiSummary" => true,
            "writeDisabledInApi" => false,
            "validation" => ["nullable"],
        ];

        return false;
    }

    /**
     * Add issue selector field to submission form
     *
     * Injects an issue selection dropdown into the submission form,
     * allowing authors to select which open issue their submission
     * should be assigned to.
     *
     * @hook Form::config::after
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [&$config, $form]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function addToSubmissionForm(string $hookName, array $params): bool
    {
        if (!$this->formHandler->isCommentsForEditorsForm($params[1])) {
            return false;
        }

        $context = $this->getContext();
        if (!$context) {
            return false;
        }

        $submission = $this->formHandler->extractSubmissionFromConfig($params[0]);
        if (!$submission) {
            return false;
        }

        $issues = IssueRepository::getOpenFutureIssues($context->getId());
        if (empty($issues)) {
            return false;
        }

        $this->formHandler->addIssueFieldToForm($params[0], $submission, $issues);

        return false;
    }

    /**
     * Add issue information to the review section
     *
     * Displays the selected issue in the submission wizard review section
     * so authors can confirm their issue selection before submitting.
     *
     * @hook Template::SubmissionWizard::Section::Review::Editors
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [$hookName, $smarty, &$output]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function addIssueReviewSection(string $hookName, array $params): bool
    {
        if (!$this->shouldShowReviewSection($params[1])) {
            return false;
        }

        $context = $this->getContext();
        if (!$context) {
            return false;
        }

        $issues = IssueRepository::getOpenFutureIssues($context->getId());
        if (empty($issues)) {
            return false;
        }

        $issueMap = $this->formHandler->buildIssueMap($issues);
        $params[1]->assign("issueMap", $issueMap);
        $params[2] .= $this->renderTemplate($params[1], "submissionReviewIssue.tpl");

        return false;
    }

    /**
     * Check if review section should be shown
     *
     * Determines if the review section should display the issue information
     * by checking if a submission exists and the locale matches.
     *
     * @param object $smarty The Smarty template engine instance
     *
     * @return bool True if the review section should be shown, false otherwise
     */
    private function shouldShowReviewSection(object $smarty): bool
    {
        $submission = $smarty->getTemplateVars("submission");
        if (!$submission) {
            return false;
        }

        $localeKey = $smarty->getTemplateVars("localeKey");
        return $localeKey === $submission->getData("locale");
    }

    /**
     * Handle submission validation - assign issue and editors when submitted
     *
     * Validates that an issue is selected when required, then assigns the
     * submission to the selected issue and automatically assigns the issue's
     * editors to the submission as guest editors.
     *
     * @hook Submission::validateSubmit
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [&$errors, $submission, $context]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function handleSubmissionValidate(string $hookName, array $params): bool
    {
        $submission = $params[1];
        $context = $params[2];
        $issueId = $submission->getData(Constants::SUBMISSION_PRESELECTED_ISSUE_ID);

        if ($this->shouldRequireIssueSelection($context, $issueId)) {
            $this->addValidationError($params[0]);
            return false;
        }

        if (!$issueId) {
            return false;
        }

        $issue = $this->getValidIssue($issueId);
        if (!$issue) {
            $this->addValidationError($params[0], "plugins.generic.issuePreselection.error.issueNotAvailable");
            return false;
        }

        try {
            $this->assignSubmissionToIssue($submission, $issue);
        } catch (Exception $e) {
            $this->addValidationError($params[0], "plugins.generic.issuePreselection.error.assignmentFailed");
            error_log(
                "[IssuePreselection] Failed to assign submission {$submission->getId()} to issue {$issue->getId()}: {$e->getMessage()}",
            );
            return false;
        }

        return false;
    }

    /**
     * Check if issue selection is required
     *
     * Determines if issue selection is mandatory by checking if there are open
     * issues available and if no issue has been selected.
     *
     * @param Context $context The journal/press context
     * @param mixed $issueId The selected issue ID (can be null, int, or other)
     *
     * @return bool True if issue selection is required but missing, false otherwise
     */
    private function shouldRequireIssueSelection(Context $context, mixed $issueId): bool
    {
        $openIssues = IssueRepository::getOpenFutureIssues($context->getId());
        return !empty($openIssues) && !$issueId;
    }

    /**
     * Add validation error for missing or invalid issue
     *
     * Adds an error message to the errors array for the preselectedIssueId field.
     *
     * @param array &$errors The errors array (passed by reference)
     * @param string $messageKey The locale key for the error message
     *
     * @return void
     */
    private function addValidationError(
        array &$errors,
        string $messageKey = "plugins.generic.issuePreselection.error.issueRequired",
    ): void {
        $errors[Constants::SUBMISSION_PRESELECTED_ISSUE_ID] = [__($messageKey)];
    }

    /**
     * Get valid issue if it exists and is open
     *
     * Retrieves the issue and validates that it exists and is marked as open
     * for submissions.
     *
     * @param int $issueId The issue ID to validate
     *
     * @return Issue|null The issue object or null if not found or not open
     */
    private function getValidIssue(int $issueId): ?object
    {
        $issue = Repo::issue()->get($issueId);

        if (!$issue || !$issue->getData(Constants::ISSUE_IS_OPEN)) {
            return null;
        }

        return $issue;
    }

    /**
     * Assign submission to issue and assign editors
     *
     * Updates the submission's current publication to be assigned to the specified
     * issue, then assigns the issue's editors to the submission.
     *
     * @param Submission $submission The submission to assign
     * @param Issue $issue The issue to assign the submission to
     *
     * @return void
     *
     * @throws Exception If the assignment fails
     */
    private function assignSubmissionToIssue(Submission $submission, Issue $issue): void
    {
        $publication = $submission->getCurrentPublication();
        if (!$publication) {
            error_log("[IssuePreselection] Cannot assign submission {$submission->getId()} - no current publication");
            return;
        }

        try {
            Repo::publication()->edit($publication, ["issueId" => $issue->getId()]);
            $this->assignIssueEditorsToSubmission($submission, $issue);
        } catch (Exception $exception) {
            error_log(
                "[IssuePreselection] Failed to assign submission {$submission->getId()} to issue {$issue->getId()}: {$exception->getMessage()}",
            );
            throw $exception;
        }
    }

    /**
     * Assign issue editors to submission
     *
     * Uses the issue's editedBy field as the single source of truth for editor
     * assignments. Syncs the submission's editor assignments to match the issue.
     *
     * @param Submission $submission The submission to assign editors to
     * @param Issue $issue The issue whose editors should be assigned
     *
     * @return void
     */
    private function assignIssueEditorsToSubmission(Submission $submission, Issue $issue): void
    {
        $editorIds = $issue->getData(Constants::ISSUE_EDITED_BY);

        if (!is_array($editorIds)) {
            $editorIds = [];
        }

        $this->editorService->syncEditorsToSubmission($submission, $editorIds, Application::get()->getRequest());
    }
}
