<?php
/**
 * Hooks for Recurring Contract Billing Module
 *
 * These hooks integrate the contract system with WHMCS order and service management.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Hook: AfterShoppingCartCheckout
 * Creates contracts for products that require them after checkout
 */
add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $orderId = $vars['OrderID'];
    $invoiceId = $vars['InvoiceID'];
    $clientId = $vars['UserID'];

    // Get order items
    $orderItems = Capsule::table('tblhosting')
        ->where('orderid', $orderId)
        ->get();

    foreach ($orderItems as $service) {
        // Check if product requires a contract
        $productContract = Capsule::table('mod_recurring_contracts_products')
            ->where('product_id', $service->packageid)
            ->first();

        if (!$productContract) {
            continue;
        }

        // Get template
        $template = Capsule::table('mod_recurring_contracts_templates')
            ->where('id', $productContract->template_id)
            ->where('is_active', 1)
            ->first();

        if (!$template) {
            continue;
        }

        // Check if contract already exists for this service
        $existingContract = Capsule::table('mod_recurring_contracts')
            ->where('service_id', $service->id)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingContract) {
            continue;
        }

        // Get client info
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if (!$client) {
            continue;
        }

        // Generate contract number
        $contractNumber = generateContractNumber();

        // Calculate dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . $template->duration_months . ' months'));

        // Get service/product info
        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
        $serviceName = $product ? $product->name : 'Service';
        if ($service->domain) {
            $serviceName .= ' (' . $service->domain . ')';
        }

        // Get company name
        $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

        // Process content with variables
        $content = str_replace(
            [
                '{$client_name}',
                '{$client_email}',
                '{$client_company}',
                '{$service_name}',
                '{$contract_number}',
                '{$start_date}',
                '{$end_date}',
                '{$duration_months}',
                '{$company_name}',
            ],
            [
                $client->firstname . ' ' . $client->lastname,
                $client->email,
                $client->companyname ?: '',
                $serviceName,
                $contractNumber,
                date('F d, Y', strtotime($startDate)),
                date('F d, Y', strtotime($endDate)),
                $template->duration_months,
                $companyName,
            ],
            $template->content
        );

        // Create contract
        $contractId = Capsule::table('mod_recurring_contracts')->insertGetId([
            'template_id' => $template->id,
            'client_id' => $clientId,
            'service_id' => $service->id,
            'invoice_id' => $invoiceId,
            'contract_number' => $contractNumber,
            'content' => $content,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'discount_percent' => $template->discount_percent,
            'penalty_amount' => $template->penalty_amount,
            'penalty_type' => $template->penalty_type,
            'renewal_type' => $template->renewal_type,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Log creation
        Capsule::table('mod_recurring_contracts_logs')->insert([
            'contract_id' => $contractId,
            'client_id' => $clientId,
            'action' => 'created',
            'details' => 'Contract created automatically from order #' . $orderId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send contract email
        sendContractPendingEmail($contractId);
    }
});

/**
 * Hook: ServiceDelete
 * Handle contract when service is deleted
 */
add_hook('ServiceDelete', 1, function ($vars) {
    $serviceId = $vars['serviceid'];

    // Cancel any pending contracts for this service
    $contracts = Capsule::table('mod_recurring_contracts')
        ->where('service_id', $serviceId)
        ->where('status', 'pending')
        ->get();

    foreach ($contracts as $contract) {
        Capsule::table('mod_recurring_contracts')
            ->where('id', $contract->id)
            ->update([
                'status' => 'cancelled',
                'cancellation_reason' => 'Service deleted',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Capsule::table('mod_recurring_contracts_logs')->insert([
            'contract_id' => $contract->id,
            'client_id' => $contract->client_id,
            'action' => 'cancelled',
            'details' => 'Contract cancelled due to service deletion',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
});

/**
 * Hook: CancellationRequest
 * Handle contract when cancellation is requested
 */
add_hook('CancellationRequest', 1, function ($vars) {
    $serviceId = $vars['relid'];
    $reason = $vars['reason'];

    // Check for active contracts
    $contracts = Capsule::table('mod_recurring_contracts')
        ->where('service_id', $serviceId)
        ->whereIn('status', ['pending', 'active'])
        ->get();

    foreach ($contracts as $contract) {
        // Calculate penalty if applicable
        if ($contract->status === 'active' && $contract->penalty_amount > 0) {
            // Check if within trial period
            $signedAt = $contract->signed_at ? strtotime($contract->signed_at) : 0;
            $trialPeriodDays = Capsule::table('tblconfiguration')
                ->where('setting', 'addon_recurring_contracts_trial_period_days')
                ->value('value') ?: 14;

            $gracePeriodDays = Capsule::table('tblconfiguration')
                ->where('setting', 'addon_recurring_contracts_grace_period_days')
                ->value('value') ?: 14;

            $withinTrialPeriod = (time() - $signedAt) < ($trialPeriodDays * 86400);
            $withinGracePeriod = (strtotime($contract->end_date) - time()) < ($gracePeriodDays * 86400);

            if (!$withinTrialPeriod && !$withinGracePeriod) {
                // Apply penalty - create invoice item
                $penaltyAmount = calculatePenalty($contract);

                if ($penaltyAmount > 0) {
                    localAPI('AddBillableItem', [
                        'clientid' => $contract->client_id,
                        'description' => 'Early termination penalty for contract ' . $contract->contract_number,
                        'amount' => $penaltyAmount,
                        'invoiceaction' => 'nextinvoice',
                    ]);

                    Capsule::table('mod_recurring_contracts_logs')->insert([
                        'contract_id' => $contract->id,
                        'client_id' => $contract->client_id,
                        'action' => 'terminated',
                        'details' => 'Early termination penalty applied: $' . number_format($penaltyAmount, 2),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // Update contract status
        Capsule::table('mod_recurring_contracts')
            ->where('id', $contract->id)
            ->update([
                'status' => 'terminated',
                'cancellation_reason' => $reason,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Capsule::table('mod_recurring_contracts_logs')->insert([
            'contract_id' => $contract->id,
            'client_id' => $contract->client_id,
            'action' => 'terminated',
            'details' => 'Contract terminated due to cancellation request: ' . substr($reason, 0, 200),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
});

/**
 * Hook: ClientAreaPage
 * Add contracts link to client area navigation
 */
add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar) {
    if (!isset($_SESSION['uid'])) {
        return;
    }

    // Check if there's a "Services" panel
    $servicesPanel = $primarySidebar->getChild('Service Details Overview');
    if (!is_null($servicesPanel)) {
        // Add contracts link
        $servicesPanel->addChild('my_contracts', [
            'name' => 'My Contracts',
            'label' => 'My Contracts',
            'uri' => 'index.php?m=recurring_contracts',
            'order' => 99,
            'icon' => 'fa-file-contract',
        ]);
    }
});

/**
 * Hook: ClientAreaSecondarySidebar
 * Show pending contracts notification
 */
add_hook('ClientAreaSecondarySidebar', 1, function ($secondarySidebar) {
    if (!isset($_SESSION['uid'])) {
        return;
    }

    $clientId = $_SESSION['uid'];

    // Check for pending contracts
    $pendingCount = Capsule::table('mod_recurring_contracts')
        ->where('client_id', $clientId)
        ->where('status', 'pending')
        ->count();

    if ($pendingCount > 0) {
        $actionsPanel = $secondarySidebar->getChild('Actions');
        if (!is_null($actionsPanel)) {
            $actionsPanel->addChild('pending_contracts', [
                'name' => 'Pending Contracts',
                'label' => 'Sign Pending Contracts (' . $pendingCount . ')',
                'uri' => 'index.php?m=recurring_contracts',
                'order' => 1,
                'icon' => 'fa-exclamation-triangle',
                'attributes' => [
                    'class' => 'btn btn-warning btn-sm',
                ],
            ]);
        }
    }
});

/**
 * Hook: DailyCronJob
 * Handle contract expiration and renewal reminders
 */
add_hook('DailyCronJob', 1, function ($vars) {
    // Process expired contracts
    $expiredContracts = Capsule::table('mod_recurring_contracts')
        ->where('status', 'active')
        ->where('end_date', '<', date('Y-m-d'))
        ->get();

    foreach ($expiredContracts as $contract) {
        if ($contract->renewal_type === 'auto') {
            // Auto-renew contract
            renewContract($contract);
        } else {
            // Mark as expired
            Capsule::table('mod_recurring_contracts')
                ->where('id', $contract->id)
                ->update([
                    'status' => 'expired',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            Capsule::table('mod_recurring_contracts_logs')->insert([
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'action' => 'expired',
                'details' => 'Contract expired',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Send expiry notification
            sendContractExpiredEmail($contract->id);
        }
    }

    // Send renewal reminders
    $reminderDays = Capsule::table('tblconfiguration')
        ->where('setting', 'addon_recurring_contracts_reminder_days')
        ->value('value') ?: 30;

    $reminderDate = date('Y-m-d', strtotime('+' . $reminderDays . ' days'));

    $expiringContracts = Capsule::table('mod_recurring_contracts')
        ->where('status', 'active')
        ->where('end_date', '<=', $reminderDate)
        ->where('end_date', '>', date('Y-m-d'))
        ->where('renewal_reminder_sent', 0)
        ->get();

    foreach ($expiringContracts as $contract) {
        sendRenewalReminderEmail($contract->id);

        Capsule::table('mod_recurring_contracts')
            ->where('id', $contract->id)
            ->update([
                'renewal_reminder_sent' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    // Clean up old logs
    $logRetentionDays = Capsule::table('tblconfiguration')
        ->where('setting', 'addon_recurring_contracts_log_retention_days')
        ->value('value') ?: 365;

    $cutoffDate = date('Y-m-d H:i:s', strtotime('-' . $logRetentionDays . ' days'));

    Capsule::table('mod_recurring_contracts_logs')
        ->where('created_at', '<', $cutoffDate)
        ->delete();
});

/**
 * Hook: AdminServiceEdit
 * Show contract info on service edit page
 */
add_hook('AdminServiceEdit', 1, function ($vars) {
    $serviceId = $vars['serviceid'];

    $contracts = Capsule::table('mod_recurring_contracts as c')
        ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
        ->select('c.*', 't.name as template_name')
        ->where('c.service_id', $serviceId)
        ->orderBy('c.created_at', 'desc')
        ->get();

    if ($contracts->isEmpty()) {
        return;
    }

    $output = '<div class="tab-content admin-tabs">';
    $output .= '<div class="tab-pane active" id="contracts">';
    $output .= '<h4><i class="fa fa-file-contract"></i> Contracts</h4>';
    $output .= '<table class="table table-striped">';
    $output .= '<thead><tr><th>Contract #</th><th>Template</th><th>Status</th><th>Start</th><th>End</th><th></th></tr></thead>';
    $output .= '<tbody>';

    foreach ($contracts as $contract) {
        $statusClass = [
            'pending' => 'warning',
            'active' => 'success',
            'expired' => 'default',
            'cancelled' => 'danger',
            'terminated' => 'danger',
        ][$contract->status] ?? 'default';

        $output .= '<tr>';
        $output .= '<td>' . htmlspecialchars($contract->contract_number) . '</td>';
        $output .= '<td>' . htmlspecialchars($contract->template_name) . '</td>';
        $output .= '<td><span class="label label-' . $statusClass . '">' . ucfirst($contract->status) . '</span></td>';
        $output .= '<td>' . date('M d, Y', strtotime($contract->start_date)) . '</td>';
        $output .= '<td>' . date('M d, Y', strtotime($contract->end_date)) . '</td>';
        $output .= '<td><a href="addonmodules.php?module=recurring_contracts&action=contract_view&id=' . $contract->id . '" class="btn btn-xs btn-default">View</a></td>';
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';
    $output .= '</div></div>';

    return $output;
});

/**
 * Helper function: Generate contract number
 */
function generateContractNumber()
{
    $settings = Capsule::table('mod_recurring_contracts_settings')
        ->pluck('setting_value', 'setting_key')
        ->toArray();

    $prefix = $settings['contract_number_prefix'] ?? 'CTR-';
    $format = $settings['contract_number_format'] ?? '{prefix}{year}{month}-{id}';

    $lastId = Capsule::table('mod_recurring_contracts')->max('id') ?? 0;
    $nextId = $lastId + 1;

    $variables = [
        '{prefix}' => $prefix,
        '{year}' => date('Y'),
        '{month}' => date('m'),
        '{day}' => date('d'),
        '{id}' => str_pad($nextId, 5, '0', STR_PAD_LEFT),
    ];

    return str_replace(array_keys($variables), array_values($variables), $format);
}

/**
 * Helper function: Calculate penalty amount
 */
function calculatePenalty($contract)
{
    switch ($contract->penalty_type) {
        case 'fixed':
            return (float) $contract->penalty_amount;

        case 'percent':
            // Get service recurring amount
            $service = Capsule::table('tblhosting')->where('id', $contract->service_id)->first();
            if ($service) {
                return ($service->amount * $contract->penalty_amount) / 100;
            }
            return 0;

        case 'remaining':
            // Calculate remaining months and multiply by monthly rate
            $service = Capsule::table('tblhosting')->where('id', $contract->service_id)->first();
            if ($service) {
                $endDate = new DateTime($contract->end_date);
                $now = new DateTime();
                $interval = $now->diff($endDate);
                $remainingMonths = ($interval->y * 12) + $interval->m + ($interval->d > 0 ? 1 : 0);
                return $service->amount * max(0, $remainingMonths);
            }
            return 0;

        default:
            return 0;
    }
}

/**
 * Helper function: Renew contract
 */
function renewContract($contract)
{
    // Get template for duration
    $template = Capsule::table('mod_recurring_contracts_templates')
        ->where('id', $contract->template_id)
        ->first();

    if (!$template) {
        return false;
    }

    $newStartDate = date('Y-m-d', strtotime($contract->end_date . ' +1 day'));
    $newEndDate = date('Y-m-d', strtotime($newStartDate . ' +' . $template->duration_months . ' months'));

    // Update contract
    Capsule::table('mod_recurring_contracts')
        ->where('id', $contract->id)
        ->update([
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'renewal_reminder_sent' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    // Log renewal
    Capsule::table('mod_recurring_contracts_logs')->insert([
        'contract_id' => $contract->id,
        'client_id' => $contract->client_id,
        'action' => 'renewed',
        'details' => 'Contract auto-renewed. New end date: ' . $newEndDate,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

/**
 * Helper function: Send contract pending email
 */
function sendContractPendingEmail($contractId)
{
    $contract = Capsule::table('mod_recurring_contracts as c')
        ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
        ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
        ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
        ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
        ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.email', 't.name as template_name', 't.duration_months', 'p.name as product_name')
        ->where('c.id', $contractId)
        ->first();

    if (!$contract) {
        return false;
    }

    $emailTemplate = Capsule::table('mod_recurring_contracts_emails')
        ->where('name', 'contract_pending')
        ->where('is_active', 1)
        ->first();

    if (!$emailTemplate) {
        return false;
    }

    $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');

    $token = hash('sha256', $contractId . 'recurring_contracts_salt_' . date('Y'));
    $signUrl = rtrim($systemUrl, '/') . '/index.php?m=recurring_contracts&action=sign&id=' . $contractId . '&token=' . $token;

    $variables = [
        '{$client_name}' => $contract->firstname . ' ' . $contract->lastname,
        '{$client_email}' => $contract->email,
        '{$service_name}' => $contract->product_name ?: 'N/A',
        '{$contract_number}' => $contract->contract_number,
        '{$contract_name}' => $contract->template_name,
        '{$start_date}' => date('F d, Y', strtotime($contract->start_date)),
        '{$end_date}' => date('F d, Y', strtotime($contract->end_date)),
        '{$contract_duration}' => $contract->duration_months,
        '{$sign_url}' => $signUrl,
        '{$company_name}' => $companyName,
    ];

    $subject = str_replace(array_keys($variables), array_values($variables), $emailTemplate->subject);
    $body = str_replace(array_keys($variables), array_values($variables), $emailTemplate->body);

    $result = localAPI('SendEmail', [
        'customtype' => 'general',
        'customsubject' => $subject,
        'custommessage' => $body,
        'id' => $contract->client_id,
    ]);

    return $result['result'] === 'success';
}

/**
 * Helper function: Send renewal reminder email
 */
function sendRenewalReminderEmail($contractId)
{
    $contract = Capsule::table('mod_recurring_contracts as c')
        ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
        ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
        ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
        ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
        ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.email', 't.name as template_name', 'p.name as product_name')
        ->where('c.id', $contractId)
        ->first();

    if (!$contract) {
        return false;
    }

    $emailTemplate = Capsule::table('mod_recurring_contracts_emails')
        ->where('name', 'contract_renewal_reminder')
        ->where('is_active', 1)
        ->first();

    if (!$emailTemplate) {
        return false;
    }

    $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

    $variables = [
        '{$client_name}' => $contract->firstname . ' ' . $contract->lastname,
        '{$client_email}' => $contract->email,
        '{$service_name}' => $contract->product_name ?: 'N/A',
        '{$contract_number}' => $contract->contract_number,
        '{$contract_name}' => $contract->template_name,
        '{$start_date}' => date('F d, Y', strtotime($contract->start_date)),
        '{$end_date}' => date('F d, Y', strtotime($contract->end_date)),
        '{$company_name}' => $companyName,
        '{$renewal_type}' => $contract->renewal_type,
    ];

    $subject = str_replace(array_keys($variables), array_values($variables), $emailTemplate->subject);
    $body = str_replace(array_keys($variables), array_values($variables), $emailTemplate->body);

    // Handle Smarty-like conditionals in template
    if ($contract->renewal_type === 'auto') {
        $body = preg_replace('/\{if \$renewal_type == "auto"\}(.*?)\{else\}.*?\{\/if\}/s', '$1', $body);
    } else {
        $body = preg_replace('/\{if \$renewal_type == "auto"\}.*?\{else\}(.*?)\{\/if\}/s', '$1', $body);
    }

    $result = localAPI('SendEmail', [
        'customtype' => 'general',
        'customsubject' => $subject,
        'custommessage' => $body,
        'id' => $contract->client_id,
    ]);

    Capsule::table('mod_recurring_contracts_logs')->insert([
        'contract_id' => $contractId,
        'client_id' => $contract->client_id,
        'action' => 'reminder_sent',
        'details' => 'Renewal reminder email sent',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return $result['result'] === 'success';
}

/**
 * Helper function: Send contract expired email
 */
function sendContractExpiredEmail($contractId)
{
    $contract = Capsule::table('mod_recurring_contracts as c')
        ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
        ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
        ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
        ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
        ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.email', 't.name as template_name', 'p.name as product_name')
        ->where('c.id', $contractId)
        ->first();

    if (!$contract) {
        return false;
    }

    $emailTemplate = Capsule::table('mod_recurring_contracts_emails')
        ->where('name', 'contract_expired')
        ->where('is_active', 1)
        ->first();

    if (!$emailTemplate) {
        return false;
    }

    $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

    $variables = [
        '{$client_name}' => $contract->firstname . ' ' . $contract->lastname,
        '{$client_email}' => $contract->email,
        '{$service_name}' => $contract->product_name ?: 'N/A',
        '{$contract_number}' => $contract->contract_number,
        '{$contract_name}' => $contract->template_name,
        '{$company_name}' => $companyName,
    ];

    $subject = str_replace(array_keys($variables), array_values($variables), $emailTemplate->subject);
    $body = str_replace(array_keys($variables), array_values($variables), $emailTemplate->body);

    $result = localAPI('SendEmail', [
        'customtype' => 'general',
        'customsubject' => $subject,
        'custommessage' => $body,
        'id' => $contract->client_id,
    ]);

    return $result['result'] === 'success';
}
