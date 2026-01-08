<div class="contracts-list">
    <h2>My Contracts</h2>

    {if $pendingCount > 0}
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        You have <strong>{$pendingCount}</strong> contract(s) awaiting your signature.
    </div>
    {/if}

    {if $contracts|@count > 0}
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Contract #</th>
                    <th>Template</th>
                    <th>Product/Service</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$contracts item=contract}
                <tr>
                    <td>
                        <a href="index.php?m=recurring_contracts&action=view&id={$contract->id}">
                            {$contract->contract_number|escape}
                        </a>
                    </td>
                    <td>{$contract->template_name|escape}</td>
                    <td>
                        {if $contract->product_name}
                            {$contract->product_name|escape}
                            {if $contract->domain}
                                <br><small class="text-muted">{$contract->domain|escape}</small>
                            {/if}
                        {else}
                            <span class="text-muted">N/A</span>
                        {/if}
                    </td>
                    <td>{$contract->start_date|date_format:"%b %d, %Y"}</td>
                    <td>{$contract->end_date|date_format:"%b %d, %Y"}</td>
                    <td>
                        {if $contract->status == 'pending'}
                            <span class="label label-warning">Pending Signature</span>
                        {elseif $contract->status == 'active'}
                            <span class="label label-success">Active</span>
                        {elseif $contract->status == 'expired'}
                            <span class="label label-default">Expired</span>
                        {elseif $contract->status == 'cancelled'}
                            <span class="label label-danger">Cancelled</span>
                        {elseif $contract->status == 'terminated'}
                            <span class="label label-danger">Terminated</span>
                        {/if}
                    </td>
                    <td>
                        <a href="index.php?m=recurring_contracts&action=view&id={$contract->id}" class="btn btn-default btn-sm">
                            <i class="fa fa-eye"></i> View
                        </a>
                        {if $contract->status == 'pending'}
                        <a href="index.php?m=recurring_contracts&action=sign&id={$contract->id}" class="btn btn-success btn-sm">
                            <i class="fa fa-signature"></i> Sign
                        </a>
                        {/if}
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
    {else}
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        You don't have any contracts yet.
    </div>
    {/if}
</div>

<style>
.contracts-list .label {
    font-size: 85%;
    padding: 5px 10px;
}
.contracts-list .btn-sm {
    margin: 2px;
}
</style>
