# tool_zoomapi - Zoom API Library for Moodle

A reusable admin tool plugin that provides a shared Zoom API integration layer for other Moodle plugins.

## Features

- OAuth 2.0 Server-to-Server authentication with Zoom (Account ID / Client ID / Client Secret)
- Automatic token caching and refresh
- Rate limit handling with automatic retries (up to 5 retries with backoff)
- Support for both Zoom global API (`zoom.us`) and Zoom for Government (`zoomgov.com`)
- User lookup by email or Zoom ID with application-level caching
- Miss-cache for known non-Zoom users to avoid redundant API calls
- Paginated API call support (`next_page_token` / `page_number`)
- Persistent user mapping table (Moodle user ↔ Zoom user)
- Behat testing support with mock API fixtures

## Requirements

- Moodle 4.5+ (requires version 2024100700 or later)
- PHP 8.1+
- A Zoom Marketplace Server-to-Server OAuth app

## Installation

1. Place the plugin files in `admin/tool/zoomapi/`
2. Navigate to **Site administration > Notifications** to complete the installation
3. Configure the plugin credentials (see Configuration)

## Configuration

Navigate to **Site administration > Plugins > Admin tools > Zoom API** and provide:

| Setting | Description |
|---------|-------------|
| Account ID | Account ID from your Zoom Marketplace App |
| Client ID | Client ID from your Zoom Marketplace App |
| Client Secret | Client Secret from your Zoom Marketplace App (stored encrypted) |
| Zoom API | Choose the API endpoint: Global (default) or Zoom for Government |

## Usage for Developers

### Getting the API Instance

The API class is registered as a singleton in Moodle's dependency injection container:

```php
use tool_zoomapi\api;

$api = api::instance();
```

### Getting a User by Email or Zoom ID

```php
use tool_zoomapi\helper;

$identifier = helper::get_api_identifier($user);
$zoomuser = helper::get_user($identifier);

if ($zoomuser !== false) {
    echo $zoomuser['id'];
    echo $zoomuser['email'];
}
```

Results are cached in the `users` application cache. Users who are not in Zoom are cached in the `unresolved` cache (1 hour TTL) to avoid repeated API calls.

### Getting the Current User's Zoom ID

```php
use tool_zoomapi\helper;

// Throws exception if user not found.
$zoomuserid = helper::get_userid();

// Returns null if user not found (no exception).
$zoomuserid = helper::get_userid_optional();
```

### Forward Lookup: Moodle User → Zoom User ID

```php
use tool_zoomapi\helper;

// Resolves by email via Zoom API, persists the mapping for future use.
$zoomuserid = helper::get_zoom_userid($moodleuser);
```

### Reverse Lookup: Zoom User ID → Moodle User ID

```php
use tool_zoomapi\helper;

// Resolves via Zoom API email lookup, persists the mapping for future use.
$moodleuserid = helper::get_moodle_userid($zoomid);
```

### Direct API Class Usage

```php
use tool_zoomapi\api;

$user = api::instance()->get_user('user@example.com');
```

### Checking Scopes

```php
if ($api->has_scope(['user:read:admin', 'user:read:user:admin'])) {
    // Proceed with API call.
}
```

## Caching

The plugin defines three application caches:

| Cache | Mode | Purpose |
|-------|------|---------|
| `token` | Application, simple keys & data | Stores OAuth access tokens and API base URLs |
| `users` | Application, simple data | Stores Zoom user data keyed by ID and email |
| `unresolved` | Application, simple data, 1h TTL | Caches known non-Zoom users to avoid repeated API lookups |

## User Mapping Table

A `tool_zoomapi_user_mappings` database table persists the relationship between Moodle users and Zoom users. Mappings are populated lazily — whenever a user is resolved through the Zoom API (by email or ID), the result is stored for future reference. The table is write-only: all lookups resolve via the Zoom API as the authoritative source.

## Privacy

The plugin stores Moodle-to-Zoom user mappings in the `tool_zoomapi_user_mappings` table. This personal data can be exported or deleted through Moodle's standard privacy API. The plugin implements `metadata_provider`, `plugin_provider`, and `core_userlist_provider` for full data subject request support.

## License

This plugin is part of Moodle and is licensed under the [GNU General Public License v3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

## Author

- **Jonathan Champ** (2026)
