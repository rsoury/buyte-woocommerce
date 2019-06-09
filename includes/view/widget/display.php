<!-- Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->
<div id="buyte-checkout-widget"></div>
<script type="text/javascript" src="https://js.buytecheckout.com/"></script>
<?php if($page_js): ?>
	<script type="text/javascript" src="<?php echo $page_js; ?>"></script>
<?php endif; ?>
<script type="text/javascript">
	(function($) {
		var buyte_original_success_endpoint =
			"/?p=buyte&route=payment&action_type=success<?php echo array_key_exists('product_id', $widget_data) ? '&product_id=' . $widget_data['product_id'] : ''; ?>";
		var buyte_success_endpoint = buyte_original_success_endpoint;
		var rawBuyteSettings = "<?php echo $buyte_settings; ?>";
		var buyteSettings = {};
		try{
			buyteSettings = JSON.parse(rawBuyteSettings);
		}catch(e){}
		console.log(rawBuyteSettings);
		console.log(buyteSettings);
		window.Buyte("load", buyteSettings);
		window.Buyte("onPayment", function(paymentToken) {
			$.ajax({
				url: buyte_success_endpoint,
				method: "POST",
				data: {
					paymentToken: paymentToken
				},
				success: function(data) {
					console.log(data);
					// window.location.href = data;
				},
				error: function(e) {
					console.error(e);
				}
			});
		});
	})(window.jQuery || window.$);
</script>
<!-- / Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->