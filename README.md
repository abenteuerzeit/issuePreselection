# Issue Preselection Plugin for OJS 3.5+

[![Release](https://img.shields.io/badge/release-1.0.0-blue.svg)](https://github.com/yourusername/ojs-issue-preselection/releases)
[![OJS](https://img.shields.io/badge/OJS-3.5.0.1+-green.svg)](https://pkp.sfu.ca/ojs/)
[![License](https://img.shields.io/badge/license-GPL--3.0-blue.svg)](LICENSE)

## Overview

The Issue Preselection Plugin allows authors to select which journal issue their submission should be assigned to during the submission process. Editors can configure which issues are open for submissions and pre-assign guest editors who will automatically be assigned to incoming submissions.

## Features

### For Authors
- 📝 **Issue Selection During Submission** - Select from available future issues when submitting
- 🎯 **Direct Response to Calls for Papers** - Submit directly to specific themed issues
- 👁️ **Filtered Issue List** - Only see issues that editors have marked as open

### For Editors
- ⚙️ **Issue Configuration** - Mark issues as open/closed for author selection
- 👥 **Editor Pre-assignment** - Assign guest editors to issues for automatic workflow assignment
- 🔄 **Automatic Workflow** - Submissions automatically assigned to configured editors with notifications

## Requirements

- **OJS Version**: 3.5.0.1 or higher
- **PHP Version**: 8.2 or higher
- **Database**: MySQL 5.7+ or PostgreSQL 9.5+

## Installation

### Method 1: Manual Installation

1. Download the latest release from the [releases page](https://github.com/yourusername/ojs-issue-preselection/releases)
2. Extract the archive
3. Copy the `issuePreselection` folder to `plugins/generic/` in your OJS installation
4. Log in to OJS as Administrator
5. Navigate to **Settings > Website > Plugins**
6. Find "Issue Preselection Plugin" under Generic Plugins
7. Click **Enable**

### Method 2: Git Installation

```bash
cd /path/to/ojs/plugins/generic
git clone https://github.com/yourusername/ojs-issue-preselection.git issuePreselection
```

Then enable via the OJS admin interface as described above.

## Configuration

### Setting Up Issues

1. Navigate to **Issues > Future Issues**
2. Click **Edit** on an issue you want to configure
3. You'll see two new fields:
   - **Enable for Submission**: Check to make this issue available for author selection
   - **Assigned Editors**: Select one or more editors to automatically assign to submissions

4. Click **Save**

### Author Submission Workflow

1. Author clicks "New Submission"
2. In the "For the Editors" step, they see an "Issue Selection" dropdown
3. Author selects the target issue
4. Upon submission:
   - Publication is scheduled to the selected issue
   - All pre-assigned editors are added as Guest Editors
   - Editors receive notifications

## Technical Details

### Architecture

The plugin uses OJS's hook system exclusively for integration:

- **Schema Hooks**: Extend issue and submission schemas with custom fields
- **Form Hooks**: Add UI elements to issue and submission forms
- **Template Hooks**: Display selected issue in review section
- **Validation Hooks**: Process issue assignment and editor assignment on submission

### Data Storage

Uses OJS's existing settings tables (no database migrations required):

- `issue_settings`: Stores `isOpen` (boolean) and `editedBy` (array of user IDs)
- `submission_settings`: Stores `preselectedIssueId` (integer)
- `publications`: Uses existing `issueId` field for scheduling

### Hooks Used

| Hook | Purpose |
|------|---------|
| `Schema::get::issue` | Add custom fields to issue schema |
| `Templates::Editor::Issues::IssueData::AdditionalMetadata` | Extend issue form |
| `issueform::readuservars` | Register custom form variables |
| `issueform::execute` | Save custom issue settings |
| `Issue::edit` | Preserve custom data during edits |
| `Schema::get::submission` | Add preselectedIssueId to submission schema |
| `Form::config::after` | Add issue selector to submission wizard |
| `Submission::getSubmissionsListProps` | Include field in Vue state |
| `Template::SubmissionWizard::Section::Review::Editors` | Display in review section |
| `Submission::validateSubmit` | Process assignment on submission |

## Known Limitations

### Review Section Display Timing

The selected issue may not immediately appear in the review section when navigating through the submission wizard step-by-step.

**Why**: OJS's submission wizard uses Vue.js for client-side forms and Smarty for server-side templates. The review section renders before the form's autosave completes.

**Impact**: Cosmetic only. All data saves correctly and functionality works as expected.

**Workarounds**:
- Click "Save for Later" to trigger a page reload
- Refresh the page
- The issue displays correctly when editing existing submissions

**What Works**:
- ✅ Issue selection saves correctly
- ✅ Publication schedules to selected issue
- ✅ Guest editors assign automatically
- ✅ Submission completes successfully

## Troubleshooting

### Issue selector doesn't appear

- Verify the plugin is enabled in Settings > Website > Plugins
- Check that at least one issue is marked as "Enable for Submission"
- Clear OJS cache: `php tools/runScheduledTasks.php`

### Editors not assigned automatically

- Verify editors are assigned to the issue in Issues > Future Issues
- Check that assigned users have Section Editor or Manager role
- Review error logs in `files/error.log`

### Selected issue not displaying in review

- This is expected behavior (see Known Limitations above)
- Data is saving correctly despite display timing
- Click "Save for Later" to see the value after page reload

## Development

### File Structure

```
plugins/generic/issuePreselection/
├── IssuePreselectionPlugin.php    # Main plugin class
├── classes/
│   ├── Constants.php              # Shared constants
│   ├── IssueManagement.php        # Issue-related functionality
│   └── SubmissionManagement.php   # Submission-related functionality
├── cypress/
│   ├── tests/functional/          # Cypress integration tests
│   ├── fixtures/                  # Test fixtures
│   └── README.md                  # Test documentation
├── index.php                       # Plugin entry point
├── version.xml                     # Plugin metadata
├── README.md                       # Documentation
├── CHANGELOG.md                    # Version history
├── LICENSE                         # GPL-3.0 license
├── locale/
│   └── en/
│       └── locale.po              # English translations
└── templates/
    └── issueFormFields.tpl        # Issue form custom fields
```

### Adding Translations

1. Copy `locale/en/locale.po` to `locale/{locale_code}/locale.po`
2. Translate the strings
3. Submit a pull request

### Testing

The plugin includes Cypress end-to-end tests. To run them:

```bash
npx cypress run --config-file plugins/generic/issuePreselection/cypress.json
```

See `cypress/README.md` for more details.

### Debugging

Enable error logging in `config.inc.php`:

```ini
[debug]
show_stacktrace = On
display_errors = On
```

The plugin logs extensively with `[IssuePreselection]` prefix.

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/ojs-issue-preselection/issues)
- **Documentation**: [Wiki](https://github.com/yourusername/ojs-issue-preselection/wiki)
- **OJS Forum**: [PKP Community Forum](https://forum.pkp.sfu.ca/)

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GNU General Public License v3.0. See [LICENSE](LICENSE) for details.
