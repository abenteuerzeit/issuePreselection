<?php

/**
 * @file plugins/generic/issuePreselection/classes/IssueGridColumnsFeature.php
 *
 * Copyright (c) 2017-2023 Simon Fraser University
 * Copyright (c) 2017-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IssueGridColumnsFeature
 * @brief Adds "Assigned Editors" and "Status" button columns to the future issues grid
 */

namespace APP\plugins\generic\issuePreselection\classes;

use APP\facades\Repo;
use PKP\controllers\grid\feature\GridFeature;
use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class IssueGridColumnsFeature extends GridFeature
{
    public function __construct()
    {
        parent::__construct("issuePreselectionColumns");
    }

    /**
     * Register the feature on the future issues grid
     *
     * Back issues are published, so "open for submissions" does not apply to them.
     *
     * @hook futureissuegridhandler::initfeatures
     *
     * @param string $hookName The name of the hook being called
     * @param array $params Hook parameters [$grid, $request, $args, &$features]
     *
     * @return bool Always returns false to continue hook processing
     */
    public static function register(string $hookName, array $params): bool
    {
        $params[3][] = new self();
        return false;
    }

    /**
     * Append the plugin columns
     *
     * @param array $args ['grid' => GridHandler, 'requestArgs' => array]
     */
    public function getRequestArgs($args)
    {
        $cellProvider = new class extends GridCellProvider {
            public function getTemplateVarsFromRowColumn($row, $column)
            {
                if ($column->getId() === Constants::ISSUE_EDITED_BY) {
                    $issue = $row->getData();
                    if (!(bool) $issue->getData(Constants::ISSUE_IS_OPEN)) {
                        $names = array_filter(
                            array_map(
                                fn($id) => Repo::user()->get((int) $id)?->getFullName(),
                                $issue->getData(Constants::ISSUE_EDITED_BY) ?: [],
                            ),
                        );
                        $text = $names
                            ? implode(", ", $names)
                            : __("plugins.generic.issuePreselection.grid.assignEditors");
                        return ["label" => htmlspecialchars($text, ENT_QUOTES)];
                    }
                }

                return ["label" => ""];
            }

            public function getCellActions(
                $request,
                $row,
                $column,
                $position = GridHandler::GRID_ACTION_POSITION_DEFAULT,
            ) {
                $issue = $row->getData();
                $router = $request->getRouter();

                if ($column->getId() === Constants::ISSUE_IS_OPEN) {
                    $isOpen = (bool) $issue->getData(Constants::ISSUE_IS_OPEN);
                    $url = $router->url(
                        $request,
                        null,
                        "grid.settings.plugins.SettingsPluginGridHandler",
                        "manage",
                        null,
                        [
                            "verb" => "toggleOpen",
                            "plugin" => "issuepreselectionplugin",
                            "category" => "generic",
                            "issueId" => $issue->getId(),
                        ],
                    );
                    return [
                        new LinkAction(
                            "issuePreselectionToggleOpen",
                            new RemoteActionConfirmationModal(
                                $request->getSession(),
                                __(
                                    $isOpen
                                        ? "plugins.generic.issuePreselection.grid.confirmClose"
                                        : "plugins.generic.issuePreselection.grid.confirmOpen",
                                ),
                                __(
                                    $isOpen
                                        ? "plugins.generic.issuePreselection.grid.close"
                                        : "plugins.generic.issuePreselection.grid.open",
                                ),
                                $url,
                            ),
                            __(
                                $isOpen
                                    ? "plugins.generic.issuePreselection.grid.close"
                                    : "plugins.generic.issuePreselection.grid.open",
                            ),
                        ),
                    ];
                }

                $names = array_filter(
                    array_map(
                        fn($id) => Repo::user()->get((int) $id)?->getFullName(),
                        $issue->getData(Constants::ISSUE_EDITED_BY) ?: [],
                    ),
                );

                if (!(bool) $issue->getData(Constants::ISSUE_IS_OPEN)) {
                    return [];
                }

                $url = $router->url($request, null, "grid.settings.plugins.SettingsPluginGridHandler", "manage", null, [
                    "verb" => "issuePreselectionEditors",
                    "plugin" => "issuepreselectionplugin",
                    "category" => "generic",
                    "issueId" => $issue->getId(),
                ]);
                return [
                    new LinkAction(
                        "issuePreselectionEditors",
                        new AjaxModal($url, __("plugins.generic.issuePreselection.settings.editedBy")),
                        $names ? implode(", ", $names) : __("plugins.generic.issuePreselection.grid.assignEditors"),
                    ),
                ];
            }
        };

        $args["grid"]->addColumn(
            new GridColumn(
                Constants::ISSUE_EDITED_BY,
                "plugins.generic.issuePreselection.settings.editedBy",
                null,
                null,
                $cellProvider,
            ),
        );
        $args["grid"]->addColumn(
            new GridColumn(
                Constants::ISSUE_IS_OPEN,
                "plugins.generic.issuePreselection.grid.toggle",
                null,
                null,
                $cellProvider,
            ),
        );
    }
}
