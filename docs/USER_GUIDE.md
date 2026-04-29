# User Guide - Fayyaz Travels CRM

## Overview

Welcome to the Fayyaz Travels CRM system! This comprehensive guide will help you navigate and effectively use all the features of our Customer Relationship Management platform.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication](#authentication)
3. [Dashboard](#dashboard)
4. [Ticket Management](#ticket-management)
5. [Messaging System](#messaging-system)
6. [Notifications](#notifications)
7. [User Management](#user-management)
8. [File Management](#file-management)
9. [Search and Filters](#search-and-filters)
10. [Keyboard Shortcuts](#keyboard-shortcuts)
11. [Troubleshooting](#troubleshooting)

## Getting Started

### First Time Setup

1. **Access the System**
   - Open your web browser
   - Navigate to: `https://crm.fayyaz.travel`
   - You'll be redirected to the login page

2. **Initial Login**
   - Click "Sign in with Google"
   - Use your Google account credentials
   - Grant necessary permissions when prompted
   - You'll be automatically redirected to the dashboard

3. **Profile Setup**
   - Complete your profile information
   - Upload a profile picture (optional)
   - Set your notification preferences

### System Requirements

- **Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Internet**: Stable internet connection
- **Screen Resolution**: Minimum 1024x768 (recommended 1920x1080)
- **JavaScript**: Must be enabled

## Authentication

### Google OAuth Login

The system uses Google OAuth for secure authentication:

1. **Login Process**
   - Click "Sign in with Google" button
   - Select your Google account
   - Grant permissions to the application
   - You'll be automatically logged in

2. **Session Management**
   - Sessions are automatically maintained
   - You'll stay logged in for 24 hours
   - Sessions expire after inactivity

3. **Logout**
   - Click the user menu (top-right corner)
   - Select "Logout"
   - You'll be redirected to the login page

### Security Features

- **SSL Encryption**: All data is encrypted in transit
- **Session Security**: Secure session management
- **Role-based Access**: Different permissions for different user types

## Dashboard

### Overview

The dashboard provides a comprehensive overview of your CRM activities:

#### Key Metrics
- **Total Tickets**: Number of all tickets
- **Open Tickets**: Tickets currently in progress
- **Closed Tickets**: Completed tickets
- **Unread Messages**: New messages in your inbox
- **Notifications**: Pending notifications

#### Quick Actions
- **Create New Ticket**: Quick access to ticket creation
- **Send Message**: Direct messaging interface
- **View Timeline**: Recent activity feed

### Dashboard Components

#### 1. Statistics Cards
```
┌─────────────────┐ ┌─────────────────┐
│   Open Tickets  │ │  Closed Tickets │
│      15         │ │      42         │
└─────────────────┘ └─────────────────┘
┌─────────────────┐ ┌─────────────────┐
│ Unread Messages │ │ Notifications   │
│       8         │ │       3         │
└─────────────────┘ └─────────────────┘
```

#### 2. Recent Activity Timeline
- Latest ticket updates
- New messages received
- System notifications
- User activity logs

#### 3. Quick Action Buttons
- **+ New Ticket**: Create estimate, supplier, or general ticket
- **📧 Messages**: Access messaging system
- **🔔 Notifications**: View notification center
- **📊 Reports**: Generate reports

### Customization

#### Personalize Dashboard
1. Click "Settings" in the user menu
2. Select "Dashboard Preferences"
3. Choose which widgets to display
4. Arrange widget positions
5. Save your preferences

## Ticket Management

### Ticket Types

The system supports three types of tickets:

#### 1. Estimate Tickets
**Purpose**: Handle travel package estimates and quotations

**Required Fields**:
- Customer Name
- Billing Address
- Email Address
- Contact Number
- Consultant Name
- Service Date
- Package Details
- Number of Persons
- Rate per Person
- Total Amount
- Description

#### 2. Supplier Tickets
**Purpose**: Manage supplier payments and invoice processing

**Required Fields**:
- Travel Date
- Due Date
- Supplier Invoice Currency
- Supplier Local Currency
- Payment Type (Deposit/Full Payment/Balance Payment)
- Bank Details
- Supplier Message

#### 3. General Tickets
**Purpose**: Handle miscellaneous requests and general issues

**Required Fields**:
- Description
- Ticket Subtype (optional)
- Supporting Images (optional)

### Creating Tickets

#### Step-by-Step Process

1. **Access Ticket Creation**
   - Click "New Ticket" on dashboard
   - Or navigate to Tickets → Create New

2. **Select Ticket Type**
   - Choose from Estimate, Supplier, or General
   - Each type has specific fields

3. **Fill Required Information**
   - Complete all mandatory fields (marked with *)
   - Add optional details as needed
   - Upload supporting files if required

4. **Set Priority and Status**
   - **Priority**: LOW, MEDIUM, HIGH, URGENT
   - **Status**: OPEN, IN_PROGRESS, RESOLVED, CLOSED

5. **Submit Ticket**
   - Review all information
   - Click "Create Ticket"
   - Ticket will be saved and notifications sent

#### Priority Levels

- **LOW**: Non-urgent requests (response within 48 hours)
- **MEDIUM**: Standard requests (response within 24 hours)
- **HIGH**: Important requests (response within 4 hours)
- **URGENT**: Critical requests (immediate response)

#### Status Tracking

- **OPEN**: New ticket, awaiting assignment
- **IN_PROGRESS**: Work has begun on the ticket
- **RESOLVED**: Ticket completed, pending verification
- **CLOSED**: Ticket fully completed and closed

### Managing Tickets

#### Viewing Tickets

1. **Ticket List View**
   - Navigate to "Tickets" in the main menu
   - View all tickets in a table format
   - Use filters to narrow down results

2. **Ticket Details**
   - Click on any ticket to view full details
   - See complete information and history
   - Access related messages and files

#### Updating Tickets

1. **Edit Ticket Information**
   - Click "Edit" on any ticket
   - Modify fields as needed
   - Save changes

2. **Change Status**
   - Use the status dropdown
   - Add comments explaining the change
   - Update estimated completion time

3. **Add Comments**
   - Use the comment section
   - Attach files if needed
   - Notify relevant users

#### Ticket Filters and Search

```
Filters Available:
├── Ticket Type (Estimate/Supplier/General)
├── Priority (LOW/MEDIUM/HIGH/URGENT)
├── Status (OPEN/IN_PROGRESS/RESOLVED/CLOSED)
├── Date Range
├── Assigned User
└── Customer Name
```

### Ticket Workflow

#### Standard Workflow
```
1. Create Ticket → OPEN
2. Assign to User → IN_PROGRESS
3. Work on Ticket → IN_PROGRESS
4. Complete Work → RESOLVED
5. Verify Completion → CLOSED
```

#### Quick Actions
- **Duplicate Ticket**: Create similar ticket quickly
- **Export Ticket**: Download ticket details as PDF
- **Share Ticket**: Send ticket link to others
- **Archive Ticket**: Move to archive (admin only)

## Messaging System

### Overview

The messaging system provides real-time communication between users:

#### Features
- **Real-time Messaging**: Instant message delivery
- **File Sharing**: Send images, documents, audio files
- **Read Receipts**: Know when messages are read
- **User Status**: See who's online/offline
- **Message History**: Complete conversation history

### Using Messages

#### Starting a Conversation

1. **Access Messages**
   - Click "Messages" in the main menu
   - Or use the messaging icon in the header

2. **Select Recipient**
   - Choose from user list
   - Search for specific users
   - Start typing to filter

3. **Send Message**
   - Type your message
   - Add attachments if needed
   - Click "Send" or press Enter

#### Message Features

#### Text Messages
- **Formatting**: Basic text formatting supported
- **Emojis**: Use emoji picker for expressions
- **Links**: URLs are automatically clickable

#### File Attachments
- **Supported Types**:
  - Images: JPG, PNG, GIF, WebP (max 10MB)
  - Documents: PDF, DOC, DOCX (max 25MB)
  - Audio: MP3, WAV, M4A (max 50MB)

- **Upload Process**:
  1. Click attachment icon
  2. Select file from device
  3. Wait for upload to complete
  4. File appears in message

#### Message Management

#### Reading Messages
- **Unread Indicator**: Red dot shows unread messages
- **Message Preview**: See first few words in list
- **Timestamp**: When message was sent

#### Message Actions
- **Reply**: Respond to specific message
- **Forward**: Send message to another user
- **Copy**: Copy message text
- **Delete**: Remove message (your own only)

#### Conversation Features

#### Search Messages
- Use search bar in messages
- Search by sender, content, or date
- Results highlight matching text

#### Message History
- Scroll up to see older messages
- Load more messages automatically
- Jump to specific dates

### User Status

#### Online/Offline Indicators
- **Green Dot**: User is currently online
- **Gray Dot**: User is offline
- **Yellow Dot**: User is away (inactive)

#### Status Updates
- Status updates automatically
- Shows "last seen" for offline users
- Real-time status changes

## Notifications

### Notification Center

#### Accessing Notifications
1. Click the bell icon in the header
2. View notification dropdown
3. Click "View All" for full center

#### Notification Types

#### Info Notifications
- **Blue Icon**: General information
- **Examples**: System updates, announcements

#### Success Notifications
- **Green Icon**: Positive outcomes
- **Examples**: Ticket completed, payment received

#### Warning Notifications
- **Yellow Icon**: Important alerts
- **Examples**: Due dates approaching, low priority

#### Error Notifications
- **Red Icon**: Critical issues
- **Examples**: System errors, failed operations

### Managing Notifications

#### Mark as Read
- Click individual notification to mark read
- Use "Mark All Read" for bulk action
- Notifications auto-mark as read when viewed

#### Notification Settings
1. Go to User Settings
2. Select "Notifications"
3. Choose notification preferences:
   - Email notifications
   - Push notifications
   - Sound alerts
   - Desktop notifications

#### Notification Filters
- **All**: Show all notifications
- **Unread**: Show only unread
- **By Type**: Filter by notification type
- **By Date**: Filter by time period

## User Management

### User Roles

#### Admin Users
- **Full Access**: All system features
- **User Management**: Create, edit, delete users
- **System Settings**: Configure system parameters
- **Reports**: Access all reports and analytics

#### Regular Users
- **Limited Access**: Basic CRM features
- **Own Tickets**: Manage their own tickets
- **Messaging**: Send/receive messages
- **Notifications**: Receive system notifications

### Profile Management

#### Edit Profile
1. Click user menu (top-right)
2. Select "Profile"
3. Edit information:
   - Name
   - Email (read-only)
   - Profile picture
   - Contact information

#### Change Password
- Password changes handled through Google OAuth
- No direct password management in system
- Contact admin for account issues

#### Notification Preferences
- **Email Notifications**: Receive emails for important events
- **Push Notifications**: Browser push notifications
- **Sound Alerts**: Audio notifications
- **Desktop Notifications**: System notifications

## File Management

### Uploading Files

#### Supported File Types
- **Images**: JPG, PNG, GIF, WebP
- **Documents**: PDF, DOC, DOCX, TXT
- **Audio**: MP3, WAV, M4A
- **Archives**: ZIP, RAR (for documents)

#### File Size Limits
- **Images**: Maximum 10MB
- **Documents**: Maximum 25MB
- **Audio**: Maximum 50MB
- **Total Upload**: Maximum 100MB per ticket

### File Organization

#### File Categories
- **Ticket Attachments**: Files related to specific tickets
- **Message Attachments**: Files shared in conversations
- **Profile Pictures**: User profile images
- **System Files**: Application files

#### File Management
- **View**: Click to preview/download
- **Download**: Save file to device
- **Delete**: Remove file (if you have permission)
- **Share**: Generate shareable link

### File Security

#### Access Control
- Files are protected by user permissions
- Only authorized users can access files
- File access is logged for security

#### File Validation
- Files are scanned for viruses
- File types are validated
- Malicious files are automatically rejected

## Search and Filters

### Global Search

#### Search Features
- **Quick Search**: Search bar in header
- **Advanced Search**: Detailed search options
- **Search History**: Recent searches
- **Saved Searches**: Frequently used searches

#### Search Categories
- **Tickets**: Search ticket content and metadata
- **Messages**: Search message content
- **Users**: Search user names and emails
- **Files**: Search file names and descriptions

### Advanced Filters

#### Ticket Filters
```
┌─────────────────────────────────────┐
│ Filter Options                      │
├─────────────────────────────────────┤
│ Type: [Estimate ▼] [Supplier] [General] │
│ Priority: [All ▼] [LOW] [MEDIUM] [HIGH] │
│ Status: [All ▼] [OPEN] [IN_PROGRESS]    │
│ Date: [All Time ▼] [Today] [This Week]  │
│ User: [All Users ▼] [Select User]       │
└─────────────────────────────────────┘
```

#### Message Filters
- **Sender**: Filter by message sender
- **Date Range**: Filter by message date
- **Has Attachments**: Show only messages with files
- **Unread Only**: Show only unread messages

#### Export Results
- **CSV Export**: Download filtered results
- **PDF Export**: Generate PDF reports
- **Email Export**: Send results via email

## Keyboard Shortcuts

### Navigation Shortcuts
- **Ctrl + H**: Go to Dashboard
- **Ctrl + T**: Open Tickets
- **Ctrl + M**: Open Messages
- **Ctrl + N**: Open Notifications
- **Ctrl + S**: Open Search

### Ticket Shortcuts
- **Ctrl + N**: Create New Ticket
- **Ctrl + E**: Edit Current Ticket
- **Ctrl + D**: Duplicate Ticket
- **Ctrl + P**: Print Ticket

### Message Shortcuts
- **Ctrl + Enter**: Send Message
- **Ctrl + U**: Upload File
- **Ctrl + F**: Search Messages
- **Ctrl + R**: Reply to Message

### General Shortcuts
- **F1**: Help/Support
- **Ctrl + K**: Quick Search
- **Ctrl + ,**: Open Settings
- **Ctrl + Q**: Logout

### Custom Shortcuts
- **Set Custom Shortcuts**:
  1. Go to Settings
  2. Select "Keyboard Shortcuts"
  3. Assign custom shortcuts
  4. Save preferences

## Troubleshooting

### Common Issues

#### Login Problems
**Issue**: Can't log in with Google
**Solutions**:
1. Clear browser cache and cookies
2. Try incognito/private browsing mode
3. Check internet connection
4. Contact system administrator

#### Message Not Sending
**Issue**: Messages not delivering
**Solutions**:
1. Check internet connection
2. Refresh the page
3. Try sending to different user
4. Check if recipient is online

#### File Upload Issues
**Issue**: Files not uploading
**Solutions**:
1. Check file size (must be under limits)
2. Verify file type is supported
3. Try smaller file size
4. Check browser compatibility

#### Slow Performance
**Issue**: System running slowly
**Solutions**:
1. Clear browser cache
2. Close unnecessary browser tabs
3. Check internet connection
4. Try refreshing the page

### Getting Help

#### Support Channels
- **In-App Help**: Click F1 or Help menu
- **Email Support**: support@fyyz.link
- **Phone Support**: +1-234-567-8900
- **Live Chat**: Available during business hours

#### Reporting Issues
1. **Gather Information**:
   - Screenshot of the issue
   - Steps to reproduce
   - Browser and version
   - Error messages

2. **Submit Report**:
   - Use in-app support form
   - Email with detailed description
   - Include screenshots if possible

#### System Status
- **Check Status**: Visit status.fyyz.link
- **Maintenance Notices**: Posted in notifications
- **Updates**: Announced in system notifications

### Best Practices

#### Data Management
- **Regular Backups**: Important data is automatically backed up
- **File Organization**: Use descriptive file names
- **Clean Up**: Archive old tickets regularly
- **Export Data**: Download important information

#### Communication
- **Clear Messages**: Write clear, concise messages
- **Use Attachments**: Share relevant files
- **Follow Up**: Check for responses regularly
- **Be Professional**: Maintain professional communication

#### Security
- **Logout**: Always logout when done
- **Secure Connection**: Use HTTPS only
- **Password Security**: Keep Google account secure
- **Report Issues**: Report suspicious activity

### Tips and Tricks

#### Productivity Tips
1. **Use Keyboard Shortcuts**: Learn and use shortcuts for faster navigation
2. **Set Up Filters**: Create saved filters for common searches
3. **Use Templates**: Create message templates for common responses
4. **Enable Notifications**: Stay updated with important events

#### Organization Tips
1. **Use Tags**: Organize tickets with consistent naming
2. **Regular Updates**: Update ticket status regularly
3. **File Naming**: Use descriptive file names
4. **Archive Old Data**: Keep workspace clean

#### Communication Tips
1. **Be Responsive**: Reply to messages promptly
2. **Use Clear Language**: Write clear, professional messages
3. **Include Context**: Provide relevant background information
4. **Follow Up**: Check on pending items regularly

This comprehensive user guide should help you effectively use all features of the Fayyaz Travels CRM system. For additional support, please contact the system administrator or refer to the technical documentation. 