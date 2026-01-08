<div class="contract-info">
    <div class="alert alert-info">
        <h4><i class="fa fa-info-circle"></i> Contract Already Signed</h4>
        <p>This contract has already been signed.</p>
        <p><strong>Contract Number:</strong> {$contract->contract_number|escape}</p>
        <p><strong>Status:</strong> {$contract->status|ucfirst}</p>
        {if $contract->signed_at}
        <p><strong>Signed on:</strong> {$contract->signed_at|date_format:"%B %d, %Y at %H:%M"}</p>
        {/if}
    </div>
    <a href="index.php?m=recurring_contracts&action=view&id={$contract->id}" class="btn btn-primary">
        <i class="fa fa-eye"></i> View Contract
    </a>
    <a href="index.php?m=recurring_contracts" class="btn btn-default">
        <i class="fa fa-arrow-left"></i> Back to My Contracts
    </a>
</div>
