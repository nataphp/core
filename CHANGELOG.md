# Changelog

Notable changes to NataPHP core. Versions follow the git tags (`vX.Y.Z`).
This file starts at 1.1.5; earlier versions are described by their commits.

## Unreleased

### Fixes

- **Form lengths count characters, not bytes.** `Input::getValueOutput()` cut
  the value at `maxLength`/`length` with `substr()`: a cut inside an accented
  letter is invalid UTF-8, which `htmlspecialchars()` turns into `''`, so a
  field with accents could come back to the form empty (on an edit form, or
  after a validation error). It now uses `mb_substr()`. `length` validated with
  `strlen()` (now `mb_strlen()`), and the `length`, `minLength`, `maxLength`
  and `between` messages reported the byte count. `maxLength` can be used
  again instead of `'attrs' => ['maxlength' => …]` plus a check in the table.

### Added

- **`Form::allowFiles()`** (and the `allowFiles` config option). `isValid()`
  still refuses requests carrying `$_FILES` by default; with `allowFiles` on,
  a plain multipart form whose controller reads its own file inputs validates
  normally, without emptying `$_FILES` first. It also sets
  `enctype="multipart/form-data"`. AJAX forms are sent as JSON and cannot
  carry files: use it with `ajax(false)`.
- **`Nata\Cache\RateLimiter`**: sliding-window limiter over a cache
  configuration (`hit()`, `remaining()`, `reset()`), as copied until now
  between apps (appontece, acertev, sergiodinislopes.pt).
- **`Nata\Form\Honeypot`**: bot trap for public forms without session or
  cookie: a hidden field bots fill, plus a time token signed with
  `Security.salt` that refuses posts sent faster than `minSeconds`
  (`html()`, `fields()`, `isBot()`, `reason()`).

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
