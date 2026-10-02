<?php

/**
 * @file plugins/generic/issuePreselection/classes/IssueManagement.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueManagement
 * @brief Handles issue-related functionality for the Issue Preselection plugin
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\controllers\grid\issues\form\IssueForm;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\template\TemplateManager;
use PKP\context\Context;

class IssueManagement extends BaseManagement
{
    /**
     * Add custom fields to issue schema
     *
     * Adds two custom properties to the issue schema:
     * - isOpen: Boolean flag indicating if the issue is open for submissions
     * - editedBy: Array of editor user IDs assigned to this issue
     *
     * @hook Schema::get::issue
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [&$schema]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function addToIssueSchema(string $hookName, array $params): bool
    {
        $params[0]->properties->{Constants::ISSUE_IS_OPEN} = (object) [
            "type" => "boolean",
            "apiSummary" => false,
            "validation" => ["nullable"],
        ];

        $params[0]->properties->{Constants::ISSUE_EDITED_BY} = (object) [
            "type" => "array",
            "items" => (object) ["type" => "integer"],
            "apiSummary" => false,
            "validation" => ["nullable"],
        ];

        return false;
    }

    /**
     * Add fields to issue form template
     *
     * Injects custom form fields into the issue editing form to allow editors
     * to mark an issue as open for submissions and assign editors to it.
     *
     * @hook Templates::Editor::Issues::IssueData::AdditionalMetadata
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [$hookName, $smarty, &$output]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function addIssueFormFields(string $hookName, array $params): bool
    {
        $context = $this->getContext();
        if (!$context) {
            return false;
        }

        $issue = $this->getCurrentIssue();
        $templateData = $this->prepareIssueFormData($issue, $context);

        $params[1]->assign($templateData);
        $params[2] .= $this->renderTemplate($params[1], "issueFormFields.tpl");

        return false;
    }

    /**
     * Get the current issue being edited
     *
     * Retrieves the issue ID from the request and fetches the corresponding issue object.
     *
     * @return Issue|null The issue object or null if not found
     */
    private function getCurrentIssue(): ?object
    {
        $issueId = $this->getRequest()->getUserVar("issueId");
        return $issueId ? Repo::issue()->get($issueId) : null;
    }

    /**
     * Prepare template data for issue form
     *
     * Assembles all data needed for the issue form template, including the issue's
     * open status, assigned editors, and available editor options.
     *
     * @param Issue|null $issue The issue being edited
     * @param Context $context The current journal/press context
     *
     * @return array Associative array of template variables
     */
    private function prepareIssueFormData(?Issue $issue, Context $context): array
    {
        return [
            "issuePreselectionIsOpen" => $this->getIssueOpenStatus($issue),
            "issuePreselectionCanAssign" => !$issue || $this->getIssueOpenStatus($issue),
            "issuePreselectionEditors" => $this->getIssueEditors($issue),
            "issuePreselectionAllEditors" => $this->getEditorList($context),
        ];
    }

    /**
     * Get template data for the editor assignment panel modal
     *
     * Public wrapper used by IssuePreselectionPlugin::manage() to render
     * the dedicated editor-assign mini-modal without duplicating logic.
     *
     * @param Issue|null $issue
     * @param Context $context
     *
     * @return array
     */
    public function getEditorPanelData(?Issue $issue, Context $context): array
    {
        return $this->prepareIssueFormData($issue, $context);
    }

    /**
     * Render the editor assignment panel template for the mini-modal GET request
     *
     * @param \APP\core\Request $request
     *
     * @return string Rendered HTML
     */
    public function renderEditorAssignPanel(\APP\core\Request $request): string
    {
        $context = $request->getContext();
        $issueId = (int) $request->getUserVar("issueId");
        $issue = $issueId ? Repo::issue()->get($issueId, $context?->getId()) : null;

        $smarty = TemplateManager::getManager($request);
        $smarty->assign($this->getEditorPanelData($issue, $context));
        $smarty->assign("issueId", $issueId);
        return $this->renderTemplate($smarty, "editorAssignPanel.tpl");
    }

    /**
     * Get issue open status
     *
     * Retrieves the isOpen flag from the issue data, defaulting to false if not set.
     *
     * @param Issue|null $issue The issue object
     *
     * @return bool True if the issue is open for submissions, false otherwise
     */
    private function getIssueOpenStatus(?Issue $issue): bool
    {
        return $issue && $issue->getData(Constants::ISSUE_IS_OPEN);
    }

    /**
     * Get editors assigned to issue
     *
     * Retrieves the editedBy array from the issue data, defaulting to an empty array.
     *
     * @param Issue|null $issue The issue object
     *
     * @return array Array of editor user IDs
     */
    private function getIssueEditors(?Issue $issue): array
    {
        return $issue ? ($issue->getData(Constants::ISSUE_EDITED_BY) ?: []) : [];
    }

    /**
     * Ensure custom data is preserved when issue is edited
     *
     * When an issue is edited, this hook ensures that our custom fields
     * (isOpen and editedBy) are preserved if they're not explicitly set
     * in the new issue data.
     *
     * @hook Issue::edit
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [&$newIssue, $issue, $request]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function beforeIssueEdit(string $hookName, array $params): bool
    {
        foreach ([Constants::ISSUE_IS_OPEN, Constants::ISSUE_EDITED_BY] as $field) {
            if ($params[0]->getData($field) === null && $params[1]->getData($field) !== null) {
                $params[0]->setData($field, $params[1]->getData($field));
            }
        }
        return false;
    }

    /**
     * Read issue form data - register our custom fields
     *
     * Registers our custom fields (isOpen and editedBy) so they are read from
     * the form submission and made available to the form handler.
     *
     * @hook issueform::readuservars
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [$issueForm, &$userVars]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function readIssueFormData(string $hookName, array $params): bool
    {
        $params[1][] = Constants::ISSUE_IS_OPEN;
        $params[1][] = Constants::ISSUE_EDITED_BY;
        return false;
    }

    /**
     * Save issue form data
     *
     * Saves the custom field values (isOpen and editedBy) when the issue form
     * is submitted. Normalizes the editedBy field to always be an array. If the
     * editor assignments have changed, syncs those changes to all submissions
     * assigned to this issue.
     *
     * @hook issueform::execute
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [$issueForm]
     *
     * @return bool Always returns false to continue hook processing
     */
    public function saveIssueFormData(string $hookName, array $params): bool
    {
        $issue = $params[0]->issue ?? null;
        if (!$issue) {
            return false;
        }

        $form = $params[0];

        $oldEditors = $issue->getData(Constants::ISSUE_EDITED_BY) ?: [];

        $this->saveIssueOpenStatus($issue, $form);
        $isCreatingIssue = !$this->getRequest()->getUserVar("issueId");
        if ($issue->getData(Constants::ISSUE_IS_OPEN) || $isCreatingIssue) {
            $this->saveIssueEditors($issue, $form);
        }

        $newEditors = $issue->getData(Constants::ISSUE_EDITED_BY) ?: [];

        if ($oldEditors !== $newEditors) {
            $this->syncSubmissionEditors($issue, $newEditors);
        }

        return false;
    }

    /**
     * Save issue open status
     *
     * Persists the isOpen flag from the form to the issue data.
     *
     * @param object $issue The issue object
     * @param object $form The form object containing submitted data
     *
     * @return void
     */
    private function saveIssueOpenStatus(object $issue, object $form): void
    {
        $issue->setData(Constants::ISSUE_IS_OPEN, (bool) $form->getData(Constants::ISSUE_IS_OPEN));
    }

    /**
     * Save issue editors
     *
     * Persists the editedBy array from the form to the issue data after normalization.
     *
     * @param Issue $issue The issue object
     * @param IssueForm $form The form object containing submitted data
     *
     * @return void
     */
    private function saveIssueEditors(Issue $issue, IssueForm $form): void
    {
        $editedBy = $form->getData(Constants::ISSUE_EDITED_BY);
        $issue->setData(Constants::ISSUE_EDITED_BY, $this->normalizeEditorIds($editedBy));
    }

    /**
     * Normalize editor IDs to array
     *
     * Converts the editedBy value from the form into a validated array of editor IDs.
     * Handles both array and single value inputs, filtering out invalid IDs.
     *
     * @param mixed $editedBy The editor ID(s) from form (array, int, or other)
     *
     * @return array Normalized array of validated positive integer editor IDs
     */
    private function normalizeEditorIds(mixed $editedBy): array
    {
        if (is_array($editedBy)) {
            return array_filter($editedBy, fn($id) => is_numeric($id) && $id > 0);
        }

        if (is_numeric($editedBy) && $editedBy > 0) {
            return [(int) $editedBy];
        }

        return [];
    }

    /**
     * Sync editors for all submissions assigned to this issue
     *
     * Iterates through all submissions assigned to the issue and updates their
     * editor assignments to match the issue's editor list.
     *
     * @param Issue $issue The issue whose submissions should be updated
     * @param array $editorIds Array of editor user IDs to assign
     *
     * @return void
     */
    private function syncSubmissionEditors(Issue $issue, array $editorIds): void
    {
        $collector = Repo::submission()->getCollector();
        $submissions = $collector->filterByContextIds([$issue->getData("journalId")])->getMany();

        $editorService = new EditorAssignmentService($this);
        $request = $this->getRequest();

        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication || $publication->getData("issueId") != $issue->getId()) {
                continue;
            }

            $editorService->syncEditorsToSubmission($submission, $editorIds, $request);
        }
    }
}
