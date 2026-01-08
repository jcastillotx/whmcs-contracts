<?php
/**
 * Recurring Contract Billing Module for WHMCS
 *
 * This module allows you to offer products and services through fixed-term contracts
 * with customizable billing cycles, contract signing, and renewal management.
 *
 * @package    WHMCS
 * @author     Your Company
 * @copyright  Copyright (c) 2024
 * @license    Proprietary
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module configuration
 */
function recurring_contracts_config()
{
    return [
        'name' => 'Recurring Contract Billing',
        'description' => 'Offer products and services through fixed-term contracts with customizable billing cycles, contract signing, and renewal management.',
        'version' => '1.0.0',
        'author' => 'Your Company',
        'language' => 'english',
        'fields' => [
            'enable_signatures' => [
                'FriendlyName' => 'Enable Electronic Signatures',
                'Type' => 'yesno',
                'Description' => 'Allow clients to sign contracts electronically',
                'Default' => 'yes',
            ],
            'enable_file_upload' => [
                'FriendlyName' => 'Enable File Upload',
                'Type' => 'yesno',
                'Description' => 'Allow clients to upload signed contract files',
                'Default' => 'yes',
            ],
            'default_contract_duration' => [
                'FriendlyName' => 'Default Contract Duration (months)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '12',
                'Description' => 'Default contract duration in months',
            ],
            'reminder_days' => [
                'FriendlyName' => 'Renewal Reminder Days',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '30',
                'Description' => 'Days before contract expiry to send reminder',
            ],
            'grace_period_days' => [
                'FriendlyName' => 'Grace Period Days',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '14',
                'Description' => 'Days before expiry for penalty-free cancellation',
            ],
            'trial_period_days' => [
                'FriendlyName' => 'Trial Period Days',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '14',
                'Description' => 'Days after signing for penalty-free cancellation',
            ],
            'log_retention_days' => [
                'FriendlyName' => 'Log Retention Days',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '365',
                'Description' => 'Days to keep logs before auto-deletion',
            ],
        ],
    ];
}

/**
 * Module activation
 */
function recurring_contracts_activate()
{
    try {
        // Create contract templates table
        if (!Capsule::schema()->hasTable('mod_recurring_contracts_templates')) {
            Capsule::schema()->create('mod_recurring_contracts_templates', function ($table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->longText('content');
                $table->string('language', 50)->default('english');
                $table->integer('duration_months')->default(12);
                $table->enum('renewal_type', ['auto', 'manual', 'none'])->default('auto');
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('penalty_amount', 10, 2)->default(0);
                $table->enum('penalty_type', ['fixed', 'percent', 'remaining'])->default('fixed');
                $table->enum('signing_method', ['checkbox', 'signature', 'file_upload', 'any'])->default('checkbox');
                $table->boolean('require_signature')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Create product-template associations table
        if (!Capsule::schema()->hasTable('mod_recurring_contracts_products')) {
            Capsule::schema()->create('mod_recurring_contracts_products', function ($table) {
                $table->increments('id');
                $table->integer('product_id')->unsigned();
                $table->integer('template_id')->unsigned();
                $table->boolean('required')->default(true);
                $table->boolean('show_on_order')->default(true);
                $table->timestamps();
                $table->unique(['product_id', 'template_id']);
            });
        }

        // Create contracts table (signed contracts)
        if (!Capsule::schema()->hasTable('mod_recurring_contracts')) {
            Capsule::schema()->create('mod_recurring_contracts', function ($table) {
                $table->increments('id');
                $table->integer('template_id')->unsigned();
                $table->integer('client_id')->unsigned();
                $table->integer('service_id')->unsigned()->nullable();
                $table->integer('invoice_id')->unsigned()->nullable();
                $table->string('contract_number', 50)->unique();
                $table->longText('content');
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['pending', 'active', 'expired', 'cancelled', 'terminated'])->default('pending');
                $table->enum('signing_method', ['checkbox', 'signature', 'file_upload'])->nullable();
                $table->dateTime('signed_at')->nullable();
                $table->string('signed_ip', 45)->nullable();
                $table->text('signature_data')->nullable();
                $table->string('uploaded_file', 255)->nullable();
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('penalty_amount', 10, 2)->default(0);
                $table->enum('penalty_type', ['fixed', 'percent', 'remaining'])->default('fixed');
                $table->enum('renewal_type', ['auto', 'manual', 'none'])->default('auto');
                $table->boolean('renewal_reminder_sent')->default(false);
                $table->text('admin_notes')->nullable();
                $table->string('cancellation_reason', 500)->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->integer('cancelled_by')->unsigned()->nullable();
                $table->timestamps();
                $table->index(['client_id']);
                $table->index(['service_id']);
                $table->index(['status']);
                $table->index(['end_date']);
            });
        }

        // Create contract logs table
        if (!Capsule::schema()->hasTable('mod_recurring_contracts_logs')) {
            Capsule::schema()->create('mod_recurring_contracts_logs', function ($table) {
                $table->increments('id');
                $table->integer('contract_id')->unsigned()->nullable();
                $table->integer('client_id')->unsigned()->nullable();
                $table->integer('admin_id')->unsigned()->nullable();
                $table->enum('action', ['created', 'signed', 'renewed', 'cancelled', 'terminated', 'expired', 'modified', 'reminder_sent', 'viewed'])->default('created');
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
                $table->index(['contract_id']);
                $table->index(['client_id']);
                $table->index(['created_at']);
            });
        }

        // Create settings table for additional configuration
        if (!Capsule::schema()->hasTable('mod_recurring_contracts_settings')) {
            Capsule::schema()->create('mod_recurring_contracts_settings', function ($table) {
                $table->increments('id');
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }

        // Create email templates table
        if (!Capsule::schema()->hasTable('mod_recurring_contracts_emails')) {
            Capsule::schema()->create('mod_recurring_contracts_emails', function ($table) {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->string('subject', 255);
                $table->longText('body');
                $table->string('language', 50)->default('english');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Insert default email templates
        $emailTemplates = [
            [
                'name' => 'contract_pending',
                'subject' => 'Contract Awaiting Your Signature',
                'body' => '<p>Dear {$client_name},</p>
<p>A contract is awaiting your signature for the following service:</p>
<p><strong>Service:</strong> {$service_name}<br>
<strong>Contract:</strong> {$contract_name}<br>
<strong>Duration:</strong> {$contract_duration} months</p>
<p>Please <a href="{$sign_url}">click here</a> to review and sign your contract.</p>
<p>Best regards,<br>{$company_name}</p>',
            ],
            [
                'name' => 'contract_signed',
                'subject' => 'Contract Successfully Signed',
                'body' => '<p>Dear {$client_name},</p>
<p>Thank you for signing your contract. Here are the details:</p>
<p><strong>Contract Number:</strong> {$contract_number}<br>
<strong>Service:</strong> {$service_name}<br>
<strong>Start Date:</strong> {$start_date}<br>
<strong>End Date:</strong> {$end_date}</p>
<p>You can view your contract at any time from your client area.</p>
<p>Best regards,<br>{$company_name}</p>',
            ],
            [
                'name' => 'contract_renewal_reminder',
                'subject' => 'Your Contract is Expiring Soon',
                'body' => '<p>Dear {$client_name},</p>
<p>Your contract is expiring soon:</p>
<p><strong>Contract Number:</strong> {$contract_number}<br>
<strong>Service:</strong> {$service_name}<br>
<strong>Expiry Date:</strong> {$end_date}</p>
<p>{if $renewal_type == "auto"}Your contract will be automatically renewed.{else}Please contact us if you wish to renew your contract.{/if}</p>
<p>Best regards,<br>{$company_name}</p>',
            ],
            [
                'name' => 'contract_expired',
                'subject' => 'Your Contract Has Expired',
                'body' => '<p>Dear {$client_name},</p>
<p>Your contract has expired:</p>
<p><strong>Contract Number:</strong> {$contract_number}<br>
<strong>Service:</strong> {$service_name}</p>
<p>Please contact us if you wish to renew your contract.</p>
<p>Best regards,<br>{$company_name}</p>',
            ],
        ];

        foreach ($emailTemplates as $template) {
            Capsule::table('mod_recurring_contracts_emails')->insertOrIgnore($template);
        }

        // Insert default settings
        $defaultSettings = [
            ['setting_key' => 'enable_logging', 'setting_value' => '1'],
            ['setting_key' => 'log_views', 'setting_value' => '1'],
            ['setting_key' => 'log_signatures', 'setting_value' => '1'],
            ['setting_key' => 'log_modifications', 'setting_value' => '1'],
            ['setting_key' => 'signature_canvas_width', 'setting_value' => '400'],
            ['setting_key' => 'signature_canvas_height', 'setting_value' => '200'],
            ['setting_key' => 'allowed_upload_extensions', 'setting_value' => 'pdf,jpg,jpeg,png'],
            ['setting_key' => 'max_upload_size_mb', 'setting_value' => '10'],
            ['setting_key' => 'contract_number_prefix', 'setting_value' => 'CTR-'],
            ['setting_key' => 'contract_number_format', 'setting_value' => '{prefix}{year}{month}-{id}'],
        ];

        foreach ($defaultSettings as $setting) {
            Capsule::table('mod_recurring_contracts_settings')->insertOrIgnore($setting);
        }

        return [
            'status' => 'success',
            'description' => 'Recurring Contract Billing module has been activated successfully.',
        ];

    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to activate module: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation
 */
function recurring_contracts_deactivate()
{
    try {
        // Drop all module tables
        Capsule::schema()->dropIfExists('mod_recurring_contracts_logs');
        Capsule::schema()->dropIfExists('mod_recurring_contracts');
        Capsule::schema()->dropIfExists('mod_recurring_contracts_products');
        Capsule::schema()->dropIfExists('mod_recurring_contracts_templates');
        Capsule::schema()->dropIfExists('mod_recurring_contracts_emails');
        Capsule::schema()->dropIfExists('mod_recurring_contracts_settings');

        return [
            'status' => 'success',
            'description' => 'Recurring Contract Billing module has been deactivated successfully.',
        ];

    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to deactivate module: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module upgrade
 */
function recurring_contracts_upgrade($vars)
{
    $currentVersion = $vars['version'];

    // Add upgrade logic here for future versions
    // Example:
    // if (version_compare($currentVersion, '1.1.0', '<')) {
    //     // Upgrade to 1.1.0
    // }

    return [
        'status' => 'success',
        'description' => 'Module upgraded successfully.',
    ];
}

/**
 * Admin area output
 */
function recurring_contracts_output($vars)
{
    // Load required classes
    require_once __DIR__ . '/lib/AdminController.php';

    $controller = new \RecurringContracts\AdminController($vars);
    echo $controller->dispatch();
}

/**
 * Admin area sidebar
 */
function recurring_contracts_sidebar($vars)
{
    $modulelink = $vars['modulelink'];

    return [
        'Dashboard' => [
            'icon' => 'fa-tachometer-alt',
            'link' => $modulelink,
        ],
        'Contract Templates' => [
            'icon' => 'fa-file-contract',
            'link' => $modulelink . '&action=templates',
            'children' => [
                'All Templates' => $modulelink . '&action=templates',
                'Add New' => $modulelink . '&action=template_add',
            ],
        ],
        'Contracts' => [
            'icon' => 'fa-file-signature',
            'link' => $modulelink . '&action=contracts',
            'children' => [
                'All Contracts' => $modulelink . '&action=contracts',
                'Pending' => $modulelink . '&action=contracts&status=pending',
                'Active' => $modulelink . '&action=contracts&status=active',
                'Expired' => $modulelink . '&action=contracts&status=expired',
            ],
        ],
        'Products' => [
            'icon' => 'fa-box',
            'link' => $modulelink . '&action=products',
        ],
        'Email Templates' => [
            'icon' => 'fa-envelope',
            'link' => $modulelink . '&action=emails',
        ],
        'Logs' => [
            'icon' => 'fa-history',
            'link' => $modulelink . '&action=logs',
        ],
        'Settings' => [
            'icon' => 'fa-cog',
            'link' => $modulelink . '&action=settings',
        ],
    ];
}

/**
 * Client area output
 */
function recurring_contracts_clientarea($vars)
{
    require_once __DIR__ . '/lib/ClientController.php';

    $controller = new \RecurringContracts\ClientController($vars);
    return $controller->dispatch();
}
