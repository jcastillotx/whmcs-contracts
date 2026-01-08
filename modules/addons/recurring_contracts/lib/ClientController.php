<?php
/**
 * Client Controller for Recurring Contract Billing Module
 */

namespace RecurringContracts;

use WHMCS\Database\Capsule;

class ClientController
{
    protected $vars;
    protected $clientId;

    public function __construct($vars)
    {
        $this->vars = $vars;
        $this->clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
    }

    /**
     * Dispatch request to appropriate handler
     */
    public function dispatch()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

        switch ($action) {
            case 'list':
                return $this->listContracts();
            case 'view':
                return $this->viewContract();
            case 'sign':
                return $this->signContract();
            case 'sign_process':
                return $this->processSignature();
            case 'upload':
                return $this->uploadSignedContract();
            case 'download':
                return $this->downloadContract();
            default:
                return $this->listContracts();
        }
    }

    /**
     * List client's contracts
     */
    protected function listContracts()
    {
        if (!$this->clientId) {
            return [
                'pagetitle' => 'My Contracts',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_login_required',
                'requirelogin' => true,
                'vars' => [],
            ];
        }

        $contracts = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select('c.*', 't.name as template_name', 'p.name as product_name', 'h.domain')
            ->where('c.client_id', $this->clientId)
            ->orderBy('c.created_at', 'desc')
            ->get();

        // Get pending contracts count
        $pendingCount = Capsule::table('mod_recurring_contracts')
            ->where('client_id', $this->clientId)
            ->where('status', 'pending')
            ->count();

        return [
            'pagetitle' => 'My Contracts',
            'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
            'templatefile' => 'contracts_list',
            'requirelogin' => true,
            'vars' => [
                'contracts' => $contracts,
                'pendingCount' => $pendingCount,
            ],
        ];
    }

    /**
     * View a specific contract
     */
    protected function viewContract()
    {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';

        // Get contract
        $contract = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select('c.*', 't.name as template_name', 't.signing_method', 'p.name as product_name', 'h.domain')
            ->where('c.id', $id)
            ->first();

        if (!$contract) {
            return [
                'pagetitle' => 'Contract Not Found',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_not_found',
                'requirelogin' => false,
                'vars' => [],
            ];
        }

        // Check access - either logged in owner or valid token
        $validToken = $this->validateToken($id, $token);
        if (!$validToken && $contract->client_id != $this->clientId) {
            return [
                'pagetitle' => 'Access Denied',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_access_denied',
                'requirelogin' => true,
                'vars' => [],
            ];
        }

        // Log view
        $this->logAction($id, $contract->client_id, 'viewed');

        // Get settings
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        return [
            'pagetitle' => 'View Contract: ' . $contract->contract_number,
            'breadcrumb' => [
                'index.php?m=recurring_contracts' => 'My Contracts',
                'index.php?m=recurring_contracts&action=view&id=' . $id => 'View Contract',
            ],
            'templatefile' => 'contracts_view',
            'requirelogin' => false,
            'vars' => [
                'contract' => $contract,
                'settings' => $settings,
                'token' => $token ?: $this->generateToken($id),
                'canSign' => $contract->status === 'pending',
            ],
        ];
    }

    /**
     * Sign contract page
     */
    protected function signContract()
    {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';

        // Get contract
        $contract = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select('c.*', 't.name as template_name', 't.signing_method as template_signing_method', 'p.name as product_name', 'h.domain')
            ->where('c.id', $id)
            ->first();

        if (!$contract) {
            return [
                'pagetitle' => 'Contract Not Found',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_not_found',
                'requirelogin' => false,
                'vars' => [],
            ];
        }

        // Check access
        $validToken = $this->validateToken($id, $token);
        if (!$validToken && $contract->client_id != $this->clientId) {
            return [
                'pagetitle' => 'Access Denied',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_access_denied',
                'requirelogin' => true,
                'vars' => [],
            ];
        }

        // Check if contract can be signed
        if ($contract->status !== 'pending') {
            return [
                'pagetitle' => 'Contract Already Signed',
                'breadcrumb' => ['index.php?m=recurring_contracts' => 'My Contracts'],
                'templatefile' => 'contracts_already_signed',
                'requirelogin' => false,
                'vars' => ['contract' => $contract],
            ];
        }

        // Get settings
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        return [
            'pagetitle' => 'Sign Contract: ' . $contract->contract_number,
            'breadcrumb' => [
                'index.php?m=recurring_contracts' => 'My Contracts',
                'index.php?m=recurring_contracts&action=sign&id=' . $id => 'Sign Contract',
            ],
            'templatefile' => 'contracts_sign',
            'requirelogin' => false,
            'vars' => [
                'contract' => $contract,
                'settings' => $settings,
                'token' => $token ?: $this->generateToken($id),
                'signingMethod' => $contract->template_signing_method,
            ],
        ];
    }

    /**
     * Process contract signature
     */
    protected function processSignature()
    {
        $id = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $method = isset($_POST['signing_method']) ? $_POST['signing_method'] : 'checkbox';

        // Validate contract
        $contract = Capsule::table('mod_recurring_contracts')->where('id', $id)->first();

        if (!$contract) {
            return $this->jsonResponse(['success' => false, 'message' => 'Contract not found.']);
        }

        // Check access
        $validToken = $this->validateToken($id, $token);
        if (!$validToken && $contract->client_id != $this->clientId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Access denied.']);
        }

        // Check if contract can be signed
        if ($contract->status !== 'pending') {
            return $this->jsonResponse(['success' => false, 'message' => 'Contract has already been signed.']);
        }

        // Process based on signing method
        $signatureData = null;
        $uploadedFile = null;

        switch ($method) {
            case 'checkbox':
                if (!isset($_POST['agree']) || $_POST['agree'] != '1') {
                    return $this->jsonResponse(['success' => false, 'message' => 'You must agree to the contract terms.']);
                }
                break;

            case 'signature':
                $signatureData = isset($_POST['signature_data']) ? $_POST['signature_data'] : '';
                if (empty($signatureData) || strpos($signatureData, 'data:image') !== 0) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Please provide a valid signature.']);
                }
                break;

            case 'file_upload':
                return $this->jsonResponse(['success' => false, 'message' => 'Please use the file upload form.']);
        }

        try {
            // Update contract
            Capsule::table('mod_recurring_contracts')
                ->where('id', $id)
                ->update([
                    'status' => 'active',
                    'signing_method' => $method,
                    'signed_at' => date('Y-m-d H:i:s'),
                    'signed_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'signature_data' => $signatureData,
                    'uploaded_file' => $uploadedFile,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Log signature
            $this->logAction($id, $contract->client_id, 'signed', 'Contract signed via ' . $method);

            // Send confirmation email
            $this->sendSignedEmail($id);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Contract signed successfully!',
                'redirect' => 'index.php?m=recurring_contracts&action=view&id=' . $id,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error signing contract: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle file upload for contract signing
     */
    protected function uploadSignedContract()
    {
        $id = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;
        $token = isset($_POST['token']) ? $_POST['token'] : '';

        // Validate contract
        $contract = Capsule::table('mod_recurring_contracts')->where('id', $id)->first();

        if (!$contract) {
            return $this->jsonResponse(['success' => false, 'message' => 'Contract not found.']);
        }

        // Check access
        $validToken = $this->validateToken($id, $token);
        if (!$validToken && $contract->client_id != $this->clientId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Access denied.']);
        }

        // Check if contract can be signed
        if ($contract->status !== 'pending') {
            return $this->jsonResponse(['success' => false, 'message' => 'Contract has already been signed.']);
        }

        // Check file upload
        if (!isset($_FILES['signed_contract']) || $_FILES['signed_contract']['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please upload a valid file.']);
        }

        // Get settings
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        $allowedExtensions = explode(',', $settings['allowed_upload_extensions'] ?? 'pdf,jpg,jpeg,png');
        $maxSize = ((int)($settings['max_upload_size_mb'] ?? 10)) * 1024 * 1024;

        // Validate file
        $file = $_FILES['signed_contract'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)]);
        }

        if ($file['size'] > $maxSize) {
            return $this->jsonResponse(['success' => false, 'message' => 'File size exceeds limit of ' . ($settings['max_upload_size_mb'] ?? 10) . 'MB.']);
        }

        // Create upload directory
        $uploadDir = ROOTDIR . '/uploads/recurring_contracts/' . $contract->client_id . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $filename = 'contract_' . $contract->contract_number . '_signed_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error saving uploaded file.']);
        }

        // Relative path for storage
        $relativePath = 'uploads/recurring_contracts/' . $contract->client_id . '/' . $filename;

        try {
            // Update contract
            Capsule::table('mod_recurring_contracts')
                ->where('id', $id)
                ->update([
                    'status' => 'active',
                    'signing_method' => 'file_upload',
                    'signed_at' => date('Y-m-d H:i:s'),
                    'signed_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'uploaded_file' => $relativePath,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            // Log signature
            $this->logAction($id, $contract->client_id, 'signed', 'Contract signed via file upload: ' . $filename);

            // Send confirmation email
            $this->sendSignedEmail($id);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Contract signed successfully!',
                'redirect' => 'index.php?m=recurring_contracts&action=view&id=' . $id,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Error signing contract: ' . $e->getMessage()]);
        }
    }

    /**
     * Download contract as PDF
     */
    protected function downloadContract()
    {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';

        // Get contract
        $contract = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->select('c.*', 't.name as template_name')
            ->where('c.id', $id)
            ->first();

        if (!$contract) {
            header('HTTP/1.0 404 Not Found');
            echo 'Contract not found';
            exit;
        }

        // Check access
        $validToken = $this->validateToken($id, $token);
        if (!$validToken && $contract->client_id != $this->clientId) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access denied';
            exit;
        }

        // Generate HTML for PDF
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract: ' . htmlspecialchars($contract->contract_number) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; margin: 40px; }
        h1 { font-size: 18px; margin-bottom: 20px; }
        .contract-info { margin-bottom: 20px; padding: 10px; background: #f5f5f5; }
        .contract-content { margin-bottom: 30px; }
        .signature-block { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px; }
        .signature-img { max-width: 300px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Contract: ' . htmlspecialchars($contract->contract_number) . '</h1>

    <div class="contract-info">
        <strong>Contract Number:</strong> ' . htmlspecialchars($contract->contract_number) . '<br>
        <strong>Start Date:</strong> ' . date('F d, Y', strtotime($contract->start_date)) . '<br>
        <strong>End Date:</strong> ' . date('F d, Y', strtotime($contract->end_date)) . '<br>
        <strong>Status:</strong> ' . ucfirst($contract->status) . '
    </div>

    <div class="contract-content">
        ' . $contract->content . '
    </div>';

        if ($contract->signed_at) {
            $html .= '
    <div class="signature-block">
        <strong>Signed:</strong> ' . date('F d, Y H:i:s', strtotime($contract->signed_at)) . '<br>
        <strong>IP Address:</strong> ' . htmlspecialchars($contract->signed_ip) . '<br>
        <strong>Method:</strong> ' . ucfirst(str_replace('_', ' ', $contract->signing_method)) . '<br>';

            if ($contract->signature_data) {
                $html .= '<img src="' . $contract->signature_data . '" class="signature-img" alt="Signature">';
            }

            $html .= '
    </div>';
        }

        $html .= '
</body>
</html>';

        // For now, output as HTML (in production, use a PDF library like TCPDF or Dompdf)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="contract_' . $contract->contract_number . '.html"');
        echo $html;
        exit;
    }

    /**
     * Send contract signed confirmation email
     */
    protected function sendSignedEmail($contractId)
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
            ->where('name', 'contract_signed')
            ->where('is_active', 1)
            ->first();

        if (!$emailTemplate) {
            return false;
        }

        // Get company name
        $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

        // Process email variables
        $variables = [
            '{$client_name}' => $contract->firstname . ' ' . $contract->lastname,
            '{$client_email}' => $contract->email,
            '{$service_name}' => $contract->product_name ?: 'N/A',
            '{$contract_number}' => $contract->contract_number,
            '{$contract_name}' => $contract->template_name,
            '{$start_date}' => date('F d, Y', strtotime($contract->start_date)),
            '{$end_date}' => date('F d, Y', strtotime($contract->end_date)),
            '{$company_name}' => $companyName,
        ];

        $subject = str_replace(array_keys($variables), array_values($variables), $emailTemplate->subject);
        $body = str_replace(array_keys($variables), array_values($variables), $emailTemplate->body);

        // Send email
        $result = localAPI('SendEmail', [
            'customtype' => 'general',
            'customsubject' => $subject,
            'custommessage' => $body,
            'id' => $contract->client_id,
        ]);

        return $result['result'] === 'success';
    }

    /**
     * Validate token
     */
    protected function validateToken($contractId, $token)
    {
        if (empty($token)) {
            return false;
        }
        return $token === $this->generateToken($contractId);
    }

    /**
     * Generate token
     */
    protected function generateToken($contractId)
    {
        return hash('sha256', $contractId . 'recurring_contracts_salt_' . date('Y'));
    }

    /**
     * Log action
     */
    protected function logAction($contractId, $clientId, $action, $details = null)
    {
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        $enableLogging = $settings['enable_logging'] ?? 1;
        $logViews = $settings['log_views'] ?? 1;
        $logSignatures = $settings['log_signatures'] ?? 1;

        if (!$enableLogging) {
            return;
        }

        if ($action === 'viewed' && !$logViews) {
            return;
        }

        if ($action === 'signed' && !$logSignatures) {
            return;
        }

        Capsule::table('mod_recurring_contracts_logs')->insert([
            'contract_id' => $contractId,
            'client_id' => $clientId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * JSON response helper
     */
    protected function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
