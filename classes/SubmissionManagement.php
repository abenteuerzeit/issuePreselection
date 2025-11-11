<?php

/**
 * @file plugins/generic/issuePreselection/classes/SubmissionManagement.php
 *
 * Handles submission-related functionality for the Issue Preselection plugin
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\issuePreselection\IssuePreselectionPlugin;

class SubmissionManagement
{
    /** @var IssuePreselectionPlugin */
    public IssuePreselectionPlugin $plugin;

    /** @param IssuePreselectionPlugin $plugin */
    public function __construct(IssuePreselectionPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Add preselectedIssueId to submission list props
     * 
     * @hook Submission::getSubmissionsListProps
     */
    public function addSubmissionListProps(string $hookName, array $params): bool
    {
        $props = &$params[0];
        $props[] = 'preselectedIssueId';
        error_log("[IssuePreselection] Added preselectedIssueId to submission list props");
        return false;
    }

    /**
     * Add issue preselection field to submission schema
     * 
     * @hook Schema::get::submission
     */
    public function addToSubmissionSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
                
        $schema->properties->preselectedIssueId = (object) [
            'type' => 'integer',
            'apiSummary' => true,
            'writeDisabledInApi' => false,
            'validation' => ['nullable']
        ];
        
        error_log("[IssuePreselection] Added preselectedIssueId to submission schema");
        
        return false;
    }

    /**
     * Add issue selector field to submission form
     * 
     * @hook Form::config::after
     */
    public function addToSubmissionForm(string $hookName, array $params): bool
    {
        $config = &$params[0];
        $form = $params[1];
        
        $formClass = get_class($form);
        if ($formClass !== 'PKP\components\forms\submission\CommentsForTheEditors') {
            return false;
        }
        
        error_log("[IssuePreselection] addToSubmissionForm called for CommentsForTheEditors form");
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        $submission = null;
        if (isset($config['action']) && preg_match('/submissions\/(\d+)/', $config['action'], $matches)) {
            $submissionId = (int) $matches[1];
            $submission = Repo::submission()->get($submissionId);
            error_log("[IssuePreselection] Found submission ID from action URL: " . $submissionId);
        }
        
        if (!$submission) {
            error_log("[IssuePreselection] Could not find submission, skipping");
            return false;
        }
        
        $currentValue = $submission->getData('preselectedIssueId');
        error_log("[IssuePreselection] Current preselectedIssueId: " . ($currentValue ?: 'none'));
        
        // Get issue management instance to access getOpenFutureIssues
        $issueManagement = new \APP\plugins\generic\issuePreselection\classes\IssueManagement($this->plugin);
        $issues = $issueManagement->getOpenFutureIssues($context->getId());
        
        if (empty($issues)) {
            error_log("[IssuePreselection] No open issues, skipping");
            return false;
        }
        
        $issueOptions = [[
            'value' => '',
            'label' => __('plugins.generic.issuePreselection.selectOption')
        ]];
        
        foreach ($issues as $issue) {
            $issueOptions[] = [
                'value' => (int) $issue->getId(),
                'label' => $issue->getIssueIdentification()
            ];
        }
        
        $fieldConfig = [
            'name' => 'preselectedIssueId',
            'component' => 'field-select',
            'label' => __('plugins.generic.issuePreselection.issueLabel'),
            'description' => __('plugins.generic.issuePreselection.description.field'),
            'options' => $issueOptions,
            'value' => $currentValue ? (int) $currentValue : '',
            'isRequired' => true,
            'groupId' => 'default'
        ];
        
        $config['fields'][] = $fieldConfig;
        
        if (!isset($config['values'])) {
            $config['values'] = [];
        }
        $config['values']['preselectedIssueId'] = $currentValue ? (int) $currentValue : '';
        
        error_log("[IssuePreselection] Added preselectedIssueId field to form config with value: " . ($currentValue ?: 'empty'));
        error_log("[IssuePreselection] Set form values[preselectedIssueId]: " . ($currentValue ?: 'empty'));
        
        return false;
    }

    /**
     * Add issue information to the review section
     * 
     * @hook Template::SubmissionWizard::Section::Review::Editors
     */
    public function addIssueReviewSection(string $hookName, array $params): bool
    {
        $smarty = $params[1];
        $output = &$params[2];
                
        $submission = $smarty->getTemplateVars('submission');
        
        if (!$submission) {
            error_log("[IssuePreselection] No submission found");
            return false;
        }
        
        $localeKey = $smarty->getTemplateVars('localeKey');
        $submissionLocale = $submission->getData('locale');
        
        if ($localeKey !== $submissionLocale) {
            error_log("[IssuePreselection] Skipping non-primary locale: {$localeKey}");
            return false;
        }
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        // Get issue management instance to access getOpenFutureIssues
        $issueManagement = new \APP\plugins\generic\issuePreselection\classes\IssueManagement($this->plugin);
        $issues = $issueManagement->getOpenFutureIssues($context->getId());
        
        if (empty($issues)) {
            error_log("[IssuePreselection] No open issues available");
            return false;
        }
        
        $issueMap = [];
        foreach ($issues as $issue) {
            $issueMap[$issue->getId()] = $issue->getIssueIdentification();
        }
        
        $smarty->assign('issuePreselectionMap', $issueMap);
        
        // Output Vue-compatible template
        $output .= '<div class="submissionWizard__reviewPanel__item" v-if="submission.preselectedIssueId">';
        $output .= '<h4 class="submissionWizard__reviewPanel__item__header">';
        $output .= htmlspecialchars(__('plugins.generic.issuePreselection.issueLabel'));
        $output .= '</h4>';
        $output .= '<div class="submissionWizard__reviewPanel__item__value">';
        
        $output .= '{{ ';
        $first = true;
        foreach ($issueMap as $id => $title) {
            if (!$first) {
                $output .= ' : ';
            }
            $output .= 'submission.preselectedIssueId === ' . $id . ' ? ' . json_encode($title);
            $first = false;
        }
        $output .= ' : "" }}';
        
        $output .= '</div>';
        $output .= '</div>';
        
        error_log("[IssuePreselection] Added Vue-compatible review section with " . count($issueMap) . " issues");
        
        return false;
    }

    /**
     * Handle submission validation - assign issue and editors when submitted
     * 
     * @hook Submission::validateSubmit
     */
    public function handleSubmissionValidate(string $hookName, array $params): bool
    {
        $errors = &$params[0];
        $submission = $params[1];
        $context = $params[2];
        
        error_log("[IssuePreselection] handleSubmissionValidate called for submission " . $submission->getId());
        
        $issueId = $submission->getData('preselectedIssueId');
        
        error_log("[IssuePreselection] Preselected issue ID: " . ($issueId ?: 'none'));
        
        if (!$issueId) {
            error_log("[IssuePreselection] No issue preselected, skipping");
            return false;
        }
        
        $issue = Repo::issue()->get($issueId);
        
        if (!$issue || !$issue->getData('isOpen')) {
            error_log("[IssuePreselection] Issue not found or not open, skipping");
            return false;
        }
        
        error_log("[IssuePreselection] Processing issue assignment for submission " . $submission->getId() . " to issue " . $issueId);
        
        // Schedule the publication to the issue
        $publication = $submission->getCurrentPublication();
        if ($publication) {
            error_log("[IssuePreselection] Current publication ID: " . $publication->getId() . ", current issueId: " . ($publication->getData('issueId') ?: 'none'));
            
            try {
                $editedPublication = Repo::publication()->edit($publication, ['issueId' => $issueId]);
                error_log("[IssuePreselection] Successfully scheduled publication " . $editedPublication->getId() . " to issue " . $issueId);
                error_log("[IssuePreselection] New issueId value: " . ($editedPublication->getData('issueId') ?: 'none'));
                
                // Assign editors from the issue to the submission
                $editorIds = $issue->getData('editedBy');
                error_log("[IssuePreselection] Issue has " . (is_array($editorIds) ? count($editorIds) : 0) . " assigned editors");
                
                if (!empty($editorIds) && is_array($editorIds)) {
                    $request = Application::get()->getRequest();
                    $this->assignEditorsToSubmission($submission, $editorIds, $request);
                }
            } catch (\Exception $e) {
                error_log("[IssuePreselection] ERROR scheduling publication: " . $e->getMessage());
            }
        } else {
            error_log("[IssuePreselection] No publication found for submission");
        }
        
        return false;
    }

    /**
     * Assign editors to submission as Guest Editors
     */
    private function assignEditorsToSubmission($submission, array $editorIds, $request): void
    {
        error_log("[IssuePreselection] assignEditorsToSubmission called for submission " . $submission->getId());
        
        $context = $request->getContext();
        
        foreach ($editorIds as $editorId) {
            error_log("[IssuePreselection] Processing editor ID: " . $editorId);
            
            $user = Repo::user()->get($editorId);
            if (!$user) {
                error_log("[IssuePreselection] User not found for ID: " . $editorId);
                continue;
            }
            
            // Get user's Section Editor (Guest Editor) group
            $userGroups = \PKP\userGroup\UserGroup::withContextIds([$context->getId()])
                ->withUserIds([$editorId])
                ->withRoleIds([ROLE_ID_SUB_EDITOR])
                ->get();
            
            // If no Section Editor role, try Manager role
            if ($userGroups->isEmpty()) {
                $userGroups = \PKP\userGroup\UserGroup::withContextIds([$context->getId()])
                    ->withUserIds([$editorId])
                    ->withRoleIds([ROLE_ID_MANAGER])
                    ->get();
            }
            
            if ($userGroups->isEmpty()) {
                error_log("[IssuePreselection] No editor/manager group found for user " . $editorId);
                continue;
            }
            
            $editorGroup = $userGroups->first();
            $roleType = $editorGroup->role_id == ROLE_ID_SUB_EDITOR ? 'Section Editor (Guest Editor)' : 'Manager';
            error_log("[IssuePreselection] Found {$roleType} group ID: " . $editorGroup->user_group_id);
            
            // Check if already assigned
            $existingAssignment = \PKP\stageAssignment\StageAssignment::withSubmissionIds([$submission->getId()])
                ->withUserId($editorId)
                ->withUserGroupId($editorGroup->user_group_id)
                ->first();
            
            if ($existingAssignment) {
                error_log("[IssuePreselection] Editor " . $editorId . " already assigned");
                continue;
            }
            
            // Create stage assignment
            $stageAssignment = new \PKP\stageAssignment\StageAssignment();
            $stageAssignment->submissionId = $submission->getId();
            $stageAssignment->userGroupId = $editorGroup->user_group_id;
            $stageAssignment->userId = $editorId;
            $stageAssignment->dateAssigned = \Core::getCurrentDate();
            $stageAssignment->recommendOnly = 0;
            $stageAssignment->canChangeMetadata = 1;
            $stageAssignment->save();
            
            error_log("[IssuePreselection] Assigned {$roleType} " . $editorId . " to submission " . $submission->getId());
        }
        
        error_log("[IssuePreselection] Finished assigning editors");
    }
}
