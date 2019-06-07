(function($) {
	var buyte_original_success_endpoint =
		"/?p=buyte&route=payment&action_type=success<?php echo array_key_exists('product_id', $widget_data) ? '&product_id=' . $widget_data['product_id'] : ''; ?>";
	var buyte_success_endpoint = buyte_original_success_endpoint;
	var buyteSettings = {
		publicKey: "<?php echo $public_key; ?>",
		widgetId: "",
		options: {
			dark: false // Should be toggleable
		},
		// Get items on page.
		items: []
	};
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
