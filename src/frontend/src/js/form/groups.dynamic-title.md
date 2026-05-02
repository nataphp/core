# Group Row Dynamic Title

The dynamic title updates a group row's header in real-time as the user fills in fields.
It is driven entirely client-side by `dynamicTitle` in `groups.js`.

---

## PHP setup

Set the group `title` config to a pattern containing `{fieldName}` tokens.
Any title with a `{` triggers pattern mode; the initial render falls back to `defaultTitle`
and the JS replaces it once the page loads.

```php
$form->addGroup('amenities', [
    'title'        => '{id} - {_joinData.note|append:" - "}',
    'defaultTitle' => __('New amenity'),
    // ...
]);
```

---

## Pattern syntax

```
{fieldName}
{fieldName|option:value}
{fieldName|option1:value1|option2:value2}
```

Multiple tokens can be combined in one string:

```
{id} — {note|append:" · "}({category})
```

---

## Field name formats

| Format | Selector generated | Example match |
|---|---|---|
| `{name}` | `[name$="[name]"]` | `amenities[0][name]` |
| `{address.city}` | `[name$="[address][city]"]` | `venue[0][address][city]` |
| `{_joinData.note}` | `[name$="[_joinData][note]"]` | `amenities[0][_joinData][note]` |

---

## Special placeholders

| Token | Description |
|---|---|
| `{_count}` | Current row index. 0-based by default, see `start` option. |

---

## Options

Options are pipe-delimited after the field name: `{name|option:value}`.

### `separator` — multi-value join string

Used when a field can return multiple values (multi-select, checkbox group).
Defaults to `', '`.

```
{tags|separator: / }          →  "Rock / Pop / Jazz"
{amenities|separator: · }     →  "WiFi · Pool · Gym"
```

### `append` — text appended after the value

Applied **only when the value is non-empty**, so empty fields leave no trailing text.

```
{note|append:" - "}           →  "Near entrance - "   (when note = "Near entrance")
                               →  ""                   (when note is empty)
```

### `prepend` — text prepended before the value

Applied **only when the value is non-empty**.

```
{name|prepend:"★ "}           →  "★ Main Hall"
```

### `scope` — restrict lookup to a nested group

Restricts the field search to a child `[data-group]` element inside the current row.
Useful for referencing fields that live inside a nested repeatable group.

```
{city|scope:addresses}
```

Generates selector `[data-group="addresses"] [name$="[city]"]` inside the current row.

### ~~`group`~~ *(deprecated — use `scope`)*

Alias for `scope`, kept for backwards compatibility. Prefer `scope` in new patterns.
`scope` takes precedence if both are present.

### `offset` — starting offset for `{_count}`

Sets the number added to the zero-based row index.

| `offset` | Row 0 | Row 1 | Row 2 |
|---|---|---|---|
| *(omitted)* | `0` | `1` | `2` |
| `offset:1` | `1` | `2` | `3` |
| `offset:10` | `10` | `11` | `12` |

```
{_count|offset:1}               →  "1", "2", "3" …
{_count|offset:1|prepend:"# "}  →  "# 1", "# 2", "# 3" …
```

### ~~`start`~~ *(deprecated — use `offset`)*

Alias for `offset`, kept for backwards compatibility. Prefer `offset` in new patterns.
`offset` takes precedence if both are present.

---

## Escaping `|` in values

Use `:pipe:` to represent a literal `|` inside an option value:

```
{tags|separator::pipe:}       →  "Rock|Pop|Jazz"
```

---

## Combining options

```
{id} - {_joinData.note|append:" - "}
```
- If `id` = "WiFi" and `note` = "Near entrance":  `WiFi - Near entrance - `
- If `id` = "WiFi" and `note` is empty:           `WiFi - `

```
{_count|offset:1|prepend:"Floor "}: {name|append:" ·"}{category|prepend:" ("|append:")"}
```
- Row 0, name "Ballroom", category "Events":  `Floor 1: Ballroom · (Events)`
- Row 0, name "Ballroom", category empty:     `Floor 1: Ballroom ·`

---

## Fallback behaviour

If every placeholder in the pattern resolves to an empty string, the row header
displays the `defaultTitle` configured in PHP (e.g. `"New amenity"`).
