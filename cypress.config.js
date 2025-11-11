const { defineConfig } = require("cypress");

module.exports = defineConfig({
  pluginName: "issuePreselection",
  defaultCommandTimeout: 10000,
  requestTimeout: 10000,
  responseTimeout: 10000,
  video: false,
  screenshotOnRunFailure: true,
  chromeWebSecurity: false,

  env: {
    contextPath: "publicknowledge",
    defaultCommandTimeout: 10000,
  },

  e2e: {
    baseUrl: "https://localhost:8443",
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
