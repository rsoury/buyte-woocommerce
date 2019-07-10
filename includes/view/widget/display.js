/**
	This PHP code is editted in it's own JS file to leverage the power of prettier.
 */

(function($) {
	var config = {
		nextNonce: "<?php echo $nextNonce; ?>",
		rawBuyteSettings: "<?php echo $buyte_settings; ?>",
		endpoint: "<?php echo $ajaxurl; ?>",
		actions: {
			getShipping: "<?php echo WC_Buyte::AJAX_GET_SHIPPING; ?>",
			productToCartWithShipping:
				"<?php echo WC_Buyte::AJAX_PRODUCT_TO_CART_WITH_SHIPPING; ?>",
			productToCart: "<?php echo WC_Buyte::AJAX_PRODUCT_TO_CART; ?>",
			success: "<?php echo WC_Buyte::AJAX_SUCCESS; ?>"
		},
		productId: parseInt(
			"<?php echo array_key_exists('product_id', $widget_data) ? $widget_data['product_id'] : 0; ?>",
			10
		),
		variationId: 0,
		quantity: 1
	};

	window.wc_buyte = {
		product_variation: function(variationId) {
			config.variationId = !!variationId ? variationId : 0;
		},
		product_quantity: function(quantity) {
			config.quantity = !!quantity ? quantity : 1;
		}
	};

	var onError = function(e) {
		console.error(e);
		alert(
			"Could not authorise payment with Buyte. Please contact support."
		);
	};
	var post = function(params, cb, errCb) {
		if (!errCb) {
			errCb = onError;
		}
		$.ajax({
			url: config.endpoint,
			method: "POST",
			data: params,
			success: function(data) {
				if (data.result === "success") {
					cb(data);
				} else {
					errCb(data);
				}
			},
			error: errCb
		});
	};

	var buyteSettings = {};
	try {
		buyteSettings = JSON.parse(config.rawBuyteSettings);
	} catch (e) {}
	// console.log(buyteSettings);
	window.Buyte("load", buyteSettings);
	window.Buyte("onReady", function(settings) {
		// onReady get gettings and check if shipping is set.
		if (settings.options.shipping) {
			window.Buyte("onShippingRequired", function(shippingContact, done) {
				// onShippingRequired get shipping details and request shipping rates.
				// If product id also exists, convert product to cart.
				var addressLines = shippingContact.addressLines || [];
				var address = addressLines.shift();
				var address_2 = addressLines.join(", ");
				var city = shippingContact.locality || "";
				var state = shippingContact.administrativeArea || "";
				var postcode = shippingContact.postalCode || "";
				var country = shippingContact.countryCode || "";

				var params = {
					action: config.actions.getShipping,
					nextNonce: config.nextNonce,
					country: country,
					state: state,
					postcode: postcode,
					city: city,
					address: address,
					address_2: address_2
				};
				// console.log(params);
				if (config.productId > 0) {
					params.action = config.actions.productToCartWithShipping;
					params.productId = config.productId;
					params.variationId = config.variationId;
					params.quantity = config.quantity;
				}
				post(
					params,
					function(data) {
						if (typeof data.items === "object") {
							if (data.items.length > 0) {
								for (var i = 0; i < data.items.length; i++) {
									window.Buyte("add", data.items[i]);
								}
							}
						}
						done(data.shippingMethods);
					},
					function(e) {
						onError(e);
						done();
					}
				);
			});
		} else if (config.productId > 0) {
			// If shipping disabled but product id exists, onAuthentication convert product to cart.
			window.Buyte("onAuthentication", function() {
				var params = {
					action: config.actions.productToCartParams,
					nextNonce: config.nextNonce,
					productId: config.productId,
					variationId: config.variationId,
					quantity: config.quantity
				};
				post(params, function(data) {
					if (typeof data.items === "object") {
						if (data.items.length > 0) {
							for (var i = 0; i < data.items.length; i++) {
								window.Buyte("add", data.items[i]);
							}
						}
					}
				});
			});
		}

		window.Buyte("onPayment", function(paymentToken, done) {
			// on successful payment, submit payment token to backend for charge creation.
			// on successful charge, redirect to received confirmation page.
			var params = {
				action: config.actions.success,
				nextNonce: config.nextNonce,
				productId: config.productId,
				variationId: config.variationId,
				quantity: config.quantity,
				paymentToken: paymentToken
			};
			// console.log(params);

			post(
				params,
				function(data) {
					window.location.replace(data.redirect);
					done();
				},
				function(e) {
					// We want to either toast an error -- browser alerts might do for now, or redirect to an error page.
					console.error(e);
					alert(
						"Could not checkout with Buyte. Please contact support."
					);
					done();
				}
			);
		});
	});

	window.Buyte("onError", function(errorType) {
		if (errorType === "LOAD_ERROR") {
			window.Buyte("destroy");
		}
	});
})(window.jQuery || window.$);
