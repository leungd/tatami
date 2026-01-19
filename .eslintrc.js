module.exports = {
  extends: [
    "airbnb",
    "plugin:import/errors",
    "plugin:import/warnings",
    "prettier",
  ],
  env: {
    browser: true,
    es6: true,
  },
  rules: {
    "prettier/prettier": ["error"],
  },
  parserOptions: {
    parser: "babel-eslint",
  },
};
