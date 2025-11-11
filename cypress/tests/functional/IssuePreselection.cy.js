/**
 * @file cypress/tests/functional/IssuePreselection.cy.js
 *
 * Cypress tests for Issue Preselection Plugin
 */

describe('Issue Preselection Plugin', function() {
    it('Plugin is installed and can be enabled', function() {
        cy.login('admin', 'admin');
        cy.get('a:contains("admin")').click();
        cy.get('a:contains("Dashboard")').click();
        cy.get('.app__nav a:contains("Website")').click();
        cy.get('button#plugins-button').click();
        
        // Find the Issue Preselection plugin
        cy.get('input[id^="select-cell-issuepreselectionplugin"]')
            .check()
            .should('be.checked');
    });

    it('Adds custom fields to issue form', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        
        // Navigate to Issues > Future Issues
        cy.get('a:contains("Issues")').click();
        cy.get('a:contains("Future Issues")').click();
        
        // Create a new issue
        cy.get('a:contains("Create Issue")').click();
        cy.wait(1000);
        
        // Verify custom fields exist
        cy.get('input[name="isOpen"]').should('exist');
        cy.get('select[name="editedBy[]"]').should('exist');
        
        // Fill in basic issue info
        cy.get('input[name="volume"]').clear().type('99');
        cy.get('input[name="number"]').clear().type('1');
        cy.get('input[name="year"]').clear().type('2025');
        
        // Enable for submission
        cy.get('input[name="isOpen"]').check();
        
        // Save
        cy.get('button:contains("Save")').click();
        cy.wait(1000);
        
        // Verify success
        cy.get('body').should('contain', 'Vol 99 No 1 (2025)');
    });

    it('Shows issue selector in submission wizard', function() {
        cy.login('ccorino', null, 'publicknowledge');
        
        // Start new submission
        cy.get('a:contains("New Submission")').click();
        cy.wait(1000);
        
        // Accept requirements
        cy.get('input[name="submissionRequirements"]').check();
        cy.get('input[name="privacyConsent"]').check();
        cy.get('button:contains("Continue")').click();
        cy.wait(1000);
        
        // Upload a file
        cy.fixture('dummy.pdf').then(fileContent => {
            cy.get('input[type="file"]').first().selectFile({
                contents: Cypress.Buffer.from(fileContent),
                fileName: 'test.pdf',
                mimeType: 'application/pdf'
            }, { force: true });
        });
        cy.wait(2000);
        cy.get('button:contains("Continue")').click();
        cy.wait(1000);
        
        // Enter metadata
        cy.get('input[name="title"]').type('Test Submission for Issue Preselection', { delay: 0 });
        cy.get('textarea[name="abstract"]').type('This is a test abstract.', { delay: 0 });
        cy.get('button:contains("Continue")').click();
        cy.wait(1000);
        
        // In "For the Editors" step, verify issue selector exists
        cy.get('select[name="preselectedIssueId"]').should('exist');
        cy.get('select[name="preselectedIssueId"] option').should('have.length.gt', 1);
    });

    it('Preserves issue settings on edit', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        
        cy.get('a:contains("Issues")').click();
        cy.get('a:contains("Future Issues")').click();
        
        // Find and edit the test issue
        cy.get('a:contains("Vol 99 No 1 (2025)")').first().click();
        cy.wait(1000);
        
        // Verify isOpen is still checked
        cy.get('input[name="isOpen"]').should('be.checked');
        
        // Make a change
        cy.get('input[name="showTitle"]').clear().type('Special Issue on Testing');
        cy.get('button:contains("Save")').click();
        cy.wait(1000);
        
        // Re-open and verify
        cy.get('a:contains("Vol 99 No 1 (2025)")').first().click();
        cy.wait(1000);
        cy.get('input[name="isOpen"]').should('be.checked');
        cy.get('input[name="showTitle"]').should('have.value', 'Special Issue on Testing');
    });
});
