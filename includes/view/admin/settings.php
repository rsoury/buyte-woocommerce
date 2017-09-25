<h2><?php _e($this->settings_title, 'woocommerce'); ?></h2>
<p><?php _e($this->settings_description, 'woocommerce'); ?></p>
<?php $this->admin_options(); ?>
<table id="shipping-methods-table">
	<thead>
		<tr>
			<td>Shipping Title</td>
			<td>Shipping Price</td>
			<td>Shipping Description</td>
			<td></td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				<input class="input-text regular-input shipping-title-input" placeholder="Express Delivery" type="text" />
			</td>
			<td>
				<input class="wc_input_decimal input-text regular-input shipping-price-input" placeholder="5.99" type="text" />
			</td>
			<td>
				<input class="input-text regular-input shipping-desc-input" placeholder="Delivery in 5 days" type="text" />
			</td>
			<td>
				<div class="button-primary">+</div>
			</td>
		</tr>
	</tbody>
</table>
<link rel="stylesheet" href="<?php echo $admin_options_css; ?>"/>
<script type="text/javascript" src="<?php echo $admin_option_js;?>"></script>