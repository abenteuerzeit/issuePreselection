/// <reference types="cypress" />

declare namespace Cypress {
    interface Chainable {
        login(username?: string, password?: string, context?: string): Chainable<void>;
        getContext(): Chainable<string>;
        loginOJS(username: string, password?: string): Chainable<void>;
        loginAsEditor(): Chainable<void>;
        loginAsAdmin(): Chainable<void>;
        loginAsAuthor(): Chainable<void>;
        visitManageIssues(): Chainable<void>;
        navigateToFutureIssues(): Chainable<void>;
        openIssueDataTab(): Chainable<void>;
        openFirstIssue(): Chainable<void>;
        saveIssue(): Chainable<void>;
        uploadPlugin(pluginPath: string): Chainable<void>;
        enablePlugin(pluginName: string): Chainable<void>;
        searchAndVerifyPluginInSettings(pluginName?: string): Chainable<void>;
    }
}
