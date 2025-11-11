# Cypress Tests for Issue Preselection Plugin

## Overview

This directory contains Cypress end-to-end tests for the Issue Preselection Plugin.

## Test Coverage

The tests verify:

1. **Plugin Installation**: Plugin can be enabled in the admin interface
2. **Issue Configuration**: Custom fields (isOpen, editedBy) appear in issue forms
3. **Submission Wizard**: Issue selector appears for authors during submission
4. **Data Persistence**: Issue settings are preserved when editing
5. **Editor Assignment**: Editors are automatically assigned to submissions (integration test)

## Running Tests

### Via GitHub Actions

Tests run automatically on push via the workflow defined in `.github/workflows/main.yml`.

### Locally

1. Install OJS with test data:
   ```bash
   php lib/pkp/tools/installPluginVersion.php plugins/generic/issuePreselection
   ```

2. Install Cypress:
   ```bash
   npm install cypress --save-dev
   ```

3. Run tests:
   ```bash
   npx cypress run --config-file plugins/generic/issuePreselection/cypress.json
   ```

4. Or open Cypress UI:
   ```bash
   npx cypress open --config-file plugins/generic/issuePreselection/cypress.json
   ```

## Test Structure

```
cypress/
├── fixtures/
│   └── dummy.pdf          # Sample PDF for file uploads
├── tests/
│   └── functional/
│       └── IssuePreselection.cy.js  # Main test suite
└── README.md              # This file
```

## Notes

- Tests assume OJS is running with default test data
- Default test users: admin/admin, dbarnes, ccorino
- Tests use the 'publicknowledge' journal context
- Some tests may need adjustment based on OJS version and configuration

## Extending Tests

To add new tests:

1. Add test cases to `tests/functional/IssuePreselection.cy.js`
2. Follow Cypress best practices for OJS testing
3. Use `cy.login()` for authentication
4. Use `cy.wait()` or `cy.waitJQuery()` for async operations
5. Clean up test data in `after()` hooks if needed

## Troubleshooting

- **Tests fail on CI**: Check GitHub Actions logs for specific errors
- **Selector not found**: OJS UI may have changed; update selectors
- **Timeout errors**: Increase `defaultCommandTimeout` in `cypress.json`
- **File upload fails**: Verify `dummy.pdf` fixture exists and is valid
