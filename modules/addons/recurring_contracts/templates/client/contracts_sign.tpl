<div class="contract-sign">
    <h2>Sign Contract: {$contract->contract_number|escape}</h2>

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-file-contract"></i> Contract Agreement</h3>
                </div>
                <div class="panel-body">
                    <div class="contract-content">
                        {$contract->content}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-signature"></i> Sign Contract</h3>
                </div>
                <div class="panel-body">
                    <div id="signing-options">
                        {if $signingMethod == 'any' || $signingMethod == 'checkbox'}
                        <div class="signing-option" id="option-checkbox">
                            <h4>Option 1: Accept Agreement</h4>
                            <form id="checkbox-form">
                                <input type="hidden" name="contract_id" value="{$contract->id}">
                                <input type="hidden" name="token" value="{$token}">
                                <input type="hidden" name="signing_method" value="checkbox">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="agree" value="1" required>
                                        I have read, understood, and agree to all terms and conditions of this contract.
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fa fa-check"></i> Accept & Sign
                                </button>
                            </form>
                        </div>
                        {/if}

                        {if $signingMethod == 'any' || $signingMethod == 'signature'}
                        {if $signingMethod == 'any'}<hr>{/if}
                        <div class="signing-option" id="option-signature">
                            <h4>{if $signingMethod == 'any'}Option 2: {/if}Electronic Signature</h4>
                            <form id="signature-form">
                                <input type="hidden" name="contract_id" value="{$contract->id}">
                                <input type="hidden" name="token" value="{$token}">
                                <input type="hidden" name="signing_method" value="signature">
                                <input type="hidden" name="signature_data" id="signature-data">

                                <div class="signature-pad-container">
                                    <canvas id="signature-pad" width="{$settings.signature_canvas_width|default:400}" height="{$settings.signature_canvas_height|default:200}"></canvas>
                                </div>
                                <div class="btn-group btn-group-sm" style="margin: 10px 0;">
                                    <button type="button" class="btn btn-default" id="clear-signature">
                                        <i class="fa fa-eraser"></i> Clear
                                    </button>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fa fa-signature"></i> Sign with Signature
                                </button>
                            </form>
                        </div>
                        {/if}

                        {if $signingMethod == 'any' || $signingMethod == 'file_upload'}
                        {if $signingMethod == 'any'}<hr>{/if}
                        <div class="signing-option" id="option-upload">
                            <h4>{if $signingMethod == 'any'}Option 3: {/if}Upload Signed Contract</h4>
                            <form id="upload-form" enctype="multipart/form-data">
                                <input type="hidden" name="contract_id" value="{$contract->id}">
                                <input type="hidden" name="token" value="{$token}">
                                <p class="text-muted small">
                                    Download the contract, sign it, and upload the signed copy.
                                    <br>Allowed formats: {$settings.allowed_upload_extensions|default:'pdf,jpg,jpeg,png'}
                                    <br>Max size: {$settings.max_upload_size_mb|default:10}MB
                                </p>
                                <div class="form-group">
                                    <input type="file" name="signed_contract" class="form-control" required
                                           accept=".{$settings.allowed_upload_extensions|default:'pdf,jpg,jpeg,png'|replace:',':',.'}">
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fa fa-upload"></i> Upload Signed Contract
                                </button>
                            </form>
                        </div>
                        {/if}
                    </div>

                    <div id="signing-result" style="display: none;">
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i>
                            <strong>Contract Signed Successfully!</strong>
                            <p>Thank you for signing the contract. You will be redirected shortly...</p>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <a href="index.php?m=recurring_contracts&action=download&id={$contract->id}&token={$token}" class="btn btn-default btn-sm">
                        <i class="fa fa-download"></i> Download Contract
                    </a>
                    <a href="index.php?m=recurring_contracts" class="btn btn-link btn-sm">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contract-sign .contract-content {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 600px;
    overflow-y: auto;
}
.contract-sign .signature-pad-container {
    border: 2px solid #ddd;
    border-radius: 4px;
    background: #fff;
}
.contract-sign #signature-pad {
    display: block;
    width: 100%;
    touch-action: none;
}
.contract-sign .signing-option {
    margin-bottom: 15px;
}
.contract-sign .signing-option h4 {
    font-size: 14px;
    margin-bottom: 10px;
    color: #555;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize signature pad
    var canvas = document.getElementById('signature-pad');
    var signaturePad = null;

    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });

        // Clear signature button
        document.getElementById('clear-signature').addEventListener('click', function() {
            signaturePad.clear();
        });

        // Handle signature form submission
        document.getElementById('signature-form').addEventListener('submit', function(e) {
            e.preventDefault();

            if (signaturePad.isEmpty()) {
                alert('Please provide a signature.');
                return;
            }

            document.getElementById('signature-data').value = signaturePad.toDataURL();
            submitSigningForm(this);
        });
    }

    // Handle checkbox form submission
    var checkboxForm = document.getElementById('checkbox-form');
    if (checkboxForm) {
        checkboxForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSigningForm(this);
        });
    }

    // Handle upload form submission
    var uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitUploadForm(this);
        });
    }

    function submitSigningForm(form) {
        var formData = new FormData(form);

        fetch('index.php?m=recurring_contracts&action=sign_process', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('signing-options').style.display = 'none';
                document.getElementById('signing-result').style.display = 'block';

                if (data.redirect) {
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 2000);
                }
            } else {
                alert(data.message || 'An error occurred. Please try again.');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error(error);
        });
    }

    function submitUploadForm(form) {
        var formData = new FormData(form);

        fetch('index.php?m=recurring_contracts&action=upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('signing-options').style.display = 'none';
                document.getElementById('signing-result').style.display = 'block';

                if (data.redirect) {
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 2000);
                }
            } else {
                alert(data.message || 'An error occurred. Please try again.');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
            console.error(error);
        });
    }
});
</script>
