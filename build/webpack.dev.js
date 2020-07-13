const path = require('path');
const common = require('./webpack.common');
const merge = require('webpack-merge');
const ManifestPlugin = require('webpack-manifest-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const config = require('./config');

const images = [
  {
    loader: 'file-loader',
    options: {
      name: '[name].[ext]',
      outputPath: 'images/',
      publicPath: `${config.assetsPath}static/images/`,
    },
  },
];

module.exports = merge(common, {
  mode: 'development',
  output: {
    path: path.resolve(__dirname, '../static'),
    filename: 'js/[name].bundle.js',
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        exclude: /node_modules/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true,
            },
          },
        ],
      },
      {
        test: /\.(png|jpe?g|gif|svg)$/,
        use: images,
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: `css/[name].css`,
      sourceMap: true,
    }),
    new ManifestPlugin(),
  ],
});
