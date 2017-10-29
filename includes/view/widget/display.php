<!-- The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->
<div class="buyte_widget" style="margin: 20px -20px 0 -20px;text-align: center;">
	<script 
		type="text/javascript"
		src="https://s3.us-east-2.amazonaws.com/buyte.cdn.production/js/latest/buyte.js"
		class="buyte-mobile-payment"
		data-public-key="<?php echo $public_key; ?>"
		
		<?php echo $output_options; ?>

		>
	</script>
	<?php if($page_js): ?>
		<script type="text/javascript" src="<?php echo $page_js; ?>"></script>
	<?php endif; ?>
	<script type="text/javascript">
		var buyte_original_success_endpoint = "/?p=buyte&route=payment&action_type=success<?php echo array_key_exists('product_id', $widget_data) ? '&product_id=' . $widget_data['product_id'] : ''; ?>";
		var buyte_success_endpoint = buyte_original_success_endpoint;
		(function($){
			window.Buyte('onSuccess', function(){
				console.log(buyte_success_endpoint);
				$.ajax({
					url: buyte_success_endpoint,
					method: 'GET',
					success: function(data){
						alert(data);
						// window.location.href = data;
					},
					error: function(e){
						console.error(e);
					}
				})
			});
		})(window.jQuery || window.$);
	</script>
</div>
<!-- / The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->