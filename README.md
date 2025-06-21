# Amelia Role Access Manager

**Version:** 1.1.0  
**WordPress Plugin** for managing AmeliaWP booking plugin role access and capabilities.

## Description

The Amelia Role Access Manager plugin allows WordPress administrators to control which user roles and individual users have access to AmeliaWP booking plugin capabilities. This plugin provides fine-grained control over who can manage appointments, bookings, services, and other Amelia features.

## Features

### ✅ **Comprehensive Role Management**
- Assign 85+ Amelia capabilities to any WordPress role
- Individual user capability assignment via user ID list
- Staff role always has full access (non-configurable)
- Real-time capability application

### ✅ **Force Override System**  
- Advanced capability override using WordPress `user_has_cap` filter
- Bypasses complex permission checks that may prevent access
- Optional force override checkbox for problematic setups

### ✅ **Complete Capability Coverage**
The plugin grants access to all essential Amelia and WordPress capabilities:

#### **Core Amelia Capabilities** (39 capabilities)
- Appointment management (read, create, edit, delete)
- Booking management and status control  
- Service and category management
- Employee/provider management
- Customer relationship management
- Calendar and availability control
- Payment and finance tracking
- Notification and communication settings
- Custom fields and form management

#### **Extended Amelia Features** (28 capabilities)
- Event management and scheduling
- Package and bundle services
- Coupon and discount management
- Resource and equipment booking
- Location and venue management
- Tax and financial reporting
- Advanced booking workflows
- Integration and import/export tools

#### **Essential WordPress Capabilities** (18 capabilities)
- `manage_options` - Core admin access
- `edit_posts`, `edit_pages` - Content management
- `upload_files` - Media handling
- `manage_categories` - Taxonomy management
- `unfiltered_html` - Advanced content editing
- `read` - Basic read access
- Plus additional administrative capabilities

### ✅ **Modern Admin Interface**
- Clean, professional WordPress-style design
- Intuitive checkbox interface for role selection
- Real-time user overview showing current capabilities
- Visual feedback and status indicators
- Responsive design for all screen sizes

### ✅ **User Management Features**
- Users with Capabilities section showing all users with Amelia access
- Sortable table with user ID, username, email, and active capabilities
- Direct links to user edit pages
- Performance-optimized (limits to 100 users)

## Installation

1. Upload the `amelia-role-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Navigate to **Settings > Amelia Role Access**
4. Configure role and user access settings

## Configuration

### **Role-Based Access**
1. Go to **Settings > Amelia Role Access**
2. Check boxes next to roles that should have Amelia access
3. Staff role is always enabled automatically
4. Click **Save Changes** to apply

### **Individual User Access**  
1. In the **Individual Users** section, enter comma-separated user IDs
2. Example: `4, 15, 23, 156`
3. Users will be validated automatically
4. Invalid user IDs are filtered out

### **Force Override**
If users with assigned roles still cannot access Amelia:
1. Enable **"Force override Amelia capabilities via filter"**
2. This uses WordPress capability filters to ensure access
3. Recommended for complex permission setups

## Plugin Details

- **Author:** SPARKWEB Studio
- **Author URI:** https://sparkwebstudio.com
- **Plugin URI:** https://sparkwebstudio.com/projects/amelia-role-access
- **Version:** 1.1.0
- **Requires:** WordPress 5.0+
- **Tested up to:** WordPress 6.4
- **License:** GPL v2 or later

## Changelog

### Version 1.1.0 (2025-06-11)
- **🔧 Fixed:** Fatal error with undefined capability arrays
- **✨ Enhanced:** Comprehensive capability system with 85+ capabilities
- **🎨 Improved:** Modern, responsive admin interface styling  
- **🧹 Cleaned:** Removed debug functionality to prevent log flooding
- **⚡ Optimized:** Better performance and error handling
- **📱 Responsive:** Mobile-friendly admin interface
- **🔒 Security:** Enhanced input validation and sanitization

### Version 1.0.0 (2025-06-10)
- Initial release with core functionality
- Basic role and user management
- Debug system for troubleshooting
- Force override capability system

## Support

For support and updates, visit [SPARKWEB Studio](https://sparkwebstudio.com) or contact the plugin author.

## Requirements

- WordPress 5.0 or higher
- AmeliaWP booking plugin (any version)
- PHP 7.4 or higher

## Security

This plugin follows WordPress security best practices:
- Nonce verification for all form submissions
- Capability checks (`manage_options`) for admin access
- Input sanitization and output escaping
- SQL injection prevention
- XSS protection

## License

This plugin is licensed under the GPL v2 or later license. 