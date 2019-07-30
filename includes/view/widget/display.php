<!-- Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->
<div id="buyte-checkout-widget"></div>
<?php if(WC_Buyte_Config::is_developer_mode()): ?>
	<script type="text/javascript" src="https://js.buytecheckout.com/dev/v1/index.js"></script>
<?php else: ?>
	<script type="text/javascript" src="https://js.buytecheckout.com/v1/"></script>
<?php endif;?>
<!-- / Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->