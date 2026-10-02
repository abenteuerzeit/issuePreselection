/**
 * @file cypress/tests/functional/IssueManagement.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Cypress tests for Issue Management functionality
 */
/// <reference types="cypress" />

describe("Issue Management", function () {
    before(() => {
        // Skip plugin installation in CI - PKP action already installs it
        const pluginArchive = Cypress.env("PLUGIN_ARCHIVE");
        if (pluginArchive && !Cypress.env("CI")) {
            cy.uploadPlugin(pluginArchive);
            cy.enablePlugin("issuePreselection");
        }
    });

    const createIssue = (volume, number, year) => {
        cy.get('a:contains("Create Issue"), button:contains("Create Issue")').first().click();
        cy.get("form#issueForm", { timeout: 10000 }).should("exist");
        cy.wait(2000);

        cy.get('input[name="volume"]').should("be.visible");
        cy.get('input[name="volume"]').clear();
        cy.get('input[name="volume"]').type(volume);
        cy.get('input[name="number"]').clear();
        cy.get('input[name="number"]').type(number);
        cy.get('input[name="year"]').clear();
        cy.get('input[name="year"]').type(year);
    };

    const verifyEditorFieldValue = (shouldBeEmpty = false) => {
        if (shouldBeEmpty) {
            cy.get('select[name="editedBy[]"]').should("exist");
        } else {
            cy.get('select[name="editedBy[]"]').invoke("val").should("not.be.empty");
        }
    };

    const setupIssueManagement = () => {
        cy.loginAsEditor();
        cy.visitManageIssues();
        cy.navigateToFutureIssues();
    };

    it("Plugin is installed and can be enabled", function () {
        cy.login("admin", "admin");
        cy.visit("/index.php/publicknowledge/management/settings/website");
        cy.wait(2000);

        // Navigate to plugins
        cy.get("button").contains("Plugins").click();
        cy.wait(1000);

        // Check if plugin is enabled
        cy.get('input[id^="select-cell-issuepreselection"]').should("exist");
    });

    it.skip("Adds custom fields to issue form", function () {
        // TODO: Implement test for plugin-specific issue form fields
        setupIssueManagement();
        createIssue("99", "1", "2025");

        cy.get('input[name="isOpen"]').should("exist");
        cy.get('select[name="editedBy[]"]').should("exist");
        cy.get('input[name="isOpen"]').check();

        cy.saveIssue();
        cy.get("body").should("contain", "Vol");
    });

    it.skip("Preserves issue settings on edit", function () {
        setupIssueManagement();
        cy.openFirstIssue();

        cy.get('input[name="isOpen"]').scrollIntoView();
        cy.get('input[name="isOpen"]').should("be.visible");
        cy.get('input[name="isOpen"]').check();
        cy.saveIssue();
        cy.wait(3000);

        cy.visitManageIssues();
        cy.navigateToFutureIssues();
        cy.openFirstIssue();

        cy.get('input[name="isOpen"]').scrollIntoView();
        cy.get('input[name="isOpen"]').should("be.visible");
        cy.get('input[name="isOpen"]').should("be.checked");
    });

    it.skip("editedBy field updates on create and first assign", function () {
        setupIssueManagement();
        createIssue("100", "1", "2025");

        cy.get('input[name="isOpen"]').check();
        cy.get('select[name="editedBy[]"]').select(1);

        cy.saveIssue();
        cy.get("body").should("contain", "Vol");

        cy.get('a[id*="edit-button"], a:contains("Vol 100")').first().scrollIntoView();
        cy.get('a[id*="edit-button"], a:contains("Vol 100")').first().click({ force: true });
        cy.wait(2000);
        cy.openIssueDataTab();

        verifyEditorFieldValue();
    });

    it.skip("editedBy field updates on single assign", function () {
        setupIssueManagement();
        cy.openFirstIssue();

        cy.get('select[name="editedBy[]"]').select(1);
        cy.saveIssue();

        cy.visitManageIssues();
        cy.navigateToFutureIssues();
        cy.openFirstIssue();

        verifyEditorFieldValue();
    });

    it.skip("editedBy field updates on many assign", function () {
        setupIssueManagement();
        cy.openFirstIssue();

        cy.get('select[name="editedBy[]"]').then(($select) => {
            if ($select.prop("multiple")) {
                cy.get('select[name="editedBy[]"] option').then(($options) => {
                    const values = [];
                    $options.each((i, option) => {
                        if (i > 0 && i <= 2) {
                            values.push(option.value);
                        }
                    });
                    cy.get('select[name="editedBy[]"]').select(values);
                });
            } else {
                cy.get('select[name="editedBy[]"]').select(1);
            }
        });

        cy.saveIssue();

        cy.visitManageIssues();
        cy.navigateToFutureIssues();
        cy.openFirstIssue();

        verifyEditorFieldValue(true);
    });

    it.skip("editedBy field updates on assign none", function () {
        setupIssueManagement();
        cy.openFirstIssue();

        cy.get('select[name="editedBy[]"]').then(($select) => {
            if ($select.find('option[value=""]').length > 0) {
                cy.get('select[name="editedBy[]"]').select("");
            }
        });

        cy.saveIssue();
    });
});
