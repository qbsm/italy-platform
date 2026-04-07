export default {
  ignoreFiles: ['assets/css/build/**', 'node_modules/**'],
  extends: ['stylelint-config-standard'],
  rules: {
    'import-notation': null,
    'selector-class-pattern': null,
    'no-descending-specificity': null,
    'block-no-empty': null,
    'no-duplicate-selectors': null,
    'declaration-property-value-no-unknown': null,
    'declaration-block-no-duplicate-properties': null,
    'declaration-block-no-shorthand-property-overrides': null,
    'declaration-block-no-redundant-longhand-properties': null,
    'keyframes-name-pattern': null,
    'no-empty-source': null,
  },
};
