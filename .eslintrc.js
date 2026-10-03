/* eslint-env node */
module.exports = {
    root: true,
    env: {
        node: true,
        es2022: true
    },
    parserOptions: {
        ecmaVersion: "latest",
        sourceType: "module"
    },
    ignorePatterns: ["node_modules/", "dist/", ".pkp-reference/"],
    overrides: [
        {
            files: ["cypress/**/*.js"],
            env: {
                browser: true,
                mocha: true
            },
            globals: {
                cy: "readonly",
                Cypress: "readonly"
            }
        }
    ]
};
