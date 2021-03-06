<?php

   include_once('../app/lib/config.inc.php');

   $tot_btc = $tot_usd = $oid = $odesc = $order = null;
   //- prefer _POSTed variables
   if(count($_POST) > 0)   {
      $filters = array(
                     'tot_btc'  => FILTER_SANITIZE_STRING,
                     'tot_usd'  => FILTER_SANITIZE_STRING,
                     'oid'      => FILTER_SANITIZE_STRING,
                     'odesc'    => FILTER_SANITIZE_STRING,
                     'oemail'   => FILTER_SANITIZE_STRING,
                     'act'      => FILTER_SANITIZE_STRING,
                     );
      extract(filter_input_array(INPUT_POST, $filters));
   }   elseif(count($_GET) > 0)   {
      //- but accept in _GET if _POST empty
      $filters = array(
                     'tot_btc'  => FILTER_SANITIZE_STRING,
                     'tot_usd'  => FILTER_SANITIZE_STRING,
                     'oid'      => FILTER_SANITIZE_STRING,
                     'odesc'    => FILTER_SANITIZE_STRING,
                     'oemail'   => FILTER_SANITIZE_STRING,
                     'act'      => FILTER_SANITIZE_STRING,
                     );
      extract(filter_input_array(INPUT_GET, $filters));
   }

   include_once('app/lib/main.inc.php');

   $order = false;
   if($_GET['oid'] != '')  {
      $order = Helper::get_order($oid);
      if($order)  {
         $tot_btc = $order->tot_btc;
         $tot_usd = $order->tot_usd;
         $address = $order->address;
         $odesc = $order->desc;
         $oemail = $order->email;
         $secret = $order->secret;
      }
   }

   if(!$order) {
      $oid = Helper::rand_id();
      $secret = Helper::rand_id(13);
   }

   if($tot_usd > 0)    {
      $tot_btc = round($tot_usd / $exch_rate, 8);
      $total = $tot_btc;
   }   else    {
      $tot_usd = round($tot_btc * $exch_rate, 2);
      $total = $tot_btc;
   }

   if($total < 0.001)  {
      header('Location: ./form.php?act=error.minimum');
      return false;
   }

   if(!$receive_addr = $api->get_receive_address(SBTCP_RECEIVE_ADDR, $secret, $oid))   {
      $receive_addr = SBTCP_RECEIVE_ADDR;
      error_log('Invalid Receive Address. Defaulting to main address. Disable at:');
      error_log('['.__LINE__.'] : '.__FILE__);
   }

   //- harcode to main address if not using a forwarding address
   //$receive_addr = SBTCP_RECEIVE_ADDR;

   try {
      $sql =   "REPLACE INTO orders ".
               "(oid, total, email, desc, status, btc_usd, ".
               "tot_usd, tot_btc, address, secret, t_stamp) ".
               "VALUES ".
               "(:oid, :total, :email, :desc, :status, :btc_usd, ".
               ":tot_usd, :tot_btc, :address, :secret, :t_stamp)";

      $qry = $db->prepare($sql);
      $vars = array(
                  ':oid'    => $oid,
                  ':total'  => round($total, 8),
                  ':email'  => $oemail,
                  ':desc'   => $odesc,
                  ':status' => 'PENDING',
                  ':btc_usd'=> round($exch_rate, 2),
                  ':tot_usd'=> round($tot_usd, 2),
                  ':tot_btc'=> round($tot_btc, 8),
                  ':address'=> $receive_addr,
                  ':secret' => $secret,
                  ':t_stamp'=> time()
                  );

      error_log('vars: '. print_r($vars,true));
      foreach($vars as $key => $val)  {
         $qry->bindValue($key, $val);
      }

      $qry->execute();

   }  catch (PDOException $e) {
      error_log('error: '. print_r($e->getMessage(),true));
      error_log('['.__LINE__.'] : '.__FILE__);
      error_log('_REQUEST: '. print_r($_REQUEST,true));
      error_log('vars: '. print_r($vars,true));
   }


   include_once('app/lib/header.inc.php');

?>

<div id="orderform">
<?php if($receive_addr): ?>
<h3 id="sendbtc">Send <?php echo round($total, 8).' '.SBTCP_COIN; ?> <?php if($tot_usd > 0) echo '($'.number_format($tot_usd, 2).') '; ?>to:</h3>

<?php echo '<img src="./qr.php?addr='.$receive_addr.'&amount='.$total.'&orderid='.$oid.'" width="264" height="264" class="qrcode">'."\n"; ?>
<div style="padding:.5em;">
<?php echo '<a href="darkcoin:'.$receive_addr.'?amount='.$total.'&label='.$oid.'" title="">'.$receive_addr.'</a>'."\n";  ?>
</div>

<div id="invoice">
  <?php if($oid != ''): ?>
	 <div class="invrow">
		  <div class="invhead">ID:</div>
		  <div class="invitem"><?php echo $oid; ?></div>
	 </div>
  <?php endif; ?>
  <?php if($odesc != ''): ?>
	 <div class="invrow">
		  <div class="invhead">Item:</div>
		  <div class="invitem"><?php echo $odesc; ?></div>
	 </div>
  <?php endif; ?>
  <?php if($oemail != ''): ?>
	 <div class="invrow">
		  <div class="invhead">Email:</div>
		  <div class="invitem"><?php echo $oemail; ?></div>
	 </div>
  <?php endif; ?>
	 <div class="invrow">
		  <div class="invhead">Total:</div>
		  <div class="invitem"><?php echo number_format($total, 8); ?> <?php echo SBTCP_COIN; ?></div>
	 </div>
</div>
<br />

<div class="wide">
<button type="submit" name="receipt" id="checkreceipt" class="btn btn-success">Check Receipt</button>
</div>
<br />
<center>
<div id="results">
</div>
</center>

<?php else: ?>

<h3 class="error">Error: Please Try Again Later</h3>

<?php endif; ?>

<?php include('app/lib/footer.inc.php'); ?>

<script>
<!--

$( "#checkreceipt" ).click(function() {
	 $("#results" ).show( "slow", function() {
		$("#results").html('<div id="loading"><img src="images/loader.gif" alt="loading" height="20" width="20" align="center" /></div>');
		$.ajax({
		  url: "ajax.php?act=check_receipt&addr=<?php echo $receive_addr;?>&oid=<?php echo $oid;?>",
		  cache: false,
		  success: function(html){
			 var json = $.parseJSON(html);
			 if(!json.return)  {
				$("#results").html(json.message);
			 } else  {
				$("#results").html('Received: '+json.balance+'<br />'+json.message);
			 }
		  }
		});
	 });
});

-->
</script>
</body>
</html>



