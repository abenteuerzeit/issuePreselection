/**
 * @file cypress/tests/functional/IssueDataForm.cy.js
 *
 * Cypress tests for issue data tabs, form settings, and editor assignments.
 */
/// <reference types="cypress" />

import { faker } from "@faker-js/faker";

Cypress.on("uncaught:exception", (err) => {
    if (err.message.includes("Cannot read properties of null") && err.message.includes("'style'")) {
        return false;
    }
});

describe("Issue Data form and editor assignments", function () {
    let createdIssueTitle = null;

    before(() => {
        const pluginArchive = Cypress.env("PLUGIN_ARCHIVE");
        if (pluginArchive && !Cypress.env("CI")) {
            cy.uploadPlugin(pluginArchive);
            cy.enablePlugin("issuePreselection");
        } else {
            cy.enablePlugin("issuePreselection");
        }
    });

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    const setupIssueDataForm = () => {
        cy.visitManageIssues();
        cy.navigateToFutureIssues();
    };

    const verifyEditorFieldValue = (shouldBeEmpty = false) => {
        const enabledEditors = cy.get('input[name="editedBy[]"]:enabled');
        if (shouldBeEmpty) {
            enabledEditors.should("not.exist");
        } else {
            enabledEditors.should("exist");
        }
    };

    const captureIssueSettings = () =>
        cy.get("form#issueForm").then(($form) => ({
            isOpen: $form.find('input[name="isOpen"]').is(":checked"),
            editorIds: [
                ...$form.find("#issuePreselectionParticipantsList li[data-editor-id]:not([hidden])")
            ].map((editor) => editor.getAttribute("data-editor-id"))
        }));

    const removeAssignedEditors = () =>
        cy.get("body").then(($body) => {
            const $editors = $body.find(
                "#issuePreselectionParticipantsList li[data-editor-id]:not([hidden])"
            );
            if ($editors.length) {
                cy.wrap($editors.first())
                    .find(".issuePreselectionRemoveBtn")
                    .click({ force: true });
                removeAssignedEditors();
            }
        });

    const restoreIssueSettings = ({ isOpen, editorIds }) => {
        cy.get('input[name="isOpen"]').check({ force: true });
        removeAssignedEditors();
        editorIds.forEach((editorId) => {
            cy.get("#issuePreselectionAssignBtn").click({ force: true });
            cy.get("#issuePreselectionEditorSelect").select(editorId);
        });
        if (!isOpen) {
            cy.get('input[name="isOpen"]').uncheck({ force: true });
        }
        cy.saveIssue();
    };

    const openIssueByTitle = (title) => {
        cy.intercept("GET", "**/future-issue-grid/edit-issue*").as("issueEditLoad");

        cy.contains("#futureIssuesGridContainer tr.gridRow", title, { timeout: 15000 })
            .find("a.pkp_linkaction_edit")
            .first()
            .click({ force: true });

        cy.wait("@issueEditLoad", { timeout: 15000 });
        cy.get("#editIssueTabs", { timeout: 15000 }).should("exist");
        cy.openIssueDataTab();
    };

    const deleteIssueByTitle = (title) => {
        const issueRow = "#futureIssuesGridContainer tr.gridRow";

        cy.contains(issueRow, title, { timeout: 15000 })
            .as("createdIssueRow")
            .find("td.first_column")
            .click();

        cy.get("@createdIssueRow")
            .next(".row_controls")
            .find("a.pkp_linkaction_delete")
            .click({ force: true });

        cy.contains("button", "OK").click();
        cy.contains(issueRow, title, { timeout: 15000 }).should("not.exist");
    };

    afterEach(() => {
        if (!createdIssueTitle) {
            return;
        }

        const title = createdIssueTitle;
        createdIssueTitle = null;

        cy.visitManageIssues();
        cy.navigateToFutureIssues();
        deleteIssueByTitle(title);
    });

    it("loads the Issue Data form for an existing issue", function () {
        setupIssueDataForm();
        cy.openFirstIssue();

        cy.get("form#issueForm").should("exist");
        cy.get('input[name="isOpen"]').should("exist");
    });

    it("persists the issue open state when edited", function () {
        setupIssueDataForm();
        cy.openFirstIssue();

        cy.get('input[name="isOpen"]')
            .then(($checkbox) => $checkbox.is(":checked"))
            .then((initialState) => {
                cy.get('input[name="isOpen"]').scrollIntoView().check();

                cy.saveIssue();

                cy.visitManageIssues();
                cy.navigateToFutureIssues();

                cy.openFirstIssue();

                cy.get('input[name="isOpen"]').scrollIntoView().should("be.checked");

                if (!initialState) {
                    cy.get('input[name="isOpen"]').scrollIntoView().uncheck();

                    cy.saveIssue();
                }
            });
    });

    it("allows editor assignment during issue creation and saves it", function () {
        setupIssueDataForm();
        const volume = String(faker.number.int({ min: 1, max: 30000 }));
        const title = `Issue ${faker.string.alphanumeric(8)}`;
        const year = String(new Date().getFullYear() + 1);
        createdIssueTitle = title;

        cy.contains(
            '#futureIssuesGridContainer a:contains("Create Issue"), #futureIssuesGridContainer button:contains("Create Issue")',
            "Create Issue",
            { timeout: 15000 }
        )
            .first()
            .click();

        cy.get("form#issueForm", { timeout: 15000 }).should("exist");
        cy.get('input[name="volume"]').clear().type(volume);
        cy.get('input[name="number"]').clear().type("1");
        cy.get('input[name="year"]').clear().type(year);
        cy.get('input[name="title[en]"]').type(title);
        cy.get("#issuePreselectionParticipantManager").should("not.be.disabled");
        cy.get("#issuePreselectionAssignBtn").click();
        cy.get("#issuePreselectionEditorSelect").select("3");
        cy.get('input[name="editedBy[]"]:enabled').should("exist");
        cy.get("#issuePreselectionParticipantManager").scrollIntoView();

        cy.screenshot("issue-editor-selected-before-save", {
            capture: "viewport"
        });

        cy.intercept("POST", "**/future-issue-grid/update-issue*").as("createIssueSave");
        cy.get('#issueForm button[id^="submitFormButton-"]').click();
        cy.wait("@createIssueSave").then(({ request, response }) => {
            const submitted = new URLSearchParams(request.body);
            expect(response?.statusCode).to.equal(200);
            expect(submitted.get("volume")).to.equal(volume);
            expect(submitted.get("title[en]")).to.equal(title);
            expect(submitted.getAll("editedBy[]")).to.include("3");
        });

        cy.visitManageIssues();
        cy.navigateToFutureIssues();

        openIssueByTitle(title);

        cy.get("#issuePreselectionParticipantManager").scrollIntoView();
        cy.get(
            '#issueForm #issuePreselectionParticipantsList li[data-editor-id="3"]:not([hidden])',
            {
                timeout: 15000
            }
        ).should("be.visible");
        cy.get('#issueForm input[name="editedBy[]"][value="3"]').should("exist");

        cy.screenshot("issue-editor-assignment-saved", {
            capture: "viewport"
        });
    });

    it("persists multiple editor assignments", function () {
        setupIssueDataForm();
        cy.openFirstIssue();

        captureIssueSettings().then((initialSettings) => {
            cy.get('input[name="isOpen"]').check({ force: true });
            removeAssignedEditors();
            cy.get("#issuePreselectionAssignBtn").click();
            cy.get("#issuePreselectionEditorSelect").select("1");
            cy.get("#issuePreselectionAssignBtn").click();
            cy.get("#issuePreselectionEditorSelect").select("2");
            cy.saveIssue();
            cy.visitManageIssues();
            cy.navigateToFutureIssues();
            cy.openFirstIssue();
            verifyEditorFieldValue();
            restoreIssueSettings(initialSettings);
        });
    });

    it("persists removal of all editor assignments", function () {
        setupIssueDataForm();
        cy.openFirstIssue();

        captureIssueSettings().then((initialSettings) => {
            cy.get('input[name="isOpen"]').check({ force: true });
            removeAssignedEditors();
            cy.saveIssue();
            cy.visitManageIssues();
            cy.navigateToFutureIssues();
            cy.openFirstIssue();
            verifyEditorFieldValue(true);
            restoreIssueSettings(initialSettings);
        });
    });
});
