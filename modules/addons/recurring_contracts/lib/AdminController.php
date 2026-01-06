<?php
/**
 * Admin Controller for Recurring Contract Billing Module
 */

namespace RecurringContracts;

use WHMCS\Database\Capsule;

class AdminController
{
    protected $vars;
    protected $modulelink;

    public function __construct($vars)
    {
        $this->vars = $vars;
        $this->modulelink = $vars['modulelink'];
    }

    /**
     * Dispatch request to appropriate handler
     */
    public function dispatch()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

        switch ($action) {
            case 'dashboard':
                return $this->dashboard();
            case 'templates':
                return $this->templates();
            case 'template_add':
            case 'template_edit':
                return $this->templateForm();
            case 'template_save':
                return $this->templateSave();
            case 'template_delete':
                return $this->templateDelete();
            case 'contracts':
                return $this->contracts();
            case 'contract_view':
                return $this->contractView();
            case 'contract_create':
                return $this->contractCreate();
            case 'contract_save':
                return $this->contractSaveNew();
            case 'contract_update':
                return $this->contractUpdate();
            case 'contract_cancel':
                return $this->contractCancel();
            case 'products':
                return $this->products();
            case 'product_save':
                return $this->productSave();
            case 'product_delete':
                return $this->productDelete();
            case 'emails':
                return $this->emails();
            case 'email_edit':
                return $this->emailEdit();
            case 'email_save':
                return $this->emailSave();
            case 'logs':
                return $this->logs();
            case 'settings':
                return $this->settings();
            case 'settings_save':
                return $this->settingsSave();
            case 'send_contract':
                return $this->sendContract();
            default:
                return $this->dashboard();
        }
    }

    /**
     * Dashboard view
     */
    protected function dashboard()
    {
        // Get statistics
        $stats = [
            'total_templates' => Capsule::table('mod_recurring_contracts_templates')->count(),
            'active_templates' => Capsule::table('mod_recurring_contracts_templates')->where('is_active', 1)->count(),
            'total_contracts' => Capsule::table('mod_recurring_contracts')->count(),
            'pending_contracts' => Capsule::table('mod_recurring_contracts')->where('status', 'pending')->count(),
            'active_contracts' => Capsule::table('mod_recurring_contracts')->where('status', 'active')->count(),
            'expired_contracts' => Capsule::table('mod_recurring_contracts')->where('status', 'expired')->count(),
            'expiring_soon' => Capsule::table('mod_recurring_contracts')
                ->where('status', 'active')
                ->where('end_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->count(),
        ];

        // Get recent contracts
        $recentContracts = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.companyname', 't.name as template_name')
            ->orderBy('c.created_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent logs
        $recentLogs = Capsule::table('mod_recurring_contracts_logs as l')
            ->leftJoin('mod_recurring_contracts as c', 'l.contract_id', '=', 'c.id')
            ->leftJoin('tblclients as cl', 'l.client_id', '=', 'cl.id')
            ->select('l.*', 'c.contract_number', 'cl.firstname', 'cl.lastname')
            ->orderBy('l.created_at', 'desc')
            ->limit(10)
            ->get();

        ob_start();
        ?>
        <div class="row">
            <div class="col-sm-3">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-3"><i class="fa fa-file-contract fa-5x"></i></div>
                            <div class="col-xs-9 text-right">
                                <div class="huge"><?php echo $stats['total_templates']; ?></div>
                                <div>Contract Templates</div>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $this->modulelink; ?>&action=templates">
                        <div class="panel-footer">
                            <span class="pull-left">View Details</span>
                            <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                            <div class="clearfix"></div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-3"><i class="fa fa-clock fa-5x"></i></div>
                            <div class="col-xs-9 text-right">
                                <div class="huge"><?php echo $stats['pending_contracts']; ?></div>
                                <div>Pending Signatures</div>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=pending">
                        <div class="panel-footer">
                            <span class="pull-left">View Details</span>
                            <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                            <div class="clearfix"></div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-3"><i class="fa fa-check-circle fa-5x"></i></div>
                            <div class="col-xs-9 text-right">
                                <div class="huge"><?php echo $stats['active_contracts']; ?></div>
                                <div>Active Contracts</div>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=active">
                        <div class="panel-footer">
                            <span class="pull-left">View Details</span>
                            <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                            <div class="clearfix"></div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <div class="row">
                            <div class="col-xs-3"><i class="fa fa-exclamation-triangle fa-5x"></i></div>
                            <div class="col-xs-9 text-right">
                                <div class="huge"><?php echo $stats['expiring_soon']; ?></div>
                                <div>Expiring Soon</div>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&expiring=1">
                        <div class="panel-footer">
                            <span class="pull-left">View Details</span>
                            <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                            <div class="clearfix"></div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-file-signature"></i> Recent Contracts</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Contract #</th>
                                    <th>Client</th>
                                    <th>Template</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentContracts as $contract): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo $this->modulelink; ?>&action=contract_view&id=<?php echo $contract->id; ?>">
                                            <?php echo htmlspecialchars($contract->contract_number); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="clientssummary.php?userid=<?php echo $contract->client_id; ?>">
                                            <?php echo htmlspecialchars($contract->firstname . ' ' . $contract->lastname); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($contract->template_name); ?></td>
                                    <td>
                                        <span class="label label-<?php echo $this->getStatusClass($contract->status); ?>">
                                            <?php echo ucfirst($contract->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($contract->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentContracts)): ?>
                                <tr><td colspan="5" class="text-center">No contracts found</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> Recent Activity</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Contract</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="label label-<?php echo $this->getActionClass($log->action); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log->action)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log->contract_number ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($log->firstname ?? '') . ' ' . ($log->lastname ?? '')); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($log->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentLogs)): ?>
                                <tr><td colspan="4" class="text-center">No activity found</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .huge { font-size: 40px; }
        .panel-heading { padding: 15px; }
        .panel-footer { padding: 10px 15px; background-color: rgba(0,0,0,0.1); }
        .panel-footer:hover { background-color: rgba(0,0,0,0.15); }
        .panel-info .panel-heading { background-color: #5bc0de; color: white; }
        .panel-warning .panel-heading { background-color: #f0ad4e; color: white; }
        .panel-success .panel-heading { background-color: #5cb85c; color: white; }
        .panel-danger .panel-heading { background-color: #d9534f; color: white; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Contract templates list
     */
    protected function templates()
    {
        $templates = Capsule::table('mod_recurring_contracts_templates')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-file-contract"></i> Contract Templates
                    <a href="<?php echo $this->modulelink; ?>&action=template_add" class="btn btn-success btn-sm pull-right">
                        <i class="fa fa-plus"></i> Add Template
                    </a>
                </h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th>Name</th>
                            <th>Language</th>
                            <th>Duration</th>
                            <th>Renewal</th>
                            <th>Signing Method</th>
                            <th>Status</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?php echo $template->id; ?></td>
                            <td><?php echo htmlspecialchars($template->name); ?></td>
                            <td><?php echo ucfirst($template->language); ?></td>
                            <td><?php echo $template->duration_months; ?> months</td>
                            <td><?php echo ucfirst($template->renewal_type); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $template->signing_method)); ?></td>
                            <td>
                                <span class="label label-<?php echo $template->is_active ? 'success' : 'default'; ?>">
                                    <?php echo $template->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $this->modulelink; ?>&action=template_edit&id=<?php echo $template->id; ?>"
                                   class="btn btn-primary btn-sm" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a href="<?php echo $this->modulelink; ?>&action=template_delete&id=<?php echo $template->id; ?>"
                                   class="btn btn-danger btn-sm" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this template?');">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Template add/edit form
     */
    protected function templateForm()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $template = null;

        if ($id) {
            $template = Capsule::table('mod_recurring_contracts_templates')->where('id', $id)->first();
            if (!$template) {
                return '<div class="alert alert-danger">Template not found.</div>';
            }
        }

        $languages = ['english', 'spanish', 'french', 'german', 'italian', 'portuguese', 'dutch', 'russian', 'chinese', 'japanese'];

        ob_start();
        ?>
        <form method="post" action="<?php echo $this->modulelink; ?>&action=template_save">
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-file-contract"></i> <?php echo $id ? 'Edit' : 'Add'; ?> Contract Template
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Template Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($template->name ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Language</label>
                                <select name="language" class="form-control">
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?php echo $lang; ?>"
                                            <?php echo ($template->language ?? 'english') == $lang ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($lang); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($template->description ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Contract Content <span class="text-danger">*</span></label>
                        <p class="help-block">
                            Available variables: {$client_name}, {$client_email}, {$client_company}, {$service_name},
                            {$contract_number}, {$start_date}, {$end_date}, {$duration_months}, {$company_name}
                        </p>
                        <textarea name="content" class="form-control" rows="15" required><?php echo htmlspecialchars($template->content ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Contract Duration (months) <span class="text-danger">*</span></label>
                                <input type="number" name="duration_months" class="form-control" required
                                       min="1" max="120" value="<?php echo $template->duration_months ?? 12; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Renewal Type</label>
                                <select name="renewal_type" class="form-control">
                                    <option value="auto" <?php echo ($template->renewal_type ?? '') == 'auto' ? 'selected' : ''; ?>>Automatic</option>
                                    <option value="manual" <?php echo ($template->renewal_type ?? '') == 'manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="none" <?php echo ($template->renewal_type ?? '') == 'none' ? 'selected' : ''; ?>>No Renewal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Signing Method</label>
                                <select name="signing_method" class="form-control">
                                    <option value="checkbox" <?php echo ($template->signing_method ?? '') == 'checkbox' ? 'selected' : ''; ?>>Checkbox Agreement</option>
                                    <option value="signature" <?php echo ($template->signing_method ?? '') == 'signature' ? 'selected' : ''; ?>>Electronic Signature</option>
                                    <option value="file_upload" <?php echo ($template->signing_method ?? '') == 'file_upload' ? 'selected' : ''; ?>>File Upload</option>
                                    <option value="any" <?php echo ($template->signing_method ?? '') == 'any' ? 'selected' : ''; ?>>Any Method</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Discount (%)</label>
                                <input type="number" name="discount_percent" class="form-control"
                                       min="0" max="100" step="0.01" value="<?php echo $template->discount_percent ?? 0; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Penalty Amount</label>
                                <input type="number" name="penalty_amount" class="form-control"
                                       min="0" step="0.01" value="<?php echo $template->penalty_amount ?? 0; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Penalty Type</label>
                                <select name="penalty_type" class="form-control">
                                    <option value="fixed" <?php echo ($template->penalty_type ?? '') == 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                    <option value="percent" <?php echo ($template->penalty_type ?? '') == 'percent' ? 'selected' : ''; ?>>Percentage of Total</option>
                                    <option value="remaining" <?php echo ($template->penalty_type ?? '') == 'remaining' ? 'selected' : ''; ?>>Remaining Balance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" class="form-control"
                                       value="<?php echo $template->sort_order ?? 0; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="is_active" class="form-control">
                                    <option value="1" <?php echo ($template->is_active ?? 1) ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo !($template->is_active ?? 1) ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Save Template
                    </button>
                    <a href="<?php echo $this->modulelink; ?>&action=templates" class="btn btn-default">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Save template
     */
    protected function templateSave()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'content' => $_POST['content'] ?? '',
            'language' => $_POST['language'] ?? 'english',
            'duration_months' => (int)($_POST['duration_months'] ?? 12),
            'renewal_type' => $_POST['renewal_type'] ?? 'auto',
            'discount_percent' => (float)($_POST['discount_percent'] ?? 0),
            'penalty_amount' => (float)($_POST['penalty_amount'] ?? 0),
            'penalty_type' => $_POST['penalty_type'] ?? 'fixed',
            'signing_method' => $_POST['signing_method'] ?? 'checkbox',
            'is_active' => (int)($_POST['is_active'] ?? 1),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            if ($id) {
                Capsule::table('mod_recurring_contracts_templates')->where('id', $id)->update($data);
                $message = 'Template updated successfully.';
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_recurring_contracts_templates')->insert($data);
                $message = 'Template created successfully.';
            }

            header('Location: ' . $this->modulelink . '&action=templates&success=' . urlencode($message));
            exit;

        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>' . $this->templateForm();
        }
    }

    /**
     * Delete template
     */
    protected function templateDelete()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id) {
            // Check if template is in use
            $inUse = Capsule::table('mod_recurring_contracts')->where('template_id', $id)->exists();

            if ($inUse) {
                header('Location: ' . $this->modulelink . '&action=templates&error=' . urlencode('Cannot delete template that is in use by contracts.'));
                exit;
            }

            Capsule::table('mod_recurring_contracts_templates')->where('id', $id)->delete();
            Capsule::table('mod_recurring_contracts_products')->where('template_id', $id)->delete();
        }

        header('Location: ' . $this->modulelink . '&action=templates&success=' . urlencode('Template deleted successfully.'));
        exit;
    }

    /**
     * Contracts list
     */
    protected function contracts()
    {
        $query = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.companyname', 'cl.email',
                     't.name as template_name', 'p.name as product_name');

        // Filter by status
        if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'active', 'expired', 'cancelled', 'terminated'])) {
            $query->where('c.status', $_GET['status']);
        }

        // Filter expiring soon
        if (isset($_GET['expiring'])) {
            $query->where('c.status', 'active')
                  ->where('c.end_date', '<=', date('Y-m-d', strtotime('+30 days')));
        }

        $contracts = $query->orderBy('c.created_at', 'desc')->get();

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-file-signature"></i> Contracts
                    <a href="<?php echo $this->modulelink; ?>&action=contract_create" class="btn btn-success btn-sm pull-right">
                        <i class="fa fa-plus"></i> Create Contract
                    </a>
                </h3>
            </div>
            <div class="panel-body">
                <div class="btn-group" style="margin-bottom: 15px;">
                    <a href="<?php echo $this->modulelink; ?>&action=contracts" class="btn btn-default <?php echo !isset($_GET['status']) && !isset($_GET['expiring']) ? 'active' : ''; ?>">All</a>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=pending" class="btn btn-default <?php echo ($_GET['status'] ?? '') == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=active" class="btn btn-default <?php echo ($_GET['status'] ?? '') == 'active' ? 'active' : ''; ?>">Active</a>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=expired" class="btn btn-default <?php echo ($_GET['status'] ?? '') == 'expired' ? 'active' : ''; ?>">Expired</a>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&status=cancelled" class="btn btn-default <?php echo ($_GET['status'] ?? '') == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts&expiring=1" class="btn btn-warning <?php echo isset($_GET['expiring']) ? 'active' : ''; ?>">Expiring Soon</a>
                </div>

                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th>Contract #</th>
                            <th>Client</th>
                            <th>Template</th>
                            <th>Product/Service</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td>
                                <a href="<?php echo $this->modulelink; ?>&action=contract_view&id=<?php echo $contract->id; ?>">
                                    <?php echo htmlspecialchars($contract->contract_number); ?>
                                </a>
                            </td>
                            <td>
                                <a href="clientssummary.php?userid=<?php echo $contract->client_id; ?>">
                                    <?php echo htmlspecialchars($contract->firstname . ' ' . $contract->lastname); ?>
                                    <?php if ($contract->companyname): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($contract->companyname); ?></small>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($contract->template_name); ?></td>
                            <td><?php echo htmlspecialchars($contract->product_name ?: 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($contract->start_date)); ?></td>
                            <td><?php echo date('M d, Y', strtotime($contract->end_date)); ?></td>
                            <td>
                                <span class="label label-<?php echo $this->getStatusClass($contract->status); ?>">
                                    <?php echo ucfirst($contract->status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $this->modulelink; ?>&action=contract_view&id=<?php echo $contract->id; ?>"
                                   class="btn btn-info btn-sm" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <?php if ($contract->status == 'pending'): ?>
                                <a href="<?php echo $this->modulelink; ?>&action=send_contract&id=<?php echo $contract->id; ?>"
                                   class="btn btn-primary btn-sm" title="Send to Client">
                                    <i class="fa fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (in_array($contract->status, ['pending', 'active'])): ?>
                                <a href="<?php echo $this->modulelink; ?>&action=contract_cancel&id=<?php echo $contract->id; ?>"
                                   class="btn btn-danger btn-sm" title="Cancel"
                                   onclick="return confirm('Are you sure you want to cancel this contract?');">
                                    <i class="fa fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * View contract details
     */
    protected function contractView()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        $contract = Capsule::table('mod_recurring_contracts as c')
            ->leftJoin('tblclients as cl', 'c.client_id', '=', 'cl.id')
            ->leftJoin('mod_recurring_contracts_templates as t', 'c.template_id', '=', 't.id')
            ->leftJoin('tblhosting as h', 'c.service_id', '=', 'h.id')
            ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
            ->select('c.*', 'cl.firstname', 'cl.lastname', 'cl.companyname', 'cl.email',
                     't.name as template_name', 'p.name as product_name')
            ->where('c.id', $id)
            ->first();

        if (!$contract) {
            return '<div class="alert alert-danger">Contract not found.</div>';
        }

        // Get logs for this contract
        $logs = Capsule::table('mod_recurring_contracts_logs')
            ->where('contract_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        ob_start();
        ?>
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-file-signature"></i> Contract: <?php echo htmlspecialchars($contract->contract_number); ?>
                            <span class="label label-<?php echo $this->getStatusClass($contract->status); ?> pull-right">
                                <?php echo ucfirst($contract->status); ?>
                            </span>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="contract-content" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto;">
                            <?php echo $contract->content; ?>
                        </div>

                        <?php if ($contract->signature_data): ?>
                        <div class="signature-section" style="margin-top: 20px;">
                            <h4>Signature</h4>
                            <div style="background: #fff; border: 1px solid #ddd; padding: 10px; display: inline-block;">
                                <img src="<?php echo $contract->signature_data; ?>" alt="Signature" style="max-width: 300px;">
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($contract->uploaded_file): ?>
                        <div class="upload-section" style="margin-top: 20px;">
                            <h4>Uploaded Document</h4>
                            <a href="<?php echo htmlspecialchars($contract->uploaded_file); ?>" class="btn btn-default" target="_blank">
                                <i class="fa fa-download"></i> Download Signed Contract
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-sticky-note"></i> Admin Notes</h3>
                    </div>
                    <div class="panel-body">
                        <form method="post" action="<?php echo $this->modulelink; ?>&action=contract_update&id=<?php echo $contract->id; ?>">
                            <textarea name="admin_notes" class="form-control" rows="3"><?php echo htmlspecialchars($contract->admin_notes ?? ''); ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                                <i class="fa fa-save"></i> Save Notes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-info-circle"></i> Contract Details</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-condensed">
                            <tr>
                                <td><strong>Contract #:</strong></td>
                                <td><?php echo htmlspecialchars($contract->contract_number); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Template:</strong></td>
                                <td><?php echo htmlspecialchars($contract->template_name); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Client:</strong></td>
                                <td>
                                    <a href="clientssummary.php?userid=<?php echo $contract->client_id; ?>">
                                        <?php echo htmlspecialchars($contract->firstname . ' ' . $contract->lastname); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($contract->email); ?></td>
                            </tr>
                            <?php if ($contract->companyname): ?>
                            <tr>
                                <td><strong>Company:</strong></td>
                                <td><?php echo htmlspecialchars($contract->companyname); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Product:</strong></td>
                                <td><?php echo htmlspecialchars($contract->product_name ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Start Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($contract->start_date)); ?></td>
                            </tr>
                            <tr>
                                <td><strong>End Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($contract->end_date)); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Renewal:</strong></td>
                                <td><?php echo ucfirst($contract->renewal_type); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Discount:</strong></td>
                                <td><?php echo $contract->discount_percent; ?>%</td>
                            </tr>
                            <tr>
                                <td><strong>Penalty:</strong></td>
                                <td>
                                    <?php
                                    if ($contract->penalty_type == 'percent') {
                                        echo $contract->penalty_amount . '%';
                                    } elseif ($contract->penalty_type == 'remaining') {
                                        echo 'Remaining balance';
                                    } else {
                                        echo '$' . number_format($contract->penalty_amount, 2);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php if ($contract->signed_at): ?>
                            <tr>
                                <td><strong>Signed At:</strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($contract->signed_at)); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Signed IP:</strong></td>
                                <td><?php echo htmlspecialchars($contract->signed_ip); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Method:</strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $contract->signing_method)); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="panel-footer">
                        <?php if ($contract->status == 'pending'): ?>
                        <a href="<?php echo $this->modulelink; ?>&action=send_contract&id=<?php echo $contract->id; ?>"
                           class="btn btn-primary btn-block">
                            <i class="fa fa-envelope"></i> Send to Client
                        </a>
                        <?php endif; ?>
                        <?php if (in_array($contract->status, ['pending', 'active'])): ?>
                        <a href="<?php echo $this->modulelink; ?>&action=contract_cancel&id=<?php echo $contract->id; ?>"
                           class="btn btn-danger btn-block" style="margin-top: 5px;"
                           onclick="return confirm('Are you sure you want to cancel this contract?');">
                            <i class="fa fa-times"></i> Cancel Contract
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> Activity Log</h3>
                    </div>
                    <div class="panel-body" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($logs as $log): ?>
                        <div class="log-entry" style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span class="label label-<?php echo $this->getActionClass($log->action); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $log->action)); ?>
                            </span>
                            <small class="text-muted">
                                <?php echo date('M d, Y H:i', strtotime($log->created_at)); ?>
                            </small>
                            <?php if ($log->details): ?>
                            <p class="text-muted small" style="margin: 5px 0 0 0;">
                                <?php echo htmlspecialchars($log->details); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <p class="text-muted">No activity recorded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Create contract form
     */
    protected function contractCreate()
    {
        $templates = Capsule::table('mod_recurring_contracts_templates')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $clients = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname', 'companyname', 'email')
            ->orderBy('firstname')
            ->limit(500)
            ->get();

        ob_start();
        ?>
        <form method="post" action="<?php echo $this->modulelink; ?>&action=contract_save">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-plus"></i> Create New Contract</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contract Template <span class="text-danger">*</span></label>
                                <select name="template_id" class="form-control" required>
                                    <option value="">Select Template...</option>
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template->id; ?>">
                                        <?php echo htmlspecialchars($template->name); ?>
                                        (<?php echo $template->duration_months; ?> months)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Client <span class="text-danger">*</span></label>
                                <select name="client_id" class="form-control" required id="client_select">
                                    <option value="">Select Client...</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client->id; ?>">
                                        <?php echo htmlspecialchars($client->firstname . ' ' . $client->lastname); ?>
                                        <?php if ($client->companyname): ?>(<?php echo htmlspecialchars($client->companyname); ?>)<?php endif; ?>
                                        - <?php echo htmlspecialchars($client->email); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Service (Optional)</label>
                                <select name="service_id" class="form-control" id="service_select">
                                    <option value="">Select Service...</option>
                                </select>
                                <p class="help-block">Services will load after selecting a client</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="send_email" value="1" checked>
                            Send contract email to client after creation
                        </label>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Create Contract
                    </button>
                    <a href="<?php echo $this->modulelink; ?>&action=contracts" class="btn btn-default">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>

        <script>
        document.getElementById('client_select').addEventListener('change', function() {
            var clientId = this.value;
            var serviceSelect = document.getElementById('service_select');
            serviceSelect.innerHTML = '<option value="">Loading...</option>';

            if (clientId) {
                // AJAX call to get client services
                fetch('<?php echo $this->modulelink; ?>&action=get_services&client_id=' + clientId)
                    .then(response => response.json())
                    .then(data => {
                        serviceSelect.innerHTML = '<option value="">Select Service...</option>';
                        data.forEach(function(service) {
                            var option = document.createElement('option');
                            option.value = service.id;
                            option.textContent = service.name + ' - ' + service.domain;
                            serviceSelect.appendChild(option);
                        });
                    });
            } else {
                serviceSelect.innerHTML = '<option value="">Select Service...</option>';
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Save new contract
     */
    protected function contractSaveNew()
    {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $clientId = (int)($_POST['client_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0) ?: null;
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $sendEmail = isset($_POST['send_email']);

        if (!$templateId || !$clientId) {
            return '<div class="alert alert-danger">Template and client are required.</div>' . $this->contractCreate();
        }

        $template = Capsule::table('mod_recurring_contracts_templates')->where('id', $templateId)->first();
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();

        if (!$template || !$client) {
            return '<div class="alert alert-danger">Invalid template or client.</div>' . $this->contractCreate();
        }

        // Generate contract number
        $contractNumber = $this->generateContractNumber();

        // Calculate end date
        $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $template->duration_months . ' months'));

        // Process content with variables
        $content = $this->processContractContent($template->content, $client, $serviceId, $contractNumber, $startDate, $endDate, $template->duration_months);

        try {
            $contractId = Capsule::table('mod_recurring_contracts')->insertGetId([
                'template_id' => $templateId,
                'client_id' => $clientId,
                'service_id' => $serviceId,
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
            $this->logAction($contractId, $clientId, null, 'created', 'Contract created by admin');

            // Send email if requested
            if ($sendEmail) {
                $this->sendContractEmail($contractId);
            }

            header('Location: ' . $this->modulelink . '&action=contract_view&id=' . $contractId . '&success=' . urlencode('Contract created successfully.'));
            exit;

        } catch (\Exception $e) {
            return '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>' . $this->contractCreate();
        }
    }

    /**
     * Update contract (admin notes, etc.)
     */
    protected function contractUpdate()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $adminNotes = $_POST['admin_notes'] ?? '';

        if ($id) {
            Capsule::table('mod_recurring_contracts')
                ->where('id', $id)
                ->update([
                    'admin_notes' => $adminNotes,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->logAction($id, null, $_SESSION['adminid'] ?? null, 'modified', 'Admin notes updated');
        }

        header('Location: ' . $this->modulelink . '&action=contract_view&id=' . $id . '&success=' . urlencode('Contract updated successfully.'));
        exit;
    }

    /**
     * Cancel contract
     */
    protected function contractCancel()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id) {
            $contract = Capsule::table('mod_recurring_contracts')->where('id', $id)->first();

            if ($contract && in_array($contract->status, ['pending', 'active'])) {
                Capsule::table('mod_recurring_contracts')
                    ->where('id', $id)
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => date('Y-m-d H:i:s'),
                        'cancelled_by' => $_SESSION['adminid'] ?? null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                $this->logAction($id, $contract->client_id, $_SESSION['adminid'] ?? null, 'cancelled', 'Contract cancelled by admin');
            }
        }

        header('Location: ' . $this->modulelink . '&action=contracts&success=' . urlencode('Contract cancelled successfully.'));
        exit;
    }

    /**
     * Products management
     */
    protected function products()
    {
        $products = Capsule::table('tblproducts')
            ->where('retired', 0)
            ->orderBy('name')
            ->get();

        $templates = Capsule::table('mod_recurring_contracts_templates')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $associations = Capsule::table('mod_recurring_contracts_products')
            ->get()
            ->groupBy('product_id');

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-box"></i> Product Contract Associations</h3>
            </div>
            <div class="panel-body">
                <p class="help-block">Configure which products require contracts. When a client orders a product with an associated template, they will be required to sign the contract.</p>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Contract Template</th>
                            <th>Required</th>
                            <th>Show on Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productAssocs = isset($associations[$product->id]) ? $associations[$product->id] : collect([]);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product->name); ?></td>
                            <td colspan="4">
                                <form method="post" action="<?php echo $this->modulelink; ?>&action=product_save" class="form-inline">
                                    <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
                                    <select name="template_id" class="form-control input-sm">
                                        <option value="">No contract required</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template->id; ?>"
                                            <?php echo $productAssocs->contains('template_id', $template->id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template->name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="required" value="1"
                                            <?php echo $productAssocs->first() && $productAssocs->first()->required ? 'checked' : ''; ?>>
                                        Required
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="show_on_order" value="1"
                                            <?php echo $productAssocs->first() && $productAssocs->first()->show_on_order ? 'checked' : ''; ?>>
                                        Show on Order
                                    </label>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa fa-save"></i> Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Save product association
     */
    protected function productSave()
    {
        $productId = (int)($_POST['product_id'] ?? 0);
        $templateId = (int)($_POST['template_id'] ?? 0);
        $required = isset($_POST['required']) ? 1 : 0;
        $showOnOrder = isset($_POST['show_on_order']) ? 1 : 0;

        if ($productId) {
            // Delete existing association
            Capsule::table('mod_recurring_contracts_products')
                ->where('product_id', $productId)
                ->delete();

            // Create new association if template selected
            if ($templateId) {
                Capsule::table('mod_recurring_contracts_products')->insert([
                    'product_id' => $productId,
                    'template_id' => $templateId,
                    'required' => $required,
                    'show_on_order' => $showOnOrder,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        header('Location: ' . $this->modulelink . '&action=products&success=' . urlencode('Product settings saved successfully.'));
        exit;
    }

    /**
     * Email templates management
     */
    protected function emails()
    {
        $emails = Capsule::table('mod_recurring_contracts_emails')->get();

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-envelope"></i> Email Templates</h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td><?php echo ucwords(str_replace('_', ' ', $email->name)); ?></td>
                            <td><?php echo htmlspecialchars($email->subject); ?></td>
                            <td>
                                <span class="label label-<?php echo $email->is_active ? 'success' : 'default'; ?>">
                                    <?php echo $email->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $this->modulelink; ?>&action=email_edit&id=<?php echo $email->id; ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Edit email template
     */
    protected function emailEdit()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $email = Capsule::table('mod_recurring_contracts_emails')->where('id', $id)->first();

        if (!$email) {
            return '<div class="alert alert-danger">Email template not found.</div>';
        }

        ob_start();
        ?>
        <form method="post" action="<?php echo $this->modulelink; ?>&action=email_save">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-envelope"></i> Edit Email Template: <?php echo ucwords(str_replace('_', ' ', $email->name)); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="form-control"
                               value="<?php echo htmlspecialchars($email->subject); ?>">
                    </div>
                    <div class="form-group">
                        <label>Body</label>
                        <p class="help-block">
                            Variables: {$client_name}, {$client_email}, {$service_name}, {$contract_number},
                            {$contract_name}, {$start_date}, {$end_date}, {$contract_duration}, {$sign_url},
                            {$company_name}, {$renewal_type}
                        </p>
                        <textarea name="body" class="form-control" rows="15"><?php echo htmlspecialchars($email->body); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php echo $email->is_active ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Save Template
                    </button>
                    <a href="<?php echo $this->modulelink; ?>&action=emails" class="btn btn-default">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Save email template
     */
    protected function emailSave()
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id) {
            Capsule::table('mod_recurring_contracts_emails')
                ->where('id', $id)
                ->update([
                    'subject' => $_POST['subject'] ?? '',
                    'body' => $_POST['body'] ?? '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        header('Location: ' . $this->modulelink . '&action=emails&success=' . urlencode('Email template saved successfully.'));
        exit;
    }

    /**
     * Logs view
     */
    protected function logs()
    {
        $logs = Capsule::table('mod_recurring_contracts_logs as l')
            ->leftJoin('mod_recurring_contracts as c', 'l.contract_id', '=', 'c.id')
            ->leftJoin('tblclients as cl', 'l.client_id', '=', 'cl.id')
            ->leftJoin('tbladmins as a', 'l.admin_id', '=', 'a.id')
            ->select('l.*', 'c.contract_number', 'cl.firstname', 'cl.lastname', 'a.username as admin_username')
            ->orderBy('l.created_at', 'desc')
            ->paginate(50);

        ob_start();
        ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-history"></i> Activity Logs</h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Action</th>
                            <th>Contract</th>
                            <th>Client</th>
                            <th>Admin</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log->created_at)); ?></td>
                            <td>
                                <span class="label label-<?php echo $this->getActionClass($log->action); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log->action)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log->contract_number): ?>
                                <a href="<?php echo $this->modulelink; ?>&action=contract_view&id=<?php echo $log->contract_id; ?>">
                                    <?php echo htmlspecialchars($log->contract_number); ?>
                                </a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log->firstname): ?>
                                <a href="clientssummary.php?userid=<?php echo $log->client_id; ?>">
                                    <?php echo htmlspecialchars($log->firstname . ' ' . $log->lastname); ?>
                                </a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log->admin_username ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log->ip_address ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log->details ?: ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Settings page
     */
    protected function settings()
    {
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        ob_start();
        ?>
        <form method="post" action="<?php echo $this->modulelink; ?>&action=settings_save">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-cog"></i> Module Settings</h3>
                </div>
                <div class="panel-body">
                    <h4>Logging Settings</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="enable_logging" value="1"
                                        <?php echo ($settings['enable_logging'] ?? 1) ? 'checked' : ''; ?>>
                                    Enable Logging
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="log_views" value="1"
                                        <?php echo ($settings['log_views'] ?? 1) ? 'checked' : ''; ?>>
                                    Log Views
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="log_signatures" value="1"
                                        <?php echo ($settings['log_signatures'] ?? 1) ? 'checked' : ''; ?>>
                                    Log Signatures
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="log_modifications" value="1"
                                        <?php echo ($settings['log_modifications'] ?? 1) ? 'checked' : ''; ?>>
                                    Log Modifications
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h4>Signature Settings</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Signature Canvas Width (px)</label>
                                <input type="number" name="signature_canvas_width" class="form-control"
                                       value="<?php echo $settings['signature_canvas_width'] ?? 400; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Signature Canvas Height (px)</label>
                                <input type="number" name="signature_canvas_height" class="form-control"
                                       value="<?php echo $settings['signature_canvas_height'] ?? 200; ?>">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h4>File Upload Settings</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Allowed File Extensions</label>
                                <input type="text" name="allowed_upload_extensions" class="form-control"
                                       value="<?php echo $settings['allowed_upload_extensions'] ?? 'pdf,jpg,jpeg,png'; ?>">
                                <p class="help-block">Comma-separated list of extensions</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max Upload Size (MB)</label>
                                <input type="number" name="max_upload_size_mb" class="form-control"
                                       value="<?php echo $settings['max_upload_size_mb'] ?? 10; ?>">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h4>Contract Number Settings</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Contract Number Prefix</label>
                                <input type="text" name="contract_number_prefix" class="form-control"
                                       value="<?php echo $settings['contract_number_prefix'] ?? 'CTR-'; ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Contract Number Format</label>
                                <input type="text" name="contract_number_format" class="form-control"
                                       value="<?php echo $settings['contract_number_format'] ?? '{prefix}{year}{month}-{id}'; ?>">
                                <p class="help-block">Variables: {prefix}, {year}, {month}, {day}, {id}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Save settings
     */
    protected function settingsSave()
    {
        $settingsToSave = [
            'enable_logging' => isset($_POST['enable_logging']) ? '1' : '0',
            'log_views' => isset($_POST['log_views']) ? '1' : '0',
            'log_signatures' => isset($_POST['log_signatures']) ? '1' : '0',
            'log_modifications' => isset($_POST['log_modifications']) ? '1' : '0',
            'signature_canvas_width' => $_POST['signature_canvas_width'] ?? '400',
            'signature_canvas_height' => $_POST['signature_canvas_height'] ?? '200',
            'allowed_upload_extensions' => $_POST['allowed_upload_extensions'] ?? 'pdf,jpg,jpeg,png',
            'max_upload_size_mb' => $_POST['max_upload_size_mb'] ?? '10',
            'contract_number_prefix' => $_POST['contract_number_prefix'] ?? 'CTR-',
            'contract_number_format' => $_POST['contract_number_format'] ?? '{prefix}{year}{month}-{id}',
        ];

        foreach ($settingsToSave as $key => $value) {
            Capsule::table('mod_recurring_contracts_settings')
                ->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
                );
        }

        header('Location: ' . $this->modulelink . '&action=settings&success=' . urlencode('Settings saved successfully.'));
        exit;
    }

    /**
     * Send contract email to client
     */
    protected function sendContract()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id) {
            $this->sendContractEmail($id);
        }

        header('Location: ' . $this->modulelink . '&action=contract_view&id=' . $id . '&success=' . urlencode('Contract email sent to client.'));
        exit;
    }

    /**
     * Helper: Send contract email
     */
    protected function sendContractEmail($contractId)
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
            ->where('name', 'contract_pending')
            ->where('is_active', 1)
            ->first();

        if (!$emailTemplate) {
            return false;
        }

        // Get company name from WHMCS settings
        $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

        // Generate sign URL
        $signUrl = \App::getSystemURL() . 'index.php?m=recurring_contracts&action=sign&id=' . $contractId . '&token=' . $this->generateToken($contractId);

        // Process email variables
        $variables = [
            '{$client_name}' => $contract->firstname . ' ' . $contract->lastname,
            '{$client_email}' => $contract->email,
            '{$service_name}' => $contract->product_name ?: 'N/A',
            '{$contract_number}' => $contract->contract_number,
            '{$contract_name}' => $contract->template_name,
            '{$start_date}' => date('F d, Y', strtotime($contract->start_date)),
            '{$end_date}' => date('F d, Y', strtotime($contract->end_date)),
            '{$contract_duration}' => $this->calculateDurationMonths($contract->start_date, $contract->end_date),
            '{$sign_url}' => $signUrl,
            '{$company_name}' => $companyName,
        ];

        $subject = str_replace(array_keys($variables), array_values($variables), $emailTemplate->subject);
        $body = str_replace(array_keys($variables), array_values($variables), $emailTemplate->body);

        // Send email using WHMCS local API
        $result = localAPI('SendEmail', [
            'customtype' => 'general',
            'customsubject' => $subject,
            'custommessage' => $body,
            'id' => $contract->client_id,
        ]);

        // Log the email send
        $this->logAction($contractId, $contract->client_id, $_SESSION['adminid'] ?? null, 'reminder_sent', 'Contract pending email sent');

        return $result['result'] === 'success';
    }

    /**
     * Helper: Generate contract number
     */
    protected function generateContractNumber()
    {
        $settings = Capsule::table('mod_recurring_contracts_settings')
            ->pluck('setting_value', 'setting_key')
            ->toArray();

        $prefix = $settings['contract_number_prefix'] ?? 'CTR-';
        $format = $settings['contract_number_format'] ?? '{prefix}{year}{month}-{id}';

        // Get next ID
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
     * Helper: Process contract content with variables
     */
    protected function processContractContent($content, $client, $serviceId, $contractNumber, $startDate, $endDate, $durationMonths)
    {
        // Get service info
        $serviceName = 'N/A';
        if ($serviceId) {
            $service = Capsule::table('tblhosting as h')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->where('h.id', $serviceId)
                ->select('p.name', 'h.domain')
                ->first();
            if ($service) {
                $serviceName = $service->name . ($service->domain ? ' (' . $service->domain . ')' : '');
            }
        }

        // Get company name
        $companyName = Capsule::table('tblconfiguration')->where('setting', 'CompanyName')->value('value');

        $variables = [
            '{$client_name}' => $client->firstname . ' ' . $client->lastname,
            '{$client_email}' => $client->email,
            '{$client_company}' => $client->companyname ?: '',
            '{$service_name}' => $serviceName,
            '{$contract_number}' => $contractNumber,
            '{$start_date}' => date('F d, Y', strtotime($startDate)),
            '{$end_date}' => date('F d, Y', strtotime($endDate)),
            '{$duration_months}' => $durationMonths,
            '{$company_name}' => $companyName,
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Helper: Generate token for contract signing URL
     */
    protected function generateToken($contractId)
    {
        return hash('sha256', $contractId . 'recurring_contracts_salt_' . date('Y'));
    }

    /**
     * Helper: Calculate duration in months
     */
    protected function calculateDurationMonths($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        return ($interval->y * 12) + $interval->m;
    }

    /**
     * Helper: Log action
     */
    protected function logAction($contractId, $clientId, $adminId, $action, $details = null)
    {
        Capsule::table('mod_recurring_contracts_logs')->insert([
            'contract_id' => $contractId,
            'client_id' => $clientId,
            'admin_id' => $adminId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Helper: Get status CSS class
     */
    protected function getStatusClass($status)
    {
        $classes = [
            'pending' => 'warning',
            'active' => 'success',
            'expired' => 'default',
            'cancelled' => 'danger',
            'terminated' => 'danger',
        ];
        return $classes[$status] ?? 'default';
    }

    /**
     * Helper: Get action CSS class
     */
    protected function getActionClass($action)
    {
        $classes = [
            'created' => 'info',
            'signed' => 'success',
            'renewed' => 'success',
            'cancelled' => 'danger',
            'terminated' => 'danger',
            'expired' => 'warning',
            'modified' => 'primary',
            'reminder_sent' => 'info',
            'viewed' => 'default',
        ];
        return $classes[$action] ?? 'default';
    }
}
