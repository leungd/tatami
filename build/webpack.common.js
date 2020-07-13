const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const webpack = require('webpack');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const config = require('./config');
const path = require('path');

module.exports = {
  entry: {
    app: ['./src/js/main.js', './src/sass/main.scss'],
  },

  watchOptions: {
    ignored: /node_modules/,
  },

  devtool: false,

  resolve: {
    alias: {
      images: path.join(__dirname, '../src/images'),
    },
  },

  plugins: [
    new webpack.ProgressPlugin(),
    new CleanWebpackPlugin(),
    new BrowserSyncPlugin(
      {
        host: 'localhost',
        port: 3000,
        proxy: config.devUrl, // YOUR DEV-SERVER URL
        files: [
          './*.php',
          './resources/views/**/*.twig',
          './static/css/*.*',
          './static/js/*.*',
        ],
      },
      {
        reload: false,
        injectCss: true,
      }
    ),
  ],
};
