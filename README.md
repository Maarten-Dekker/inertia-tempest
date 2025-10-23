# Inertia.js Tempest Adapter

A feature-complete Inertia.js adapter for the [Tempest](https://tempestphp.com) framework.  
Mirrors the official [Inertia.js Laravel Adapter](https://github.com/inertiajs/inertia-laravel).

## Server-side setup

### Install dependencies

Install the Inertia server-side adapter using the Composer package manager.

```bash
composer require maartendekker/inertia-tempest
```

### Root template

Create a root view template `inertia.view.php` in your `app` directory. The adapter will automatically look for it.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title inertia>Inertia Tempest</title>

    <x-vite-tags entrypoint="app/inertia.entrypoint.ts"/>
</head>
<body>
    <?= $this->inertia() ?>
</body>
</html>
```

That's it, you're all ready to start creating Inertia pages. You can use either the globally available `inertia()` helper function or the static `Inertia` facade:

```php
use Inertia\Response;

final readonly class AircraftController
{
    #[Get('/aircraft/{aircraft}')]
    public function show(Aircraft $aircraft): Response
    {
        return inertia('Aircraft/Show', [ /* … */ ]);
    }
}
```

```php
use Inertia\Inertia;
use Inertia\Response;

final readonly class AircraftController
{
    #[Get('/aircraft/{aircraft}')]
    public function show(Aircraft $aircraft): Response
    {
        return Inertia::render('Aircraft/Show', [ /* … */ ]);
    }
}
```

## Client-side setup

### Install dependencies

Install the Inertia client-side adapter corresponding to your framework of choice.

```bash
npm install @inertiajs/vue3
```

<details>
<summary>React</summary>

```bash
npm install @inertiajs/react
```
</details>

<details>
<summary>Svelte</summary>

```bash
npm install @inertiajs/svelte
```
</details>

### Initialize the Inertia app

Update your main JavaScript file to boot your Inertia app.

```js
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h } from 'vue'

void createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./**/*.vue')
        return pages[`./${name}.vue`]()
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

<details>
<summary>React</summary>

```js
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'

void createInertiaApp({
  resolve: name => {
      const pages = import.meta.glob('./**/*.jsx')
      return pages[`./${name}.jsx`]()
  },
  setup({ el, App, props }) {
      createRoot(el).render(<App {...props} />)
  },
})
```
</details>

<details>
<summary>Svelte</summary>

```js
import { createInertiaApp } from '@inertiajs/svelte'
import { mount } from 'svelte'

void createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./**/*.svelte')
        return pages[`./${name}.svelte`]()
    },
    setup({ el, App, props }) {
        mount(App, { target: el, props })
    },
})
```
</details>

The adapter will automatically expose these errors under the errors prop on the client side.

## Shared Data

The `Inertia\Middleware\Middleware` is the perfect place to define props that should be available on every page. This
middleware provides a `version()` method for setting your asset version, as well as a `share()` method for defining
shared data. To add your own shared data, you can extend the base middleware:

```php
use Inertia\Middleware\Middleware;
use Tempest\Http\Request;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
```

## Optional Configuration

This package works out-of-the-box with sensible defaults. To customize the settings, you can create an
`inertia.config.php` file in your config directory. Because the configuration uses typed objects, your IDE will provide
excellent autocompletion. You only need to specify the options you wish to change.

```php
use Inertia\Configs\InertiaConfig;
use Inertia\Configs\PageConfig;

use function Tempest\env;

return new InertiaConfig(
    // Enforce that page components exist on disk.
    pages: new PageConfig(
        ensure_pages_exists: env('INERTIA_ENSURE_PAGES_EXIST', false),
    ),

    // Enable Laravel's paginator format on the front-end.
    transform_pagination: true
);
```

## Server-Side Rendering (SSR)

To enable SSR, you'll need to configure your front-end build process to generate a server-side bundle.

### 1. Update Vite Configuration

Modify your `vite.config.js` to handle both client and server builds. The `ssrBuild` flag provided by Vite allows you to
conditionally change the configuration.

```diff
  import tailwindcss from '@tailwindcss/vite'
- import { defineConfig } from 'vite'
+ import { defineConfig, type ConfigEnv } from 'vite'
  import tempest from 'vite-plugin-tempest'
  import vue from '@vitejs/plugin-vue';

- export default defineConfig({
+ export default defineConfig((ConfigEnv) => {
+     const isSsrBuild = configEnv.ssrBuild === true;
+
+     return {
          plugins: [
              tailwindcss(),
              tempest(),
              vue(),
          ],
+         build: {
+             outDir: isSsrBuild ? 'ssr' : 'public/build',
+             manifest: !isSsrBuild ? 'manifest.json' : false,
+             rollupOptions: {
+                 input: isSsrBuild ? 'app/inertia.ssr.ts' : 'app/inertia.entrypoint.ts',
+             },
+         },
+         ssr: {
+             // Add any packages that should be bundled with your SSR build.
+             noExternal: ['@inertiajs/vue3'],
+         },
+     };
  });
```

### 2. Create an SSR Entry Point

Create a new file, `app/inertia.ssr.js`, that will serve as the entry point for your Node.js server. This
file is responsible for creating the SSR server. Unlike client-side entrypoints, this file should not include `.entrypoint.` in
its name. Tempest automatically discovers those for the browser, and this file is meant to stay server-side.

```diff
- import { createApp, h } from 'vue'
+ import { createSSRApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

void createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./**/*.vue')
        return pages[`./${name}.vue`]()
    },
    setup({ el, App, props, plugin }) {
-       createApp({ render: () => h(App, props) })
+       createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

<details>
<summary>React</summary>

```diff
import { createInertiaApp } from '@inertiajs/react'
- import { createRoot } from 'react-dom/client'
+ import { hydrateRoot } from 'react-dom/client'

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./**/*.jsx')
        return pages[`./${name}.jsx`]()
    },
    setup({ el, App, props }) {
-       createRoot(el).render(<App {...props} />)
+       hydrateRoot(el, <App {...props} />)
    },
})
```
</details>

<details>
<summary>Svelte</summary>

```diff
import { createInertiaApp } from '@inertiajs/svelte'
- import { mount } from 'svelte'
+ import { hydrate, mount } from 'svelte'

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./**/*.svelte')
        return pages[`./${name}.svelte`]()
    },
    setup({ el, App, props }) {
-       mount(App, { target: el, props })
+       if (el.dataset.serverRendered === 'true') {
+           hydrate(App, { target: el, props })
+       } else {
+           mount(App, { target: el, props })
+      }
    },
})
```
</details>

### 3. Update Build Script

Add a build script to your `package.json` that builds both the client and server assets.

```diff
"scripts": {
    "build": "vite build"
+   "build:ssr": "vite build && vite build --ssr"
},
```

### 4. Update Root View

Modify your `inertia.view.php` template to include the `inertiaHead()` helper. This will inject any SSR-generated
content.

```diff
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title inertia>Inertia Tempest</title>
    
    <x-vite-tags entrypoint="app/inertia.entrypoint.ts"/>
+   <?= $this->inertiaHead() ?>
</head>
<body>
    <?= $this->inertia() ?>
</body>
</html>
```

### 5. Enable SSR

Finally, enable SSR in your `inertia.config.php` file. The package will automatically discover the `ssr/inertia.ssr.mjs`
or `ssr/inertia.ssr.js` bundle. If your bundle is located elsewhere, you must specify the path.

```php
use Inertia\Configs\InertiaConfig;
use Inertia\Configs\SsrConfig;
use function Tempest\root_path;

return new InertiaConfig(
    ssr: new SsrConfig(
        enabled: true,
        // bundle: root_path('custom/path/ssr.js'),
    ),
);
```

### 6. Run the Server

With everything configured, you can now start the SSR server:

```bash
./tempest inertia:start-ssr
```

For more details, please refer to the
official [Inertia.js SSR documentation](https://tempestphp.com/docs/extra-topics/contributing).

## Contributing

Contributions are welcome. For consistency, follow the patterns and style
of [tempestphp/framework](https://tempestphp.com/docs/extra-topics/contributing).

Pull requests should aim for feature parity with [inertia-laravel](https://github.com/inertiajs/inertia-laravel).
