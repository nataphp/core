# Changelog

Notable changes to NataPHP core. Versions follow the git tags (`vX.Y.Z`).
This file starts at 1.1.5; earlier versions are described by their commits.

## 1.1.5 (2026-11-20)

### Security

- **Database passwords no longer show when printed.** A deprecation notice in
  `Nata\Database\Connection` (`str_replace()` on a null `App.encoding`), with
  Xdebug collecting parameters, printed the whole connection configuration,
  password included, in the stack trace. Now:
  - the password is held by `Nata\Database\Password`, which prints as an empty
    object in `var_dump()`, `print_r()`, `var_export()`, `json_encode()` and
    Xdebug, and refuses `serialize()`; only `reveal()` gives the value;
  - it reaches the Doctrine driver only at connect time, through a DBAL
    middleware (`Nata\Database\Driver\PasswordMiddleware`), so the Doctrine
    connection never holds it;
  - the configuration parameters of `Connection::__construct()`,
    `ConnectionManager::config()` and `ConnectionManager::create()` are
    `#[\SensitiveParameter]`: PHP and Xdebug show `[Sensitive Parameter]` in
    stack traces;
  - the deprecation notice itself is fixed.

  Rotate any database password that may have been printed by an earlier
  version (logs, error pages, terminal output).

### Behaviour changes

- **`getDoctrineConnection()->getParams()` no longer contains `password`.**
  Code that read the password from there (for example to run `mysqldump` or
  the `mysql` client) must read it from the connection configuration instead:

  ```php
  $settings = ConnectionManager::config($name);
  $password = $settings['password'] instanceof Password ? $settings['password']->reveal() : (string)($settings['password'] ?? '');
  ```

  `ConnectionManager::config()` still returns the password as it was given.
- **`ORM\Behavior\ChangesHistory` was removed** (unused; `VersionedHistory`
  remains). Applications that still attach it must drop it before updating.
