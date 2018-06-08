let mix = require('laravel-mix');

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

// mix.js('resources/assets/js/app.js', 'public/js')
//    .sass('resources/assets/sass/app.scss', 'public/css');

mix.babel([
  'resources/assets/js/header/*',
  'resources/assets/js/base/*',
  'resources/assets/js/supply/*js',
  'resources/assets/js/supply/find/*',
  'resources/assets/js/footer/*',
], 'public/-/find.js');

mix.babel([
  'resources/assets/js/header/*',
  'resources/assets/js/base/*',
  'resources/assets/js/supply/*js',
  'resources/assets/js/supply/test/sha256.js',
  'resources/assets/js/supply/test/test.js',
  'resources/assets/js/footer/*',
], 'public/-/test.js');

mix.babel([
  'resources/assets/js/header/*',
  'resources/assets/js/base/*',
  'resources/assets/js/demand/view.js',
  'resources/assets/js/footer/*',
], 'public/-/view.js');
