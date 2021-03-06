<?php

class Helper
{

    public static $api = null;
    public static $db = null;

    public function __construct($db, $api)
    {
        Helper::$api = $api;
        Helper::$db = $db;
    }

    /*
     *  Performs after payment received functionality.
     *  Called from www/ajax.php or www/callback.php.
     *
     *
     *
     *
     */
    public static function complete_order($oid)
    {

        $order = Helper::get_order($oid);
        error_log('complete_order.order: '. print_r($order,true));

        //- We can use this too if we need it
        //$history = Helper::$api->get_address_history($order->address);
        //error_log('complete_order.history: '. print_r($history,true));

        //- remove cached fwd_addr, oid etc.
        session_unset();

        /*
         *  Processes to run once payment is received
         *  Update DB, download link, send email etc.
         *
         *
         *  USES
         *  Add function here to create secure dowload links for your download system.
         *  Customize email template to include purchased information or attachment.
         *  Process micro-pay contact / support request form on receipt of funds.
         *
         */

        $message = ('<a href="http://simplebtcpay.com/download.php?oid='.$oid.'&secret='.$order->secret.'" target="_new">Download File</a>');

        //- so we don't process status update and emails again
        if($order->status != 'COMPLETE')    {
            Helper::update_order($oid, 'status', 'COMPLETE');

            Helper::order_email_admin($oid);
            Helper::order_email_user($oid);
        }

        return $message;
    }


    public static function send_email($msg, $subj, $to)
    {

        $headers = 'From: '.SBTCP_EMAIL_FROM."\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        if(trim($msg) == '')    return false;

        return mail($to, $subj, $msg, $headers);
    }

    public static function send_email_sms($msg, $to)
    {
        if(trim($msg) == '')    return false;
        if($to == '')   return false;

        $headers = 'From: '.SBTCP_EMAIL_FROM."\r\n";

        return mail($to, null, $msg."\n.", $headers);
    }

    public static function get_order($oid, $where='oid')
    {
        $sql = "SELECT * FROM orders WHERE `$where` = :$where";

        $qry = Helper::$db->prepare($sql);
        $qry->bindValue(':'.$where, $oid);
        $qry->execute();

        return $qry->fetch(PDO::FETCH_OBJ);
    }

    public static function order_email_admin($oid)
    {

        if(!$order = Helper::get_order($oid))   return false;

        $history = Helper::$api->get_address_history($order->address);

        $msg  = 'Order: '.$order->oid."\n";
        $msg .= 'BTC: '.number_format($order->total, 4)."B\n";
        $msg .= 'USD: $'.number_format($order->tot_usd, 2)."\n";
        //if($order->email != '') $msg .= 'Email: '.print_r($order->email, true)."\n";
        if($order->desc != '') $msg .= 'Item: '. print_r($order->desc, true)."\n";

        $tmpl = file_get_contents(SBTCP_PATH.'/style/tmpl/email.admin.tmpl.html');

        if(defined('SBTCP_CALLBACK'))   {
            $filters = array(
                             'address'   => FILTER_SANITIZE_STRING,
                             'secret' => FILTER_SANITIZE_STRING,
                             'oid'    => FILTER_SANITIZE_STRING,
                             'value'  => FILTER_SANITIZE_STRING,
                             'input_address'      => FILTER_SANITIZE_STRING,
                             'confirmations'      => FILTER_SANITIZE_STRING,
                             'transaction_hash'   => FILTER_SANITIZE_STRING,
                             'destination_address'   => FILTER_SANITIZE_STRING,
                             'input_transaction_hash'=> FILTER_SANITIZE_STRING,
                            );
            $get = filter_input_array(INPUT_GET, $filters);

            $balance = Helper::$api->get_address_balance($get['destination_address']);

            $map['{input_address}'] = $get['input_address'];
            $map['{confirmations}'] = $get['confirmations'];
            $map['{trans_hash}'] = $get['transaction_hash'];
            $map['{callback}'] = 'true';

            $msg .= 'Bal:'.$balance."\n";
            $msg .= "CLBK\n";
        } else  {
            $get = null;
            $balance = Helper::$api->get_address_balance($order->address);
            $map['{input_address}'] = $history->address;
            $map['{confirmations}'] = 'na';
            $map['{trans_hash}'] = $history->txns[0]->hash;
            $map['{callback}'] = 'false';

        }

	$map['{receipt_address}'] = $history->address;
        $map['{final_balance}'] = $history->final_balance;
        $map['{total_received}'] = $history->total_received;
        $map['{total_sent}'] = $history->total_sent;

        if($input_address != '') $msg .= 'InAddr:'.$input_address."\n";
        if($map['{receipt_address}'] != '')    $msg .= 'RcvAddr:'.substr($map['{receipt_address}'], 0, 3).'..'.substr($map['{receipt_address}'], -3)."\n";
        if($balance != '') $msg .= 'Bal: '.number_format($balance, 4)."B\n";

        foreach($order as $key => $val) {
            $map['{'.$key.'}'] = $val;
        }

        $map['{balance}'] = number_format($balance, 8);
        $map['{total}'] = number_format($order->total, 8);
        $map['{tot_usd}'] = number_format($order->tot_usd, 2);
        $map['{timestamp}'] = date('Y-m-d H:i:s', SBTCP_GLOBAL_TIMESTAMP);
        $map['{hostname}'] = php_uname('n');
        //$map['{callback}'] = defined('SBTCP_CALLBACK') ? 'true':'false';

        $html = str_replace(array_keys($map), array_values($map), $tmpl);

        //- lop off last newline
        $msg = substr($msg, 0, -1);

        //- send to carrier's email to SMS gateway if configured
        if(defined(SBTCP_SMS_ADMIN) && filter_var(SBTCP_SMS_ADMIN, FILTER_VALIDATE_EMAIL))  {
            Helper::send_email_sms($msg, SBTCP_SMS_ADMIN);
        }

        return Helper::send_email($html, 'SBTCP:Order Completed', SBTCP_EMAIL_ADMIN);
    }

    public static function order_email_user($oid)
    {
        if(!$order = Helper::get_order($oid))   return false;
        if(!$order->email || !filter_var($order->email, FILTER_VALIDATE_EMAIL))  return false;

        $history = Helper::$api->get_address_history($order->address);

        $tmpl = file_get_contents(SBTCP_PATH.'/style/tmpl/email.user.tmpl.html');

        if(defined('SBTCP_CALLBACK'))   {
            $filters = array(
                             'address'   => FILTER_SANITIZE_STRING,
                             'secret' => FILTER_SANITIZE_STRING,
                             'oid'    => FILTER_SANITIZE_STRING,
                             'value'  => FILTER_SANITIZE_STRING,
                             'input_address'      => FILTER_SANITIZE_STRING,
                             'confirmations'      => FILTER_SANITIZE_STRING,
                             'transaction_hash'   => FILTER_SANITIZE_STRING,
                             'destination_address'   => FILTER_SANITIZE_STRING,
                             'input_transaction_hash'=> FILTER_SANITIZE_STRING,
                            );
            $get = filter_input_array(INPUT_GET, $filters);

            $balance = Helper::$api->get_address_balance($get['destination_address']);

            $map['{input_address}'] = $get['input_address'];
            $map['{confirmations}'] = $get['confirmations'];
            $map['{trans_hash}'] = $get['transaction_hash'];
            $map['{callback}'] = 'true';
        } else  {
            $get = null;
            $balance = Helper::$api->get_address_balance($order->address);
            $map['{input_address}'] = $history->address;
            $map['{confirmations}'] = 'na';
            $map['{trans_hash}'] = $history->txns[0]->hash;
            $map['{callback}'] = 'false';
        }

        $map['{receipt_address}'] = $history->address;
        $map['{final_balance}'] = $history->final_balance;
        $map['{total_received}'] = $history->total_received;
        $map['{total_sent}'] = $history->total_sent;

        foreach($order as $key => $val) {
            $map['{'.$key.'}'] = $val;
        }
        $map['{balance}'] = number_format($balance, 8);
        $map['{total}'] = number_format($order->total, 8);
        $map['{tot_usd}'] = number_format($order->tot_usd, 2);
        $map['{timestamp}'] = date('Y-m-d H:i:s', SBTCP_GLOBAL_TIMESTAMP);
        //$map['{callback}'] = defined('SBTCP_CALLBACK') ? 'true':'false';

        $html = str_replace(array_keys($map), array_values($map), $tmpl);

        return Helper::send_email($html, 'Order Completed', $order->email);
    }

    public static function rand_id($length=6)
    {
        $rand_id = crypt(uniqid(rand(), 1));
        $rand_id = strip_tags(stripslashes($rand_id));
        $rand_id = str_replace(".", "", $rand_id);
        $rand_id = strrev(str_replace("/", "", $rand_id));

        return substr($rand_id, 0, $length);
    }

    public static function update_order($oid, $key, $val)
    {
        try {
            $sql = "UPDATE orders SET `".$key."` = :val WHERE `oid` = :oid";
            $qry = Helper::$db->prepare($sql);
            $qry->bindValue(':oid', $oid);
            $qry->bindValue(':val', $val);
            return $qry->execute();
        }  catch (PDOException $e) {
            error_log('error: '. print_r($e->getMessage(),true));
            error_log('['.__LINE__.'] : '.__FILE__);
            error_log('oid: '. print_r($oid,true));
            return false;
        }
    }

    public static function walletnotify_email($txnhead)
    {
        if($txnhead['confirmations'] > 0)   return;

        $tmpl = file_get_contents(SBTCP_PATH.'/style/tmpl/email.notify.tmpl.html');

        foreach($txnhead as $key => $val)   {
            $map['{'.$key.'}'] = $val;
        }

        $map['{hostname}'] = php_uname('n');
        $map['{timestamp}'] = date('Y-m-d H:i:s', SBTCP_GLOBAL_TIMESTAMP);

        $html = str_replace(array_keys($map), array_values($map), $tmpl);

        $txid_short = substr($txnhead['txid'], 0, 4).' .. '.substr($txnhead['txid'], -4);
        $msg = "=WNotify=\nTxID: ".$txid_short.
                "\nAmt : ".$txnhead['amount'].
                "\nCmnt: ".$txnhead['comment'].
                "\nAcct: ".$txnhead['account'].
                "\nConf: ".$txnhead['confirmations'].
                "\nCat : ".$txnhead['category'].
                "\nAddr: ".$txnhead['address'].
                "";

        //- send to carrier's email to SMS gateway if configured
        if(defined('SBTCP_SMS_ADMIN') && filter_var(SBTCP_SMS_ADMIN, FILTER_VALIDATE_EMAIL))  {
            Helper::send_email_sms($msg, SBTCP_SMS_ADMIN);
        }

        return Helper::send_email($html, 'SBTCP:WalletNotify', SBTCP_EMAIL_ADMIN);;
    }
}
