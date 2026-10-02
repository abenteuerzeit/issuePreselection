<?php

/**
 * @file plugins/generic/issuePreselection/classes/BaseManagement.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseManagement
 * @brief Base class for management classes with common utilities
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\issuePreselection\IssuePreselectionPlugin;
use PKP\context\Context;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

abstract class BaseManagement
{
    /**
     * Reference to the main plugin instance
     *
     * @var IssuePreselectionPlugin
     */
    public IssuePreselectionPlugin $plugin;

    /**
     * Constructor
     *
     * @param IssuePreselectionPlugin $plugin Reference to the main plugin instance
     */
    public function __construct(IssuePreselectionPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Get editor user group for a user
     *
     * Finds the appropriate user group (sub-editor or manager) for a given
     * user in the specified context. Checks sub-editor role first, then manager,
     * to ensure the most specific role is used.
     *
     * @param int $contextId The journal/press context ID
     * @param int $userId The user ID to find the group for
     *
     * @return object|null The UserGroup object or null if user has no editor role
     */
    public function getEditorUserGroup(int $contextId, int $userId): ?object
    {
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER] as $roleId) {
            $userGroup = UserGroup::query()
                ->withContextIds([$contextId])
                ->withUserIds([$userId])
                ->withRoleIds([$roleId])
                ->first();

            if ($userGroup) {
                return $userGroup;
            }
        }

        return null;
    }

    /**
     * Get current context from request
     *
     * Retrieves the current journal/press context from the request.
     *
     * @return Context|null The current context or null if not available
     */
    protected function getContext(): ?Context
    {
        return $this->getRequest()->getContext();
    }

    /**
     * Get current request
     *
     * Retrieves the current HTTP request object from the application.
     *
     * @return Request The current request object
     */
    protected function getRequest(): Request
    {
        return Application::get()->getRequest();
    }

    /**
     * Get detailed editor information (managers and sub-editors) for a context
     *
     * Retrieves all managers and sub-editors for the given context
     * with their full details (ID, full name, initials, user group/role name).
     *
     * @param Context $context The journal/press context
     *
     * @return array Array of editor detail arrays
     */
    public function getEditorList(Context $context): array
    {
        $editors = [];

        foreach ([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR] as $roleId) {
            $users = Repo::user()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByRoleIds([$roleId])
                ->getMany();

            foreach ($users as $user) {
                $userGroup = $this->getEditorUserGroup($context->getId(), $user->getId());
                $editors[$user->getId()] ??= [
                    "id" => $user->getId(),
                    "fullName" => $user->getFullName(),
                    "initials" =>
                        mb_strtoupper(
                            mb_substr($user->getLocalizedGivenName() ?? "", 0, 1) .
                                mb_substr($user->getLocalizedFamilyName() ?? "", 0, 1),
                        ) ?:
                        "U",
                    "roleName" => $userGroup?->getLocalizedData("name") ?? "",
                ];
            }
        }

        return array_values($editors);
    }

    /**
     * Render a plugin template
     *
     * Helper method to fetch and render a plugin template using Smarty.
     *
     * @param object $smarty The Smarty template engine instance
     * @param string $templateName The template filename (relative to plugin templates directory)
     *
     * @return string The rendered template HTML output
     */
    protected function renderTemplate(object $smarty, string $templateName): string
    {
        return $smarty->fetch($this->plugin->getTemplateResource($templateName));
    }
}
