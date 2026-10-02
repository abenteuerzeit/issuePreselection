import { defineConfig } from "cypress";
import failedLog from "cypress-failed-log/on.js";
import { readdir, unlink } from "node:fs/promises";
import path from "node:path";

export default defineConfig({
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 10000,
    video: false,
    screenshotOnRunFailure: true,
    chromeWebSecurity: false,
    viewportWidth: 1080,
    viewportHeight: 617,

    env: {
        pluginName: "issuePreselection",
        contextPath: "publicknowledge"
    },

    e2e: {
        baseUrl: "http://localhost",
        specPattern: "cypress/tests/**/*.cy.{js,jsx,ts,tsx}",
        setupNodeEvents(on, config) {
            failedLog(on);
            on("before:run", async () => {
                const logsDirectory = path.join(config.projectRoot, "cypress", "logs");
                let entries;
                try {
                    entries = await readdir(logsDirectory, { withFileTypes: true });
                } catch (error) {
                    if (error.code === "ENOENT") {
                        return;
                    }
                    throw error;
                }

                await Promise.all(
                    entries
                        .filter(
                            (entry) =>
                                entry.isFile() &&
                                entry.name.startsWith("failed-cypress-tests-") &&
                                entry.name.endsWith(".json")
                        )
                        .map((entry) => unlink(path.join(logsDirectory, entry.name)))
                );
            });
            return config;
        }
    }
});
