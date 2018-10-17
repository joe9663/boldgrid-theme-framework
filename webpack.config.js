const webpack = require( 'webpack' );
const path = require( 'path' );
const src = path.resolve( __dirname, 'src' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

module.exports = {
	mode: process.env.NODE_ENV || 'production',
	devtool: 'development' === process.env.NODE_ENV ? 'source-map' : false,
	context: src,

	entry: {
		'assets/js/customizer/customizer': './assets/js/customizer/customizer.js',
		'assets/js/customizer/base-controls': './assets/js/customizer/base-controls.js',
		'assets/js/front-end': './assets/js/front-end.js'
	},

	output: {
		filename: './[name].min.js',
		path: path.resolve( __dirname, 'boldgrid-theme-framework' )
	},

	externals: {
		jquery: 'jQuery'
	},

	devServer: {
		contentBase: src,
		publicPath: '/',
		historyApiFallback: true,

		port: 4009,
		overlay: {
			errors: true,
			warnings: true
		}
	},

	module: {
		rules: [
			{
				test: /\.ejs$/,
				loader: 'ejs-loader'
			},
			{
				test: /\.html$/,
				use: [
					{
						loader: 'html-loader',
						options: {
							minimize: true
						}
					}
				]
			},
			{
				test: /\.svg$/,
				loader: 'svg-inline-loader'
			},
			{
				test: /\.s?[ac]ss$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',

					// 'postcss-loader',
					{
						loader: 'sass-loader',
						options: {
							includePaths: [
								path.resolve( __dirname, 'node_modules' )
							]
						}
					}
				]
			},
			{
				test: /\.js$/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [ '@babel/preset-env' ]
					}
				}
			},
			{
				test: /\.js$/,
				enforce: 'pre',
				exclude: /node_modules/,
				loader: 'eslint-loader',
				options: {
					emitWarning: true
				}
			}
		]
	},
	plugins: [
		new MiniCssExtractPlugin( {
			filename: './assets/css/base-controls-bundle.min.css'
		} ),

		new webpack.ProvidePlugin( {
			$: 'jquery',
			jQuery: 'jquery'
		} )
	]
};
