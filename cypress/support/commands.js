/**
 * @file cypress/support/commands.js
 *
 * Custom Cypress commands for Issue Preselection plugin tests
 */
/// <reference types="cypress" />

Cypress.Commands.add("login", (username, password, context) => {
    context = context || Cypress.env("contextPath") || "publicknowledge";
    cy.visit(`/index.php/${context}/login/signOut`, { failOnStatusCode: false });
    cy.visit(`/index.php/${context}/login`);
    cy.wait(1000);
    cy.get('input[name="username"]').type(username, { delay: 0 });
    cy.get('input[name="password"]').type(password, { delay: 0 });
    cy.get('button[type="submit"]').click();
    cy.wait(2000);
});

Cypress.Commands.add("getContext", () => {
    return Cypress.env("contextPath") || "publicknowledge";
});

Cypress.Commands.add("loginOJS", (username, password) => {
    cy.getContext().then((context) => {
        cy.visit(`/index.php/${context}/login/signOut`, { failOnStatusCode: false });
        cy.visit(`/index.php/${context}/login`, { failOnStatusCode: false });
        cy.get('input[name="username"]').should("be.visible").clear();
        cy.get('input[name="username"]').type(username);
        cy.get('input[name="password"]').clear();
        cy.get('input[name="password"]').type(password);
        cy.intercept("POST", "**/login/signIn").as("loginSubmit");
        cy.get('button[type="submit"]').click();
        cy.wait("@loginSubmit").its("response.statusCode").should("be.within", 200, 399);
        cy.location("pathname").should("not.include", "/login");
    });
});

Cypress.Commands.add("loginAsEditor", () => {
    const editorUser = Cypress.env("editorUsername") || "dbarnes";
    const editorPass = Cypress.env("editorPassword") || `${editorUser}${editorUser}`;
    cy.loginOJS(editorUser, editorPass);
});

Cypress.Commands.add("loginAsAdmin", () => {
    const adminUser = Cypress.env("adminUsername") || "admin";
    const adminPass = Cypress.env("adminPassword") || "admin";
    const context = Cypress.env("contextPath") || "publicknowledge";
    cy.session(
        ["admin", context, adminUser],
        () => {
            cy.loginOJS(adminUser, adminPass);
        },
        {
            validate: () => {
                cy.visitManageIssues();
            }
        }
    );
});

Cypress.Commands.add("loginAsAuthor", () => {
    const authorUser = Cypress.env("authorUsername") || "zzedd";
    const authorPass = Cypress.env("authorPassword") || `${authorUser}${authorUser}`;
    const context = Cypress.env("contextPath") || "publicknowledge";

    cy.session(
        ["author", context, authorUser],
        () => {
            cy.loginOJS(authorUser, authorPass);
        },
        {
            validate: () => {
                cy.visit(`/index.php/${context}/dashboard/mySubmissions`, { failOnStatusCode: false });

                cy.location("pathname").should("not.include", "/login");
            }
        }
    );
});

Cypress.Commands.add("visitManageIssues", () => {
    cy.getContext().then((context) => {
        cy.visit(`/index.php/${context}/manageIssues`);
        cy.get("#futureIssuesGridContainer", { timeout: 15000 }).should("exist");
    });
});

Cypress.Commands.add("navigateToFutureIssues", () => {
    cy.get("#future-button").click();
    cy.get("#futureIssuesGridContainer", { timeout: 15000 }).should("be.visible");
    cy.get("#futureIssuesGridContainer tr.gridRow", { timeout: 15000 }).should("have.length.greaterThan", 0);
});

Cypress.Commands.add("openIssueDataTab", () => {
    cy.get("#editIssueTabs", { timeout: 15000 }).should("exist");

    cy.get("#editIssueTabs .ui-tabs-anchor")
        .contains("Issue Data")
        .then(($anchor) => {
            const $li = $anchor.closest("li");
            const panelId = $li.attr("aria-controls");
            const tabIndex = $li.index();

            if ($li.attr("aria-selected") !== "true") {
                cy.intercept("GET", "**/future-issue-grid/edit-issue-data*").as("issueDataTabLoad");

                cy.get("#editIssueTabs").then(($tabs) => {
                    $tabs.tabs("option", "active", tabIndex);
                });

                cy.wait("@issueDataTabLoad", { timeout: 15000 }).its("response.statusCode").should("eq", 200);
            }

            cy.get(`#editIssueTabs li[aria-controls="${panelId}"]`).should("have.attr", "aria-selected", "true");
        });

    cy.get("form#issueForm", { timeout: 15000 }).should("exist");

    cy.get("#issuePreselectionParticipantsList", { timeout: 15000 }).should("exist");
});

Cypress.Commands.add("openFirstIssue", () => {
    cy.intercept("GET", "**/future-issue-grid/edit-issue*").as("issueEditLoad");

    cy.get("#futureIssuesGridContainer tr.gridRow", { timeout: 15000 })
        .first()
        .find("a.pkp_linkaction_edit")
        .first()
        .click({ force: true });

    cy.wait("@issueEditLoad", { timeout: 15000 });

    cy.location("pathname", { timeout: 15000 }).should("include", "/manageIssues");
    cy.get("#editIssueTabs", { timeout: 15000 }).should("exist");
    cy.openIssueDataTab();
});

Cypress.Commands.add("saveIssue", () => {
    cy.intercept("POST", "**/$$$call$$$/**").as("saveIssueRequest");
    cy.get('button:contains("Save")').first().click({ force: true });
    cy.wait("@saveIssueRequest", { timeout: 15000 });
    cy.wait(1500);
});

Cypress.Commands.add("uploadPlugin", (pluginPath) => {
    cy.loginAsAdmin();
    cy.getContext().then((context) => {
        cy.visit(`/index.php/${context}/management/settings/website#plugins`);
        cy.wait(2000);

        cy.get('button:contains("Upload A New Plugin")').scrollIntoView();
        cy.get('button:contains("Upload A New Plugin")').click();
        cy.wait(1000);

        cy.get('input[type="file"]').selectFile(pluginPath, { force: true });
        cy.wait(1000);

        cy.get('button:contains("Save"), button:contains("Upload")').click();
        cy.wait(3000);
    });
});

Cypress.Commands.add("enablePlugin", (pluginName) => {
    const pluginId = pluginName.toLowerCase();
    const searchTerm = pluginName === "issuePreselection" ? "Issue Preselection" : pluginName;
    cy.loginAsAdmin();
    cy.getContext().then((context) => {
        cy.visit(`/index.php/${context}/management/settings/website#plugins`);
        cy.get("#pluginGridContainer", { timeout: 15000 }).should("be.visible");
        cy.get('#pluginGridContainer a.pkp_linkaction_search, #pluginGridContainer a:contains("Search")')
            .first()
            .click({ force: true });
        cy.get('#pluginGridContainer input[name="pluginName"], #pluginGridContainer input#pluginName')
            .first()
            .clear({ force: true })
            .type(searchTerm, { force: true });
        cy.intercept("POST", "**/fetch-grid**").as("pluginGridSearch");
        cy.get("#pluginSearchForm button[type='submit']").click({ force: true });
        cy.wait("@pluginGridSearch", { timeout: 15000 });
        cy.intercept("POST", "**/settings-plugin-grid/enable**").as("pluginEnable");
        cy.contains("#pluginGridContainer tr.gridRow", searchTerm, { timeout: 15000 })
            .find(`input[type="checkbox"][id*="${pluginId}"][id*="enabled"]`)
            .should("exist")
            .then(($checkbox) => {
                if (!$checkbox.is(":checked")) {
                    cy.wrap($checkbox).check({ force: true });
                    cy.wait("@pluginEnable", { timeout: 15000 })
                        .its("response.statusCode")
                        .should("be.within", 200, 399);
                }
            });
        cy.contains("#pluginGridContainer tr.gridRow", searchTerm, { timeout: 15000 })
            .find(`input[type="checkbox"][id*="${pluginId}"][id*="enabled"]`)
            .should("be.checked");
    });
});

Cypress.Commands.add("searchAndVerifyPluginInSettings", (pluginName = "Issue Preselection") => {
    cy.loginAsAdmin();
    cy.visit("/index.php/publicknowledge/management/settings/website");
    cy.wait(2000);

    cy.get("button").contains("Plugins").click();
    cy.get("#pluginGridContainer", { timeout: 15000 }).should("be.visible");
    cy.wait(1000);

    cy.get('#pluginGridContainer a.pkp_linkaction_search, #pluginGridContainer a:contains("Search")')
        .first()
        .click({ force: true });
    cy.wait(500);

    cy.get('#pluginGridContainer input[name="pluginName"], #pluginGridContainer input#pluginName')
        .first()
        .clear({ force: true })
        .type(`${pluginName}{enter}`, { force: true });
    cy.wait(1500);

    cy.contains("#pluginGridContainer tr.gridRow", pluginName).should("exist");
});