<?php

/**
 * @file plugins/generic/issuePreselection/classes/IssueManagement.php
 *
 * Handles issue-related functionality for the Issue Preselection plugin
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\issuePreselection\IssuePreselectionPlugin;
use PKP\context\Context;

class IssueManagement
{
    /** @var IssuePreselectionPlugin */
    public IssuePreselectionPlugin $plugin;

    /** @param IssuePreselectionPlugin $plugin */
    public function __construct(IssuePreselectionPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Add custom fields to issue schema
     * 
     * @hook Schema::get::issue
     */
    public function addToIssueSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
                
        $schema->properties->{Constants::ISSUE_IS_OPEN} = (object) [
            'type' => 'boolean',
            'apiSummary' => false,
            'validation' => ['nullable']
        ];
        
        $schema->properties->{Constants::ISSUE_EDITED_BY} = (object) [
            'type' => 'array',
            'items' => (object) ['type' => 'integer'],
            'apiSummary' => false,
            'validation' => ['nullable']
        ];
        
        return false;
    }

    /**
     * Add fields to issue form template
     * 
     * @hook Templates::Editor::Issues::IssueData::AdditionalMetadata
     */
    public function addIssueFormFields(string $hookName, array $params): bool
    {
        $smarty = $params[1];
        $output = &$params[2];
                
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        $issueId = $request->getUserVar('issueId');
        $issue = null;
        $isOpen = false;
        $assignedEditors = [];
        
        if ($issueId) {
            $issue = Repo::issue()->get($issueId);
            
            if ($issue) {
                $isOpen = (bool) $issue->getData(Constants::ISSUE_IS_OPEN);
                $assignedEditors = $issue->getData(Constants::ISSUE_EDITED_BY) ?: [];
            }
        }
        
        $editorOptions = $this->getEditorOptions($context);
        
        $smarty->assign([
            'issuePreselectionIsOpen' => $isOpen,
            'issuePreselectionEditors' => $assignedEditors,
            'issuePreselectionEditorOptions' => $editorOptions,
        ]);
        
        $output .= $smarty->fetch($this->plugin->getTemplateResource('issueFormFields.tpl'));
        
        return false;
    }

    /**
     * Ensure custom data is preserved when issue is edited
     * 
     * @hook Issue::edit
     */
    public function beforeIssueEdit(string $hookName, array $params): bool
    {
        $newIssue = &$params[0];
        $issue = $params[1];
        
        if ($newIssue->getData(Constants::ISSUE_IS_OPEN) === null && $issue->getData(Constants::ISSUE_IS_OPEN) !== null) {
            $newIssue->setData(Constants::ISSUE_IS_OPEN, $issue->getData(Constants::ISSUE_IS_OPEN));
        }
        
        if ($newIssue->getData(Constants::ISSUE_EDITED_BY) === null && $issue->getData(Constants::ISSUE_EDITED_BY) !== null) {
            $newIssue->setData(Constants::ISSUE_EDITED_BY, $issue->getData(Constants::ISSUE_EDITED_BY));
        }
        
        return false;
    }

    /**
     * Read issue form data - register our custom fields
     * 
     * @hook issueform::readuservars
     */
    public function readIssueFormData(string $hookName, array $params): bool
    {
        $userVars = &$params[1];
        $userVars[] = Constants::ISSUE_IS_OPEN;
        $userVars[] = Constants::ISSUE_EDITED_BY;
        
        return false;
    }

    /**
     * Save issue form data
     * 
     * @hook issueform::execute
     */
    public function saveIssueFormData(string $hookName, array $params): bool
    {        
        $form = $params[0];
        
        if (!isset($form->issue) || !$form->issue) {
            return false;
        }
        
        $issue = $form->issue;
        
        $isOpen = (bool) $form->getData(Constants::ISSUE_IS_OPEN);
        $editedBy = $form->getData(Constants::ISSUE_EDITED_BY);
        
        if (!is_array($editedBy)) {
            $editedBy = $editedBy ? [$editedBy] : [];
        }
        
        $issue->setData(Constants::ISSUE_IS_OPEN, $isOpen);
        $issue->setData(Constants::ISSUE_EDITED_BY, $editedBy);
        
        return false;
    }

    /**
     * Get editor options for the select field
     */
    public function getEditorOptions(Context $context): array
    {
        $editorIds = [];
        $contextId = $context->getId();
        
        $managers = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([ROLE_ID_MANAGER])
            ->getMany();
        
        foreach ($managers as $user) {
            $editorIds[$user->getId()] = $user->getFullName();
        }
        
        $subEditors = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([ROLE_ID_SUB_EDITOR])
            ->getMany();
        
        foreach ($subEditors as $user) {
            if (!isset($editorIds[$user->getId()])) {
                $editorIds[$user->getId()] = $user->getFullName();
            }
        }
        
        return $editorIds;
    }

    /**
     * Get open future issues
     */
    public function getOpenFutureIssues(int $contextId): array
    {
        $collector = Repo::issue()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublished(false);
        
        $issues = $collector->getMany();
        
        $openIssues = [];
        foreach ($issues as $issue) {
            if ($issue->getData(Constants::ISSUE_IS_OPEN) === true) {
                $openIssues[] = $issue;
            }
        }
        
        return $openIssues;
    }
}
