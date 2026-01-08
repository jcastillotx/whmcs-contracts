<div class="contract-view">
    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-file-contract"></i> Contract: {$contract->contract_number|escape}
                        {if $contract->status == 'pending'}
                            <span class="label label-warning pull-right">Pending Signature</span>
                        {elseif $contract->status == 'active'}
                            <span class="label label-success pull-right">Active</span>
                        {elseif $contract->status == 'expired'}
                            <span class="label label-default pull-right">Expired</span>
                        {elseif $contract->status == 'cancelled'}
                            <span class="label label-danger pull-right">Cancelled</span>
                        {elseif $contract->status == 'terminated'}
                            <span class="label label-danger pull-right">Terminated</span>
                        {/if}
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="contract-content">
                        {$contract->content}
                    </div>

                    {if $contract->signature_data}
                    <div class="signature-section">
                        <h4>Your Signature</h4>
                        <div class="signature-display">
                            <img src="{$contract->signature_data}" alt="Signature" class="signature-image">
                        </div>
                        <p class="text-muted">
                            Signed on {$contract->signed_at|date_format:"%B %d, %Y at %H:%M"}
                        </p>
                    </div>
                    {/if}

                    {if $contract->uploaded_file}
                    <div class="upload-section">
                        <h4>Uploaded Signed Document</h4>
                        <p>
                            <a href="{$contract->uploaded_file|escape}" class="btn btn-default" target="_blank">
                                <i class="fa fa-download"></i> Download Signed Contract
                            </a>
                        </p>
                    </div>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Contract Details</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed contract-details">
                        <tr>
                            <td><strong>Contract Number:</strong></td>
                            <td>{$contract->contract_number|escape}</td>
                        </tr>
                        <tr>
                            <td><strong>Template:</strong></td>
                            <td>{$contract->template_name|escape}</td>
                        </tr>
                        {if $contract->product_name}
                        <tr>
                            <td><strong>Product:</strong></td>
                            <td>{$contract->product_name|escape}</td>
                        </tr>
                        {/if}
                        {if $contract->domain}
                        <tr>
                            <td><strong>Domain:</strong></td>
                            <td>{$contract->domain|escape}</td>
                        </tr>
                        {/if}
                        <tr>
                            <td><strong>Start Date:</strong></td>
                            <td>{$contract->start_date|date_format:"%B %d, %Y"}</td>
                        </tr>
                        <tr>
                            <td><strong>End Date:</strong></td>
                            <td>{$contract->end_date|date_format:"%B %d, %Y"}</td>
                        </tr>
                        <tr>
                            <td><strong>Renewal:</strong></td>
                            <td>
                                {if $contract->renewal_type == 'auto'}
                                    Automatic
                                {elseif $contract->renewal_type == 'manual'}
                                    Manual
                                {else}
                                    No Renewal
                                {/if}
                            </td>
                        </tr>
                        {if $contract->discount_percent > 0}
                        <tr>
                            <td><strong>Discount:</strong></td>
                            <td>{$contract->discount_percent}%</td>
                        </tr>
                        {/if}
                        {if $contract->signed_at}
                        <tr>
                            <td><strong>Signed:</strong></td>
                            <td>{$contract->signed_at|date_format:"%B %d, %Y at %H:%M"}</td>
                        </tr>
                        {/if}
                    </table>
                </div>
                <div class="panel-footer">
                    {if $canSign}
                    <a href="index.php?m=recurring_contracts&action=sign&id={$contract->id}&token={$token}" class="btn btn-success btn-block">
                        <i class="fa fa-signature"></i> Sign Contract
                    </a>
                    {/if}
                    <a href="index.php?m=recurring_contracts&action=download&id={$contract->id}&token={$token}" class="btn btn-default btn-block" style="margin-top: 10px;">
                        <i class="fa fa-download"></i> Download Contract
                    </a>
                    <a href="index.php?m=recurring_contracts" class="btn btn-link btn-block">
                        <i class="fa fa-arrow-left"></i> Back to Contracts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contract-view .contract-content {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 600px;
    overflow-y: auto;
}
.contract-view .signature-section,
.contract-view .upload-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
.contract-view .signature-display {
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    display: inline-block;
    border-radius: 4px;
}
.contract-view .signature-image {
    max-width: 300px;
    height: auto;
}
.contract-view .contract-details td {
    padding: 8px 5px;
}
.contract-view .label {
    font-size: 85%;
}
</style>
