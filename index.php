<?php
/*
Plugin Name: WooCommerce CCAvenue gateway
Plugin URI: http://www.mrova.com/
Description: Extends WooCommerce with mrova ccavenue gateway.
Version: 1.2.2
Author: mRova
Author URI: http://www.mrova.com/

    Copyright: © 2009-2013 mRova.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action('plugins_loaded', 'woocommerce_mrova_ccave_init', 0);

function woocommerce_mrova_ccave_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-mrova-ccave', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    if($_GET['msg']!=''){
        add_action('the_content', 'showMessage');
    }

    function showMessage($content){
            return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }
    /**
     * Gateway class
     */
    class WC_Mrova_Ccave extends WC_Payment_Gateway {
    protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'ccavenue';
            $this -> method_title = __('CCAvenue', 'mrova');
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.gif';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> merchant_id = $this -> settings['merchant_id'];
            $this -> working_key = $this -> settings['working_key'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            $this -> liveurl = 'https://www.ccavenue.com/shopzone/cc_details.jsp';
            $this -> msg['message'] = "";
            $this -> msg['class'] = "";
          
            add_action('init', array(&$this, 'check_ccavenue_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_wc_mrova_ccave', array( $this, 'check_ccavenue_response' ) );

            add_action('valid-ccavenue-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_ccavenue', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_ccavenue',array($this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'mrova'),
                    'type' => 'checkbox',
                    'label' => __('Enable CCAvenue Payment Module.', 'mrova'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'mrova'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'mrova'),
                    'default' => __('CCAvenue', 'mrova')),
                'description' => array(
                    'title' => __('Description:', 'mrova'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'mrova'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through CCAvenue Secure Servers.', 'mrova')),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'mrova'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at CCAvenue."')),
                'working_key' => array(
                    'title' => __('Working Key', 'mrova'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by CCAvenue', 'mrova'),
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('CCAvenue Payment Gateway', 'mrova').'</h3>';
            echo '<p>'.__('CCAvenue is most popular payment gateway for online shopping in India').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for CCAvenue, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){
            echo '<p>'.__('Thank you for your order, please click the button below to pay with CCAvenue.', 'mrova').'</p>';
            echo $this -> generate_ccavenue_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
        }
        /**
         * Check for valid CCAvenue server callback
         **/
        function check_ccavenue_response(){
            global $woocommerce;
            if(isset($_REQUEST['Order_Id']) && isset($_REQUEST['AuthDesc'])){
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

                $order_id_time = $_REQUEST['Order_Id'];
                $order_id = explode('_', $_REQUEST['Order_Id']);
                $order_id = (int)$order_id[0];
                $this -> msg['class'] = 'error';
                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

                if($order_id != ''){
                    try{
                        $order = new WC_Order($order_id);
                        $merchant_id = $_REQUEST['Merchant_Id'];
                        $amount = $_REQUEST['Amount'];
                        $checksum = $_REQUEST['Checksum'];
                        $AuthDesc = $_REQUEST['AuthDesc'];
                        $Checksum = $this -> verifyCheckSum($merchant_id, $order_id_time, $amount, $AuthDesc, $checksum, $this -> working_key);
                        $transauthorised = false;
                        if($order -> status !=='completed'){
                            if($Checksum=="true")
                            {

                                if($AuthDesc=="Y"){
                                    $transauthorised = true;
                                    $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this -> msg['class'] = 'success';
                                    if($order -> status == 'processing'){

                                    }else{
                                        $order -> payment_complete();
                                        $order -> add_order_note('CCAvenue payment successful<br/>Bank Ref Number: '.$_REQUEST['nb_bid']);
                                        $order -> add_order_note($this->msg['message']);
                                        $woocommerce -> cart -> empty_cart();

                                    }

                                }else if($AuthDesc=="B"){
                                    $this -> msg['message'] = "Thank you for shopping with us. We will keep you posted regarding the status of your order through e-mail";
                                    $this -> msg['class'] = 'info';

                                    //Here you need to put in the routines/e-mail for a  "Batch Processing" order
                                    //This is only if payment for this transaction has been made by an American Express Card
                                    //since American Express authorisation status is available only after 5-6 hours by mail from ccavenue and at the "View Pending Orders"
                                }
                                else{
                                    $this -> msg['class'] = 'error';
                                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }
                            }else{
                                $this -> msg['class'] = 'error';
                                $this -> msg['message'] = "Security Error. Illegal access detected";

                                //Here you need to simply ignore this and dont need
                                //to perform any operation in this condition
                            }
                            if($transauthorised==false){
                                $order -> update_status('failed');
                                $order -> add_order_note('Failed');
                                $order -> add_order_note($this->msg['message']);
                            }
                            //removed for WooCOmmerce 2.0
                            //add_action('the_content', array(&$this, 'showMessage'));
                        }}catch(Exception $e){
                            // $errorOccurred = true;
                            $msg = "Error";
                        }

                }
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
                exit;



            }



        }
       /*
        //Removed For WooCommerce 2.0
       function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }*/
        /**
         * Generate CCAvenue button link
         **/
        public function generate_ccavenue_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
          //For wooCoomerce 2.0
            $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
            $order_id = $order_id.'_'.date("ymds");
            $checksum = $this -> getCheckSum($this -> merchant_id, $order -> order_total, $order_id, $redirect_url, $this -> working_key);
            $ccavenue_args = array(
                'Merchant_Id' => $this -> merchant_id,
                'Amount' => $order -> order_total,
                'Order_Id' => $order_id,
                'Redirect_Url' => $redirect_url,
                'Checksum' => $checksum,
                'billing_cust_name' => $order -> billing_first_name .' '. $order -> billing_last_name,
                'billing_cust_address' => $order -> billing_address_1,
                'billing_cust_country' => $order -> billing_country,
                'billing_cust_state' => $order -> billing_state,
                'billing_cust_city' => $order -> billing_city,
                'billing_zip' => $order -> shipping_postcode,
                'billing_cust_tel',
                'billing_cust_email' => $order -> billing_email,
                'delivery_cust_name' => $order -> shipping_first_name .' '. $order -> shipping_last_name,
                'delivery_cust_address' => $order -> shipping_address_1,
                'delivery_cust_country' => $order -> shipping_country,
                'delivery_cust_state' => $order -> shipping_state,
                'delivery_cust_tel' => '',
                'delivery_cust_notes' => '',
                'Merchant_Param' => '',
                'billing_zip_code' => $order -> billing_postcode,
                'delivery_cust_city' => $order -> shipping_city,
                'delivery_zip_code' => $order -> shipping_postcode
                );

            $ccavenue_args_array = array();
            foreach($ccavenue_args as $key => $value){
                $ccavenue_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            wc_enqueue_js( '
            $.blockUI({
                    message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to CcAvenue to make payment.', 'woocommerce' ) ) . '",
                    baseZ: 99999,
                    overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                    css: {
                        padding:        "20px",
                        zindex:         "9999999",
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:     "24px",
                    }
                });
            jQuery("#submit_ccavenue_payment_form").click();
        ' );

        return '<form action="' . esc_url( $this -> liveurl ) . '" method="post" id="ccavenue_payment_form" target="_top">
                ' . implode( '', $ccavenue_args_array ) . '
                <!-- Button Fallback -->
                <div class="payment_buttons">
                    <input type="submit" class="button alt" id="submit_ccavenue_payment_form" value="' . __( 'Pay via CCAvenue', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
                </div>
                <script type="text/javascript">
                    jQuery(".payment_buttons").hide();
                </script>
            </form>';




        }


        /**
         *  CCAvenue Essential Functions
         **/
        private function getCheckSum($MerchantId,$Amount,$OrderId ,$URL,$WorkingKey)
        {
            $str ="$MerchantId|$OrderId|$Amount|$URL|$WorkingKey";
            $adler = 1;
            $adler = $this -> adler32($adler,$str);
            return $adler;
        }

        private function verifyCheckSum($MerchantId,$OrderId,$Amount,$AuthDesc,$CheckSum,$WorkingKey)
        {
            $str = "$MerchantId|$OrderId|$Amount|$AuthDesc|$WorkingKey";
            $adler = 1;
            $adler = $this -> adler32($adler,$str);

            if($adler == $CheckSum)
                return "true" ;
            else
                return "false" ;
        }

        private function adler32($adler , $str)
        {
            $BASE =  65521 ;

            $s1 = $adler & 0xffff ;
            $s2 = ($adler >> 16) & 0xffff;
            for($i = 0 ; $i < strlen($str) ; $i++)
            {
                $s1 = ($s1 + Ord($str[$i])) % $BASE ;
                $s2 = ($s2 + $s1) % $BASE ;
                //echo "s1 : $s1 <BR> s2 : $s2 <BR>";

            }
            return $this -> leftshift($s2 , 16) + $s1;
        }

        private function leftshift($str , $num)
        {

            $str = DecBin($str);

            for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
                $str = "0".$str ;

            for($i = 0 ; $i < $num ; $i++)
            {
                $str = $str."0";
                $str = substr($str , 1 ) ;
                //echo "str : $str <BR>";
            }
            return $this -> cdec($str) ;
        }

        private function cdec($num)
        {

            for ($n = 0 ; $n < strlen($num) ; $n++)
            {
                $temp = $num[$n] ;
                $dec =  $dec + $temp*pow(2 , strlen($num) - $n - 1);
            }

            return $dec;
        }
        /*
         * End CCAvenue Essential Functions
         **/
        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_mrova_ccave_gateway($methods) {
        $methods[] = 'WC_Mrova_Ccave';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_mrova_ccave_gateway' );
    }

?>
