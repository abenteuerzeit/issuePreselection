/**
 * @file cypress/tests/functional/IssueOverview.cy.js
 *
 * Cypress tests for the Future Issues overview and its editor-assignment modal.
 */
/// <reference types="cypress" />

import { faker } from "@faker-js/faker";

describe("Future Issues overview", { viewportWidth: 1280, viewportHeight: 720 }, function () {
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

    const setupIssueOverview = () => {
        cy.visitManageIssues();
        cy.navigateToFutureIssues();
    };

    const firstIssueRow = () => cy.get("#futureIssuesGridContainer tr.gridRow").first();

    const createDemoIssue = (editorIds, isOpen) => {
        const volume = String(faker.number.int({ min: 10000, max: 30000 }));
        const title = `Overview ${faker.string.alphanumeric(5)}`;
        const saveAlias = `createOverviewIssue${volume}`;

        cy.contains(
            '#futureIssuesGridContainer a:contains("Create Issue"), #futureIssuesGridContainer button:contains("Create Issue")',
            "Create Issue"
        )
            .first()
            .click();
        cy.get("form#issueForm").should("exist");
        cy.get('input[name="volume"]').clear().type(volume);
        cy.get('input[name="number"]').clear().type("1");
        cy.get('input[name="year"]')
            .clear()
            .type(String(new Date().getFullYear() + 1));
        cy.get('input[name="title[en]"]').type(title);

        if (isOpen) {
            cy.get('input[name="isOpen"]').check({ force: true });
        }
        editorIds.forEach((editorId) => {
            cy.get("#issuePreselectionAssignBtn").click();
            cy.get("#issuePreselectionEditorSelect").select(editorId);
        });

        cy.intercept("POST", "**/future-issue-grid/update-issue*").as(saveAlias);
        cy.get('#issueForm button[id^="submitFormButton-"]').click();
        cy.wait(`@${saveAlias}`).its("response.statusCode").should("eq", 200);
        cy.visitManageIssues();
        cy.navigateToFutureIssues();

        return volume;
    };

    const deleteDemoIssue = (volume) => {
        const issueRow = "#futureIssuesGridContainer tr.gridRow";
        cy.contains(issueRow, `Vol. ${volume}`).find("td.first_column").click();
        cy.contains(issueRow, `Vol. ${volume}`)
            .next(".row_controls")
            .find("a.pkp_linkaction_delete")
            .click({ force: true });
        cy.contains("button", "OK").click();
        cy.contains(issueRow, `Vol. ${volume}`).should("not.exist");
    };

    afterEach(function () {
        if (this.originalFirstIssueOpen === false) {
            cy.visitManageIssues();
            cy.navigateToFutureIssues();
            cy.openFirstIssue();
            cy.get('input[name="isOpen"]').uncheck({ force: true });
            cy.saveIssue();
        }

        if (this.demoIssueVolumes?.length) {
            cy.visitManageIssues();
            cy.navigateToFutureIssues();
            this.demoIssueVolumes.forEach(deleteDemoIssue);
        }
    });

    it("shows future issues and plugin columns", function () {
        this.demoIssueVolumes = [];
        setupIssueOverview();

        [
            { editorIds: [], isOpen: false },
            { editorIds: ["3"], isOpen: true },
            { editorIds: ["1", "2", "3"], isOpen: false }
        ].forEach(({ editorIds, isOpen }) => {
            this.demoIssueVolumes.push(createDemoIssue(editorIds, isOpen));
        });

        cy.get("#futureIssuesGridContainer th")
            .should("contain", "Assigned Editors")
            .and("contain", "Call for Papers");
        cy.get("#futureIssuesGridContainer tr.gridRow").should("exist");
        cy.contains("#futureIssuesGridContainer", "Create Issue").should("exist");
        cy.screenshot("issues-overview", {
            capture: "viewport",
            scale: true,
            disableTimersAndAnimations: false
        });
    });

    it("opens the editor-assignment modal for an open issue", function () {
        setupIssueOverview();

        cy.openFirstIssue();
        cy.get('input[name="isOpen"]').then(($isOpen) => {
            this.originalFirstIssueOpen = $isOpen.is(":checked");
            if (!this.originalFirstIssueOpen) {
                cy.wrap($isOpen).check({ force: true });
                cy.saveIssue();
            }
        });
        cy.visitManageIssues();
        cy.navigateToFutureIssues();

        cy.contains("#futureIssuesGridContainer tr.gridRow td:last-child a", "Close")
            .closest("tr.gridRow")
            .find("td:nth-last-child(2) a")
            .click();
        cy.get("#issuePreselectionParticipantManager", { timeout: 15000 })
            .should("be.visible")
            .and("not.be.disabled");
        cy.get("#issuePreselectionParticipantManager").scrollIntoView();
        cy.screenshot("issue-editor-assignment-modal", { capture: "viewport" });
    });

    it("toggles an issue closed and restores its original state", function () {
        setupIssueOverview();

        firstIssueRow()
            .find("td:last-child a")
            .then(($action) => {
                const initialState = $action.text().trim();
                const toggledState = initialState === "Close" ? "Open" : "Close";

                const confirmToggle = (expectedState) => {
                    firstIssueRow().find("td:last-child a").click();
                    cy.contains("button", "OK").click();
                    firstIssueRow().find("td:last-child a").should("contain", expectedState);
                };

                confirmToggle(toggledState);
                confirmToggle(initialState);
            });
    });
});
