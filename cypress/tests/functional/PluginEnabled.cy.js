/**
 * @file cypress/tests/functional/PluginEnabled.cy.js
 *
 * Test to verify the Issue Preselection plugin is installed, enabled, and searchable in settings
 */
/// <reference types="cypress" />

describe("Issue Preselection Plugin", { viewportWidth: 1280, viewportHeight: 720 }, function () {
    it("Plugin is installed, enabled, and searchable in settings", function () {
        cy.enablePlugin("issuePreselection");
        cy.contains("#pluginGridContainer tr.category", "Generic Plugins").should("contain", "(1)");
        cy.contains("#pluginGridContainer tr.gridRow", "Issue Preselection")
            .find('input[type="checkbox"][id*="issuepreselection"][id*="enabled"]')
            .should("be.checked");
        cy.contains("#pluginGridContainer tr.category", "Import/Export Plugins").scrollIntoView({
            offset: { top: 150, left: 0 }
        });
        cy.contains("#pluginGridContainer tr.category", "Generic Plugins").should(($category) => {
            const heading = $category[0].getBoundingClientRect();
            expect(heading.top).to.be.at.least(0);
            expect(heading.bottom).to.be.lessThan(680);
        });
        cy.contains("#pluginGridContainer tr.gridRow", "Issue Preselection").should(($row) => {
            const row = $row[0].getBoundingClientRect();
            expect(row.top).to.be.at.least(0);
            expect(row.bottom).to.be.lessThan(680);
        });
        cy.contains("#pluginGridContainer tr.category", "Import/Export Plugins").should(
            "be.visible"
        );
        cy.screenshot("plugin-enabled-generic-section", {
            capture: "viewport"
        });
    });
});
