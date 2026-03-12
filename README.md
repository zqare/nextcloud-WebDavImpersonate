# WebDAV Impersonate

Secure user impersonation for Nextcloud WebDAV operations via HTTP header.

## Features

- **Group-based permissions**: Separate "Impersonator Groups" (may impersonate) and "Imitatee Groups" (may be impersonated)
- **Username validation**: Only users in allowed groups can impersonate/be impersonated
- **Audit logging**: All attempts logged (Caller → Target → Method)
- **Admin UI**: Simple group & log level configuration
- **Fail-secure**: Empty group lists → **100% blocked**

## Use Cases

- **Service Accounts** → Automate end-user file operations
- **Support Teams** → Manage customer files without password sharing
- **Backup Tools** → File access as target user (no app passwords)

## Security

- **Real-time validation**: Group membership checked **every request**
- **Transparent logs**: Complete audit trail for every attempt
- **Zero credential sharing**: Uses existing Basic Auth credentials

## Quick Start

1. Install the app from Nextcloud App Store or manually
2. Configure impersonator and imitatee groups in admin settings
3. Use WebDAV with `X-Impersonate-User` header:

```bash
curl -u service_user:password \
     -H "X-Impersonate-User: target_user" \
     -X PUT \
     -T file.txt \
     https://nextcloud.local/remote.php/dav/files/target_user/file.txt
```

## Documentation

- [Developer Documentation](docs/developer.md)
- [Setup Guide](docs/setup.md)
- [Security Analysis](docs/security.md)
- [API Reference](docs/api-reference.md)

## License

AGPL-3.0-or-later - see [LICENSE](LICENSE) file for details.

## Support

- [GitHub Issues](https://github.com/zqare/nextcloud-WebDavImpersonate/issues)
- [Nextcloud Forums](https://help.nextcloud.com/)
