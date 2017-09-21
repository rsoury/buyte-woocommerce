<!-- The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->
<script 
	class="buyte-mobile-payment"
	data-public-key="pk_test_2bac8470ff6cda601f1b25118d377f73f2ab953d"
	
	<?php $this->output_widget_options(); ?>

	data-on-success="apple_pay_success"
	data-on-failure="apple_pay_failure"
	data-on-cancel="apple_pay_cancel"
	>
    !function(t,e){var a=function(){var t=e.createElement("script");t.async=!0,t.type="text/javascript",t.src="https://s3.us-east-2.amazonaws.com/buyte.cdn.production/js/v1/buyte.js";var a=e.getElementsByTagName("script")[0];a.parentNode.insertBefore(t,a)};"complete"===e.readyState?a():t.attachEvent?t.attachEvent("onload",a):t.addEventListener("load",a)}(window,document);
</script>

<script type="text/javascript">
	var apple_pay_success = function(){
		console.log("Apple Pay SUCCESS Window Callback");
	};
	var apple_pay_failure = function(){
		console.log("Apple Pay FAILURE Window Callback");
	};
	var apple_pay_cancel = function(){
		console.log("Apple Pay CANCEL Window Callback");
	}
</script>
<!-- / The Buyte Mobile Payments Button - For more info visit: https://buyte.co/ -->