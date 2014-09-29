<?php

    include_once('../lib/config.inc.php');
    include_once('main.inc.php');
    include_once('header.inc.php');
?>

<div id="bodycontent" style="">

    <h3>Overview</h3>
    <p>simpleBTCpay provides an easy to setup bitcoin donation / micro-payment interface.</p>

    <h4>Features</h4>
    <ul>
    <li>Simple Secure API driven functionality</li>
    <li>No secure keys or passwords stored or required.</li>
    <li>Open Source [ <a href="/download.php">download</a> ]</li>
    <li>Live BTC/USD quotes from <a href="http://BitcoinAverage.com">BitcoinAverage.com</a></li>
    <li>On-the-fly BTC forwarding addresses generated from <a href="http://blockchain.io">Blochain.io</a></li>
    <li>Only one incoming BTC address needed.</li>
    <li>Configurable minimum payment amount. [Default: 0.001B]</li>
    </ul>

    <h4>Examples</h4>
    <ul>
    <li>Link to form to allow customer to complete (all fields):<br />
        <a href="http://simplebtcpay.com/form.php?oemail=user@domain.com&tot_usd=0.50&odesc=Donation</li>">http://simplebtcpay.com/form.php?oemail=user@domain.com&tot_usd=0.50&odesc=Donation</a></li>
    <li>Direct link to payment page (all fields):
        <a href="http://simplebtcpay.com/form.php?oemail=user@domain.com&tot_usd=0.50&odesc=Donation">http://simplebtcpay.com/form.php?oemail=user@domain.com&tot_usd=0.50&odesc=Donation</a></li>
    <li>Link to minimal form:<br />
        <a href="http://simplebtcpay.com/form.php?tot_usd=0.50">http://simplebtcpay.com/form.php?tot_usd=0.50</a></li>
    <li>Form with optional fields disabled:<br />
        <a href="http://simplebtcpay.com/form.php?oid=false&oemail=false&tot_usd=0.50&odesc=false">http://simplebtcpay.com/form.php?oid=false&oemail=false&tot_usd=0.50&odesc=false</a></li>
    </ul>

<h2 id="exchrate">1 BTC = $<?php echo number_format($exch_rate, 2); ?></h2>
<div style="text-align:center;"><img src="images/bitcoin-logo.png" id="logo" alt="SimpleBTCPay" width="200" height="52" border="0" align="center" /></div>

<?php

    include_once('form.inc.php');

?>

<div class="">
<h3 class="">Uses</h3>
<ul style="">
    <li>Donations</li>
    <li>Download Forms</li>
    <li>Invoice Payments</li>
    <li>Micro-pay Support Form</li>
</ul>
</div>


</div>


<?php include('footer.inc.php'); ?>

<script>
<!--



-->
</script>
</body>
</html>
