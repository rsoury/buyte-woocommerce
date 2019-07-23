(function($) {
	function onVariation(settings, variation, quantity) {
		if (!variation.is_purchasable) {
			window.Buyte("disable");
			return;
		}

		var variationExtensions = [];
		for (var key in variation.attributes) {
			variationExtensions.push(variation.attributes[key]);
		}

		var updateSettings = {
			items: [
				{
					name:
						variationExtensions.length > 0
							? settings.items[0].name +
							  " -- " +
							  variationExtensions.join(", ")
							: settings.items[0].name,
					amount: parseInt(
						Math.round(parseFloat(variation.display_price) * 100)
					),
					quantity: quantity
				}
			]
		};
		// console.log(updateSettings);
		window.Buyte("update", updateSettings);

		window.wc_buyte.product_variation(variation.variation_id);

		window.Buyte("enable");
	}

	window.Buyte("onReady", function(settings) {
		var $form = $(".single_variation_wrap");
		if ($form.length) {
			var $variationInput = $form.find(
				'input[name="variation_id"], input.variation_id'
			);
			if ($variationInput.length) {
				var variationId = parseInt($variationInput.val(), 10);
				var variationData = $form
					.closest("form.cart")
					.attr("data-product_variations");
				try {
					if (typeof variationData === "string") {
						variationData = JSON.parse(variationData);
					}
					for (var i = 0; i < variationData.length; i++) {
						var variation = variationData[i];
						if (
							parseInt(variation.variation_id, 10) == variationId
						) {
							onVariation(settings, variation, 1);
						}
					}
				} catch (e) {
					console.error(e);
				}
			}
		} else {
			// Enable on ready and no variation exists.
			window.Buyte("enable");
		}

		// This is last stored settings
		window.Buyte("onUpdate", function(updatedSettings) {
			// console.log(settings);
			settings = updatedSettings;
		});

		// Listen for quantity
		var $quantity = $('form [name="quantity"]');
		var quantity = $quantity.val() || 1;
		window.wc_buyte.product_quantity(quantity);
		$quantity.on("change", function() {
			var $this = $(this);
			quantity = $this.val();
			quantity = parseInt(quantity);
			window.wc_buyte.product_quantity(quantity);
			var updateSettings = {
				items: settings.items
			};
			updateSettings.items[0].quantity = quantity;
			window.Buyte("update", updateSettings);
		});

		// Add events if variation form
		$form
			.on("show_variation", function(event, variation) {
				onVariation(settings, variation, quantity);
			})
			.on("hide_variation", function(event) {
				// Reset lastSettings but keep quantity and disable.
				window.wc_buyte.product_variation();
				window.Buyte("disable");
			});
	});
})(window.jQuery || window.$);
