<h2><?php _e($this->settings_title, 'woocommerce'); ?></h2>
<p><?php _e($this->settings_description, 'woocommerce'); ?></p>
<p><a href="<?php echo $this->$settings_webite; ?>" target="_blank" rel="noopener noreferrer">Don't have a Buyte account?</a></p>
<?php $this->admin_options(); ?>
<link rel="stylesheet" href="<?php echo $admin_options_css; ?>"/>
<script type="text/javascript" src="<?php echo $admin_option_js;?>"></script>