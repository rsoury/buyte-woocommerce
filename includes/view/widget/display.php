<!-- The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->
<div id="buyte_widget" style="margin: 20px 0 0 0;text-align: center;">
	<script 
		class="buyte-mobile-payment"
		data-public-key="<?php echo $public_key; ?>"
		
		<?php echo $output_options; ?>

		data-on-success="buyte_apple_pay_success"
		data-on-failure="buyte_apple_pay_failure"
		data-on-cancel="buyte_apple_pay_cancel"
		>
	    !function(t,e){var a=function(){var t=e.createElement("script");t.async=!0,t.type="text/javascript",t.src="https://s3.us-east-2.amazonaws.com/buyte.cdn.production/js/v1/buyte.js";var a=e.getElementsByTagName("script")[0];a.parentNode.insertBefore(t,a)};"complete"===e.readyState?a():t.attachEvent?t.attachEvent("onload",a):t.addEventListener("load",a)}(window,document);
	</script>
</div>

<script type="text/javascript">
	var buyte_apple_pay_success = function(){
		console.log("Apple Pay SUCCESS Window Callback");
	};
	var buyte_apple_pay_failure = function(){
		console.log("Apple Pay FAILURE Window Callback");
	};
	var buyte_apple_pay_cancel = function(){
		console.log("Apple Pay CANCEL Window Callback");
	}
</script>
<!-- / The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->