/**
	This PHP code is editted in it's own JS file to leverage the power of prettier.

	config variable should localised through PHP.
 */

(function($) {
	// Validate
	config.productId = parseInt(config.productId, 10);
	config.variationId = parseInt(config.variationId, 10);
	config.quantity = parseInt(config.quantity, 10);

	console.log(config);

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
		console.error(e.messages);
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

	// console.log(buyteSettings);
	window.Buyte("load", config.buyteSettings);
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
					security: config.nonce.getShipping,
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
					params.security = config.nonce.productToCartWithShipping;
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
						console.error(e);
						console.error(e.messages);
						alert(
							"No shipping methods available for your location. Please contact Merchant Support."
						);
						done();
					}
				);
			});
		} else if (config.productId > 0) {
			// If shipping disabled but product id exists, onAuthentication convert product to cart.
			window.Buyte("onAuthentication", function() {
				var params = {
					action: config.actions.productToCartParams,
					security: config.nonce.productToCartParams,
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
				security: config.nonce.success,
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
					console.error(e.messages);
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
