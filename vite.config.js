import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';

// Switch parts of the stack off for projects that don't need them. Bootstrap
// is trimmed separately: Sass components in resources/css/app.scss,
// JavaScript plugins in resources/js/bootstrap.js.
const features = {
  // React islands (resources/js/components). With this off, React is left out
  // of the build entirely and data-react elements keep their server-rendered
  // HTML. Editor blocks are unaffected: their JSX compiles against the
  // WordPress-bundled React either way (see wordpressPlugin below).
  react: true,
}

// Set APP_URL if it doesn't exist for Laravel Vite plugin
if (! process.env.APP_URL) {
  process.env.APP_URL = 'http://example.test';
}

export default defineConfig({
  base: './',
  plugins: [
    laravel({
      input: [
        'resources/css/app.scss',
        'resources/js/app.js',
        'resources/css/editor.scss',
        'resources/js/editor.js',
      ],
      refresh: true,
      assets: ['resources/images/**', 'resources/fonts/**'],
    }),

    // Fast Refresh for the islands in dev. JSX compilation is NOT set here:
    // wordpressPlugin below sets a global wp.element pragma so editor blocks
    // use the React that WordPress ships, and each island component opts back
    // into npm React with the @jsxRuntime pragma comment on its first line.
    // (include only scopes Fast Refresh — one JSX config is global in Vite 8.)
    features.react && react({
      include: /resources\/js\/.*\.jsx$/,
    }),

    wordpressPlugin(),

    // Copy theme.json into public/build/assets. Its palette is maintained by
    // hand to match Bootstrap's $theme-colors, since there is no Tailwind
    // theme left for the plugin to read.
    wordpressThemeJson({
      disableTailwindColors: true,
      disableTailwindFonts: true,
      disableTailwindFontSizes: true,
      disableTailwindBorderRadius: true,
    }),
  ],
  define: {
    __THEME_REACT__: JSON.stringify(features.react),
  },
  css: {
    preprocessorOptions: {
      scss: {
        // Bootstrap 5.3 still uses @import and Sass global functions, which
        // Dart Sass deprecates. Silence the noise from Bootstrap's own files
        // without hiding deprecations in ours.
        quietDeps: true,
        silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
      },
    },
  },
  resolve: {
    alias: {
      '@scripts': '/resources/js',
      '@styles': '/resources/css',
      '@fonts': '/resources/fonts',
      '@images': '/resources/images',
    },
  },
})
