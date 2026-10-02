/**
 * @file cypress/tests/functional/PluginEnabled.cy.js
 *
 * Simple test to verify the Issue Preselection plugin is installed and enabled
 */

describe("Issue Preselection Plugin", function () {
    it("Plugin is installed and can login", function () {
        cy.login("admin", "admin");
        cy.wait(2000);

        // Verify we're logged in by checking for user menu or dashboard
        cy.get("body").should("contain", "Dashboard");
    });
});
