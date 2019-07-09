<!-- Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->
<?php
	$ajaxurl = admin_url( 'admin-ajax.php' );
	$nextNonce = wp_create_nonce( WC_Buyte::NONCE_NAME );
?>
<div id="buyte-checkout-widget"></div>
<script type="text/javascript" src="https://js.buytecheckout.com/v1/"></script>
<?php if($page_js): ?>
	<script type="text/javascript" src="<?php echo $page_js; ?>"></script>
<?php endif; ?>
<script type="text/javascript">
	(function($) {
		var params = {
			nextNonce: "<?php echo $nextNonce; ?>"
		};
		var productId = parseInt(
			"<?php echo array_key_exists('product_id', $widget_data) ? $widget_data['product_id'] : 0; ?>",
			10
		);
		if (!!productId) {
			params.productId = productId;
		}
		window.wc_buyte = {
			product_variation: function(variationId) {
				if (!!variationId) {
					params.variationId = variationId;
				} else {
					delete params.variationId;
				}
			},
			product_quantity: function(quantity) {
				if (!!quantity) {
					params.quantity = quantity;
				} else {
					params.quantity = 1;
				}
			}
		};

		var rawBuyteSettings = "<?php echo $buyte_settings; ?>";
		var buyteSettings = {};
		try {
			buyteSettings = JSON.parse(rawBuyteSettings);
		} catch (e) {}
		// console.log(buyteSettings);
		window.Buyte("load", buyteSettings);
		window.Buyte("onAuthorise", function(shippingContact, setShippingMethods) {
			var addressLines = shippingContact.addressLines || [];
			var address = addressLines.shift();
			var address_2 = addressLines.join(", ");
			var city = shippingContact.locality || "";
			var state = shippingContact.administrativeArea || "";
			var postcode = shippingContact.postalCode || "";
			var country = shippingContact.country || "";

			if (!!productId) {
				// is product page.
				var productToCartParams = {
					action: buyteSettings.options.shipping
						? "<?php echo WC_Buyte::AJAX_PRODUCT_TO_CART_WITH_SHIPPING; ?>"
						: "<?php echo WC_Buyte::AJAX_PRODUCT_TO_CART; ?>",
					nextNonce: params.nextNonce,
					productId: params.productId,
					variationId: params.variationId || null,
					quantity: params.quantity || 1
				};
				if (buyteSettings.options.shipping) {
					productToCartParams.country = country;
					productToCartParams.state = state;
					productToCartParams.postcode = postcode;
					productToCartParams.city = city;
					productToCartParams.address = address;
					productToCartParams.address_2 = address_2;
				}
				$.ajax({
					url: "<?php echo $ajaxurl; ?>",
					method: "POST",
					data: productToCartParams,
					success: function(data) {
						console.log(data);
					},
					error: function(e) {
						console.error(e);
					}
				});
			} else if (buyteSettings.options.shipping) {
				// is cart/checkout page -- cart already set.
				// since cart already set, only request that needs to be made is for shipping methods if shipping enabled.
				var shippingParams = {
					action: "<?php echo WC_Buyte::AJAX_GET_SHIPPING; ?>",
					nextNonce: params.nextNonce,
					country: country,
					state: state,
					postcode: postcode,
					city: city,
					address: address,
					address_2: address_2
				};
				$.ajax({
					url: "<?php echo $ajaxurl; ?>",
					method: "POST",
					data: shippingParams,
					success: function(data) {
						console.log(data);
					},
					error: function(e) {
						console.error(e);
					}
				});
			}
		});
		window.Buyte("onPayment", function(paymentToken, done) {
			var createOrderParams = {
				action: "<?php echo WC_Buyte::AJAX_SUCCESS; ?>",
				nextNonce: params.nextNonce,
				productId: params.productId,
				variationId: params.variationId || null,
				quantity: params.quantity || 1,
				paymentToken: paymentToken
			};
			console.log(createOrderParams);
			$.ajax({
				url: "<?php echo $ajaxurl; ?>",
				method: "POST",
				data: createOrderParams,
				success: function(data) {
					if (data.result === "success") {
						window.location.replace(data.redirect);
					} else if (data.result === "failure") {
						console.error(data);
						alert(
							"Could not checkout with Buyte. Please contact support."
						);
					}
					done();
				},
				error: function(e) {
					// We want to either toast an error -- browser alerts might do for now, or redirect to an error page.
					console.error(e);
					alert("Could not checkout with Buyte. Please contact support.");
					done();
				}
			});
		});
		window.Buyte("onError", function(errorType) {
			if (errorType === "LOAD_ERROR") {
				window.Buyte("destroy");
			}
		});
	})(window.jQuery || window.$);
</script>
<!-- / Buyte Checkout Widget - For more info visit: https://buytecheckout.com/ -->