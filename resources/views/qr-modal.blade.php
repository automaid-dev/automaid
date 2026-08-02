<div id="printArea">
    <div class="logo-wrapper">
        <img src="{{ asset('assets/images/Logo_AutoMaid.png') }}" alt="Logo" class="logo" />
    </div>

    <div class="qrcode-wrapper">
        {!! QrCode::size(200)->errorCorrection('H')->style('round')->generate($qrCode) !!}
    </div>

    <div class="code-text">{!! $qrCode !!}</div>
</div>

<script>
    window.onload = function () {
        window.print();
    };
</script>

<style>
/* Optional screen styling */
#printArea {
    text-align: center;
    padding: 20px;
    border: 1px solid #CCC;
}

.logo-wrapper {
    margin-bottom: 20px;
}

.logo {
    width: 150px;
    height: auto;
}

.qrcode-wrapper {
    margin-bottom: 10px;
}

.code-text {
    font-family: 'Courier New', Courier, monospace; /* gives a code-like appearance */
    font-size: 16px;
    color: #333;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    padding: 10px 15px;
    border-radius: 8px;
    display: inline-block;
    margin-top: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

/* Print-specific styles */
@media print {
    body, html {
        height: 100%;
        margin: 0;
        padding: 0;
    }

    body * {
        visibility: hidden;
    }

    #printArea, #printArea * {
        visibility: visible;
    }

    #printArea {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
}
</style>
