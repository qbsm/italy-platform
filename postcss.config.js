module.exports = {
  plugins: [
    require('postcss-import')({
      path: ['node_modules', 'assets/css']
    }),
    require('postcss-preset-env')({
      stage: 2,
      features: {
        'custom-media-queries': true,
        'nesting-rules': true,
        'custom-properties': { preserve: true },
        'container-queries': true
      }
    }),
    require('postcss-url')({
      url: 'rebase'
    }),
    require('autoprefixer'),
  ],
};
