<h2><?php _e($this->settings_title, 'woocommerce'); ?></h2>
<p><?php _e($this->settings_description, 'woocommerce'); ?></p>
<?php $this->admin_options(); ?>
<table id="shipping-methods-table">
	<thead>
		<tr>
			<td>Shipping Title<div><i>eg. Express Delivery</i></div></td>
			<td>Shipping Price<div><i>eg. 5.99</i></div></td>
			<td>Shipping Description<div><i>eg. Delivery in 5 days</i></div></td>
			<td></td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				<input class="input-text regular-input shipping-title-input" type="text" />
			</td>
			<td>
				<input class="wc_input_decimal input-text regular-input shipping-price-input" type="text" />
			</td>
			<td>
				<input class="input-text regular-input shipping-desc-input" type="text" />
			</td>
			<td>
				<div class="button-primary">+</div>
			</td>
		</tr>
	</tbody>
</table>
<link rel="stylesheet" href="<?php echo $admin_options_css; ?>"/>
<script type="text/javascript" src="<?php echo $admin_option_js;?>"></script>