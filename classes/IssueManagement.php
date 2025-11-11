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
                
        $schema->properties->isOpen = (object) [
            'type' => 'boolean',
            'apiSummary' => false,
            'validation' => ['nullable']
        ];
        
        $schema->properties->editedBy = (object) [
            'type' => 'array',
            'items' => (object) ['type' => 'integer'],
            'apiSummary' => false,
            'validation' => ['nullable']
        ];
        
        error_log("[IssuePreselection] Added isOpen and editedBy to issue schema");
        
        return false;
    }

    /**
     * Add fields to issue form template
     * 
     * @hook Templates::Editor::Issues::IssueData::AdditionalMetadata
     */
    public function addIssueFormFields(string $hookName, array $params): bool
    {
        $hookParams = &$params[0];
        $smarty = $params[1];
        $output = &$params[2];
                
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        $issueId = $request->getUserVar('issueId');
        $issue = null;
        $isOpen = false;
        $assignedEditors = [];
        
        if ($issueId) {
            error_log("[IssuePreselection] Loading issue data for issue ID: " . $issueId);
            $issue = Repo::issue()->get($issueId);
            
            if ($issue) {
                $isOpen = $issue->getData('isOpen') ? true : false;
                $assignedEditors = $issue->getData('editedBy') ?: [];
                error_log("[IssuePreselection] Loaded issue - isOpen: " . ($isOpen ? 'true' : 'false') . ", editors: " . json_encode($assignedEditors));
            } else {
                error_log("[IssuePreselection] Issue not found for ID: " . $issueId);
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
        $editParams = $params[2];
        
        error_log("[IssuePreselection] beforeIssueEdit called for issue " . $issue->getId());
        error_log("[IssuePreselection] Current issue data - isOpen: " . json_encode($issue->getData('isOpen')) . ", editedBy: " . json_encode($issue->getData('editedBy')));
        error_log("[IssuePreselection] New issue data - isOpen: " . json_encode($newIssue->getData('isOpen')) . ", editedBy: " . json_encode($newIssue->getData('editedBy')));
        
        if ($newIssue->getData('isOpen') === null && $issue->getData('isOpen') !== null) {
            $newIssue->setData('isOpen', $issue->getData('isOpen'));
            error_log("[IssuePreselection] Preserved isOpen value");
        }
        
        if ($newIssue->getData('editedBy') === null && $issue->getData('editedBy') !== null) {
            $newIssue->setData('editedBy', $issue->getData('editedBy'));
            error_log("[IssuePreselection] Preserved editedBy value");
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
        $form = $params[0];
        $userVars = &$params[1];
        $userVars[] = 'isOpen';
        $userVars[] = 'editedBy';
        
        error_log("[IssuePreselection] Registered fields: isOpen, editedBy");
        
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
            error_log("[IssuePreselection] No issue object found in form");
            return false;
        }
        
        $issue = $form->issue;
        
        $isOpen = $form->getData('isOpen') ? true : false;
        $editedBy = $form->getData('editedBy');
        
        error_log("[IssuePreselection] Form data - isOpen: " . ($isOpen ? 'true' : 'false') . ", editedBy: " . json_encode($editedBy));
        error_log("[IssuePreselection] Issue before setting data - isOpen: " . json_encode($issue->getData('isOpen')) . ", editedBy: " . json_encode($issue->getData('editedBy')));
        
        if (!is_array($editedBy)) {
            $editedBy = $editedBy ? [$editedBy] : [];
        }
        
        $issue->setData('isOpen', $isOpen);
        $issue->setData('editedBy', $editedBy);
        
        error_log("[IssuePreselection] Issue after setting data - isOpen: " . json_encode($issue->getData('isOpen')) . ", editedBy: " . json_encode($issue->getData('editedBy')));
        error_log("[IssuePreselection] Set issue data - issueId: " . ($issue->getId() ?: 'new') . ", isOpen: " . ($isOpen ? 'true' : 'false') . ", editedBy count: " . count($editedBy));
        
        return false;
    }

    /**
     * Get editor options for the select field
     */
    public function getEditorOptions($context): array
    {        
        $editorIds = [];
        
        $userCollector = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([ROLE_ID_MANAGER]);
        
        foreach ($userCollector->getMany() as $user) {
            $editorIds[$user->getId()] = $user->getFullName();
        }
        
        $userCollector = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([ROLE_ID_SUB_EDITOR]);
        
        foreach ($userCollector->getMany() as $user) {
            $editorIds[$user->getId()] = $user->getFullName();
        }
        
        error_log("[IssuePreselection] Found " . count($editorIds) . " editors/managers");
        
        return $editorIds;
    }

    /**
     * Get open future issues
     */
    public function getOpenFutureIssues(int $contextId): array
    {
        error_log("[IssuePreselection] getOpenFutureIssues called for context: " . $contextId);
        
        $collector = Repo::issue()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublished(false);
        
        $issues = $collector->getMany();
        
        $openIssues = [];
        foreach ($issues as $issue) {
            if ($issue->getData('isOpen') === true) {
                $openIssues[] = $issue;
                error_log("[IssuePreselection] Issue " . $issue->getId() . " is open");
            }
        }
        
        error_log("[IssuePreselection] Returning " . count($openIssues) . " open issues");
        
        return $openIssues;
    }
}
