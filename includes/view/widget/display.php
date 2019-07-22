<!-- Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->
<div id="buyte-checkout-widget"></div>
<?php if(WC_Buyte::DEVELOPER_MODE): ?>
	<script type="text/javascript" src="https://js.buytecheckout.com/dev/v1/index.js"></script>
<?php else: ?>
	<script type="text/javascript" src="https://js.buytecheckout.com/v1/"></script>
<?php endif;?>
<?php if($page_js): ?>
	<script type="text/javascript" src="<?php echo $page_js; ?>"></script>
<?php endif; ?>
<script type="text/javascript">
	<?php require_once plugin_dir_path( __FILE__ ) . 'display.js'; ?>
</script>
<!-- / Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->