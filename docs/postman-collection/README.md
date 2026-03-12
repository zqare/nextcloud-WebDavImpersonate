# Nextcloud WebDAV Impersonate Plugin - Standalone Postman Collection

**Main File**: `Nextcloud-WebDAV-Impersonate-Standalone.postman_collection.json`

## 🎯 Overview

This is a **fully standalone Postman collection** for testing Nextcloud WebDAV with and without the WebDAV Impersonate Plugin. **All configuration is included in the collection** - no external environment files required.

## 📁 Files

### Main File
- **`Nextcloud-WebDAV-Impersonate-Standalone.postman_collection.json`** - Complete Postman collection with all tests and configuration

### Test Files
- **`test-files/small-test.txt`** - Small text file (~200 Bytes) for basic upload tests
- **`test-files/test-data.json`** - JSON file for structured data tests
- **`test-files/larger-test.txt`** - Larger text file (~1.5KB) for size tests

### Documentation
- **`README-Standalone.md`** - This documentation
- **`README.md`** - Original documentation (reference)

## 🚀 Quick Start

### 1. Import
1. Open Postman
2. Click "Import" (top left)
3. Select "Files" tab
4. Upload `Nextcloud-WebDAV-Impersonate-Standalone.postman_collection.json`

### 2. Configuration
1. Select imported collection
2. Open "Variables" tab
3. Adjust values if needed:
   - `baseUrl`: Nextcloud instance URL (default: `http://localhost:8080`)
   - `username`: Admin username (default: `admin`)
   - `password`: Admin password (default: `admin`)
   - `impersonatedUser`: User to impersonate (default: `john` - must exist in Nextcloud)

### 3. Run Tests
1. Run individual tests or entire folders
2. Check results in "Test Results" tab

## 🔧 Collection Variables

All configuration is stored as **Collection Variables**:

| Variable | Default Value | Description |
|----------|---------------|-------------|
| `baseUrl` | `http://localhost:8080` | Nextcloud instance URL |
| `username` | `admin` | Admin username for authentication |
| `password` | `admin` | Admin password for authentication |
| `impersonatedUser` | `john` | User to impersonate (must exist in Nextcloud) |
| `timestamp` | *auto-generated* | Timestamp for each request (automatically set) |

## 📋 Test Structure

### 1. Standard WebDAV Tests (Without Impersonation)
Basic WebDAV operations with standard authentication:
- **PROPFIND** - List directory contents
- **MKCOL** - Create directories
- **PUT** - Upload files
- **GET** - Download files
- **DELETE** - Delete files and directories

### 2. Impersonation WebDAV Tests
Same operations but with impersonation header:
- All requests contain `X-Impersonate-User` header
- Admin acts on behalf of another user
- Tests validate impersonation functionality

### 3. File Upload Tests
Different file types and sizes:
- Small text files
- JSON files
- Larger text files
- Content validation

### 4. Error Handling Tests
Test error conditions:
- **Invalid Authentication** - 401 with fixed invalid credentials
- **Impersonate Non-existent User** - Error for non-existent user

## 🔒 Cookie Security

### Aggressive Cookie Clearing
The collection implements **comprehensive cookie security**:

#### Pre-Request (for each request):
```javascript
// AGGRESSIVE COOKIE CLEARING
pm.cookies.clear();

// Remove session-relevant headers
pm.request.headers.remove('Cookie');
pm.request.headers.remove('Set-Cookie');
pm.request.headers.remove('Authorization');
pm.request.headers.remove('X-Session-ID');
pm.request.headers.remove('X-CSRF-Token');

// Delete Nextcloud-specific session cookies
const sessionCookies = ['nc_session_id', 'oc_session', 'nextcloud_session', 'csrf_token', 'token'];
sessionCookies.forEach(cookieName => {
    pm.cookies.unset(cookieName, domain, '/');
    pm.cookies.unset(cookieName, domain, '/remote.php');
    pm.cookies.unset(cookieName, domain, '/remote.php/dav');
});
```

#### Global Response Test (for each response):
```javascript
// CRITICAL: Ensure no session cookies are ever set
pm.test('No session cookies in response', function () {
    const setCookieHeader = pm.response.headers.get('Set-Cookie');
    const cookieHeader = pm.response.headers.get('Cookie');
    
    if (setCookieHeader) {
        pm.expect(setCookieHeader.toLowerCase()).to.not.include('session');
        pm.expect(setCookieHeader.toLowerCase()).to.not.include('csrf');
        pm.expect(setCookieHeader.toLowerCase()).to.not.include('token');
    }
    
    if (cookieHeader) {
        pm.expect(cookieHeader.toLowerCase()).to.not.include('session');
        pm.expect(cookieHeader.toLowerCase()).to.not.include('csrf');
        pm.expect(cookieHeader.toLowerCase()).to.not.include('token');
    }
});
```

### Security Advantages:
✅ **Stateless Testing** - Each request starts without session persistence  
✅ **Pure Basic Auth** - WebDAV uses only HTTP Basic Authentication  
✅ **No Session Contamination** - Tests don't interfere with each other  
✅ **Forced Clean State** - Aggressive cookie clearing before each request  

## 🌐 WebDAV Endpoints

### Standard WebDAV
- `{{baseUrl}}/remote.php/dav/files/{{username}}/` - User root directory

### Impersonation WebDAV
- `{{baseUrl}}/remote.php/dav/files/{{impersonatedUser}}/` - Impersonated user root directory

## 🔐 Authentication

### Collection-Level Authorization
The collection uses **Collection-Level HTTP Basic Authentication**:
- Admin credentials are automatically pulled from collection variables
- `{{username}}` - Admin username
- `{{password}}` - Admin password

### Impersonation
For impersonation tests:
- Admin authenticates with Basic Auth
- `X-Impersonate-User: {{impersonatedUser}}` header is added to individual requests
- Admin acts on behalf of the impersonated user

## 🧪 Test Scripts

### Dynamic Variable Usage
All tests use **dynamic variable resolution**:

```javascript
// Example: Dynamically read impersonated user
const impersonatedUser = pm.collectionVariables.get('impersonatedUser');
pm.expect(impersonateHeader.value).to.eql(impersonatedUser);
```

### Validations
Each request contains automated tests for:
- HTTP status codes
- Response headers
- Content validation
- Impersonation header check (for impersonation tests)

## 📊 Test Sequences

### Recommended Test Order
1. **Standard WebDAV Tests** - Verify basic functionality
2. **Error Handling Tests** - Check error handling
3. **File Upload Tests** - Test different file types
4. **Impersonation WebDAV Tests** - Basic impersonation operations

### Expected Results
- **Standard Tests**: 2xx status codes (200, 201, 204, 207)
- **Error Tests**: Appropriate error codes (401, 403, 404)
- **Impersonation Tests**: Like standard tests but on behalf of impersonated user

## 🛠️ Troubleshooting

### Common Issues
1. **401 Unauthorized**: Check collection variables `username`/`password`
2. **404 Not Found**: Is `baseUrl` correct and Nextcloud reachable?
3. **Impersonation fails**: WebDAV Impersonate Plugin installed and configured?
4. **Test user not found**: Create test user in Nextcloud or adjust `impersonatedUser`

### Debug Tips
- Use Postman "Console" for request/response details
- Check "Headers" tab for impersonation header verification
- Check Nextcloud logs for additional error information
- Verify collection variables in "Variables" tab

## 🏗️ Nextcloud Setup for Tests

### Create Test Users
1. Log in to Nextcloud as admin
2. Open "Users" section
3. Create new user (e.g., `john`)
4. Set password for user
5. Add user to appropriate groups (if needed)

### WebDAV Impersonate Plugin Setup
1. Install WebDAV Impersonate Plugin
2. Configure allowed groups in admin settings
3. Ensure admin user is in allowed groups
4. Test impersonation functionality

## 🔒 Security Notes

- **Admin Credentials**: Collection uses admin credentials - handle securely
- **Test Files**: Contain only sample data
- **Production**: Adjust collection variables for production environments
- **Sharing**: Share collection safely as it contains credentials

## ✅ Advantages of Standalone Solution

🚀 **Self-contained** - Everything in one file, no external dependencies  
🔧 **Dynamic** - Tests automatically read from collection variables  
📦 **Portable** - Easy to share and import  
🛠️ **Maintainable** - All configuration in one place  
🔄 **Flexible** - Easily adaptable for different environments  
🔒 **Secure** - Aggressive cookie clearing for stateless testing  

## 📝 Test Examples

### Standard WebDAV Request
```http
PROPFIND /remote.php/dav/files/admin/ HTTP/1.1
Host: localhost:8080
Depth: 1
Content-Type: application/xml
Authorization: Basic YWRtaW46YWRtaW4=
```

### Impersonation WebDAV Request
```http
PROPFIND /remote.php/dav/files/john/ HTTP/1.1
Host: localhost:8080
Depth: 1
Content-Type: application/xml
X-Impersonate-User: john
Authorization: Basic YWRtaW46YWRtaW4=
```

### Error Request (Invalid Auth)
```http
PROPFIND /remote.php/dav/files/nonexistent-user/ HTTP/1.1
Host: localhost:8080
Depth: 1
Content-Type: application/xml
Authorization: Basic bm9uZXhpc3RlbnQtdXNlcjpmYWtlLXBhc3N3b3JkLTEyMzQ1
```

## 🔄 Future Development

When adding new tests:
1. Follow existing naming conventions
2. Include appropriate test scripts
3. Use `pm.collectionVariables.get()` for dynamic values
4. Test both standard and impersonation scenarios

## 📄 License

This test collection is part of the WebDAV Impersonate Plugin and follows the same AGPL-3.0 license.

---

**Important**: This collection is specifically designed for **secure, stateless WebDAV Basic Auth tests** and implements **comprehensive cookie security** to ensure pure Basic Auth without session persistence.
