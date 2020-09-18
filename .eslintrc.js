module.exports = {
  extends: [
    '@ridi/eslint-config',
    '@ridi/eslint-config/typescript',
  ],
  parser: '@typescript-eslint/parser',
  settings: {
    "import/resolver": {
      "typescript": {}
    }
  }
}
