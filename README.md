# LogicPanel Blesta Module

Official Blesta provisioning module for LogicPanel PaaS hosting platform.

## Overview

This module integrates LogicPanel with Blesta billing system, enabling automated account provisioning, management, and one-click SSO login for your hosting customers.

## Features

- **Automated Provisioning**: Create hosting accounts automatically when orders are placed
- **Package Management**: Auto-retrieve packages from LogicPanel API
- **One-Click SSO**: Secure single sign-on for clients and admins
- **Full Lifecycle Management**: Suspend, unsuspend, terminate, and upgrade accounts
- **Client Area Integration**: Display account stats, services, databases, and domains
- **Password Management**: Change account passwords from Blesta
- **Package Upgrades**: Seamlessly upgrade/downgrade hosting packages

## Requirements

- Blesta 5.0 or higher
- LogicPanel server with API access
- PHP 7.4 or higher with cURL extension
- Valid LogicPanel API key

## Installation

1. **Upload the Module**
   ```bash
   cd /path/to/blesta/components/modules/
   cp -r /path/to/logicpanel ./
   ```

2. **Set Permissions**
   ```bash
   chmod -R 755 logicpanel/
   ```

3. **Install in Blesta**
   - Navigate to: `Settings > Modules > Available`
   - Find "LogicPanel" and click `Install`

## Configuration

### 1. Add a LogicPanel Server

Navigate to: `Settings > Modules > LogicPanel > Add Server`

Configure the following fields:

| Field | Description | Example |
|-------|-------------|---------|
| **Host** | LogicPanel server hostname or IP | `panel.example.com` |
| **Port** | Master API port | `9999` (default) |
| **User Port** | Client panel port | `7777` (default) |
| **API Key** | Master API key from LogicPanel | `lp_xxxxxxxxxxxx` |
| **Use SSL** | Enable HTTPS connections | ✓ Checked (recommended) |

**Getting Your API Key:**
1. Login to LogicPanel Master Panel
2. Navigate to: `Settings > API Keys`
3. Create a new API key with appropriate permissions
4. Copy the key (starts with `lp_`)

### 2. Create a Package

Navigate to: `Packages > Browse Packages > Create Package`

1. **Basic Settings**
   - Name: e.g., "Starter Hosting"
   - Module: Select "LogicPanel"
   - Module Group: Select your LogicPanel server

2. **Module Options**
   - **Package**: Select from dropdown (auto-populated from LogicPanel)
   - The dropdown shows all available packages from your LogicPanel server

3. **Pricing & Terms**
   - Configure pricing as needed

### 3. Test Connection

Navigate to: `Settings > Modules > LogicPanel > Manage`

- Click on your server name
- Verify connection status shows "Connected"
- Check that packages are loading correctly

## Usage

### Automatic Provisioning

When a client orders a package using the LogicPanel module:

1. Account is automatically created on LogicPanel
2. Credentials are stored securely in Blesta
3. Client receives welcome email with login details
4. Service appears in client area with SSO login button

### Client Area Features

Clients can view in their service details:

- **Account Information**: Username, domain, package details
- **One-Click Login**: Direct SSO access to LogicPanel
- **Usage Statistics**: 
  - Active services/applications
  - Database count
  - Domain count
- **Account Status**: Active, suspended, or terminated

### Admin Functions

From the Blesta admin panel, you can:

- **Suspend Account**: Temporarily disable access
- **Unsuspend Account**: Restore access
- **Terminate Account**: Permanently delete the account
- **Change Password**: Update client password
- **Upgrade/Downgrade**: Change hosting package
- **View in Panel**: Direct link to account in LogicPanel

## API Endpoints Used

The module communicates with LogicPanel using these endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/v1/api/packages` | GET | Retrieve available packages |
| `/v1/api/accounts` | POST | Create new account |
| `/v1/api/accounts/{id}` | GET | Get account details |
| `/v1/api/accounts/{id}` | DELETE | Terminate account |
| `/v1/api/accounts/{id}/suspend` | POST | Suspend account |
| `/v1/api/accounts/{id}/unsuspend` | POST | Unsuspend account |
| `/v1/api/accounts/{id}/password` | POST | Change password |
| `/v1/api/accounts/{id}/package` | POST | Change package |
| `/v1/api/accounts/{id}/login` | POST | Generate SSO token |
| `/v1/api/accounts/{id}/services` | GET | List services |
| `/v1/api/accounts/{id}/databases` | GET | List databases |
| `/v1/api/accounts/{id}/domains` | GET | List domains |

## Troubleshooting

### Connection Failed

**Problem**: Cannot connect to LogicPanel server

**Solutions**:
- Verify hostname/IP is correct and accessible
- Check firewall allows connections on port 9999
- Ensure SSL certificate is valid (or disable SSL verification for testing)
- Verify API key is correct and active

### Packages Not Loading

**Problem**: Package dropdown is empty or shows error

**Solutions**:
- Test API connection in module settings
- Verify API key has permission to list packages
- Check LogicPanel server has packages configured
- Review Blesta module logs for API errors

### Account Creation Fails

**Problem**: "User already exists" error

**Solutions**:
- LogicPanel checks both username AND email
- Client email may already be registered
- Try different username or check existing accounts
- Verify package ID is valid

### SSO Login Not Working

**Problem**: Login button doesn't work or redirects incorrectly

**Solutions**:
- Verify "User Port" is set correctly (default: 7777)
- Check LogicPanel user panel is accessible
- Ensure account is active (not suspended)
- Review API logs for token generation errors

## File Structure

```
logicpanel/
├── config.json                 # Module metadata
├── logicpanel.php             # Main module class
├── lib/
│   └── api_client.php         # API client library
├── language/
│   └── en_us/
│       └── logicpanel.php     # English language strings
└── views/
    └── default/
        ├── admin_service_info.pdt    # Admin service view
        └── client_service_info.pdt   # Client service view
```

## Security Notes

- API keys are stored encrypted in Blesta database
- Passwords are encrypted using Blesta's encryption system
- SSL/TLS is recommended for all API communications
- SSO tokens are single-use and time-limited
- Module validates all API responses before processing

## Support

For issues related to:

- **Module functionality**: Check Blesta module logs at `Settings > System > Logs > Module`
- **LogicPanel API**: Review LogicPanel server logs
- **Connection issues**: Verify network connectivity and firewall rules

## Version History

### v2.0.0
- Complete rewrite with modern API integration
- Added one-click SSO login
- Enhanced client area with usage statistics
- Improved error handling and logging
- Auto-package retrieval from API
- Support for package upgrades/downgrades

## License

This module is provided as-is for use with LogicPanel hosting platform.

## Author

**cyber-wahid**  
Website: https://logicdock.cloud/

---

For more information about LogicPanel, visit the main project documentation.
