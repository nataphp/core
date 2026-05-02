# Nata Routing

How to define and use routes with the Nata Router: basic connects, scopes, prefixes, and plugin routes.

---

## Basic usage

### Connecting a route

```php
use Nata\Routing\Router;

// Static route with fixed controller/action
Router::connect('/about', ['controller' => 'pages', 'action' => 'about']);

// Dynamic :controller and :action (default action is 'index')
Router::connect('/:controller', ['action' => 'index']);
Router::connect('/:controller/:action/*');
```

The third parameter is **options**: patterns for named segments, `pass`, `routeClass`, etc.

```php
// Constrain :id to digits and pass it as an argument to the action
Router::connect('/posts/:id', ['controller' => 'posts', 'action' => 'view'], [
    'id'   => '[0-9]+',
    'pass' => ['id'],
]);

// Named route for URL generation
Router::connect('/users/:id', ['controller' => 'users', 'action' => 'show'], [
    'id'   => '[0-9]+',
    'name' => 'users.show',
]);
```

**Special option keys:**

- **`pass`** – List of parameter names to move into the "pass" (passed arguments) array.
- **`routeClass`** – Custom route class (e.g. `PluginShort`, or your own).
- **`name`** – Name for reverse routing.
- **`[method]`** – Restrict to HTTP method(s), e.g. `'[method]' => 'GET'`.
- **`[host]`** / **`[server]`** – Restrict by host or server name.

---

## Scopes

Group routes under a common path and/or default parameters with `Router::scope()`.

### Path prefix and default params

```php
Router::scope('/admin', ['prefix' => 'admin'], function () {
    Router::connect('/', ['controller' => 'dashboard', 'action' => 'index']);
    Router::connect('/users', ['controller' => 'users', 'action' => 'index']);
    Router::connect('/users/:action/*', ['controller' => 'users']);
});
```

- **Path:** All routes get the path prefix `/admin` (e.g. `/admin`, `/admin/users`).
- **Params:** All routes in the scope get `prefix => 'admin'` in their defaults (you may also set `admin => true` for the dispatcher), so the dispatcher uses `App\Controller\Admin\*` controllers.

You can nest scopes; path and params are merged (inner scope wins on conflict).

```php
Router::scope('/admin', ['prefix' => 'admin'], function () {
    Router::scope('/settings', ['controller' => 'settings'], function () {
        Router::connect('/', ['action' => 'index']);
        Router::connect('/profile', ['action' => 'profile']);
    });
});
// Routes: /admin/settings, /admin/settings/profile
```

### Domain (host) scope

Scope by host so routes only match on that host:

```php
Router::scope('api.example.com', ['prefix' => 'api'], function () {
    Router::connect('/users', ['controller' => 'users', 'action' => 'index']);
});
// Matches api.example.com/users only
```

Domain + path:

```php
Router::scope('api.example.com/v1', ['version' => 1], function () {
    Router::connect('/search', ['controller' => 'search', 'action' => 'index']);
});
// Matches api.example.com/v1/search
```

### Disabling the prefix inside a scope

When you're under a path like `/admin` (prefix `admin`), every route normally gets `prefix => 'admin'` and the dispatcher looks for `App\Controller\Admin\*`. For **plugin** routes you want the controller to come from the **plugin**, not from `App\Controller\Admin`. So you need to **turn off the prefix** for that nested scope.

Use **`'prefix' => false`** in the scope params:

```php
Router::scope('/admin', ['prefix' => 'admin'], function () {
    // App controllers: App\Controller\Admin\Dashboard, etc.
    Router::connect('/', ['controller' => 'dashboard', 'action' => 'index']);
    Router::connect('/:controller/:action/*');

    // Plugin routes: do NOT use Admin namespace for the controller;
    // use the plugin's Controller (e.g. Geography\Controller\Admin\*).
    Router::scope('/:plugin', ['prefix' => false], function () {
        Router::scope('/:controller', function () {
            Router::connect('/', ['controller' => ':controller', 'action' => 'index']);
        });
        Router::scope('/:controller', function () {
            Router::scope('/:action', function () {
                Router::connect('/', ['controller' => ':controller', 'action' => ':action']);
            });
        });
    });
});
```

So:

- Routes in the outer scope keep `prefix => 'admin'` → `App\Controller\Admin\*`.
- Routes in the inner `/:plugin` scope get **`prefix => false`** from the scope, so the dispatcher uses the **plugin** namespace (e.g. `Geography\Controller\Admin\Languages`) instead of `App\Controller\Admin\*`.

The comment in the Router code describes it as: *"Allow connect() or inner scope to disable prefix (e.g. plugin routes under /pro using plugin's Controller, not Pro sub-namespace)."*

---

## Plugin routes and the `:plugin` parameter

The Route class treats **`:plugin`** as a reserved name: when the template contains `:plugin` and you don't pass an explicit `plugin` option, the segment is constrained to **loaded plugin names** (hyphenated). So URLs like `/admin/profile/123` won't match a `/:plugin/...` route when "profile" is not a plugin; they fall through to normal `/:controller/...` routes instead.

You don't need to pass a plugin pattern in app config; the Route class prepares and caches it from `Plugin::loaded()`.

### Example: admin plugin routes

```php
Router::scope('/admin', ['prefix' => 'admin'], function () {
    // Normal app admin routes
    Router::scope('/', function () {
        Router::connect('/', ['controller' => 'dashboard', 'action' => 'index']);
        Router::connect('/:controller', ['controller' => ':controller', 'action' => 'index']);
        Router::connect('/:controller/:action', ['controller' => ':controller', 'action' => ':action']);
    });

    // Plugin routes: first segment must be a loaded plugin name; prefix disabled
    Router::scope('/:plugin', ['prefix' => false], function () {
        Router::scope('/:controller', function () {
            Router::connect('/', ['controller' => ':controller', 'action' => 'index']);
        });
        Router::scope('/:controller', function () {
            Router::scope('/:action', function () {
                Router::connect('/', ['controller' => ':controller', 'action' => ':action']);
            });
        });
    });
});
```

- `/admin/geography/languages` → plugin `Geography`, controller `Languages`, action `index` (if "geography" is a loaded plugin).
- `/admin/profile/45269/name` → does **not** match the plugin scope (profile is not a plugin); it matches the normal `/:controller/:action` routes as controller `profile`, etc.

### Overriding the plugin pattern

If you need a custom pattern for `:plugin` (e.g. for tests or a custom list), pass it in the connect options; it takes precedence over the built-in one:

```php
Router::connect('/:plugin/help', ['controller' => 'help', 'action' => 'index'], [
    'plugin' => 'my-plugin|another',
]);
```

---

## Reserved parameter names

- **`:controller`** – Dispatcher maps this to a controller class (with prefix/plugin if set).
- **`:action`** – Dispatcher uses this as the action method name.
- **`:plugin`** – Constrained to loaded plugin names (unless you override with the `plugin` option). Used so plugin routes don't capture arbitrary segments like "profile" or "users".

You don't add logic in app config for these; the Router and Route class handle their meaning.

---

## Greedy routes and pass arguments

Use `/*` so the route accepts extra path segments; they are parsed into the `pass` array for the action:

```php
Router::connect('/:controller/:action/*');
// /posts/view/1/some-slug → pass = [1, 'some-slug']
```

Use the **`pass`** option to map specific named segments into pass:

```php
Router::connect('/posts/:id/:slug', ['controller' => 'posts', 'action' => 'view'], [
    'id'   => '[0-9]+',
    'pass' => ['id', 'slug'],
]);
```

---

## Summary

| Need | Use |
|------|-----|
| Static URL | `Router::connect('/path', ['controller' => 'x', 'action' => 'y'])` |
| Dynamic controller/action | `Router::connect('/:controller/:action/*')` |
| Group by path + default params | `Router::scope('/admin', ['prefix' => 'admin'], function () { ... })` |
| Restrict by host | `Router::scope('api.example.com', ...)` |
| Plugin routes under a prefix | `Router::scope('/:plugin', ['prefix' => false], ...)` so the plugin's controllers are used, not `App\Controller\Admin\*` |
| Constrain a segment | Pass in options: `['id' => '[0-9]+']` |
| `:plugin` only for real plugins | Use `:plugin` in the template; no extra config needed |
