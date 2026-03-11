# WebDAV Impersonate - API Reference

## Core Classes

### ImpersonateService

Main service class handling WebDAV impersonation logic.

#### Constructor

```php
public function __construct(
    IConfig $config,
    IGroupManager $groupManager,
    IUserManager $userManager,
    IUserSession $userSession,
    LoggerInterface $logger
)
```

#### Methods

##### impersonate()

Perform user impersonation for WebDAV operations.

```php
public function impersonate(string $callerUserId, string $targetUserId, string $method): void
```

**Parameters:**
- `callerUserId` (string): UID of the user attempting to impersonate (from Sabre auth)
- `targetUserId` (string): UID of the user to impersonate  
- `method` (string): HTTP method being used (GET, PUT, PROPPATCH, etc.)

**Throws:**
- `NotAuthenticated`: When no authenticated user is found
- `Forbidden`: When impersonation is not allowed or user not found

**Example:**
```php
try {
    $service->impersonate('admin', 'john_doe', 'PUT');
} catch (NotAuthenticated $e) {
    // Handle authentication error
} catch (Forbidden $e) {
    // Handle authorization error
}
```

##### getImpersonatorGroups()

Get the list of allowed impersonator groups from configuration.

```php
public function getImpersonatorGroups(): array
```

**Returns:** `array<string>` - Array of group IDs that are allowed to impersonate others

**Example:**
```php
$groups = $service->getImpersonatorGroups();
// Returns: ['admin', 'service_accounts']
```

##### setImpersonatorGroups()

Set the list of allowed impersonator groups in configuration.

```php
public function setImpersonatorGroups(array $groups): void
```

**Parameters:**
- `groups` (array<string>): Array of group IDs that are allowed to impersonate others

**Example:**
```php
$service->setImpersonatorGroups(['admin', 'webdav_users']);
```

##### getImitateeGroups()

Get the list of allowed imitatee groups from configuration.

```php
public function getImitateeGroups(): array
```

**Returns:** `array<string>` - Array of group IDs whose members can be impersonated

##### setImitateeGroups()

Set the list of allowed imitatee groups in configuration.

```php
public function setImitateeGroups(array $groups): void
```

**Parameters:**
- `groups` (array<string>): Array of group IDs whose members can be impersonated

##### getLogLevel()

Get the configured log level.

```php
public function getLogLevel(): string
```

**Returns:** `string` - The current log level (debug, info, warning, error)

##### setLogLevel()

Set the log level in configuration.

```php
public function setLogLevel(string $logLevel): void
```

**Parameters:**
- `logLevel` (string): The log level to set (debug, info, warning, error)

**Throws:** `\InvalidArgumentException` - When an invalid log level is provided

### ImpersonatePlugin

SabreDAV plugin for WebDAV user impersonation.

#### Constructor

```php
public function __construct(ImpersonateService $impersonateService, LoggerInterface $logger)
```

#### Constants

##### HEADER_NAME

HTTP header name for impersonation requests.

```php
public const HEADER_NAME = 'X-Impersonate-User';
```

#### Methods

##### initialize()

Initialize the plugin with the SabreDAV server.

```php
public function initialize(Server $server): void
```

**Parameters:**
- `server` (Server): The SabreDAV server instance

**Priority:** 30 (runs after auth and ACL plugins)

##### beforeMethod()

Called before any HTTP method is handled.

```php
public function beforeMethod(RequestInterface $request, ResponseInterface $response): void
```

**Parameters:**
- `request` (RequestInterface): The HTTP request object
- `response` (ResponseInterface): The HTTP response object

**Throws:**
- `Forbidden`: When impersonation is not allowed
- `NotAuthenticated`: When no authenticated user is found

##### getPluginName()

Returns a plugin name for identification purposes.

```php
public function getPluginName(): string
```

**Returns:** `string` - The plugin name 'impersonate'

##### getFeatures()

Returns a list of plugin features.

```php
public function getFeatures(): array
```

**Returns:** `array<string>` - Array of feature identifiers (currently empty)

##### getSupportedMethods()

Returns a list of supported HTTP methods.

```php
public function getSupportedMethods(): array
```

**Returns:** `array<string>` - Array of supported HTTP method names (currently empty)

### SabrePluginListener

Event listener for registering the ImpersonatePlugin with SabreDAV.

#### Constructor

```php
public function __construct(ImpersonateService $impersonateService, LoggerInterface $logger)
```

#### Methods

##### handle()

Handle the SabrePluginAddEvent to register the impersonation plugin.

```php
public function handle(Event $event): void
```

**Parameters:**
- `event` (Event): The event object (should be SabrePluginAddEvent)

## Configuration API

### App Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `impersonator_groups` | JSON array | `[]` | Groups allowed to impersonate others |
| `imitatee_groups` | JSON array | `[]` | Groups whose members can be impersonated |
| `log_level` | string | `info` | Logging verbosity level |

### Configuration Access

```php
// Get configuration value
$value = $config->getAppValue('webdavimpersonate', 'impersonator_groups', '[]');

// Set configuration value
$config->setAppValue('webdavimpersonate', 'impersonator_groups', json_encode(['admin']));

// Delete configuration value
$config->deleteAppValue('webdavimpersonate', 'impersonator_groups');
```

## HTTP API

### Impersonation Header

**Header:** `X-Impersonate-User`

**Value:** Target user ID to impersonate

**Example:**
```http
X-Impersonate-User: john_doe
```

### Request Format

```http
PUT /remote.php/dav/files/john_doe/file.txt HTTP/1.1
Host: nextcloud.local
Authorization: Basic d2ViZGF2X3NlcnZpY2U6cGFzc3dvcmQ=
X-Impersonate-User: john_doe
Content-Type: application/octet-stream
Content-Length: 1024

[file content]
```

### Response Format

#### Success Response

```http
HTTP/1.1 201 Created
Content-Type: application/xml; charset=utf-8
Content-Length: 0
ETag: "abc123"
```

#### Error Response

```http
HTTP/1.1 403 Forbidden
Content-Type: application/xml; charset=utf-8
Content-Length: 200

<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
    <s:exception>Sabre\DAV\Exception\Forbidden</s:exception>
    <s:message>User 'admin' is not allowed to use WebDAV impersonation</s:message>
</d:error>
```

## Error Codes

### Sabre\DAV\Exception\NotAuthenticated

**HTTP Status:** 401 Unauthorized

**Causes:**
- No Basic Auth credentials provided
- Invalid credentials
- Auth plugin not available
- No authenticated principal found

**Example Response:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
    <s:exception>Sabre\DAV\Exception\NotAuthenticated</s:exception>
    <s:message>No authenticated user found for impersonation</s:message>
</d:error>
```

### Sabre\DAV\Exception\Forbidden

**HTTP Status:** 403 Forbidden

**Causes:**
- Caller not in impersonator groups
- Target user not found
- Target not in imitatee groups
- Impersonation disabled

**Example Response:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
    <s:exception>Sabre\DAV\Exception\Forbidden</s:exception>
    <s:message>User 'admin' is not allowed to use WebDAV impersonation</s:message>
</d:error>
```

## Logging API

### Log Levels

| Level | Use Case | Examples |
|-------|----------|----------|
| `DEBUG` | Detailed execution flow | User context switching, plugin initialization |
| `INFO` | Successful operations | Successful impersonation, configuration updates |
| `WARNING` | Security events | Denied impersonation attempts, permission issues |
| `ERROR` | System failures | Missing plugins, authentication failures |

### Log Message Format

```
WebDAV impersonation: {caller} → {target} [{method}] - {result}
```

**Variables:**
- `{caller}`: User attempting impersonation
- `{target}`: Target user to impersonate
- `{method}`: HTTP method (GET, PUT, etc.)
- `{result}`: Operation result (success, denied - reason)

### Log Examples

```php
// DEBUG level
$this->logger->debug('User context switched from {original} to {target}', [
    'original' => 'admin',
    'target' => 'john_doe'
]);

// INFO level  
$this->logger->info('WebDAV impersonation: admin → john_doe [PUT] - success');

// WARNING level
$this->logger->warning('WebDAV impersonation: service → admin [GET] - denied - target not in imitatee groups');

// ERROR level
$this->logger->error('WebDAV impersonation failed: no auth plugin found');
```

## Events

### SabrePluginAddEvent

The app listens for this event to automatically register the impersonation plugin.

**Event Class:** `OCA\DAV\Events\SabrePluginAddEvent`

**Handler:** `SabrePluginListener::handle()`

**Registration:**
```xml
<listener>
    <event>OCA\DAV\Events\SabrePluginAddEvent</event>
    <listener>OCA\WebDavImpersonate\Dav\SabrePluginListener</listener>
</listener>
```

## Hooks and Filters

### No Hooks Used

This app uses the modern event system instead of hooks for better integration with Nextcloud's architecture.

### Plugin Registration

The impersonation plugin is registered automatically through the event system, ensuring it's loaded for every WebDAV request without manual intervention.

## Constants

### ImpersonateService Constants

```php
public const APP_ID = 'webdavimpersonate';
private const CONFIG_KEY_IMPERSONATOR_GROUPS = 'impersonator_groups';
private const CONFIG_KEY_IMITATEE_GROUPS = 'imitatee_groups';
private const CONFIG_KEY_LOG_LEVEL = 'log_level';
```

### ImpersonatePlugin Constants

```php
public const HEADER_NAME = 'X-Impersonate-User';
```

## Utility Functions

### Principal Extraction

```php
/**
 * Extract username from Sabre principal path
 * 
 * @param string $principal Principal path like "principals/users/username"
 * @return string Username part
 */
private function extractUsernameFromPrincipal(string $principal): string
{
    return basename($principal);
}
```

### Group Validation

```php
/**
 * Validate and filter group IDs
 * 
 * @param array $groups Array of group IDs to validate
 * @return array Array of valid group IDs
 */
private function validateGroups(array $groups): array
{
    return array_filter($groups, function($groupId) {
        return is_string($groupId) && $this->groupManager->groupExists($groupId);
    });
}
```

### Log Level Validation

```php
/**
 * Validate log level against allowed values
 * 
 * @param string $level Log level to validate
 * @return string Valid log level
 */
private function validateLogLevel(string $level): string
{
    $allowed = [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR];
    return in_array($level, $allowed, true) ? $level : LogLevel::INFO;
}
```

## Integration Examples

### cURL Integration

```bash
# Upload file with impersonation
curl -u service_user:password \
     -H "X-Impersonate-User: target_user" \
     -X PUT \
     -T local_file.txt \
     https://nextcloud.local/remote.php/dav/files/target_user/file.txt

# Download file with impersonation
curl -u service_user:password \
     -H "X-Impersonate-User: target_user" \
     -O \
     https://nextcloud.local/remote.php/dav/files/target_user/document.pdf
```

### PHP Integration

```php
<?php
// WebDAV client with impersonation
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://nextcloud.local/remote.php/dav/files/target_user/file.txt');
curl_setopt($ch, CURLOPT_USERPWD, 'service_user:password');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Impersonate-User: target_user',
    'Content-Type: application/octet-stream'
]);
curl_setopt($ch, CURLOPT_PUT, true);
curl_setopt($ch, CURLOPT_INFILE, fopen('local_file.txt', 'r'));
curl_setopt($ch, CURLOPT_INFILESIZE, filesize('local_file.txt'));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode === 201) {
    echo "File uploaded successfully\n";
} else {
    echo "Upload failed with HTTP code: $httpCode\n";
}

curl_close($ch);
?>
```

### Python Integration

```python
import requests
from requests.auth import HTTPBasicAuth

# Upload file with impersonation
url = 'https://nextcloud.local/remote.php/dav/files/target_user/file.txt'
headers = {
    'X-Impersonate-User': 'target_user',
    'Content-Type': 'application/octet-stream'
}

with open('local_file.txt', 'rb') as f:
    response = requests.put(
        url,
        data=f,
        headers=headers,
        auth=HTTPBasicAuth('service_user', 'password')
    )

if response.status_code == 201:
    print("File uploaded successfully")
else:
    print(f"Upload failed: {response.status_code} - {response.text}")
```

## Testing API

### Unit Testing

```php
<?php
use PHPUnit\Framework\TestCase;
use OCA\WebDavImpersonate\Service\ImpersonateService;

class ImpersonateServiceTest extends TestCase
{
    private $service;
    private $mockConfig;
    private $mockGroupManager;
    private $mockUserManager;
    private $mockUserSession;
    private $mockLogger;

    protected function setUp(): void
    {
        $this->mockConfig = $this->createMock(IConfig::class);
        $this->mockGroupManager = $this->createMock(IGroupManager::class);
        $this->mockUserManager = $this->createMock(IUserManager::class);
        $this->mockUserSession = $this->createMock(IUserSession::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        $this->service = new ImpersonateService(
            $this->mockConfig,
            $this->mockGroupManager,
            $this->mockUserManager,
            $this->mockUserSession,
            $this->mockLogger
        );
    }

    public function testSuccessfulImpersonation(): void
    {
        // Setup mocks
        $this->mockConfig->method('getAppValue')
            ->willReturn('["admin"]');
        
        $this->mockGroupManager->method('isInGroup')
            ->willReturn(true);
        
        $mockUser = $this->createMock(IUser::class);
        $this->mockUserManager->method('get')
            ->willReturn($mockUser);

        // Test impersonation
        $this->expectNotToPerformAssertions();
        $this->service->impersonate('admin', 'target_user', 'PUT');
    }
}
?>
```

### Integration Testing

```php
<?php
use Sabre\DAV\Server;
use OCA\WebDavImpersonate\Dav\ImpersonatePlugin;

class WebDavIntegrationTest extends TestCase
{
    public function testWebDavImpersonation(): void
    {
        // Setup test environment
        $server = new Server($rootNode);
        $plugin = new ImpersonatePlugin($service, $logger);
        $server->addPlugin($plugin);

        // Create test request
        $request = new Request('PUT', '/files/target/test.txt');
        $request->setHeader('Authorization', 'Basic ' . base64_encode('service:pass'));
        $request->setHeader('X-Impersonate-User', 'target');
        
        $response = new Response();

        // Execute request
        $server->httpRequest = $request;
        $server->httpResponse = $response;
        $server->exec();

        // Verify results
        $this->assertEquals(201, $response->getStatus());
    }
}
?>
```
