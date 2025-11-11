<?php

/**
 * @file plugins/generic/issuePreselection/IssuePreselectionPlugin.php
 *
 * Issue Preselection Plugin
 * Allows authors to select issue assignment during submission
 */

namespace APP\plugins\generic\issuePreselection;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\plugins\generic\issuePreselection\classes\IssueManagement;
use APP\plugins\generic\issuePreselection\classes\SubmissionManagement;

class IssuePreselectionPlugin extends GenericPlugin
{
    /** @var IssueManagement */
    private $issueManagement;

    /** @var SubmissionManagement */
    private $submissionManagement;

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
                
        if ($success && $this->getEnabled($mainContextId)) {
            $this->issueManagement = new IssueManagement($this);
            $this->submissionManagement = new SubmissionManagement($this);

            Hook::add('Schema::get::issue', [$this->issueManagement, 'addToIssueSchema']);
            Hook::add('Templates::Editor::Issues::IssueData::AdditionalMetadata', [$this->issueManagement, 'addIssueFormFields']);
            Hook::add('issueform::readuservars', [$this->issueManagement, 'readIssueFormData']);
            Hook::add('issueform::execute', [$this->issueManagement, 'saveIssueFormData']);
            Hook::add('Issue::edit', [$this->issueManagement, 'beforeIssueEdit']);
            
            Hook::add('Schema::get::submission', [$this->submissionManagement, 'addToSubmissionSchema']);
            Hook::add('Form::config::after', [$this->submissionManagement, 'addToSubmissionForm']);
            Hook::add('Submission::getSubmissionsListProps', [$this->submissionManagement, 'addSubmissionListProps']);
            Hook::add('Template::SubmissionWizard::Section::Review::Editors', [$this->submissionManagement, 'addIssueReviewSection']);
            Hook::add('Submission::validateSubmit', [$this->submissionManagement, 'handleSubmissionValidate']);
        }
        
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.issuePreselection.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.issuePreselection.description');
    }

}
