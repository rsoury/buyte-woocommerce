<!-- The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->
<div id="buyte_widget" style="margin: 20px -20px 0 -20px;text-align: center;">
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
</div>
<!-- / The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->