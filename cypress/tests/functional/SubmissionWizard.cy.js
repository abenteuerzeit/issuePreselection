/**
 * @file cypress/tests/functional/SubmissionWizard.cy.js
 *
 * Cypress tests for Submission Wizard with Issue Preselection.
 *
 * Issue creation, issue state, and editor assignment are covered by the
 * Issue Manager suites. These tests only exercise the submission workflow
 * and its observable effects.
 *
 * Screenshots map to the README "Author Submission Workflow" steps and are
 * written to cypress/screenshots/SubmissionWizard.cy.js/author-<step>.png
 * (overwritten on every run).
 */

/// <reference types="cypress" />

import { faker } from "@faker-js/faker";

const ISSUE_SELECT = 'select[name="preselectedIssueId"]';
const TITLE_EDITOR = "startSubmission-title-control";
const DOCX_MIME = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";

const shot = (step, target) => {
    cy.wait(500);
    cy.get(target).first().should("be.visible").scrollIntoView({ block: "center" });
    cy.screenshot(`${step}`, {
        capture: "viewport",
        overwrite: true
    });
};

const shotOpenIssueMenu = () => {
    cy.get(ISSUE_SELECT).should("be.visible").scrollIntoView({ block: "center" });
    cy.get(ISSUE_SELECT).then(($select) => {
        const optionCount = $select.find("option").length;
        $select.attr("size", Math.min(Math.max(optionCount, 4), 6));
    });
    cy.screenshot("issue-selection-open", {
        capture: "viewport",
        overwrite: true
    });
    cy.get(ISSUE_SELECT).invoke("removeAttr", "size");
};

const footerClick = (label) =>
    cy.contains(".submissionWizard__footer button", label).scrollIntoView().click({ force: true });

const startSubmission = () => {
    cy.loginAsAuthor();
    cy.getContext().then((context) => {
        cy.visit(`/index.php/${context}/submission`);
        cy.wait(3000);
        cy.window()
            .should((win) => expect(win.tinymce?.get(TITLE_EDITOR)).to.exist)
            .then((win) => {
                const editor = win.tinymce.get(TITLE_EDITOR);
                editor.setContent(`<p>${faker.lorem.sentence()}</p>`);
                editor.fire("change");
            });
        cy.get('label:contains("Articles")').click();
        cy.get('label:contains("English")').click();
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.contains("button", /Begin Submission/i).click();
    });
};

const fillAbstract = () => {
    cy.get('iframe[id*="abstract"]')
        .first()
        .should("be.visible")
        .then(($iframe) => {
            cy.wrap($iframe.contents().find("body"))
                .click()
                .type(faker.lorem.paragraphs(3, " "), { delay: 0 });
        });

    footerClick(/Continue/i);
};

const uploadFile = () => {
    cy.fixture("dummy.docx", "base64").then((fileContent) => {
        cy.get('input[type="file"]')
            .first()
            .selectFile(
                {
                    contents: Cypress.Buffer.from(fileContent, "base64"),
                    fileName: "test-article.docx",
                    mimeType: DOCX_MIME
                },
                { force: true }
            );
    });
    cy.contains("button", /Article Text/i).click();
    footerClick(/Continue/i);
};

const addContributor = () => {
    cy.contains("button", /Add Contributor/i).click();
    cy.get('input[name*="givenName"]').first().should("be.visible").type(faker.person.firstName());
    cy.get('input[name*="familyName"]').first().type(faker.person.lastName());
    cy.get('input[name*="email"]').first().type(faker.internet.email());
    cy.get('select[name*="country"]').first().select("US");
    cy.get('input[type="radio"][name*="userGroupId"]').first().check({ force: true });
    cy.contains(".pkpFormPage__footer button", "Save").scrollIntoView().click({ force: true });
    cy.wait(2000);
    footerClick(/Continue/i);
};

const navigateToIssueSelectionStep = () => {
    fillAbstract();
    cy.get('input[type="file"], button:contains("Add Contributor")')
        .first()
        .then(($el) => {
            if ($el.is('input[type="file"]')) uploadFile();
        });
    addContributor();
};

const selectFirstIssue = () =>
    cy
        .get(`${ISSUE_SELECT} option`)
        .eq(1)
        .invoke("val")
        .then((issueId) =>
            cy
                .get(ISSUE_SELECT)
                .select(issueId)
                .then(() => issueId)
        );

const reviewStepReached = () => {
    cy.contains("button", /Submit/i)
        .should("be.visible")
        .scrollIntoView({ block: "center" });

    cy.contains("Checking your submission").should("not.exist");
};

const submit = () => {
    cy.get("button")
        .contains(/Submit/i)
        .scrollIntoView()
        .click({ force: true });
    cy.wait(1000);
    cy.get("body").then(($body) => {
        if ($body.find('.pkpModal, [role="dialog"]').length) {
            cy.get(
                '.pkpModal button:contains("Submit"), [role="dialog"] button:contains("Submit")'
            ).click({ force: true });
        }
    });
    cy.get("body").should("contain", "Submission complete");
    return cy.location("search").then((search) => new URLSearchParams(search).get("id"));
};

let firstIssueWasOpen = true;

const setFirstIssueOpen = (open) => {
    cy.loginAsAdmin();
    cy.visitManageIssues();
    cy.navigateToFutureIssues();
    cy.openFirstIssue();
    cy.get('input[name="isOpen"]').then(($isOpen) => {
        if ($isOpen.is(":checked") === open) {
            return;
        }
        cy.wrap($isOpen).scrollIntoView();
        if (open) {
            cy.wrap($isOpen).check({ force: true });
        } else {
            cy.wrap($isOpen).uncheck({ force: true });
        }
        cy.saveIssue();
    });
};

describe("Submission Wizard - Issue Preselection", () => {
    before(() => {
        const pluginArchive = Cypress.env("PLUGIN_ARCHIVE");

        if (pluginArchive && !Cypress.env("CI")) {
            cy.uploadPlugin(pluginArchive);
        }

        cy.enablePlugin("issuePreselection");

        cy.loginAsAdmin();
        cy.visitManageIssues();
        cy.navigateToFutureIssues();
        cy.openFirstIssue();
        cy.get('input[name="isOpen"]').then(($isOpen) => {
            firstIssueWasOpen = $isOpen.is(":checked");
        });
        setFirstIssueOpen(true);
    });

    after(() => {
        if (!firstIssueWasOpen) {
            setFirstIssueOpen(false);
        }
    });

    beforeEach(() => {
        startSubmission();
        navigateToIssueSelectionStep();
    });

    it("shows the issue selector with the enabled issues", () => {
        cy.get(ISSUE_SELECT).should("be.visible").find("option").should("have.length.gt", 1);
        cy.get(`${ISSUE_SELECT} option`).eq(1).should("not.have.value", "0");
        shot("issue-selection", ISSUE_SELECT);
        shotOpenIssueMenu();
    });

    it("requires an issue before continuing", () => {
        cy.get(ISSUE_SELECT).should("be.visible").and("have.value", "0");
        footerClick("Continue");
        cy.get(".pkpNotification--warning")
            .contains("Please select an issue for your submission.")
            .should("be.visible");
        shot("missing-issue-error", ".submissionWizard__review_errors");
    });

    it("keeps the issue selector available when going back", () => {
        selectFirstIssue();
        cy.get(ISSUE_SELECT).should("be.visible");
        footerClick("Continue");
        reviewStepReached();
        footerClick("Back");
        cy.get(ISSUE_SELECT).should("be.visible");
        shot("back-navigation", ISSUE_SELECT);
    });

    it("assigns the successful submission to the selected issue", () => {
        selectFirstIssue().then((issueId) => {
            footerClick("Continue");
            reviewStepReached();
            submit().then((submissionId) => {
                cy.loginAsAdmin();
                cy.getContext().then((context) => {
                    cy.visit(
                        `/index.php/${context}/dashboard/editorial` +
                            `?currentViewId=assigned-to-me` +
                            `&workflowSubmissionId=${submissionId}` +
                            `&workflowMenuKey=publication_issue`
                    );
                });
                cy.url().should("include", `workflowSubmissionId=${submissionId}`);
                cy.contains("span", "This has been assigned to").should("be.visible").first();
                cy.contains("but it has not been scheduled for publication.").should("be.visible");
                cy.get(`a[href*="/issue/view/${issueId}"]`).should("exist");
                shot("submission-success", 'span:contains("This has been assigned to")');
            });
        });
    });
});
