(function($) {
	window.Buyte("onReady", function(settings) {
		var $form = $(".single_variation_wrap");
		if (!$form.length) {
			// Enable on ready and variation set.
			window.Buyte("enable");
		}

		// This is last stored settings
		var lastSettings = settings;
		window.Buyte("onUpdate", function(settings) {
			console.log(settings);
			lastSettings = settings;
		});

		// Listen for quantity
		var $quantity = $('form [name="quantity"]');
		var quantity = $quantity.val() || 1;
		$quantity.on("change", function() {
			var $this = $(this);
			quantity = $this.val();
			quantity = parseInt(quantity);
			var updateSettings = {
				items: lastSettings.items
			};
			updateSettings.items[0].quantity = quantity;
			window.Buyte("update", updateSettings);
		});

		// Add events if variation form
		$form
			.on("show_variation", function(event, variation) {
				if (!variation.is_purchasable) {
					window.Buyte("disable");
					return;
				}

				var variationExtensions = [];
				for (var key in variation.attributes) {
					variationExtensions.push(variation.attributes[key]);
				}

				updateSettings = {
					items: [
						{
							name:
								variationExtensions.length > 0
									? settings.items[0].name +
									  " -- " +
									  variationExtensions.join(", ")
									: settings.items[0].name,
							amount: parseInt(
								Math.round(
									parseFloat(variation.display_price) * 100
								)
							),
							quantity: quantity
						}
					]
				};
				console.log(updateSettings);
				window.Buyte("update", updateSettings);

				window.buyte_success_endpoint =
					window.buyte_original_success_endpoint +
					"&variation_id=" +
					variation.variation_id;

				window.Buyte("enable");
			})
			.on("hide_variation", function(event) {
				// Reset lastSettings but keep quantity and disable.
				lastSettings = settings;
				lastSettings.items[0].quantity = quantity;
				window.Buyte("disable");
			});
	});
})(window.jQuery || window.$);
