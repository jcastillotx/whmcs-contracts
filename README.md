# WHMCS Recurring Contract Billing Module

A comprehensive WHMCS addon module that allows you to offer products and services through fixed-term contracts with customizable billing cycles, contract signing, and renewal management.

## Features

### Contract Management
- Create and manage contract templates with multiple languages
- Fixed-term contracts from 1 month to 10 years
- Custom contract duration and renewal options
- Discount values and penalty amounts for early termination
- Trial period and grace period for penalty-free cancellation

### Signing Methods
- **Checkbox Agreement**: Simple checkbox to accept terms
- **Electronic Signature**: Canvas-based signature pad for digital signatures
- **File Upload**: Upload signed PDF/image documents
- Configurable per template

### Admin Area Features
- Dashboard with statistics and recent activity
- Contract templates management with rich text editor
- View and manage all signed contracts
- Product-to-template associations
- Email templates customization
- Activity logs with retention settings
- Comprehensive settings panel

### Client Area Features
- View all contracts and their status
- Sign pending contracts with multiple methods
- Download contracts
- View signature history

### Automation
- Automatic contract creation on order checkout
- Renewal reminders via email
- Auto-renewal support
- Contract expiration handling
- Early termination penalties

## Installation

1. Upload the `modules/addons/recurring_contracts` folder to your WHMCS `modules/addons/` directory.

2. In WHMCS Admin Area, navigate to:
   - **Setup** > **Addon Modules**

3. Find "Recurring Contract Billing" and click **Activate**.

4. Configure the module settings:
   - Enable Electronic Signatures
   - Enable File Upload
   - Set Default Contract Duration
   - Configure Reminder Days
   - Set Grace Period and Trial Period
   - Configure Log Retention

5. Grant access to admin roles as needed.

## Directory Structure

```
modules/addons/recurring_contracts/
├── recurring_contracts.php      # Main module file
├── hooks.php                    # WHMCS hooks integration
├── lib/
│   ├── AdminController.php      # Admin area controller
│   └── ClientController.php     # Client area controller
├── templates/
│   └── client/
│       ├── contracts_list.tpl
│       ├── contracts_view.tpl
│       ├── contracts_sign.tpl
│       └── ...
├── lang/
│   └── english.php              # Language file
└── assets/
    ├── css/
    └── js/
```

## Usage

### Creating Contract Templates

1. Go to **Addons** > **Recurring Contract Billing** > **Contract Templates**
2. Click **Add Template**
3. Fill in the template details:
   - **Name**: Template identifier
   - **Language**: Template language
   - **Content**: Contract text with variables
   - **Duration**: Contract length in months
   - **Renewal Type**: Auto, Manual, or None
   - **Signing Method**: How clients sign
   - **Discount/Penalty**: Optional financial terms

### Available Template Variables

Use these variables in contract content:
- `{$client_name}` - Client's full name
- `{$client_email}` - Client's email
- `{$client_company}` - Client's company name
- `{$service_name}` - Product/service name
- `{$contract_number}` - Unique contract number
- `{$start_date}` - Contract start date
- `{$end_date}` - Contract end date
- `{$duration_months}` - Contract duration
- `{$company_name}` - Your company name

### Associating Products

1. Go to **Products** in the module
2. Select a contract template for each product
3. Configure:
   - **Required**: Contract must be signed
   - **Show on Order**: Display during checkout

### Managing Contracts

- **View**: See full contract details and signature
- **Send**: Email contract link to client
- **Cancel**: Terminate contract with optional penalty
- **Add Notes**: Admin notes on contracts

### Client Workflow

1. Client places order for a product with contract
2. Contract is created automatically (status: pending)
3. Client receives email with signing link
4. Client views and signs contract
5. Contract becomes active
6. Renewal reminders sent before expiration
7. Auto-renew or expire based on settings

## Email Templates

The module includes these email templates:
- **Contract Pending**: Sent when contract awaits signature
- **Contract Signed**: Confirmation after signing
- **Renewal Reminder**: Sent before expiration
- **Contract Expired**: Notification when contract expires

## Hooks Integration

The module integrates with these WHMCS hooks:
- `AfterShoppingCartCheckout` - Create contracts on order
- `ServiceDelete` - Cancel contracts when service deleted
- `CancellationRequest` - Handle early termination
- `DailyCronJob` - Process expirations and reminders
- `ClientAreaPrimarySidebar` - Add navigation link
- `AdminServiceEdit` - Show contracts on service page

## Database Tables

- `mod_recurring_contracts_templates` - Contract templates
- `mod_recurring_contracts_products` - Product associations
- `mod_recurring_contracts` - Signed contracts
- `mod_recurring_contracts_logs` - Activity logs
- `mod_recurring_contracts_settings` - Module settings
- `mod_recurring_contracts_emails` - Email templates

## Requirements

- WHMCS 8.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Support

For issues and feature requests, please open an issue on GitHub.

## License

Proprietary - All rights reserved.

## Changelog

### Version 1.0.0
- Initial release
- Contract templates management
- Multiple signing methods (checkbox, signature, file upload)
- Product associations
- Email notifications
- Automatic contract creation
- Renewal management
- Activity logging
