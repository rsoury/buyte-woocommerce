<!-- Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->
<?php
	$ajaxurl = admin_url( 'admin-ajax.php' );
	$nextNonce = wp_create_nonce( WC_Buyte::NONCE_NAME );
	$checkoutNonce = wp_create_nonce( 'woocommerce-process_checkout' ); // Required to process checkout using WC
?>
<div id="buyte-checkout-widget"></div>
<script type="text/javascript" src="https://js.buytecheckout.com/"></script>
<?php if($page_js): ?>
	<script type="text/javascript" src="<?php echo $page_js; ?>"></script>
<?php endif; ?>
<script type="text/javascript">
	(function($) {
		var params = {
			action: "<?php echo WC_Buyte::AJAX_SUCCESS; ?>",
			nextNonce: "<?php echo $nextNonce; ?>",
			_wpnonce: "<?php echo $checkoutNonce; ?>"
		};
		var productId = <?php echo array_key_exists('product_id', $widget_data) ? $widget_data['product_id'] : 0; ?>;
		if(!!productId){
			params.productId = productId;
		}
		window.wc_buyte = {
			product_variation: function(variationId){
				if(!!variationId){
					params.variationId = variationId;
				}else{
					delete params.variationId;
				}
			},
			product_quantity: function(quantity){
				if(!!quantity){
					params.quantity = quantity;
				}else{
					params.quantity = 1;
				}
			}
		};

		var rawBuyteSettings = '<?php echo $buyte_settings; ?>';
		var buyteSettings = {};
		try{
			buyteSettings = JSON.parse(rawBuyteSettings);
		}catch(e){}
		window.Buyte("load", buyteSettings);
		window.Buyte("onPayment", function(paymentToken) {
			params.paymentToken = paymentToken;
			console.log(params);
			$.ajax({
				url: "<?php echo $ajaxurl; ?>",
				method: "POST",
				data: params,
				success: function(data) {
					if(data.result === "success"){
						// ...
					}else if(data.result === "failure"){
						console.log(data);
						alert("Could not checkout with Buyte. Please contact support.");
					}
				},
				error: function(e) {
					// We want to either toast an error -- browser alerts might do for now, or redirect to an error page.
					console.error(e);
					alert("Could not checkout with Buyte. Please contact support.");
				}
			});
		});
		window.Buyte("onError", function(errorType){
			if(errorType === "LOAD_ERROR"){
				window.Buyte("destroy");
			}
		})
	})(window.jQuery || window.$);
</script>
<!-- / Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->