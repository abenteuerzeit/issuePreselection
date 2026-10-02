<?php

/**
 * @file plugins/generic/issuePreselection/IssuePreselectionPlugin.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssuePreselectionPlugin
 * @brief Allows authors to select issue assignment during submission
 */

namespace APP\plugins\generic\issuePreselection;

use APP\core\Application;
use APP\plugins\generic\issuePreselection\classes\IssueManagement;
use APP\plugins\generic\issuePreselection\classes\SubmissionManagement;
use Exception;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class IssuePreselectionPlugin extends GenericPlugin
{
    /**
     * @copydoc GenericPlugin::register()
     *
     * @param string $category The category of the plugin
     * @param string $path The path to the plugin
     * @param int|null $mainContextId The main context ID
     *
     * @return bool True if registration was successful
     * @throws Exception
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            $this->_init();
        }

        return $success;
    }

    /**
     * Register hooks used in normal plugin setup and in CLI tools
     *
     * Initializes the management classes and registers all necessary hooks
     * for both issue and submission functionality.
     *
     * @return void
     * @throws Exception
     */
    private function _init(): void
    {
        $issueManagement = new IssueManagement($this);
        $submissionManagement = new SubmissionManagement($this);

        $this->registerIssueHooks($issueManagement);
        $this->registerSubmissionHooks($submissionManagement);
    }

    /**
     * Register hooks for issue management functionality
     *
     * Registers all hooks related to issue schema, forms, and data handling.
     *
     * @param IssueManagement $issueManagement The issue management instance
     *
     * @return void
     * @throws Exception
     */
    private function registerIssueHooks(IssueManagement $issueManagement): void
    {
        Hook::add("Schema::get::issue", [$issueManagement, "addToIssueSchema"]);
        Hook::add("Templates::Editor::Issues::IssueData::AdditionalMetadata", [$issueManagement, "addIssueFormFields"]);
        Hook::add("issueform::readuservars", [$issueManagement, "readIssueFormData"]);
        Hook::add("issueform::execute", [$issueManagement, "saveIssueFormData"]);
        Hook::add("Issue::edit", [$issueManagement, "beforeIssueEdit"]);
    }

    /**
     * Register hooks for submission management functionality
     *
     * Registers all hooks related to submission schema, forms, validation,
     * and editor assignment.
     *
     * @param SubmissionManagement $submissionManagement The submission management instance
     *
     * @return void
     * @throws Exception
     */
    private function registerSubmissionHooks(SubmissionManagement $submissionManagement): void
    {
        Hook::add("Schema::get::submission", [$submissionManagement, "addToSubmissionSchema"]);
        Hook::add("Form::config::after", [$submissionManagement, "addToSubmissionForm"]);
        Hook::add("Template::SubmissionWizard::Section::Review::Editors", [
            $submissionManagement,
            "addIssueReviewSection",
        ]);
        Hook::add("Submission::validateSubmit", [$submissionManagement, "handleSubmissionValidate"]);
    }

    /**
     * Get the display name of this plugin
     *
     * @copydoc Plugin::getDisplayName()
     *
     * @return object|array|string|null Localized plugin display name
     */
    public function getDisplayName(): object|array|string|null
    {
        return __("plugins.generic.issuePreselection.displayName");
    }

    /**
     * Get the description of this plugin
     *
     * @copydoc Plugin::getDescription()
     *
     * @return object|array|string|null Localized plugin description
     */
    public function getDescription(): object|array|string|null
    {
        return __("plugins.generic.issuePreselection.description");
    }
}

// For backwards compatibility -- expect this to be removed approx. OJS/OMP/OPS 3.6
if (!PKP_STRICT_MODE) {
    class_alias("\APP\plugins\generic\issuePreselection\IssuePreselectionPlugin", "\IssuePreselectionPlugin");
}
