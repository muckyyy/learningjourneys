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
    .options({
        processCssUrls: false // Keep disabled for consistency
    })
    .webpackConfig({
        plugins: [
            new webpack.DefinePlugin({
                // Map the process.env variables that bootstrap.js expects
                'process.env.MIX_VITE_REVERB_APP_KEY': JSON.stringify(process.env.VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj'),
                'process.env.MIX_VITE_REVERB_HOST': JSON.stringify(process.env.VITE_REVERB_HOST || 'the-thinking-course.com'),
                'process.env.MIX_VITE_REVERB_PORT': JSON.stringify(process.env.VITE_REVERB_PORT || '443'),
                'process.env.MIX_VITE_REVERB_SCHEME': JSON.stringify(process.env.VITE_REVERB_SCHEME || 'https'),
            })
        ]
    });

// Add versioning for production, but not for development to avoid cache issues during dev
if (mix.inProduction()) {
    mix.version(); // Only add cache busting in production
} else {
    mix.sourceMaps(); // Only add source maps in development
}
