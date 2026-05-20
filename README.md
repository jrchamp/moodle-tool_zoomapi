# tool_zoomapi - Zoom API Library for Moodle

A reusable admin tool plugin that provides a shared Zoom API integration layer for other Moodle plugins.

## Features

- OAuth 2.0 Server-to-Server authentication with Zoom
- Automatic token caching and refresh
- Rate limit handling with automatic retries (up to 5 retries)
- Support for both Zoom global API (`zoom.us`) and Zoom for Government (`zoomgov.com`)
- User lookup by email or Zoom ID
- Paginated API call support
- Application-level caching for tokens and user data
- Behat testing support with mock API fixtures

## Requirements

- Moodle 4.4+ (requires version 2024042200 or later)
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

```php
use tool_zoomapi\helper;

$api = helper::api();
```

### Getting a User

```php
use tool_zoomapi\helper;

$identifier = helper::get_api_identifier($user);
$zoomuser = helper::get_user($identifier);

if ($zoomuser !== false) {
    echo $zoomuser['id'];
    echo $zoomuser['email'];
}
```

### Getting the Current User's Zoom ID

```php
use tool_zoomapi\helper;

// Throws exception if user not found.
$zoomuserid = helper::get_userid();

// Returns null if user not found (no exception).
$zoomuserid = helper::get_userid_optional();
```

### Direct API Class Usage

```php
use tool_zoomapi\api;
use core\http_client;

$client = new http_client();
$api = new api($client);

$user = $api->get_user('user@example.com');
```

### Checking Scopes

```php
if ($api->has_scope(['user:read:admin', 'user:read:user:admin'])) {
    // Proceed with API call.
}
```

## Caching

The plugin defines two application caches:

| Cache | Purpose |
|-------|---------|
| `token` | Stores OAuth access tokens and API URLs |
| `users` | Stores Zoom user data keyed by ID and email |

## Privacy

This plugin does not permanently store any personal data. User data is only cached temporarily and complies with Moodle's privacy API as a `null_provider`.

## License

This plugin is part of Moodle and is licensed under the [GNU General Public License v3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

## Author

- **Jonathan Champ** (2026)
