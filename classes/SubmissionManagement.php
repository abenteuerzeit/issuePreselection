<?php

/**
 * @file plugins/generic/issuePreselection/classes/SubmissionManagement.php
 *
 * Handles submission-related functionality for the Issue Preselection plugin
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\issuePreselection\IssuePreselectionPlugin;
use APP\submission\Submission;

class SubmissionManagement
{
    /** @var IssuePreselectionPlugin */
    public IssuePreselectionPlugin $plugin;

    /** @var IssueManagement */
    private IssueManagement $issueManagement;

    /** @param IssuePreselectionPlugin $plugin */
    public function __construct(IssuePreselectionPlugin &$plugin)
    {
        $this->plugin = &$plugin;
        $this->issueManagement = new IssueManagement($plugin);
    }

    /**
     * Add preselectedIssueId to submission list props
     * 
     * @hook Submission::getSubmissionsListProps
     */
    public function addSubmissionListProps(string $hookName, array $params): bool
    {
        $props = &$params[0];
        $props[] = Constants::SUBMISSION_PRESELECTED_ISSUE_ID;
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
                
        $schema->properties->{Constants::SUBMISSION_PRESELECTED_ISSUE_ID} = (object) [
            'type' => 'integer',
            'apiSummary' => true,
            'writeDisabledInApi' => false,
            'validation' => ['nullable']
        ];
        
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
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        if (!isset($config['action']) || !preg_match('/submissions\/(\d+)/', $config['action'], $matches)) {
            return false;
        }
        
        $submissionId = (int) $matches[1];
        $submission = Repo::submission()->get($submissionId);
        
        if (!$submission) {
            return false;
        }
        
        $currentValue = $submission->getData(Constants::SUBMISSION_PRESELECTED_ISSUE_ID);
        
        $issues = $this->issueManagement->getOpenFutureIssues($context->getId());
        
        if (empty($issues)) {
            return false;
        }
        
        $issueOptions = [[
            'value' => 0,
            'label' => __('plugins.generic.issuePreselection.selectOption')
        ]];
        
        foreach ($issues as $issue) {
            $issueOptions[] = [
                'value' => (int) $issue->getId(),
                'label' => $issue->getIssueIdentification()
            ];
        }
        
        $fieldConfig = [
            'name' => Constants::SUBMISSION_PRESELECTED_ISSUE_ID,
            'component' => 'field-select',
            'label' => __('plugins.generic.issuePreselection.issueLabel'),
            'description' => __('plugins.generic.issuePreselection.description.field'),
            'options' => $issueOptions,
            'value' => $currentValue ? (int) $currentValue : 0,
            'isRequired' => true,
            'groupId' => 'default'
        ];
        
        $config['fields'][] = $fieldConfig;
        
        if (!isset($config['values'])) {
            $config['values'] = [];
        }
        $config['values'][Constants::SUBMISSION_PRESELECTED_ISSUE_ID] = $currentValue ? (int) $currentValue : 0;
        
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
            return false;
        }
        
        $localeKey = $smarty->getTemplateVars('localeKey');
        $submissionLocale = $submission->getData('locale');
        
        if ($localeKey !== $submissionLocale) {
            return false;
        }
        
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        $issues = $this->issueManagement->getOpenFutureIssues($context->getId());
        
        if (empty($issues)) {
            return false;
        }
        
        $issueMap = [];
        foreach ($issues as $issue) {
            $issueMap[$issue->getId()] = $issue->getIssueIdentification();
        }
        
        $output .= '<div class="submissionWizard__reviewPanel__item" v-if="submission.preselectedIssueId && submission.preselectedIssueId !== 0">';
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
        
        $output .= '<div class="submissionWizard__reviewPanel__item" v-if="!submission.preselectedIssueId || submission.preselectedIssueId === 0">';
        $output .= '<h4 class="submissionWizard__reviewPanel__item__header">';
        $output .= htmlspecialchars(__('plugins.generic.issuePreselection.issueLabel'));
        $output .= '</h4>';
        $output .= '<div class="submissionWizard__reviewPanel__item__value" style="color: #d00;">';
        $output .= '<span class="fa fa-exclamation-triangle" aria-hidden="true"></span> ';
        $output .= htmlspecialchars(__('plugins.generic.issuePreselection.error.issueRequired'));
        $output .= '</div>';
        $output .= '</div>';
        
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
        
        $issueId = $submission->getData(Constants::SUBMISSION_PRESELECTED_ISSUE_ID);
        
        $openIssues = $this->issueManagement->getOpenFutureIssues($context->getId());
        
        if (!empty($openIssues) && (!$issueId || $issueId === 0)) {
            $errors[Constants::SUBMISSION_PRESELECTED_ISSUE_ID] = [__('plugins.generic.issuePreselection.error.issueRequired')];
            return false;
        }
        
        if (!$issueId || $issueId === 0) {
            return false;
        }
        
        $issue = Repo::issue()->get($issueId);
        
        if (!$issue || !$issue->getData(Constants::ISSUE_IS_OPEN)) {
            return false;
        }
        
        $publication = $submission->getCurrentPublication();
        if (!$publication) {
            return false;
        }
        
        try {
            Repo::publication()->edit($publication, ['issueId' => $issueId]);
            
            $editorIds = $issue->getData(Constants::ISSUE_EDITED_BY);
            
            if (!empty($editorIds) && is_array($editorIds)) {
                $request = Application::get()->getRequest();
                $this->assignEditorsToSubmission($submission, $editorIds, $request);
            }
        } catch (\Exception $e) {
            error_log("[IssuePreselection] ERROR scheduling publication: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Assign editors to submission as Guest Editors
     */
    private function assignEditorsToSubmission(Submission $submission, array $editorIds, Request $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            return;
        }
        
        $contextId = $context->getId();
        $submissionId = $submission->getId();
        
        foreach ($editorIds as $editorId) {
            if (!Repo::user()->get($editorId)) {
                continue;
            }
            
            $editorGroup = $this->getEditorUserGroup($contextId, $editorId);
            if (!$editorGroup) {
                continue;
            }
            
            if ($this->isAlreadyAssigned($submissionId, $editorId, $editorGroup->user_group_id)) {
                continue;
            }
            
            $this->createStageAssignment($submissionId, $editorId, $editorGroup->user_group_id);
        }
    }

    /**
     * Get editor user group for a user
     */
    private function getEditorUserGroup(int $contextId, int $userId): ?object
    {
        $userGroups = \PKP\userGroup\UserGroup::withContextIds([$contextId])
            ->withUserIds([$userId])
            ->withRoleIds([ROLE_ID_SUB_EDITOR])
            ->get();
        
        if ($userGroups->isEmpty()) {
            $userGroups = \PKP\userGroup\UserGroup::withContextIds([$contextId])
                ->withUserIds([$userId])
                ->withRoleIds([ROLE_ID_MANAGER])
                ->get();
        }
        
        return $userGroups->isEmpty() ? null : $userGroups->first();
    }

    /**
     * Check if editor is already assigned to submission
     */
    private function isAlreadyAssigned(int $submissionId, int $userId, int $userGroupId): bool
    {
        return \PKP\stageAssignment\StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->first() !== null;
    }

    /**
     * Create stage assignment for editor
     */
    private function createStageAssignment(int $submissionId, int $userId, int $userGroupId): void
    {
        $stageAssignment = new \PKP\stageAssignment\StageAssignment();
        $stageAssignment->submissionId = $submissionId;
        $stageAssignment->userGroupId = $userGroupId;
        $stageAssignment->userId = $userId;
        $stageAssignment->dateAssigned = \Core::getCurrentDate();
        $stageAssignment->recommendOnly = 0;
        $stageAssignment->canChangeMetadata = 1;
        $stageAssignment->save();
    }
}
