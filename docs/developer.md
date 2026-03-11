# WebDAV Impersonate - Developer Documentation

## Overview

The WebDAV Impersonate app allows authorized users to impersonate other users for WebDAV operations. This is particularly useful for service accounts, admin tasks, and automated processes that need to access files on behalf of other users.

## Architecture

### Core Components

1. **ImpersonatePlugin** - SabreDAV plugin that intercepts WebDAV requests
2. **ImpersonateService** - Business logic for validation and user switching
3. **SabrePluginListener** - Event listener that registers the plugin with Nextcloud

### Authentication Flow

```
Client Request → Auth Plugin (Prio 10) → ACL Plugin (Prio 20) → Impersonate Plugin (Prio 30)
```

1. **Client** sends Basic Auth + `X-Impersonate-User: target_user` header
2. **Auth Plugin** validates Basic Auth credentials and sets principal
3. **ACL Plugin** handles access control checks
4. **Impersonate Plugin** extracts authenticated user and performs impersonation

## Key Design Decisions

### 1. Sabre Auth Plugin vs IUserSession

**Problem**: WebDAV clients use Basic Auth without PHP sessions
**Solution**: Extract authenticated user from Sabre's auth plugin principal

```php
$authPlugin = $this->server->getPlugin('auth');
$currentPrincipal = $authPlugin->getCurrentPrincipal(); // "principals/users/USERNAME"
$callerUserId = basename($currentPrincipal); // "USERNAME"
```

### 2. CSRF Prevention Strategy

**Problem**: `setUser()` modifies session and breaks CSRF validation
**Solution**: Use volatile user switching

```php
// ❌ Breaks CSRF - modifies session
$this->userSession->setUser($targetUser);

// ✅ CSRF-safe - only affects current request
$this->userSession->setImpersonatingUserID(true);
$this->userSession->setVolatileActiveUser($targetUser);
```

### 3. Plugin Priority System

**Problem**: Plugin execution order not guaranteed
**Solution**: Use priority 30 to run after auth (10) and ACL (20)

```php
$this->server->on('beforeMethod:*', [$this, 'beforeMethod'], 30);
```

## Security Model

### Fail-Secure Approach
- No impersonator groups configured = impersonation disabled
- No imitatee groups configured = no one can be impersonated
- All attempts logged with configurable levels

### Group-Based Permissions
- **Impersonator Groups**: Users allowed to impersonate others
- **Imitatee Groups**: Users that can be impersonated
- **Intersection**: Both conditions must be satisfied

### Validation Process
1. Verify caller user exists and is authenticated
2. Check caller is in allowed impersonator groups
3. Verify target user exists in the system
4. Validate target is in allowed imitatee groups
5. Perform volatile user context switch

## Usage Examples

### Basic WebDAV Request

```bash
curl -u ServiceUser:password \
     -H "X-Impersonate-User: Steffen" \
     -X PUT \
     -T file.txt \
     https://nextcloud.local/remote.php/dav/files/Steffen/file.txt
```

### Different HTTP Methods

```bash
# GET - Download file
curl -u ServiceUser:password \
     -H "X-Impersonate-User: Steffen" \
     -O \
     https://nextcloud.local/remote.php/dav/files/Steffen/document.pdf

# MKCOL - Create directory
curl -u ServiceUser:password \
     -H "X-Impersonate-User: Steffen" \
     -X MKCOL \
     https://nextcloud.local/remote.php/dav/files/Steffen/new_folder

# DELETE - Remove file
curl -u ServiceUser:password \
     -H "X-Impersonate-User: Steffen" \
     -X DELETE \
     https://nextcloud.local/remote.php/dav/files/Steffen/old_file.txt
```

## Configuration

### Admin Settings

The app provides an admin interface for:

1. **Impersonator Groups** - Groups allowed to impersonate others
2. **Imitatee Groups** - Groups whose members can be impersonated
3. **Log Level** - Debug, Info, Warning, Error

### Configuration Storage

```php
// Stored in Nextcloud's app config
$this->config->setAppValue('webdavimpersonate', 'impersonator_groups', json_encode($groups));
$this->config->setAppValue('webdavimpersonate', 'imitatee_groups', json_encode($groups));
$this->config->setAppValue('webdavimpersonate', 'log_level', LogLevel::INFO);
```

## Logging

### Log Format

```
WebDAV impersonation: caller → target [method] - result
```

### Examples

```
WebDAV impersonation: admin → steffen [PUT] - success
WebDAV impersonation: service → maria [GET] - denied - caller not in impersonator groups
WebDAV impersonation: admin → unknown [PUT] - denied - target user not found
```

### Log Levels

- **DEBUG**: Detailed execution flow
- **INFO**: Successful impersonations
- **WARNING**: Denied attempts
- **ERROR**: System errors and failures

## Error Handling

### Common Errors

1. **NotAuthenticated**: No authenticated user found
2. **Forbidden**: User not allowed to impersonate or target not found
3. **CSRF Error**: Fixed by using volatile switching

### Error Responses

```xml
<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
    <s:exception>Sabre\DAV\Exception\Forbidden</s:exception>
    <s:message>User 'admin' is not allowed to use WebDAV impersonation</s:message>
</d:error>
```

## Testing

### Unit Tests

All classes have corresponding unit tests in `tests/Unit/`:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/Unit/Service/ImpersonateServiceTest.php
```

### Integration Testing

Test with actual WebDAV clients:

```bash
# Test with curl
curl -v -u testuser:password \
     -H "X-Impersonate-User: targetuser" \
     https://nextcloud.local/remote.php/dav/files/targetuser/

# Test with cadaver
cadaver https://nextcloud.local/remote.php/dav/files/targetuser/
# Enter credentials and test operations
```

## Troubleshooting

### "No authenticated user found"
- Check Basic Auth credentials
- Verify plugin priority (should be 30)
- Ensure auth plugin is loaded

### "CSRF check not passed"
- Verify using `setVolatileActiveUser()` instead of `setUser()`
- Check that `setImpersonatingUserID()` is called first

### "User not allowed to use WebDAV impersonation"
- Verify user is in impersonator groups
- Check group configuration in admin settings
- Review logs for specific denial reason

## Development Guidelines

### Code Standards

- PSR-12 coding style
- Strict types enabled
- Comprehensive PHPDoc blocks
- Unit tests for all classes
- AGPL-3.0 license headers

### Dependencies

- Only use OCP\* interfaces (never OC\* internal classes)
- Dependency injection via constructor
- No static method calls
- Mock all dependencies in tests

### Security Considerations

- Validate all inputs
- Use fail-secure defaults
- Log all impersonation attempts
- Never expose sensitive data in logs
- Follow Nextcloud security guidelines
