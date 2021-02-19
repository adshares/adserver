/*
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

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

// mix.js('resources/js/app.js', 'public/js')
//    .sass('resources/sass/app.scss', 'public/css');

mix.babel([
  'resources/js/header/*',
  'resources/js/base/*',
  'resources/js/supply/pops/*',
  'resources/js/supply/inv.js',
  'resources/js/supply/sha1.js',
  'resources/js/supply/find/*',
  'resources/js/footer/*',
], 'public/-/find.js');

mix.babel([
  'resources/js/header/*',
  'resources/js/base/*',
  'resources/js/supply/*js',
  'resources/js/supply/test/sha256.js',
  'resources/js/supply/test/test.js',
  'resources/js/footer/*',
], 'public/-/test.js');

mix.babel([
  'resources/js/header/*',
  'resources/js/base/*',
  'resources/js/demand/view.js',
  'resources/js/footer/*',
], 'public/-/view.js');

mix.babel([
    'resources/js/header/*',
    'resources/js/base/compat.js',
    'resources/js/base/domready.js',
    'resources/js/demand/banner.js',
    'resources/js/footer/*',
], 'public/-/banner.js');

mix.babel([
    'resources/js/supply/main/*',
], 'public/-/main.js');


mix.copyDirectory('resources/img', 'public/img');
