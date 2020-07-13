const path = require('path');
const common = require('./webpack.common');
const merge = require('webpack-merge');
const ManifestPlugin = require('webpack-manifest-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const OptimizeCssAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
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
  {
    loader: 'image-webpack-loader',
    options: {
      mozjpeg: {
        progressive: true,
        quality: 65,
      },
      // optipng.enabled: false will disable optipng
      optipng: {
        enabled: false,
      },
      pngquant: {
        quality: [0.65, 0.9],
        speed: 4,
      },
      gifsicle: {
        interlaced: false,
      },
      // the webp option will enable WEBP
      webp: {
        quality: 75,
      },
    },
  },
];

module.exports = merge(common, {
  mode: 'production',
  output: {
    path: path.resolve(__dirname, '../static'),
    filename: `js/[name].[contentHash].bundle.js`,
  },
  optimization: {
    minimizer: [new OptimizeCssAssetsPlugin(), new TerserPlugin()],
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
      filename: `css/[name].[chunkHash].css`,
      sourceMap: true,
    }),
    new ManifestPlugin(),
  ],
});
