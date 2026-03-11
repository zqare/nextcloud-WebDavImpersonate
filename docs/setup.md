# WebDAV Impersonate - Setup Guide

## Requirements

- Nextcloud 28+
- PHP 8.1+
- Administrative access to Nextcloud instance

## Installation

### 1. Install the App

```bash
# Clone to apps-extra directory
cd /var/www/html/apps-extra/
git clone <repository-url> webdavimpersonate

# Or download and extract
wget <release-url>
tar -xzf webdavimpersonate.tar.gz
mv webdavimpersonate /var/www/html/apps-extra/
```

### 2. Enable the App

```bash
# Using occ command
sudo -u www-data php /var/www/html/occ app:enable webdavimpersonate

# Or through Nextcloud admin interface
# Navigate to Apps → Disabled Apps → Enable WebDAV Impersonate
```

### 3. Verify Installation

```bash
# Check app status
sudo -u www-data php /var/www/html/occ app:list | grep webdavimpersonate

# Should show:
#  webdavimpersonate:
#    - version: 1.0.0
#    - active: true
```

## Configuration

### 1. Access Admin Settings

1. Log in to Nextcloud as administrator
2. Navigate to **Settings** → **Administration** → **WebDAV Impersonate**
3. Configure the following settings:

### 2. Configure Impersonator Groups

**Impersonator Groups**: Users who are allowed to impersonate others

- Click **Add Group** and select groups from the dropdown
- Typical choices: `admin`, `service_accounts`, `webdav_users`
- Multiple groups can be selected
- Leave empty to disable impersonation entirely

### 3. Configure Imitatee Groups

**Imitatee Groups**: Users who can be impersonated

- Click **Add Group** and select groups
- Typical choices: `users`, `staff`, `students`
- Multiple groups can be selected
- Leave empty to prevent any impersonation

### 4. Set Log Level

**Log Level**: Controls verbosity of impersonation logs

- **Error**: Only critical errors (recommended for production)
- **Warning**: Errors + denied attempts
- **Info**: Successful impersonations
- **Debug**: Detailed execution flow (development only)

### 5. Save Configuration

Click **Save** to apply the settings. The configuration is stored in Nextcloud's app configuration.

## User Setup

### 1. Create Service Account (Optional)

For automated processes, create a dedicated service account:

```bash
# Create service user
sudo -u www-data php /var/www/html/occ user:add --display-name="WebDAV Service" webdav_service

# Add to impersonator group
sudo -u www-data php /var/www/html/occ group:adduser webdav_users webdav_service

# Set a strong password
sudo -u www-data php /var/www/html/occ user:resetpassword webdav_service
```

### 2. Configure User Groups

Ensure users who should be impersonatable are in the correct groups:

```bash
# Add user to imitatee group
sudo -u www-data php /var/www/html/occ group:adduser users john_doe

# Check group membership
sudo -u www-data php /var/www/html/occ group:list
```

## Usage Examples

### Basic WebDAV Operations

#### Upload File as Another User

```bash
curl -u webdav_service:password \
     -H "X-Impersonate-User: john_doe" \
     -X PUT \
     -T local_file.txt \
     https://nextcloud.local/remote.php/dav/files/john_doe/uploaded_file.txt
```

#### Download File as Another User

```bash
curl -u webdav_service:password \
     -H "X-Impersonate-User: john_doe" \
     -O \
     https://nextcloud.local/remote.php/dav/files/john_doe/document.pdf
```

#### Create Directory

```bash
curl -u webdav_service:password \
     -H "X-Impersonate-User: john_doe" \
     -X MKCOL \
     https://nextcloud.local/remote.php/dav/files/john_doe/new_folder
```

#### List Directory Contents

```bash
curl -u webdav_service:password \
     -H "X-Impersonate-User: john_doe" \
     -X PROPFIND \
     https://nextcloud.local/remote.php/dav/files/john_doe/
```

### Using WebDAV Clients

#### Cyberduck

1. Open Cyberduck
2. Create new connection
3. Server: `nextcloud.local`
4. Username: `webdav_service`
5. Password: `[service account password]`
6. Path: `/remote.php/dav/files/`
7. In **Advanced** → **HTTP Headers**, add: `X-Impersonate-User: john_doe`

#### Windows Explorer (WebDAV)

1. Map network drive
2. URL: `\\nextcloud.local@SSL\remote.php\dav\files\`
3. Use service account credentials
4. Custom headers not supported - use curl or other client

#### Rclone

```bash
rclone copy local_file.txt webdav:john_doe/ \
  --webdav-headers "X-Impersonate-User,john_doe" \
  --webdav-url "https://nextcloud.local/remote.php/dav/files/" \
  --webdav-user "webdav_service" \
  --webdav-pass "[password]"
```

## Security Considerations

### 1. Service Account Security

- Use strong, unique passwords for service accounts
- Limit service account permissions to minimum necessary
- Regularly rotate service account passwords
- Monitor service account usage logs

### 2. Group Management

- Principle of least privilege for impersonator groups
- Regularly review group memberships
- Consider using dedicated groups for impersonation
- Document business justification for impersonation access

### 3. Audit Trail

- Enable appropriate logging level
- Regularly review impersonation logs
- Set up alerts for denied attempts
- Monitor for unusual patterns

### 4. Network Security

- Use HTTPS for all WebDAV connections
- Consider IP whitelisting for service accounts
- Implement rate limiting if needed
- Monitor for brute force attempts

## Troubleshooting

### Common Issues

#### "No authenticated user found"

**Causes:**
- Incorrect Basic Auth credentials
- Plugin priority issues
- Auth plugin not loaded

**Solutions:**
1. Verify username and password
2. Check app is enabled: `occ app:list`
3. Review Nextcloud logs for auth errors

#### "CSRF check not passed"

**Causes:**
- Using `setUser()` instead of `setVolatileActiveUser()`
- Session manipulation in WebDAV context

**Solutions:**
1. Ensure app is updated to latest version
2. Check Nextcloud logs for CSRF errors
3. Verify plugin is using volatile switching

#### "User not allowed to use WebDAV impersonation"

**Causes:**
- User not in impersonator groups
- No impersonator groups configured
- Group configuration issue

**Solutions:**
1. Check admin settings for impersonator groups
2. Verify user group membership: `occ user:info username`
3. Ensure groups are properly configured

### Debug Mode

Enable debug logging for troubleshooting:

1. Go to admin settings
2. Set log level to **Debug**
3. Perform test operation
4. Check Nextcloud logs: `/var/www/html/data/nextcloud.log`

```bash
# Filter for impersonation logs
grep "WebDAV impersonation" /var/www/html/data/nextcloud.log
```

### Log Analysis

#### Successful Impersonation

```
WebDAV impersonation: webdav_service → john_doe [PUT] - success
User context switched from webdav_service to john_doe
```

#### Failed Authentication

```
WebDAV impersonation failed: no authenticated principal found
```

#### Permission Denied

```
WebDAV impersonation: webdav_service → admin [PUT] - denied - target not in imitatee groups
```

## Performance Considerations

### 1. Logging Impact

- Debug logging can impact performance
- Use INFO or WARNING level in production
- Regularly rotate log files

### 2. Group Checks

- Group membership validation adds minimal overhead
- Caching handled by Nextcloud core
- Consider group size for very large organizations

### 3. User Switching

- Volatile switching is lightweight
- No session persistence overhead
- Minimal memory footprint

## Maintenance

### 1. Regular Updates

```bash
# Check for updates
sudo -u www-data php /var/www/html/occ app:update --all

# Update specific app
sudo -u www-data php /var/www/html/occ app:update webdavimpersonate
```

### 2. Configuration Backup

```bash
# Export app configuration
sudo -u www-data php /var/www/html/occ config:list webdavimpersonate
```

### 3. Log Management

```bash
# Set up log rotation
sudo nano /etc/logrotate.d/nextcloud

# Add entry for webdavimpersonate logs
/var/www/html/data/nextcloud.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
}
```

### 4. Security Audit

- Quarterly review of impersonator groups
- Annual service account password rotation
- Monthly log analysis
- Semi-annual configuration audit

## Support

### 1. Documentation

- [Developer Documentation](developer.md)
- [Nextcloud App Developer Guide](https://docs.nextcloud.com/server/latest/developer_manual/)
- [SabreDAV Documentation](https://sabre.io/dav/)

### 2. Community Support

- [Nextcloud Forums](https://help.nextcloud.com/)
- [GitHub Issues](https://github.com/your-repo/issues)
- [WebDAV Client Documentation](https://docs.nextcloud.com/server/latest/user_manual/en/files/access_webdav.html)

### 3. Professional Support

For enterprise support and custom development, contact the development team or Nextcloud partners.

## License

This app is licensed under AGPL-3.0-or-later. See [COPYING](../COPYING) for full license details.
