const mix = require('laravel-mix');
const webpack = require('webpack');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .copyDirectory('node_modules/bootstrap-icons/font/fonts', 'public/fonts')
    .sourceMaps()
    .webpackConfig({
        plugins: [
            new webpack.DefinePlugin({
                'process.env.MIX_VITE_REVERB_APP_KEY': JSON.stringify(process.env.VITE_REVERB_APP_KEY),
                'process.env.MIX_VITE_REVERB_HOST': JSON.stringify(process.env.VITE_REVERB_HOST),
                'process.env.MIX_VITE_REVERB_PORT': JSON.stringify(process.env.VITE_REVERB_PORT),
                'process.env.MIX_VITE_REVERB_SCHEME': JSON.stringify(process.env.VITE_REVERB_SCHEME),
            })
        ]
    });
